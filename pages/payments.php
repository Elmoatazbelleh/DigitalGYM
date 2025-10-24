<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

requireLogin();

$message = '';
$messageType = 'success';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $member_id = intval($_POST['member_id']);
        $membership_id = !empty($_POST['membership_id']) ? intval($_POST['membership_id']) : null;
        $amount = floatval($_POST['amount']);
        $payment_method = $_POST['payment_method'];
        $payment_date = $_POST['payment_date'];
        $notes = sanitize($_POST['notes']);
        
        if ($membership_id) {
            $stmt = $conn->prepare("INSERT INTO payments (member_id, membership_id, amount, payment_method, payment_date, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iidsss", $member_id, $membership_id, $amount, $payment_method, $payment_date, $notes);
        } else {
            $stmt = $conn->prepare("INSERT INTO payments (member_id, amount, payment_method, payment_date, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("idsss", $member_id, $amount, $payment_method, $payment_date, $notes);
        }
        
        if ($stmt->execute()) {
            $message = 'Payment recorded successfully!';
        } else {
            $message = 'Error recording payment: ' . $conn->error;
            $messageType = 'danger';
        }
        $stmt->close();
    }
}

// Get payments list
$query = "SELECT p.*, m.first_name, m.last_name, mp.plan_name 
          FROM payments p 
          JOIN members m ON p.member_id = m.id 
          LEFT JOIN member_memberships mm ON p.membership_id = mm.id 
          LEFT JOIN membership_plans mp ON mm.plan_id = mp.id 
          ORDER BY p.payment_date DESC, p.created_at DESC";
$payments = $conn->query($query);

// Get members for dropdown
$members = $conn->query("SELECT id, first_name, last_name FROM members ORDER BY first_name");

// Calculate statistics
$today_revenue = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE DATE(payment_date) = CURRENT_DATE()")->fetch_assoc()['total'];
$month_revenue = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE())")->fetch_assoc()['total'];
$total_revenue = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - <?php echo SITE_NAME; ?></title>
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
        <h2 style="margin-bottom: 20px;">Payment Management</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <!-- Revenue Statistics -->
        <div class="stats-grid">
            <div class="stat-card success">
                <h3>Today's Revenue</h3>
                <div class="stat-value"><?php echo formatCurrency($today_revenue); ?></div>
            </div>
            
            <div class="stat-card">
                <h3>This Month</h3>
                <div class="stat-value"><?php echo formatCurrency($month_revenue); ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="stat-value"><?php echo formatCurrency($total_revenue); ?></div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h2>Payment Records</h2>
                <button onclick="document.getElementById('addModal').style.display='block'" class="btn btn-primary">Record Payment</button>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Member</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Plan</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($payments->num_rows > 0): ?>
                        <?php while ($payment = $payments->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $payment['id']; ?></td>
                                <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                                <td><strong><?php echo formatCurrency($payment['amount']); ?></strong></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                <td><?php echo $payment['plan_name'] ? htmlspecialchars($payment['plan_name']) : '-'; ?></td>
                                <td><?php echo formatDate($payment['payment_date']); ?></td>
                                <td><span class="badge badge-<?php echo getStatusBadge($payment['payment_status']); ?>"><?php echo ucfirst($payment['payment_status']); ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">No payments found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Payment Modal -->
    <div id="addModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="background: white; max-width: 500px; margin: 100px auto; padding: 30px; border-radius: 8px;">
            <h2>Record Payment</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label>Member *</label>
                    <select name="member_id" id="member_id" class="form-control" required>
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
                    <label>Membership (Optional)</label>
                    <select name="membership_id" id="membership_id" class="form-control">
                        <option value="">Select Membership (if applicable)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Amount *</label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label>Payment Method *</label>
                    <select name="payment_method" class="form-control" required>
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="online">Online</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Payment Date *</label>
                    <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-success">Record Payment</button>
                    <button type="button" onclick="document.getElementById('addModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/main.js"></script>
    <script>
        // Load memberships when member is selected
        document.getElementById('member_id').addEventListener('change', function() {
            const memberId = this.value;
            const membershipSelect = document.getElementById('membership_id');
            
            if (!memberId) {
                membershipSelect.innerHTML = '<option value="">Select Membership (if applicable)</option>';
                return;
            }
            
            fetch(`../includes/get_memberships.php?member_id=${memberId}`)
                .then(response => response.json())
                .then(data => {
                    membershipSelect.innerHTML = '<option value="">Select Membership (if applicable)</option>';
                    data.forEach(membership => {
                        const option = document.createElement('option');
                        option.value = membership.id;
                        option.textContent = `${membership.plan_name} (${membership.start_date} - ${membership.end_date})`;
                        membershipSelect.appendChild(option);
                    });
                })
                .catch(error => console.error('Error loading memberships:', error));
        });
    </script>
</body>
</html>
