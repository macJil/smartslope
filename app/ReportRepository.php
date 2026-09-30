<?php
declare(strict_types=1);

final class ReportRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function activeLocations(): array
    {
        $statement = $this->pdo->query(
            'SELECT l.location_id, l.location_name, l.purok_zone, l.landmark
             FROM locations AS l
             INNER JOIN barangays AS b ON b.barangay_id = l.barangay_id
             WHERE l.is_active = 1 AND b.is_active = 1
             ORDER BY l.location_name, l.purok_zone'
        );

        return $statement->fetchAll();
    }

    public function create(
        int $locationId,
        ?int $reporterId,
        ?string $houseLandmark,
        string $message
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO reports
                (location_id, reported_by_user_id, house_landmark, message)
             SELECT l.location_id, :reporter_id, :house_landmark, :message
             FROM locations AS l
             INNER JOIN barangays AS b ON b.barangay_id = l.barangay_id
             WHERE l.location_id = :location_id
               AND l.is_active = 1 AND b.is_active = 1'
        );
        $statement->execute([
            'reporter_id' => $reporterId,
            'house_landmark' => $houseLandmark,
            'message' => $message,
            'location_id' => $locationId,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new InvalidArgumentException('The selected location is unavailable.');
        }
    }

    public function adminQueue(?string $status = null): array
    {
        $sql =
            'SELECT r.report_id, r.location_id, r.house_landmark, r.message,
                    r.status, r.created_at, r.reviewed_at,
                    l.location_name, l.purok_zone,
                    reporter.full_name AS reporter_name,
                    reviewer.full_name AS reviewer_name
             FROM reports AS r
             INNER JOIN locations AS l ON l.location_id = r.location_id
             LEFT JOIN users AS reporter ON reporter.user_id = r.reported_by_user_id
             LEFT JOIN users AS reviewer ON reviewer.user_id = r.reviewed_by_user_id';
        $parameters = [];
        if ($status !== null && in_array($status, ['pending', 'reviewed', 'resolved'], true)) {
            $sql .= ' WHERE r.status = :status';
            $parameters['status'] = $status;
        }
        $sql .= ' ORDER BY r.created_at DESC, r.report_id DESC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function updateStatus(int $reportId, string $status, int $adminId): bool
    {
        if (!in_array($status, ['reviewed', 'resolved'], true)) {
            return false;
        }

        $exists = $this->pdo->prepare('SELECT 1 FROM reports WHERE report_id = :report_id');
        $exists->execute(['report_id' => $reportId]);
        if (!$exists->fetchColumn()) {
            return false;
        }

        $statement = $this->pdo->prepare(
            'UPDATE reports
             SET status = :status,
                 reviewed_by_user_id = :admin_id,
                 reviewed_at = UTC_TIMESTAMP()
             WHERE report_id = :report_id'
        );
        $statement->execute([
            'status' => $status,
            'admin_id' => $adminId,
            'report_id' => $reportId,
        ]);

        return true;
    }
}
