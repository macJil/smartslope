<?php
declare(strict_types=1);

// Readings database operations.

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
