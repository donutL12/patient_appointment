<?php
// controllers/AppointmentController.php

class AppointmentController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Fetches all appointments with joined details (Patient and Doctor names/specialization).
     */
    public function getAllAppointmentsWithDetails($filter_status = 'all', $search_term = '') {
        $sql = "
            SELECT 
                a.id, a.user_id, a.doctor_id, a.appointment_date, a.appointment_time, a.status, a.reason, a.created_at,
                u.fullname as patient_name, u.email as patient_email, 
                d.fullname as doctor_name, d.specialization
            FROM appointments a
            JOIN users u ON a.user_id = u.id
            JOIN doctors d ON a.doctor_id = d.id
        ";
        
        $params = [];
        $where = [];

        // 1. Status Filter
        if ($filter_status !== 'all') {
            $where[] = "a.status = ?";
            $params[] = $filter_status;
        }

        // 2. Search Filter (Search patient name, doctor name, or reason)
        if (!empty($search_term)) {
            $search_like = "%" . $search_term . "%";
            $where[] = "(u.fullname LIKE ? OR d.fullname LIKE ? OR a.reason LIKE ?)";
            $params[] = $search_like;
            $params[] = $search_like;
            $params[] = $search_like;
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Updates the status of a specific appointment.
     */
    public function updateAppointmentStatus($appointment_id, $new_status) {
        $stmt = $this->pdo->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        return $stmt->execute([$new_status, $appointment_id]);
    }

    /**
     * Gets the count of appointments by status for the sidebar badge.
     */
    public function getAppointmentCountByStatus($status) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE status = ?");
        $stmt->execute([$status]);
        return $stmt->fetchColumn();
    }
}
?>