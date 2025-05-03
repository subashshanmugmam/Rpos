<?php
// Start session
session_start();

// If user is already logged in, redirect to the appropriate dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: ../admin/dashboard.php');
    } elseif ($_SESSION['role'] === 'salesperson') {
        header('Location: ../salesperson/dashboard.php');
    } elseif ($_SESSION['role'] === 'stock_manager') {
        header('Location: ../stock_manager/dashboard.php');
    }
    exit;
}
    
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Process login request
$error_message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../config/database.php';
    $conn = getConnection();
    
    // Sanitize inputs
    $username = sanitizeInput($_POST['username'], $conn);
    $password = $_POST['password']; // We'll verify the password directly against database
    
    // Query to get user data
    $query = "SELECT user_id, username, full_name, role, status, password FROM users WHERE username = ? AND status = 'active'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
        
        // Set session variables
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        
        // Update last login time
        $update_query = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("i", $user['user_id']);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Redirect to appropriate dashboard based on role
        $redirect_url = '';
        if ($user['role'] === 'admin') {
            $redirect_url = '../admin/dashboard.php';
        } elseif ($user['role'] === 'salesperson') {
            $redirect_url = '../salesperson/dashboard.php';
        } elseif ($user['role'] === 'stock_manager') {
            $redirect_url = '../stock_manager/dashboard.php';
        }
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'redirect' => $redirect_url]);
            exit;
        } else {
            header('Location: ' . $redirect_url);
            exit;
        }
        } else {
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid password.']);
                exit;
            } else {
                $error_message = 'Invalid password. Please try again.';
            }
        }
    } else {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
            exit;
        } else {
            $error_message = 'Invalid username or password. Please try again.';
        }
    }
    
    // Close the database connection
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retail POS - Login</title>
    <!-- Main stylesheet -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <!-- Premium 3D Glassmorphism Login Styles -->
    <style>
    body.login-page {
        background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 55%, #fad0c4 100%) !important;
        font-family: 'Poppins', sans-serif !important;
        margin: 0; height: 100vh;
        display: flex; align-items: center; justify-content: center;
    }
    .login-container {
        width: 360px; background: rgba(255,255,255,0.15);
        border-radius: 1.5rem; border: 1px solid rgba(255,255,255,0.18);
        box-shadow: 0 8px 32px rgba(0,0,0,0.37);
        backdrop-filter: blur(10px); padding: 2rem 1.5rem;
        color: #fff; text-align: center;
        transition: transform 0.3s, box-shadow 0.3s;
    }
    .login-container:hover {
        transform: perspective(500px) rotateY(3deg) rotateX(3deg);
    }
    .login-container.success {
        box-shadow: 0 0 20px rgba(0,255,0,0.7);
    }
    .login-container.error {
        animation: shake 0.6s;
    }
    @keyframes shake {
        0%,100% { transform: translateX(0); }
        20%,60% { transform: translateX(-10px); }
        40%,80% { transform: translateX(10px); }
    }
    .login-header h1 { text-shadow: 0 2px 8px rgba(0,0,0,0.4); }
    .form-group input { transition: box-shadow 0.3s, transform 0.2s; }
    .btn-primary { transition: background 0.3s, transform 0.2s; }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-header">
            <h1>Retail POS System</h1>
            <p>Enter your credentials to access the system</p>
        </div>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <form id="loginForm" action="index.php" method="post">
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </div>
        </form>
        
        <div class="login-footer">
            <p>&copy; <?php echo date('Y'); ?> Retail POS System. All rights reserved.</p>
        </div>
    </div>
    
    <!-- 3D Login UX Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('#loginForm');
        const submitBtn = form.querySelector('button[type="submit"]');
        form.addEventListener('submit', function() {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';
        });
    });
    </script>

</body>
</html>