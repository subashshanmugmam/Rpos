<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a salesperson
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'salesperson') {
    header('Location: ../public/index.php');
    exit;
}

$conn = getConnection();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = sanitizeInput($_POST['full_name'], $conn);
    $email = sanitizeInput($_POST['email'], $conn);
    $password = !empty($_POST['password']) ? $_POST['password'] : null;

    if ($password) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $query = "UPDATE users SET full_name = ?, email = ?, password = ? WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssi", $fullName, $email, $hashedPassword, $_SESSION['user_id']);
    } else {
        $query = "UPDATE users SET full_name = ?, email = ? WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssi", $fullName, $email, $_SESSION['user_id']);
    }

    if ($stmt->execute()) {
        $message = "Profile updated successfully.";
        $messageType = "success";
        $_SESSION['full_name'] = $fullName;
    } else {
        $message = "Failed to update profile.";
        $messageType = "danger";
    }
    $stmt->close();
}

$query = "SELECT full_name, email, username FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

include '../includes/header/header.php';
?>

<div class="page-header">
    <h2><i class="fas fa-user-circle"></i> My Profile</h2>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3>Personal Information</h3>
            </div>
            <div class="card-body">
                <form action="profile.php" method="post">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" class="form-control" id="username" value="<?php echo $user['username']; ?>" readonly>
                        <small class="form-text text-muted">Username cannot be changed.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo $user['full_name']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo $user['email']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password (Leave blank to keep current)</label>
                        <input type="password" class="form-control" id="password" name="password">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
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
                <table class="table">
                    <tr>
                        <th>User ID:</th>
                        <td><?php echo $_SESSION['user_id']; ?></td>
                    </tr>
                    <tr>
                        <th>Role:</th>
                        <td><span class="badge badge-success">Salesperson</span></td>
                    </tr>
                    <tr>
                        <th>Last Login:</th>
                        <td>
                            <?php
                            $query = "SELECT last_login FROM users WHERE user_id = ?";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param("i", $_SESSION['user_id']);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $login_data = $result->fetch_assoc();
                            $stmt->close();
                            echo $login_data['last_login'] ? date('M d, Y H:i', strtotime($login_data['last_login'])) : 'Never';
                            ?>
                        </td>
                    </tr>
                </table>
                
                <div class="alert alert-info">
                    <p><i class="fas fa-info-circle"></i> As a salesperson, you have access to sales management and customer management features.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
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
    
    .form-group {
        margin-bottom: 1rem;
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
    }
    
    .form-text {
        display: block;
        margin-top: 0.25rem;
        font-size: 80%;
        color: #6c757d;
    }
    
    .form-actions {
        margin-top: 20px;
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
    
    .badge-success {
        color: #fff;
        background-color: #28a745;
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
    
    .alert-info {
        color: #0c5460;
        background-color: #d1ecf1;
        border-color: #bee5eb;
    }
    
    @media (max-width: 767.98px) {
        .col-md-6 {
            flex: 0 0 100%;
            max-width: 100%;
        }
    }
</style>

<?php
mysqli_close($conn);
include '../includes/footer/footer.php';
?>