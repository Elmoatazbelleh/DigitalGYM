<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

requireLogin();

// Get statistics
$stats = [];

// Total members
$result = $conn->query("SELECT COUNT(*) as count FROM members");
$stats['total_members'] = $result->fetch_assoc()['count'];

// Active members
$result = $conn->query("SELECT COUNT(*) as count FROM members WHERE status = 'active'");
$stats['active_members'] = $result->fetch_assoc()['count'];

// Total revenue this month
$result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE())");
$stats['monthly_revenue'] = $result->fetch_assoc()['total'];

// Active memberships
$result = $conn->query("SELECT COUNT(*) as count FROM member_memberships WHERE status = 'active' AND end_date >= CURRENT_DATE()");
$stats['active_memberships'] = $result->fetch_assoc()['count'];

// Today's attendance
$result = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE DATE(check_in) = CURRENT_DATE()");
$stats['today_attendance'] = $result->fetch_assoc()['count'];

// Expiring memberships (next 7 days)
$result = $conn->query("SELECT COUNT(*) as count FROM member_memberships WHERE status = 'active' AND end_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)");
$stats['expiring_soon'] = $result->fetch_assoc()['count'];

// Recent members
$recent_members = $conn->query("SELECT id, first_name, last_name, email, status, registration_date FROM members ORDER BY registration_date DESC LIMIT 5");

// Recent payments
$recent_payments = $conn->query("SELECT p.id, p.amount, p.payment_date, p.payment_method, m.first_name, m.last_name 
    FROM payments p 
    JOIN members m ON p.member_id = m.id 
    ORDER BY p.payment_date DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>🏋️‍♂️ <?php echo SITE_NAME; ?></h1>
            <nav class="header-nav">
                <a href="index.php">Dashboard</a>
                <a href="pages/members.php">Members</a>
                <a href="pages/memberships.php">Memberships</a>
                <a href="pages/payments.php">Payments</a>
                <a href="pages/attendance.php">Attendance</a>
                <a href="logout.php">Logout</a>
            </nav>
        </div>
    </div>

    <div class="container">
        <h2 style="margin-bottom: 20px;">Dashboard Overview</h2>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card success">
                <h3>Total Members</h3>
                <div class="stat-value"><?php echo $stats['total_members']; ?></div>
            </div>
            
            <div class="stat-card success">
                <h3>Active Members</h3>
                <div class="stat-value"><?php echo $stats['active_members']; ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Monthly Revenue</h3>
                <div class="stat-value"><?php echo formatCurrency($stats['monthly_revenue']); ?></div>
            </div>
            
            <div class="stat-card success">
                <h3>Active Memberships</h3>
                <div class="stat-value"><?php echo $stats['active_memberships']; ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Today's Attendance</h3>
                <div class="stat-value"><?php echo $stats['today_attendance']; ?></div>
            </div>
            
            <div class="stat-card warning">
                <h3>Expiring Soon</h3>
                <div class="stat-value"><?php echo $stats['expiring_soon']; ?></div>
            </div>
        </div>

        <!-- Recent Members -->
        <div class="card">
            <div class="card-header">
                <h2>Recent Members</h2>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Registration Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_members->num_rows > 0): ?>
                        <?php while ($member = $recent_members->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($member['email']); ?></td>
                                <td><span class="badge badge-<?php echo getStatusBadge($member['status']); ?>"><?php echo ucfirst($member['status']); ?></span></td>
                                <td><?php echo formatDate($member['registration_date']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center;">No members found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent Payments -->
        <div class="card">
            <div class="card-header">
                <h2>Recent Payments</h2>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_payments->num_rows > 0): ?>
                        <?php while ($payment = $recent_payments->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                                <td><?php echo formatCurrency($payment['amount']); ?></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                <td><?php echo formatDate($payment['payment_date']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center;">No payments found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="js/main.js"></script>
</body>
</html>
