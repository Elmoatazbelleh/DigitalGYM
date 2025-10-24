<?php
// Helper functions for the Digital Gym application

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Redirect to login if not authenticated
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Sanitize input
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Format date
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

// Format currency
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

// Get membership status badge class
function getStatusBadge($status) {
    $badges = [
        'active' => 'success',
        'inactive' => 'secondary',
        'suspended' => 'danger',
        'expired' => 'warning',
        'cancelled' => 'dark',
        'pending' => 'info',
        'completed' => 'success',
        'failed' => 'danger'
    ];
    return isset($badges[$status]) ? $badges[$status] : 'secondary';
}

// Calculate membership end date
function calculateEndDate($startDate, $months) {
    return date('Y-m-d', strtotime($startDate . " + $months months"));
}

// Check if membership is expired
function isMembershipExpired($endDate) {
    return strtotime($endDate) < time();
}

// Display alert message
function showAlert($message, $type = 'success') {
    return "<div class='alert alert-$type alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='close' data-dismiss='alert'>&times;</button>
            </div>";
}

// Upload file
function uploadFile($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif']) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error'];
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }
    
    $filename = uniqid() . '.' . $ext;
    $destination = UPLOAD_PATH . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'filename' => $filename];
    }
    
    return ['success' => false, 'message' => 'Failed to upload file'];
}

// Pagination helper
function getPagination($totalRecords, $recordsPerPage, $currentPage) {
    $totalPages = ceil($totalRecords / $recordsPerPage);
    $offset = ($currentPage - 1) * $recordsPerPage;
    
    return [
        'totalPages' => $totalPages,
        'offset' => $offset,
        'currentPage' => $currentPage
    ];
}
?>
