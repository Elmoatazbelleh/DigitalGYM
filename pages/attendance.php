<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

requireLogin();

$message = '';
$messageType = 'success';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'check_in') {
            $member_id = intval($_POST['member_id']);
            
            // Check if already checked in today
            $check = $conn->query("SELECT id FROM attendance WHERE member_id = $member_id AND DATE(check_in) = CURRENT_DATE() AND check_out IS NULL");
            
            if ($check->num_rows > 0) {
                $message = 'Member is already checked in!';
                $messageType = 'warning';
            } else {
                $stmt = $conn->prepare("INSERT INTO attendance (member_id) VALUES (?)");
                $stmt->bind_param("i", $member_id);
                
                if ($stmt->execute()) {
                    $message = 'Check-in recorded successfully!';
                } else {
                    $message = 'Error recording check-in: ' . $conn->error;
                    $messageType = 'danger';
                }
                $stmt->close();
            }
        } elseif ($_POST['action'] === 'check_out') {
            $attendance_id = intval($_POST['attendance_id']);
            
            $stmt = $conn->prepare("UPDATE attendance SET check_out = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param("i", $attendance_id);
            
            if ($stmt->execute()) {
                $message = 'Check-out recorded successfully!';
            } else {
                $message = 'Error recording check-out: ' . $conn->error;
                $messageType = 'danger';
            }
            $stmt->close();
        }
    }
}

// Get today's attendance
$today_attendance = $conn->query("SELECT a.id, a.check_in, a.check_out, m.first_name, m.last_name, m.id as member_id 
                                  FROM attendance a 
                                  JOIN members m ON a.member_id = m.id 
                                  WHERE DATE(a.check_in) = CURRENT_DATE() 
                                  ORDER BY a.check_in DESC");

// Get all attendance for history
$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$all_attendance = $conn->query("SELECT a.id, a.check_in, a.check_out, m.first_name, m.last_name 
                                FROM attendance a 
                                JOIN members m ON a.member_id = m.id 
                                WHERE DATE(a.check_in) = '$date_filter' 
                                ORDER BY a.check_in DESC");

// Get members for dropdown
$members = $conn->query("SELECT id, first_name, last_name FROM members WHERE status = 'active' ORDER BY first_name");

// Get statistics
$today_count = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE DATE(check_in) = CURRENT_DATE()")->fetch_assoc()['count'];
$checked_in = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE DATE(check_in) = CURRENT_DATE() AND check_out IS NULL")->fetch_assoc()['count'];
$checked_out = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE DATE(check_in) = CURRENT_DATE() AND check_out IS NOT NULL")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>🏋️‍♂️ <?php echo SITE_NAME; ?></h1>
            <nav class="header-nav">
                <a href="../index.php">Dashboard</a>
                <a href="members.php">Members</a>
                <a href="memberships.php">Memberships</a>
                <a href="payments.php">Payments</a>
                <a href="attendance.php">Attendance</a>
                <a href="../logout.php">Logout</a>
            </nav>
        </div>
    </div>

    <div class="container">
        <h2 style="margin-bottom: 20px;">Attendance Tracking</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Today's Total</h3>
                <div class="stat-value"><?php echo $today_count; ?></div>
            </div>
            
            <div class="stat-card success">
                <h3>Currently In</h3>
                <div class="stat-value"><?php echo $checked_in; ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Checked Out</h3>
                <div class="stat-value"><?php echo $checked_out; ?></div>
            </div>
        </div>
        
        <!-- Check-in/Check-out Section -->
        <div class="card">
            <div class="card-header">
                <h2>Quick Check-in</h2>
            </div>
            <form method="POST" action="" style="display: flex; gap: 10px; align-items: end;">
                <input type="hidden" name="action" value="check_in">
                <div class="form-group" style="flex: 1; margin-bottom: 0;">
                    <label>Select Member</label>
                    <select name="member_id" class="form-control" required>
                        <option value="">Choose a member...</option>
                        <?php 
                        $members->data_seek(0);
                        while ($member = $members->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $member['id']; ?>">
                                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-success">Check In</button>
            </form>
        </div>
        
        <!-- Today's Attendance -->
        <div class="card">
            <div class="card-header">
                <h2>Today's Attendance</h2>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Check-in Time</th>
                        <th>Check-out Time</th>
                        <th>Duration</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($today_attendance->num_rows > 0): ?>
                        <?php while ($attendance = $today_attendance->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($attendance['first_name'] . ' ' . $attendance['last_name']); ?></td>
                                <td><?php echo date('h:i A', strtotime($attendance['check_in'])); ?></td>
                                <td>
                                    <?php 
                                    if ($attendance['check_out']) {
                                        echo date('h:i A', strtotime($attendance['check_out']));
                                    } else {
                                        echo '<span class="badge badge-success">In Gym</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($attendance['check_out']) {
                                        $duration = (strtotime($attendance['check_out']) - strtotime($attendance['check_in'])) / 60;
                                        echo round($duration) . ' min';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if (!$attendance['check_out']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="check_out">
                                            <input type="hidden" name="attendance_id" value="<?php echo $attendance['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-primary">Check Out</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">No attendance records for today</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Attendance History -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h2>Attendance History</h2>
                <form method="GET" style="display: flex; gap: 10px;">
                    <input type="date" name="date" class="form-control" value="<?php echo $date_filter; ?>" style="width: auto;">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Check-in</th>
                        <th>Check-out</th>
                        <th>Duration</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($all_attendance->num_rows > 0): ?>
                        <?php while ($attendance = $all_attendance->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($attendance['first_name'] . ' ' . $attendance['last_name']); ?></td>
                                <td><?php echo date('h:i A', strtotime($attendance['check_in'])); ?></td>
                                <td><?php echo $attendance['check_out'] ? date('h:i A', strtotime($attendance['check_out'])) : '-'; ?></td>
                                <td>
                                    <?php 
                                    if ($attendance['check_out']) {
                                        $duration = (strtotime($attendance['check_out']) - strtotime($attendance['check_in'])) / 60;
                                        echo round($duration) . ' min';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center;">No attendance records for this date</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="../js/main.js"></script>
</body>
</html>
