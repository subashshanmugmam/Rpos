<?php
// Start session and include database connection
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../public/index.php');
    exit;
}

// Get database connection
$conn = getConnection();

// Handle user actions
$message = '';
$messageType = '';

// Handle user activation/deactivation
if (isset($_GET['action']) && isset($_GET['id'])) {
    $userId = (int)$_GET['id'];
    
    if ($_GET['action'] === 'activate') {
        $updateQuery = "UPDATE users SET status = 'active' WHERE user_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("i", $userId);
        
        if ($stmt->execute()) {
            $message = "User activated successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to activate user.";
            $messageType = "danger";
        }
        $stmt->close();
    } elseif ($_GET['action'] === 'deactivate') {
        $updateQuery = "UPDATE users SET status = 'inactive' WHERE user_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("i", $userId);
        
        if ($stmt->execute()) {
            $message = "User deactivated successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to deactivate user.";
            $messageType = "danger";
        }
        $stmt->close();
    } elseif ($_GET['action'] === 'delete' && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
        // Make sure we don't delete the current user
        if ($userId !== $_SESSION['user_id']) {
            $deleteQuery = "DELETE FROM users WHERE user_id = ?";
            $stmt = $conn->prepare($deleteQuery);
            $stmt->bind_param("i", $userId);
            
            if ($stmt->execute()) {
                $message = "User deleted successfully.";
                $messageType = "success";
            } else {
                $message = "Failed to delete user.";
                $messageType = "danger";
            }
            $stmt->close();
        } else {
            $message = "You cannot delete your own account.";
            $messageType = "danger";
        }
    }
}

// Handle form submission for adding/editing users
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'], $conn);
    $fullName = sanitizeInput($_POST['full_name'], $conn);
    $email = sanitizeInput($_POST['email'], $conn);
    $role = sanitizeInput($_POST['role'], $conn);
    $status = sanitizeInput($_POST['status'], $conn);
    
    // If editing existing user
    if (isset($_POST['user_id']) && !empty($_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];
        
        // Check if password is being updated
        if (!empty($_POST['password'])) {
            $password = $_POST['password']; // In a production environment, this should be hashed
            $updateQuery = "UPDATE users SET username = ?, password = ?, full_name = ?, email = ?, role = ?, status = ? WHERE user_id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("ssssssi", $username, $password, $fullName, $email, $role, $status, $userId);
        } else {
            $updateQuery = "UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, status = ? WHERE user_id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("sssssi", $username, $fullName, $email, $role, $status, $userId);
        }
        
        if ($stmt->execute()) {
            $message = "User updated successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to update user. Error: " . $stmt->error;
            $messageType = "danger";
        }
        $stmt->close();
    }
    // If adding new user
    else {
        $password = $_POST['password']; // In a production environment, this should be hashed
        
        $insertQuery = "INSERT INTO users (username, password, full_name, email, role, status) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("ssssss", $username, $password, $fullName, $email, $role, $status);
        
        if ($stmt->execute()) {
            $message = "User added successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to add user. Error: " . $stmt->error;
            $messageType = "danger";
        }
        $stmt->close();
    }
}

// Get user data for editing if ID is provided
$userData = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $userId = (int)$_GET['id'];
    $userQuery = "SELECT * FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($userQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $userData = $result->fetch_assoc();
    }
    $stmt->close();
}

// Fetch all users
$users = [];
$usersQuery = "SELECT * FROM users ORDER BY full_name ASC";
$result = mysqli_query($conn, $usersQuery);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
    mysqli_free_result($result);
}

// Include header
include '../includes/header/header.php';
?>

