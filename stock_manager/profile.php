<?php
// Start session and include database connection
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a stock manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'stock_manager') {
    header('Location: ../public/index.php');
    exit;
}

// Get database connection
$conn = getConnection();

// Handle profile update
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $fullName = sanitizeInput($_POST['full_name'], $conn);
    $email = sanitizeInput($_POST['email'], $conn);
    
    // Check if password was provided
    if (!empty($_POST['password']) && !empty($_POST['confirm_password'])) {
        // Validate password match
        if ($_POST['password'] === $_POST['confirm_password']) {
            // Hash the password
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            
            // Update user with new password
            $query = "UPDATE users SET full_name = ?, email = ?, password = ? WHERE user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssi", $fullName, $email, $password, $_SESSION['user_id']);
        } else {
            $message = "Passwords do not match!";
            $messageType = "danger";
        }
    } else {
        // Update user without changing password
        $query = "UPDATE users SET full_name = ?, email = ? WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssi", $fullName, $email, $_SESSION['user_id']);
    }
    
    // Execute update if no error occurred
    if (empty($message)) {
        if ($stmt->execute()) {
            $message = "Profile updated successfully.";
            $messageType = "success";
            $_SESSION['full_name'] = $fullName;
        } else {
            $message = "Failed to update profile: " . $conn->error;
            $messageType = "danger";
        }
        $stmt->close();
    }
}

// Fetch user data
$query = "SELECT full_name, email, username, created_at, last_login FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Include header
include '../includes/header/header.php';
?>

