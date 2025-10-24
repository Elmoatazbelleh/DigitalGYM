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
            $first_name = sanitize($_POST['first_name']);
            $last_name = sanitize($_POST['last_name']);
            $email = sanitize($_POST['email']);
            $phone = sanitize($_POST['phone']);
            $address = sanitize($_POST['address']);
            $dob = $_POST['date_of_birth'];
            $gender = $_POST['gender'];
            $emergency_contact = sanitize($_POST['emergency_contact']);
            $emergency_phone = sanitize($_POST['emergency_phone']);
            
            $stmt = $conn->prepare("INSERT INTO members (first_name, last_name, email, phone, address, date_of_birth, gender, emergency_contact, emergency_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssss", $first_name, $last_name, $email, $phone, $address, $dob, $gender, $emergency_contact, $emergency_phone);
            
            if ($stmt->execute()) {
                $message = 'Member added successfully!';
            } else {
                $message = 'Error adding member: ' . $conn->error;
                $messageType = 'danger';
            }
            $stmt->close();
        } elseif ($_POST['action'] === 'update') {
            $id = intval($_POST['id']);
            $first_name = sanitize($_POST['first_name']);
            $last_name = sanitize($_POST['last_name']);
            $email = sanitize($_POST['email']);
            $phone = sanitize($_POST['phone']);
            $address = sanitize($_POST['address']);
            $dob = $_POST['date_of_birth'];
            $gender = $_POST['gender'];
            $emergency_contact = sanitize($_POST['emergency_contact']);
            $emergency_phone = sanitize($_POST['emergency_phone']);
            $status = $_POST['status'];
            
            $stmt = $conn->prepare("UPDATE members SET first_name=?, last_name=?, email=?, phone=?, address=?, date_of_birth=?, gender=?, emergency_contact=?, emergency_phone=?, status=? WHERE id=?");
            $stmt->bind_param("ssssssssssi", $first_name, $last_name, $email, $phone, $address, $dob, $gender, $emergency_contact, $emergency_phone, $status, $id);
            
            if ($stmt->execute()) {
                $message = 'Member updated successfully!';
            } else {
                $message = 'Error updating member: ' . $conn->error;
                $messageType = 'danger';
            }
            $stmt->close();
        } elseif ($_POST['action'] === 'delete') {
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("DELETE FROM members WHERE id=?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $message = 'Member deleted successfully!';
            } else {
                $message = 'Error deleting member: ' . $conn->error;
                $messageType = 'danger';
            }
            $stmt->close();
        }
    }
}

// Get members list
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$query = "SELECT * FROM members WHERE 1=1";
if (!empty($search)) {
    $query .= " AND (first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR email LIKE '%$search%')";
}
if (!empty($status_filter)) {
    $query .= " AND status = '$status_filter'";
}
$query .= " ORDER BY registration_date DESC";

$members = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members - <?php echo SITE_NAME; ?></title>
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
        <h2 style="margin-bottom: 20px;">Member Management</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h2>Members List</h2>
                <button onclick="document.getElementById('addModal').style.display='block'" class="btn btn-primary">Add New Member</button>
            </div>
            
            <!-- Search and Filter -->
            <div style="margin-bottom: 20px; display: flex; gap: 10px;">
                <form method="GET" style="display: flex; gap: 10px; flex: 1;">
                    <input type="text" name="search" placeholder="Search members..." class="form-control" value="<?php echo htmlspecialchars($search); ?>" style="flex: 1;">
                    <select name="status" class="form-control" style="width: 150px;">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
            </div>
            
            <table class="table" id="membersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Registration Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($members->num_rows > 0): ?>
                        <?php while ($member = $members->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $member['id']; ?></td>
                                <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($member['email']); ?></td>
                                <td><?php echo htmlspecialchars($member['phone']); ?></td>
                                <td><span class="badge badge-<?php echo getStatusBadge($member['status']); ?>"><?php echo ucfirst($member['status']); ?></span></td>
                                <td><?php echo formatDate($member['registration_date']); ?></td>
                                <td class="actions">
                                    <button onclick="editMember(<?php echo htmlspecialchars(json_encode($member)); ?>)" class="btn btn-sm btn-primary">Edit</button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirmDelete('Delete this member?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $member['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">No members found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Member Modal -->
    <div id="addModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="background: white; max-width: 600px; margin: 50px auto; padding: 30px; border-radius: 8px; max-height: 90vh; overflow-y: auto;">
            <h2>Add New Member</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender" class="form-control">
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Emergency Contact</label>
                        <input type="text" name="emergency_contact" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Emergency Phone</label>
                        <input type="tel" name="emergency_phone" class="form-control">
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-success">Save Member</button>
                    <button type="button" onclick="document.getElementById('addModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Member Modal -->
    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="background: white; max-width: 600px; margin: 50px auto; padding: 30px; border-radius: 8px; max-height: 90vh; overflow-y: auto;">
            <h2>Edit Member</h2>
            <form method="POST" action="" id="editForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" id="edit_phone" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth" id="edit_date_of_birth" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender" id="edit_gender" class="form-control">
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Emergency Contact</label>
                        <input type="text" name="emergency_contact" id="edit_emergency_contact" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Emergency Phone</label>
                        <input type="tel" name="emergency_phone" id="edit_emergency_phone" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-success">Update Member</button>
                    <button type="button" onclick="document.getElementById('editModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/main.js"></script>
    <script>
        function editMember(member) {
            document.getElementById('edit_id').value = member.id;
            document.getElementById('edit_first_name').value = member.first_name;
            document.getElementById('edit_last_name').value = member.last_name;
            document.getElementById('edit_email').value = member.email;
            document.getElementById('edit_phone').value = member.phone || '';
            document.getElementById('edit_address').value = member.address || '';
            document.getElementById('edit_date_of_birth').value = member.date_of_birth || '';
            document.getElementById('edit_gender').value = member.gender;
            document.getElementById('edit_emergency_contact').value = member.emergency_contact || '';
            document.getElementById('edit_emergency_phone').value = member.emergency_phone || '';
            document.getElementById('edit_status').value = member.status;
            document.getElementById('editModal').style.display = 'block';
        }
    </script>
</body>
</html>
