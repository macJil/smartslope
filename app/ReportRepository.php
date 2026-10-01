<?php
declare(strict_types=1);

final class ReportRepository
{
    public function __construct(private PDO $pdo) {}

    /** Reports have no dependent records; an authorized admin may delete one. */
    public function delete(int $reportId): bool
    {
        $query = $this->pdo->prepare("DELETE FROM reports WHERE report_id=:id AND location_id IN (
            SELECT l.location_id FROM locations l JOIN barangays b ON b.barangay_id=l.barangay_id
            WHERE b.barangay_name='Barangay Irisan' AND b.city_name='Baguio City')");
        $query->execute(['id'=>$reportId]);
        return $query->rowCount() === 1;
    }

    public function activeLocations(): array
    {
        return $this->pdo->query(
            "SELECT l.location_id,l.location_name,l.purok_zone,l.landmark
             FROM locations AS l JOIN barangays AS b ON b.barangay_id=l.barangay_id
             WHERE l.is_active=1 AND b.is_active=1
             AND b.barangay_name='Barangay Irisan' AND b.city_name='Baguio City'
             ORDER BY l.location_name,l.purok_zone"
        )->fetchAll();
    }

    public function create(int $locationId, ?int $reporterId, string $contactNumber, ?string $email, ?string $houseLandmark, string $message): void
    {
        $query=$this->pdo->prepare(
            "INSERT INTO reports (location_id,reported_by_user_id,contact_number,email,house_landmark,message)
             SELECT l.location_id,:reporter_id,:contact_number,:email,:house_landmark,:message
             FROM locations AS l JOIN barangays AS b ON b.barangay_id=l.barangay_id
             WHERE l.location_id=:location_id AND l.is_active=1 AND b.is_active=1
             AND b.barangay_name='Barangay Irisan' AND b.city_name='Baguio City'"
        );
        $query->execute(['location_id'=>$locationId,'reporter_id'=>$reporterId,
            'contact_number'=>$contactNumber,'email'=>$email,
            'house_landmark'=>$houseLandmark,'message'=>$message]);
        if ($query->rowCount()!==1) throw new InvalidArgumentException('Location unavailable.');
    }

    public function adminQueue(?string $status=null): array
    {
        $sql="SELECT r.report_id,r.location_id,r.house_landmark,r.message,r.status,
                     r.created_at,r.reviewed_at,l.location_name,l.purok_zone,
                     reporter.full_name AS reporter_name,r.email AS reporter_email,
                     COALESCE(r.contact_number,reporter.contact_number) AS reporter_contact_number,
                     reviewer.full_name AS reviewer_name
              FROM reports AS r JOIN locations AS l ON l.location_id=r.location_id
              JOIN barangays AS b ON b.barangay_id=l.barangay_id
              LEFT JOIN users AS reporter ON reporter.user_id=r.reported_by_user_id
              LEFT JOIN users AS reviewer ON reviewer.user_id=r.reviewed_by_user_id
              WHERE b.barangay_name='Barangay Irisan' AND b.city_name='Baguio City'";
        $params=[];
        if ($status!==null && in_array($status,['pending','reviewed','resolved'],true)) {
            $sql.=' AND r.status=:status'; $params['status']=$status;
        }
        $query=$this->pdo->prepare($sql.' ORDER BY r.created_at DESC,r.report_id DESC');
        $query->execute($params);
        return $query->fetchAll();
    }

    /** Pending report totals are displayed on location markers, without personal data. */
    public function pendingCountsByLocation(): array
    {
        $query=$this->pdo->query(
            "SELECT r.location_id, COUNT(*) AS pending_count
             FROM reports AS r JOIN locations AS l ON l.location_id=r.location_id
             JOIN barangays AS b ON b.barangay_id=l.barangay_id
             WHERE r.status='pending' AND l.is_active=1 AND b.is_active=1
               AND b.barangay_name='Barangay Irisan' AND b.city_name='Baguio City'
             GROUP BY r.location_id"
        );
        $counts=[];
        foreach ($query->fetchAll() as $row) $counts[(int)$row['location_id']]=(int)$row['pending_count'];
        return $counts;
    }

    public function updateStatus(int $reportId,string $status,int $adminId): bool
    {
        if (!in_array($status,['reviewed','resolved'],true)) return false;
        $query=$this->pdo->prepare(
            "UPDATE reports AS r JOIN locations AS l ON l.location_id=r.location_id
             JOIN barangays AS b ON b.barangay_id=l.barangay_id
             SET r.status=:status,r.reviewed_by_user_id=:admin_id,r.reviewed_at=UTC_TIMESTAMP()
             WHERE r.report_id=:report_id AND b.barangay_name='Barangay Irisan'
               AND b.city_name='Baguio City'"
        );
        $query->execute(['status'=>$status,'admin_id'=>$adminId,'report_id'=>$reportId]);
        return $query->rowCount()>0;
    }
}
