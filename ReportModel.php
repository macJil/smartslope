<?php
// Simple Report Model
class Report {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Create new report
    public function create($data) {
        $this->pdo->beginTransaction();
        try {
            // Create event
            $event = $this->pdo->prepare("INSERT INTO events (location_id, user_id, type) VALUES (:location_id, :user_id, 'report')");
            $event->execute([
                'location_id' => $data['location_id'],
                'user_id' => $data['user_id']
            ]);
            $eventId = $this->pdo->lastInsertId();
            
            // Create report - only use fields that exist in the reports table
            $sql = "INSERT INTO reports (event_id, location_id, message, contact_phone, contact_email, house_landmark, report_type, occurred_at, status)
                    VALUES (:event_id, :location_id, :message, :contact_phone, :contact_email, :house_landmark, :report_type, :occurred_at, 'pending')";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'event_id' => $eventId,
                'location_id' => $data['location_id'],
                'message' => $data['message'],
                'contact_phone' => $data['contact_phone'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'house_landmark' => $data['house_landmark'] ?? null,
                'report_type' => $data['report_type'] ?? 'other',
                'occurred_at' => $data['occurred_at'] ?? date('Y-m-d H:i:s')
            ]);
            
            $this->pdo->commit();
            return $eventId;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    // Get all reports for admin
    public function getAll() {
        $sql = "SELECT r.*, e.id as event_id, e.created_at, e.user_id as reporter_id, 
                       u.full_name as reporter_name, u.username as reporter_username,
                       u.email as reporter_email
                FROM reports r
                JOIN events e ON e.id = r.event_id
                LEFT JOIN users u ON u.id = e.user_id
                WHERE e.type = 'report'
                ORDER BY e.created_at DESC";
        
        try {
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            // Fallback query if the above fails (compatibility mode)
            $sqlFallback = "SELECT r.*, e.id as event_id, e.created_at
                           FROM reports r
                           JOIN events e ON e.id = r.event_id
                           WHERE e.type = 'report'
                           ORDER BY e.created_at DESC";
            $stmt = $this->pdo->query($sqlFallback);
            return $stmt->fetchAll();
        }
    }
    
    // Get pending counts by location
    public function getPendingCounts() {
        $sql = "SELECT location_id, COUNT(*) as count
                FROM reports r
                JOIN events e ON e.id = r.event_id
                WHERE e.type = 'report' AND r.status = 'pending'
                GROUP BY location_id";
        
        $stmt = $this->pdo->query($sql);
        $results = $stmt->fetchAll();
        $counts = [];
        foreach ($results as $row) {
            $counts[$row['location_id']] = (int)$row['count'];
        }
        return $counts;
    }
    
    // Update report status
    public function updateStatus($eventId, $status, $adminId) {
        $sql = "UPDATE reports SET status = :status, reviewed_by = :admin_id, reviewed_at = NOW()
                WHERE event_id = :event_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'status' => $status,
            'admin_id' => $adminId,
            'event_id' => $eventId
        ]);
        return $stmt->rowCount() === 1;
    }
    
    // Delete report
    public function delete($eventId) {
        $this->pdo->beginTransaction();
        try {
            // Delete from reports
            $stmt = $this->pdo->prepare("DELETE FROM reports WHERE event_id = :event_id");
            $stmt->execute(['event_id' => $eventId]);
            
            // Delete from events
            $stmt = $this->pdo->prepare("DELETE FROM events WHERE id = :event_id AND type = 'report'");
            $stmt->execute(['event_id' => $eventId]);
            
            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}