<?php
declare(strict_types=1);

function create_user(string $fullName, string $username, string $email, string $phone, string $password): int {
    $pdo = db();
    $stmt = $pdo->prepare(
        "INSERT INTO users (full_name, username, email, phone, password, role)
         VALUES (?, ?, ?, ?, ?, 'user')"
    );
    $stmt->execute([$fullName, $username, $email, $phone, hash_password($password)]);
    return (int)$pdo->lastInsertId();
}

// ============================================================================
// DATA ACCESS FUNCTIONS
// ============================================================================

// Get all active locations

function get_locations(): array {
    $pdo = db();
    return $pdo->query("SELECT * FROM locations WHERE active = 1 ORDER BY name")->fetchAll();
}

// Get location by ID

function get_location(int $id): ?array {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM locations WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

// Deactivate location

function deactivate_location(int $id): bool {
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE locations SET active = 0 WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->rowCount() === 1;
}

// Get latest reading for location

function get_latest_reading(int $locationId): ?array {
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT e.id, e.location_id, e.user_id, e.type, e.created_at, r.*
         FROM events e JOIN readings r ON r.event_id = e.id
         WHERE e.location_id = ? AND e.type = 'reading' AND r.archived = 0
         ORDER BY r.observed_at DESC, e.id DESC LIMIT 1"
    );
    $stmt->execute([$locationId]);
    $row = $stmt->fetch();
    return $row ? assess_reading($row) : null;
}

// Get readings for location

function get_readings(int $locationId, int $limit = 50): array {
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT e.id, e.location_id, e.user_id, e.type, e.created_at, r.*
         FROM events e JOIN readings r ON r.event_id = e.id
         WHERE e.location_id = ? AND e.type = 'reading' AND r.archived = 0
         ORDER BY r.observed_at DESC, e.id DESC LIMIT ?"
    );
    $stmt->bindValue(1, $locationId, PDO::PARAM_INT);
    $stmt->bindValue(2, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    return array_map('assess_reading', $stmt->fetchAll());
}

// Get all recent readings

function get_all_readings(int $limit = 100): array {
    $pdo = db();
    $rows = $pdo->query(
                "SELECT e.id, e.location_id, e.user_id, e.type, e.created_at, r.*,
                                l.name as location_name, l.purok, l.lat, l.lng
         FROM events e
                 JOIN readings r ON r.event_id = e.id
         JOIN locations l ON l.id = e.location_id
                 WHERE e.type = 'reading' AND r.archived = 0 AND l.active = 1
                 ORDER BY r.observed_at DESC, e.id DESC
         LIMIT " . max(1, (int)$limit)
    )->fetchAll();
    return array_map('assess_reading', $rows);
}

// Create reading

