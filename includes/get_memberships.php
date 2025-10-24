<?php
require_once 'config.php';

header('Content-Type: application/json');

if (isset($_GET['member_id'])) {
    $member_id = intval($_GET['member_id']);
    
    $query = "SELECT mm.id, mp.plan_name, mm.start_date, mm.end_date 
              FROM member_memberships mm 
              JOIN membership_plans mp ON mm.plan_id = mp.id 
              WHERE mm.member_id = ? AND mm.status = 'active'
              ORDER BY mm.start_date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $memberships = [];
    while ($row = $result->fetch_assoc()) {
        $memberships[] = $row;
    }
    
    echo json_encode($memberships);
    $stmt->close();
} else {
    echo json_encode([]);
}
?>
