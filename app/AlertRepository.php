<?php
declare(strict_types=1);

/** Medium/high academic indicators, one record per reading. */
final class AlertRepository
{
    public function __construct(private PDO $pdo) {}

    public function synchronize(int $readingId, int $locationId, string $level): void
    {
        if (in_array($level, ['medium', 'high'], true)) {
            $query = $this->pdo->prepare(
                "INSERT INTO alerts (reading_id, location_id, risk_level, status)
                 VALUES (:reading_id, :location_id, :risk_level, 'active')
                 ON DUPLICATE KEY UPDATE location_id=VALUES(location_id),
                    risk_level=VALUES(risk_level), status='active'"
            );
            $query->execute(['reading_id'=>$readingId, 'location_id'=>$locationId, 'risk_level'=>$level]);
            return;
        }
        $query = $this->pdo->prepare("UPDATE alerts SET status='resolved' WHERE reading_id=:reading_id");
        $query->execute(['reading_id'=>$readingId]);
    }

    public function activeForReading(int $readingId): ?array
    {
        $query = $this->pdo->prepare(
            "SELECT a.alert_id, a.risk_level, a.status, a.created_at
             FROM alerts AS a JOIN readings AS r ON r.reading_id=a.reading_id
             WHERE a.reading_id=:reading_id AND a.status='active' AND r.is_archived=0 LIMIT 1"
        );
        $query->execute(['reading_id'=>$readingId]);
        return $query->fetch() ?: null;
    }
}