function create_reading(array $data): int {
    $pdo = db();
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $event = $pdo->prepare("INSERT INTO events (location_id, user_id, type) VALUES (?, ?, 'reading')");
        $event->execute([$data['location_id'], $data['user_id'] ?? null]);
        $id = (int)$pdo->lastInsertId();
        $reading = $pdo->prepare(
            'INSERT INTO readings (event_id, rainfall_1h, rainfall_24h, rainfall_72h, risk_level,
                rainfall_forecast_24h, precipitation_probability_24h, soil_moisture_9_27cm,
                soil_moisture_27_81cm, temperature, humidity, wind_speed, weather_code, observed_at,
                source, rule_version, rainfall_window_end, provider_payload)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $reading->execute([
            $id, $data['rainfall_1h'] ?? null, $data['rainfall_24h'] ?? null,
            $data['rainfall_72h'] ?? null, $data['risk_level'] ?? null,
            $data['rainfall_forecast_24h'] ?? null, $data['precipitation_probability_24h'] ?? null,
            $data['soil_moisture_9_27cm'] ?? null, $data['soil_moisture_27_81cm'] ?? null,
            $data['temperature'] ?? null, $data['humidity'] ?? null, $data['wind_speed'] ?? null,
            $data['weather_code'] ?? null, $data['observed_at'], $data['source'] ?? 'openmeteo',
            $data['rule_version'] ?? RiskAnalyzer::VERSION, $data['rainfall_window_end'] ?? null,
            $data['provider_payload'] ?? null
        ]);
        if ($ownsTransaction) $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

// Calculate risk level

function get_reports(?string $status = null): array {
    $pdo = db();
        $sql = "SELECT e.id, e.location_id, e.user_id, e.type, e.created_at, r.*,
                                     l.name as location_name, l.purok, l.landmark, l.lat, l.lng,
                   l.susceptibility, u.full_name as reporter_name
            FROM events e
                        JOIN reports r ON r.event_id = e.id
            JOIN locations l ON l.id = e.location_id
            LEFT JOIN users u ON u.id = e.user_id
            WHERE e.type = 'report'";
    $params = [];
    if ($status) {
        $sql .= " AND r.status = ?";
        $params[] = $status;
    }
    $sql .= " ORDER BY e.created_at DESC, e.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reports = $stmt->fetchAll();
    $assessments = [];
    foreach ($reports as &$report) {
        $id = (int)$report['location_id'];
        if (!isset($assessments[$id])) $assessments[$id] = reading_assessment(get_latest_reading($id), $report);
        $report['location_assessment'] = $assessments[$id];
        $report['location_risk_level'] = $assessments[$id]['current_category'] ?? 'unavailable';
    }
    unset($report);
    return $reports;
}

// Get pending report counts by location

function get_pending_counts(): array {
    $pdo = db();
    $stmt = $pdo->query(
        "SELECT e.location_id, COUNT(*) as count
         FROM events e JOIN reports r ON r.event_id = e.id
         WHERE e.type = 'report' AND r.status = 'pending'
         GROUP BY e.location_id"
    );
    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $counts[(int)$row['location_id']] = (int)$row['count'];
    }
    return $counts;
}

// Create report

function create_report(array $data): int {
    $pdo = db();
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $event = $pdo->prepare("INSERT INTO events (location_id, user_id, type) VALUES (?, ?, 'report')");
        $event->execute([$data['location_id'], $data['user_id'] ?? null]);
        $id = (int)$pdo->lastInsertId();
        $report = $pdo->prepare(
            "INSERT INTO reports (event_id, message, contact_phone, contact_email, house_landmark, status, report_type, occurred_at)
             VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)"
        );
        $report->execute([
            $id, $data['message'], $data['contact_phone'], $data['contact_email'] ?? null,
            $data['house_landmark'] ?? null, $data['report_type'] ?? 'other', $data['occurred_at'] ?? null
        ]);
        if ($ownsTransaction) $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

// Update report status

