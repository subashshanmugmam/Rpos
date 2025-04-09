<?php
session_start();
require '../config/database.php';

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'salesperson') {
    header('Location: ../public/index.php');
    exit;
}

$conn = getConnection();
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['save_customer'])) {
            $customerId = $_POST['customer_id'] ?? null;
            $firstName = sanitizeInput($_POST['first_name'], $conn);
            $lastName = sanitizeInput($_POST['last_name'], $conn);
            $email = sanitizeInput($_POST['email'], $conn);
            $phone = sanitizeInput($_POST['phone'], $conn);
            $address = sanitizeInput($_POST['address'], $conn);

            if ($customerId) {
                // Update existing customer
                $stmt = $conn->prepare("UPDATE customers SET 
                                      first_name = ?, last_name = ?, email = ?, 
                                      phone = ?, address = ?, updated_at = NOW() 
                                      WHERE customer_id = ?");
                $stmt->bind_param("sssssi", $firstName, $lastName, $email, $phone, $address, $customerId);
                $message = "Customer updated successfully";
            } else {
                // Create new customer
                $stmt = $conn->prepare("INSERT INTO customers 
                                      (first_name, last_name, email, phone, address) 
                                      VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $firstName, $lastName, $email, $phone, $address);
                $message = "Customer added successfully";
            }
            
            $stmt->execute();
            $messageType = "success";
            $stmt->close();
        }
    } catch (mysqli_sql_exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Handle customer deletion
if (isset($_GET['delete'])) {
    $customerId = (int)$_GET['delete'];
    $stmt = $conn->prepare("UPDATE customers SET status = 'inactive' WHERE customer_id = ?");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $message = "Customer marked as inactive";
    $messageType = "warning";
    $stmt->close();
}

// Get search parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search'], $conn) : '';
$query = "SELECT * FROM customers 
          WHERE status = 'active' 
          AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)
          ORDER BY last_name, first_name";
$stmt = $conn->prepare($query);
$searchTerm = "%$search%";
$stmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();
$customers = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include '../includes/header/header.php';
?>

<div class="container">
    <div class="page-header">
        <h2><i class="fas fa-users-cog"></i> Customer Management</h2>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
    <?php endif; ?>

    <div class="customer-tools mb-4">
        <div class="row">
            <div class="col-md-8">
                <form class="form-inline">
                    <div class="input-group w-100">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search customers..." value="<?= htmlspecialchars($search) ?>">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="col-md-4 text-right">
                <button class="btn btn-success" data-toggle="modal" data-target="#customerModal">
                    <i class="fas fa-plus"></i> Add Customer
                </button>
            </div>
        </div>
    </div>

    <div class="customer-list">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="thead-dark">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td><?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?></td>
                        <td><?= htmlspecialchars($customer['email']) ?></td>
                        <td><?= htmlspecialchars($customer['phone']) ?></td>
                        <td><?= htmlspecialchars(truncateString($customer['address'], 30)) ?></td>
                        <td>
                            <button class="btn btn-sm btn-primary edit-btn"
                                    data-toggle="modal"
                                    data-target="#customerModal"
                                    data-id="<?= $customer['customer_id'] ?>"
                                    data-first="<?= htmlspecialchars($customer['first_name']) ?>"
                                    data-last="<?= htmlspecialchars($customer['last_name']) ?>"
                                    data-email="<?= htmlspecialchars($customer['email']) ?>"
                                    data-phone="<?= htmlspecialchars($customer['phone']) ?>"
                                    data-address="<?= htmlspecialchars($customer['address']) ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="customers.php?delete=<?= $customer['customer_id'] ?>" 
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Are you sure you want to deactivate this customer?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Customer Modal -->
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Customer Details</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="customer_id" id="customerId">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="tel" class="form-control" name="phone">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea class="form-control" name="address" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" name="save_customer" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Handle edit button clicks
document.querySelectorAll('.edit-btn').forEach(button => {
    button.addEventListener('click', () => {
        const modal = document.getElementById('customerModal');
        modal.querySelector('#customerId').value = button.dataset.id;
        modal.querySelector('[name="first_name"]').value = button.dataset.first;
        modal.querySelector('[name="last_name"]').value = button.dataset.last;
        modal.querySelector('[name="email"]').value = button.dataset.email;
        modal.querySelector('[name="phone"]').value = button.dataset.phone;
        modal.querySelector('[name="address"]').value = button.dataset.address;
    });
});
</script>

<?php 
// Helper function to truncate long text
function truncateString($string, $length) {
    if (strlen($string) > $length) {
        return substr($string, 0, $length) . '...';
    }
    return $string;
}

mysqli_close($conn);
include '../includes/footer/footer.php';
?>