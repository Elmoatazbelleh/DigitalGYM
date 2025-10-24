<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

requireLogin();

$message = '';
$messageType = 'success';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $member_id = intval($_POST['member_id']);
            $plan_id = intval($_POST['plan_id']);
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            
            $stmt = $conn->prepare("INSERT INTO member_memberships (member_id, plan_id, start_date, end_date) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $member_id, $plan_id, $start_date, $end_date);
            
            if ($stmt->execute()) {
                $message = 'Membership assigned successfully!';
            } else {
                $message = 'Error assigning membership: ' . $conn->error;
                $messageType = 'danger';
            }
            $stmt->close();
        } elseif ($_POST['action'] === 'update_status') {
            $id = intval($_POST['id']);
            $status = $_POST['status'];
            
            $stmt = $conn->prepare("UPDATE member_memberships SET status=? WHERE id=?");
            $stmt->bind_param("si", $status, $id);
            
            if ($stmt->execute()) {
                $message = 'Membership status updated successfully!';
            } else {
                $message = 'Error updating membership: ' . $conn->error;
                $messageType = 'danger';
            }
            $stmt->close();
        }
    }
}

// Get memberships list
$query = "SELECT mm.*, m.first_name, m.last_name, m.email, mp.plan_name, mp.price 
          FROM member_memberships mm 
          JOIN members m ON mm.member_id = m.id 
          JOIN membership_plans mp ON mm.plan_id = mp.id 
          ORDER BY mm.created_at DESC";
$memberships = $conn->query($query);

// Get members for dropdown
$members = $conn->query("SELECT id, first_name, last_name FROM members WHERE status = 'active' ORDER BY first_name");

// Get plans for dropdown
$plans = $conn->query("SELECT id, plan_name, duration_months, price FROM membership_plans WHERE status = 'active' ORDER BY plan_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memberships - <?php echo SITE_NAME; ?></title>
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
        <h2 style="margin-bottom: 20px;">Membership Management</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h2>Active Memberships</h2>
                <button onclick="document.getElementById('addModal').style.display='block'" class="btn btn-primary">Assign Membership</button>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Member</th>
                        <th>Plan</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($memberships->num_rows > 0): ?>
                        <?php while ($membership = $memberships->fetch_assoc()): ?>
                            <?php 
                            $isExpired = isMembershipExpired($membership['end_date']);
                            if ($isExpired && $membership['status'] === 'active') {
                                $conn->query("UPDATE member_memberships SET status='expired' WHERE id=" . $membership['id']);
                                $membership['status'] = 'expired';
                            }
                            ?>
                            <tr>
                                <td><?php echo $membership['id']; ?></td>
                                <td><?php echo htmlspecialchars($membership['first_name'] . ' ' . $membership['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($membership['plan_name']); ?></td>
                                <td><?php echo formatDate($membership['start_date']); ?></td>
                                <td><?php echo formatDate($membership['end_date']); ?></td>
                                <td><span class="badge badge-<?php echo getStatusBadge($membership['status']); ?>"><?php echo ucfirst($membership['status']); ?></span></td>
                                <td>
                                    <?php if ($membership['status'] === 'active'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="id" value="<?php echo $membership['id']; ?>">
                                            <input type="hidden" name="status" value="cancelled">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Cancel this membership?')">Cancel</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">No memberships found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Membership Modal -->
    <div id="addModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="background: white; max-width: 500px; margin: 100px auto; padding: 30px; border-radius: 8px;">
            <h2>Assign Membership</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label>Member *</label>
                    <select name="member_id" class="form-control" required>
                        <option value="">Select Member</option>
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
                
                <div class="form-group">
                    <label>Membership Plan *</label>
                    <select name="plan_id" id="plan_id" class="form-control" required>
                        <option value="">Select Plan</option>
                        <?php 
                        $plans->data_seek(0);
                        while ($plan = $plans->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $plan['id']; ?>" data-months="<?php echo $plan['duration_months']; ?>">
                                <?php echo htmlspecialchars($plan['plan_name']) . ' - ' . formatCurrency($plan['price']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Start Date *</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>End Date *</label>
                    <input type="date" name="end_date" id="end_date" class="form-control" required>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-success">Assign</button>
                    <button type="button" onclick="document.getElementById('addModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/main.js"></script>
    <script>
        // Auto-calculate end date based on plan duration
        document.getElementById('plan_id').addEventListener('change', updateEndDate);
        document.getElementById('start_date').addEventListener('change', updateEndDate);
        
        function updateEndDate() {
            const planSelect = document.getElementById('plan_id');
            const startDate = document.getElementById('start_date').value;
            
            if (planSelect.value && startDate) {
                const months = parseInt(planSelect.options[planSelect.selectedIndex].dataset.months);
                const start = new Date(startDate);
                start.setMonth(start.getMonth() + months);
                
                const year = start.getFullYear();
                const month = String(start.getMonth() + 1).padStart(2, '0');
                const day = String(start.getDate()).padStart(2, '0');
                
                document.getElementById('end_date').value = `${year}-${month}-${day}`;
            }
        }
    </script>
</body>
</html>