function update_report(int $id, string $status, int $adminId): bool {
    require_awareness_schema();
    $pdo = db();
    if (!in_array($status, ['reviewed','resolved'], true)) throw new InvalidArgumentException('Invalid review status.');
    $stmt = $pdo->prepare("UPDATE reports r JOIN events e ON e.id = r.event_id
        SET r.status = ?, r.reviewed_by = ?, r.reviewed_at = UTC_TIMESTAMP()
        WHERE e.id = ? AND e.type = 'report' AND r.status = ?");
    $stmt->execute([$status, $adminId, $id, $status === 'reviewed' ? 'pending' : 'reviewed']);
    return $stmt->rowCount() === 1;
}

// Delete report

function delete_report(int $id): bool {
    $pdo = db();
    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ? AND type = 'report'");
    $stmt->execute([$id]);
    return $stmt->rowCount() === 1;
}

// Archive reading

function archive_reading(int $id): bool {
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE readings r JOIN events e ON e.id = r.event_id
        SET r.archived = 1 WHERE e.id = ? AND e.type = 'reading' AND r.archived = 0");
    $stmt->execute([$id]);
    return $stmt->rowCount() === 1;
}


function update_reading_risk(int $readingId, string $risk, int $adminId, string $reason): void {
    require_awareness_schema();
    if (!in_array($risk, ['low','normal','medium','high'], true) || trim($reason)==='' || strlen($reason)>500) throw new InvalidArgumentException('Valid category and adjustment reason are required.');
    $pdo=db(); $pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare("SELECT r.risk_level, r.adjustment_log FROM readings r
            JOIN events e ON e.id=r.event_id
            WHERE e.id=? AND e.type='reading' AND r.archived=0 FOR UPDATE");
        $stmt->execute([$readingId]); $row=$stmt->fetch();
        if (!$row) throw new InvalidArgumentException('Reading not found.');
        $log=json_decode($row['adjustment_log'] ?? '[]',true);
        if (!is_array($log)) throw new RuntimeException('Invalid existing adjustment log.');
        $log[]=['by'=>$adminId,'at'=>gmdate('Y-m-d H:i:s'),'from'=>$row['risk_level'],'to'=>$risk,'reason'=>trim($reason)];
        $stmt=$pdo->prepare('UPDATE readings SET risk_level=?, adjustment_log=? WHERE event_id=?');
        $stmt->execute([$risk,json_encode($log,JSON_THROW_ON_ERROR),$readingId]); $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

// ============================================================================
// WEATHER API FUNCTIONS
// ============================================================================

// Fetch weather from Open-Meteo

function get_or_create_location(float $lat, float $lng, ?string $landmark = null): array {
    if (!is_finite($lat) || !is_finite($lng) || !is_in_irisan($lat, $lng) || strlen((string)$landmark) > 255) {
        throw new InvalidArgumentException('Invalid Irisan location or address.');
    }
    $pdo = db();

    // Round to 5 decimal places
    $lat = round($lat, 5);
    $lng = round($lng, 5);

    // Check if exists
    $stmt = $pdo->prepare("SELECT * FROM locations WHERE lat = ? AND lng = ?");
    $stmt->execute([$lat, $lng]);

    if ($loc = $stmt->fetch()) {
        if (!$loc['active']) {
            throw new InvalidArgumentException('This monitoring point was removed by an administrator.');
        }
        $savedAddress = trim((string)($loc['landmark'] ?? ''));
        $placeholder = $savedAddress === '' || strcasecmp($savedAddress, 'Map point') === 0 ||
            (bool)preg_match('/^(?:Map point,\s*)?Barangay Irisan,\s*Baguio City,\s*Benguet,\s*Philippines$/i', $savedAddress);
        if ($placeholder && trim((string)$landmark) !== '') {
            // Preserve an existing specific address, including one entered by a resident or administrator.
            $stmt = $pdo->prepare('UPDATE locations SET landmark = ? WHERE id = ? AND landmark <=> ?');
            $stmt->execute([trim((string)$landmark), $loc['id'], $loc['landmark']]);
            return get_location((int)$loc['id']) ?? $loc;
        }
        return $loc;
    }

    // Create new
    $name = sprintf('Irisan %.5f, %.5f', $lat, $lng);
    $stmt = $pdo->prepare(
        "INSERT INTO locations (name, purok, landmark, lat, lng, susceptibility, active)
         VALUES (?, NULL, ?, ?, ?, 'unknown', 1)"
    );
    $landmark = trim((string)$landmark);
    try {
        $stmt->execute([$name, $landmark !== '' ? $landmark : null, $lat, $lng]);
    } catch (PDOException $error) {
        if ($error->getCode() !== '23000') throw $error;
        $existing = $pdo->prepare('SELECT * FROM locations WHERE lat = ? AND lng = ?');
        $existing->execute([$lat, $lng]);
        $row = $existing->fetch();
        if (!$row || !$row['active']) throw $error;
        return $row;
    }
    return get_location((int)$pdo->lastInsertId());
}