<div class="profile-container">
    <div class="page-header">
        <h2><i class="fas fa-user"></i> My Profile</h2>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>Update Profile</h3>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                            <small class="form-text text-muted">Username cannot be changed.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <hr>
                        
                        <div class="form-group">
                            <label for="password">New Password</label>
                            <input type="password" class="form-control" id="password" name="password" minlength="8">
                            <small class="form-text text-muted">Leave blank to keep your current password.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>Account Information</h3>
                </div>
                <div class="card-body">
                    <div class="account-info">
                        <div class="info-item">
                            <span class="info-label">User ID:</span>
                            <span class="info-value"><?php echo $_SESSION['user_id']; ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Role:</span>
                            <span class="info-value">
                                <span class="badge badge-info">Stock Manager</span>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Account Created:</span>
                            <span class="info-value">
                                <?php echo date('F j, Y', strtotime($user['created_at'])); ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Last Login:</span>
                            <span class="info-value">
                                <?php echo $user['last_login'] ? date('F j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-4">
                        <h5><i class="fas fa-info-circle"></i> Stock Manager Responsibilities:</h5>
                        <ul>
                            <li>Manage inventory and stock levels</li>
                            <li>Process stock receipts and transfers</li>
                            <li>Monitor low stock items</li>
                            <li>Generate inventory reports</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h3>Recent Activity</h3>
                </div>
                <div class="card-body">
                    <?php
                    // Fetch recent stock activity
                    $query = "SELECT sm.*, p.name as product_name 
                            FROM stock_movements sm
                            JOIN products p ON sm.product_id = p.product_id
                            WHERE sm.performed_by = ?
                            ORDER BY sm.movement_date DESC
                            LIMIT 5";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("i", $_SESSION['user_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        echo '<div class="activity-list">';
                        while ($activity = $result->fetch_assoc()) {
                            $iconClass = $activity['movement_type'] === 'in' ? 'text-success fa-arrow-down' : 'text-danger fa-arrow-up';
                            echo '<div class="activity-item">';
                            echo '<div class="activity-icon"><i class="fas ' . $iconClass . '"></i></div>';
                            echo '<div class="activity-content">';
                            echo '<div class="activity-title">' . htmlspecialchars($activity['product_name']) . '</div>';
                            echo '<div class="activity-details">';
                            echo '<span class="activity-quantity">' . $activity['quantity'] . ' units</span>';
                            echo '<span class="activity-type">' . ($activity['movement_type'] === 'in' ? 'Stock In' : 'Stock Out') . '</span>';
                            echo '</div>';
                            echo '<div class="activity-date">' . date('M d, Y h:i A', strtotime($activity['movement_date'])) . '</div>';
                            echo '</div>';
                            echo '</div>';
                        }
                        echo '</div>';
                    } else {
                        echo '<p class="text-muted">No recent activity found.</p>';
                    }
                    $stmt->close();
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .profile-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .page-header {
        margin-bottom: 20px;
    }
    
    .row {
        display: flex;
        flex-wrap: wrap;
        margin-right: -15px;
        margin-left: -15px;
    }
    
    .col-md-6 {
        flex: 0 0 50%;
        max-width: 50%;
        padding-right: 15px;
        padding-left: 15px;
    }
    
    .card {
        position: relative;
        display: flex;
        flex-direction: column;
        min-width: 0;
        word-wrap: break-word;
        background-color: #fff;
        background-clip: border-box;
        border: 1px solid rgba(0,0,0,.125);
        border-radius: .25rem;
        margin-bottom: 20px;
    }
    
    .card-header {
        padding: .75rem 1.25rem;
        margin-bottom: 0;
        background-color: rgba(0,0,0,.03);
        border-bottom: 1px solid rgba(0,0,0,.125);
    }
    
    .card-body {
        flex: 1 1 auto;
        min-height: 1px;
        padding: 1.25rem;
    }
    
    .form-group {
        margin-bottom: 1rem;
    }
    
    .form-control {
        display: block;
        width: 100%;
        height: calc(1.5em + .75rem + 2px);
        padding: .375rem .75rem;
        font-size: 1rem;
        font-weight: 400;
        line-height: 1.5;
        color: #495057;
        background-color: #fff;
        background-clip: padding-box;
        border: 1px solid #ced4da;
        border-radius: .25rem;
        transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out;
    }
    
    textarea.form-control {
        height: auto;
    }
    
    .form-text {
        display: block;
        margin-top: .25rem;
        font-size: 80%;
        color: #6c757d;
    }
    
    .btn {
        display: inline-block;
        font-weight: 400;
        text-align: center;
        vertical-align: middle;
        cursor: pointer;
        padding: .375rem .75rem;
        font-size: 1rem;
        line-height: 1.5;
        border-radius: .25rem;
        transition: color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;
    }
    
    .btn-primary {
        color: #fff;
        background-color: #007bff;
        border-color: #007bff;
    }
    
    .alert {
        position: relative;
        padding: .75rem 1.25rem;
        margin-bottom: 1rem;
        border: 1px solid transparent;
        border-radius: .25rem;
    }
    
    .alert-info {
        color: #0c5460;
        background-color: #d1ecf1;
        border-color: #bee5eb;
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
    
    .badge {
        display: inline-block;
        padding: .25em .4em;
        font-size: 75%;
        font-weight: 700;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: .25rem;
    }
    
    .badge-info {
        color: #fff;
        background-color: #17a2b8;
    }
    
    .account-info {
        margin-bottom: 1rem;
    }
    
    .info-item {
        display: flex;
        margin-bottom: .5rem;
        padding-bottom: .5rem;
        border-bottom: 1px solid #eee;
    }
    
    .info-label {
        font-weight: bold;
        min-width: 150px;
    }
    
    .info-value {
        flex-grow: 1;
    }
    
    .activity-list {
        max-height: 300px;
        overflow-y: auto;
    }
    
    .activity-item {
        display: flex;
        padding: 10px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .activity-icon {
        margin-right: 15px;
        font-size: 1.2rem;
        width: 20px;
        text-align: center;
    }
    
    .activity-content {
        flex-grow: 1;
    }
    
    .activity-title {
        font-weight: 500;
    }
    
    .activity-details {
        display: flex;
        justify-content: space-between;
        font-size: 0.85rem;
        color: #6c757d;
        margin-top: 3px;
    }
    
    .activity-date {
        font-size: 0.8rem;
        color: #999;
        margin-top: 3px;
    }
    
    .mt-4 {
        margin-top: 1.5rem;
    }
    
    @media (max-width: 767.98px) {
        .col-md-6 {
            flex: 0 0 100%;
            max-width: 100%;
        }
    }
</style>

<?php
// Close connection
mysqli_close($conn);

// Include footer
include '../includes/footer/footer.php';
?>