<div class="page-header">
    <h2><i class="fas fa-users"></i> User Management</h2>
    <div class="page-actions">
        <a href="?action=add" class="btn btn-primary"><i class="fas fa-user-plus"></i> Add New User</a>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['action']) && ($_GET['action'] === 'add' || $_GET['action'] === 'edit')): ?>
    <div class="card">
        <div class="card-header">
            <h3><?php echo $_GET['action'] === 'add' ? 'Add New User' : 'Edit User'; ?></h3>
        </div>
        <div class="card-body">
            <form action="users.php" method="post">
                <?php if (isset($userData)): ?>
                    <input type="hidden" name="user_id" value="<?php echo $userData['user_id']; ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="username">Username</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo isset($userData) ? $userData['username'] : ''; ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="password">Password <?php echo isset($userData) ? '(Leave blank to keep current)' : ''; ?></label>
                        <input type="password" class="form-control" id="password" name="password" <?php echo isset($userData) ? '' : 'required'; ?>>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="full_name">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo isset($userData) ? $userData['full_name'] : ''; ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($userData) ? $userData['email'] : ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="role">Role</label>
                        <select class="form-control" id="role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="admin" <?php echo (isset($userData) && $userData['role'] === 'admin') ? 'selected' : ''; ?>>Administrator</option>
                            <option value="salesperson" <?php echo (isset($userData) && $userData['role'] === 'salesperson') ? 'selected' : ''; ?>>Salesperson</option>
                            <option value="stock_manager" <?php echo (isset($userData) && $userData['role'] === 'stock_manager') ? 'selected' : ''; ?>>Stock Manager</option>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="active" <?php echo (isset($userData) && $userData['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo (isset($userData) && $userData['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            <option value="suspended" <?php echo (isset($userData) && $userData['status'] === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save</button>
                    <a href="users.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                </div>
            </form>
        </div>
    </div>
<?php elseif (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && !isset($_GET['confirm'])): ?>
    <div class="alert alert-warning">
        <p>Are you sure you want to delete this user? This action cannot be undone.</p>
        <a href="?action=delete&id=<?php echo (int)$_GET['id']; ?>&confirm=yes" class="btn btn-danger">Yes, Delete</a>
        <a href="users.php" class="btn btn-secondary">Cancel</a>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['user_id']; ?></td>
                                <td><?php echo $user['full_name']; ?></td>
                                <td><?php echo $user['username']; ?></td>
                                <td><?php echo $user['email']; ?></td>
                                <td>
                                    <span class="badge <?php 
                                        if ($user['role'] === 'admin') echo 'badge-primary';
                                        elseif ($user['role'] === 'salesperson') echo 'badge-success';
                                        elseif ($user['role'] === 'stock_manager') echo 'badge-warning';
                                    ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php 
                                        if ($user['status'] === 'active') echo 'badge-success';
                                        elseif ($user['status'] === 'inactive') echo 'badge-secondary';
                                        elseif ($user['status'] === 'suspended') echo 'badge-danger';
                                    ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?action=edit&id=<?php echo $user['user_id']; ?>" class="btn btn-info" title="Edit"><i class="fas fa-edit"></i></a>
                                        
                                        <?php if ($user['status'] === 'active'): ?>
                                            <a href="?action=deactivate&id=<?php echo $user['user_id']; ?>" class="btn btn-warning" title="Deactivate"><i class="fas fa-user-slash"></i></a>
                                        <?php else: ?>
                                            <a href="?action=activate&id=<?php echo $user['user_id']; ?>" class="btn btn-success" title="Activate"><i class="fas fa-user-check"></i></a>
                                        <?php endif; ?>
                                        
                                        <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                                            <a href="?action=delete&id=<?php echo $user['user_id']; ?>" class="btn btn-danger" title="Delete"><i class="fas fa-trash-alt"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">No users found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .page-actions {
        text-align: right;
    }
    
    .card {
        background-color: #fff;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
    }
    
    .card-header {
        padding: 15px 20px;
        border-bottom: 1px solid #eee;
    }
    
    .card-body {
        padding: 20px;
    }
    
    .form-row {
        display: flex;
        flex-wrap: wrap;
        margin-right: -10px;
        margin-left: -10px;
        margin-bottom: 15px;
    }
    
    .form-group {
        margin-bottom: 1rem;
        padding-right: 10px;
        padding-left: 10px;
    }
    
    .col-md-6 {
        flex: 0 0 50%;
        max-width: 50%;
    }
    
    .form-control {
        display: block;
        width: 100%;
        padding: 0.375rem 0.75rem;
        font-size: 1rem;
        line-height: 1.5;
        color: #495057;
        background-color: #fff;
        background-clip: padding-box;
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    
    .form-actions {
        margin-top: 20px;
    }
    
    .btn {
        display: inline-block;
        font-weight: 400;
        text-align: center;
        white-space: nowrap;
        vertical-align: middle;
        user-select: none;
        border: 1px solid transparent;
        padding: 0.375rem 0.75rem;
        font-size: 1rem;
        line-height: 1.5;
        border-radius: 0.25rem;
        transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        margin-right: 5px;
    }
    
    .btn-primary {
        color: #fff;
        background-color: #007bff;
        border-color: #007bff;
    }
    
    .btn-success {
        color: #fff;
        background-color: #28a745;
        border-color: #28a745;
    }
    
    .btn-info {
        color: #fff;
        background-color: #17a2b8;
        border-color: #17a2b8;
    }
    
    .btn-warning {
        color: #212529;
        background-color: #ffc107;
        border-color: #ffc107;
    }
    
    .btn-danger {
        color: #fff;
        background-color: #dc3545;
        border-color: #dc3545;
    }
    
    .btn-secondary {
        color: #fff;
        background-color: #6c757d;
        border-color: #6c757d;
    }
    
    .btn-group {
        position: relative;
        display: inline-flex;
        vertical-align: middle;
    }
    
    .btn-group-sm>.btn, .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
        line-height: 1.5;
        border-radius: 0.2rem;
    }
    
    .alert {
        position: relative;
        padding: 0.75rem 1.25rem;
        margin-bottom: 1rem;
        border: 1px solid transparent;
        border-radius: 0.25rem;
    }
    
    .alert-success {
        color: #155724;
        background-color: #d4edda;
        border-color: #c3e6cb;
    }
    
    .alert-danger {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }
    
    .alert-warning {
        color: #856404;
        background-color: #fff3cd;
        border-color: #ffeeba;
    }
    
    .table {
        width: 100%;
        margin-bottom: 1rem;
        color: #212529;
        border-collapse: collapse;
    }
    
    .table th,
    .table td {
        padding: 0.75rem;
        vertical-align: top;
        border-top: 1px solid #dee2e6;
    }
    
    .table-striped tbody tr:nth-of-type(odd) {
        background-color: rgba(0, 0, 0, 0.05);
    }
    
    .badge {
        display: inline-block;
        padding: 0.25em 0.4em;
        font-size: 75%;
        font-weight: 700;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: 0.25rem;
    }
    
    .badge-primary {
        color: #fff;
        background-color: #007bff;
    }
    
    .badge-success {
        color: #fff;
        background-color: #28a745;
    }
    
    .badge-warning {
        color: #212529;
        background-color: #ffc107;
    }
    
    .badge-danger {
        color: #fff;
        background-color: #dc3545;
    }
    
    .badge-secondary {
        color: #fff;
        background-color: #6c757d;
    }
    
    .text-center {
        text-align: center;
    }
</style>

<?php
// Close connection
mysqli_close($conn);

// Include footer
include '../includes/footer/footer.php';
?>