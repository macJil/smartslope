<?php

declare(strict_types=1);

// Report operations use one shared PDO connection, supplied by the caller.
class Report
{
    private PDO $connection;
    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }
    public function getAll(?string $status = null): array
    {
        $pdo = $this->connection;
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
        $reading = new Reading($this->connection);
        foreach ($reports as &$report) {
            $id = (int)$report['location_id'];
            if (!isset($assessments[$id])) {
                $assessments[$id] = reading_assessment($reading->getLatest($id), $report);
            }
            $report['location_assessment'] = $assessments[$id];
            // Reports should retain the last saved location risk after a later login.
            $report['location_risk_level'] = $assessments[$id]['category'] ?? 'unavailable';
        }
        unset($report);
        return $reports;
    }
    public function getPendingCounts(): array
    {
        $pdo = $this->connection;
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
    public function create(array $data): int
    {
        $pdo = $this->connection;
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
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
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return $id;
        } catch (Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }
    public function review(int $id, string $status, int $adminId): bool
    {
        require_awareness_schema();
        $pdo = $this->connection;
        if (!in_array($status, ['reviewed','resolved'], true)) {
            throw new InvalidArgumentException('Invalid review status.');
        }
        $stmt = $pdo->prepare("UPDATE reports r JOIN events e ON e.id = r.event_id
            SET r.status = ?, r.reviewed_by = ?, r.reviewed_at = UTC_TIMESTAMP()
            WHERE e.id = ? AND e.type = 'report' AND r.status = ?");
        $stmt->execute([$status, $adminId, $id, $status === 'reviewed' ? 'pending' : 'reviewed']);
        return $stmt->rowCount() === 1;
    }
    public function delete(int $id): bool
    {
        $pdo = $this->connection;
        $stmt = $pdo->prepare("DELETE FROM events WHERE id = ? AND type = 'report'");
        $stmt->execute([$id]);
        return $stmt->rowCount() === 1;
    }
}
