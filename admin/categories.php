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

// Handle category actions
$message = '';
$messageType = '';

// Handle category activation/deactivation/deletion
if (isset($_GET['action']) && isset($_GET['id'])) {
    $categoryId = (int)$_GET['id'];
    
    if ($_GET['action'] === 'activate') {
        $updateQuery = "UPDATE categories SET status = 'active' WHERE category_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("i", $categoryId);
        
        if ($stmt->execute()) {
            $message = "Category activated successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to activate category.";
            $messageType = "danger";
        }
        $stmt->close();
    } elseif ($_GET['action'] === 'deactivate') {
        $updateQuery = "UPDATE categories SET status = 'inactive' WHERE category_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("i", $categoryId);
        
        if ($stmt->execute()) {
            $message = "Category deactivated successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to deactivate category.";
            $messageType = "danger";
        }
        $stmt->close();
    } elseif ($_GET['action'] === 'delete' && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
        // Check if category has products before deletion
        $checkQuery = "SELECT COUNT(*) as product_count FROM products WHERE category_id = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("i", $categoryId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row['product_count'] > 0) {
            $message = "Cannot delete category as it has products assigned. Consider deactivating it instead.";
            $messageType = "danger";
        } else {
            $deleteQuery = "DELETE FROM categories WHERE category_id = ?";
            $stmt = $conn->prepare($deleteQuery);
            $stmt->bind_param("i", $categoryId);
            
            if ($stmt->execute()) {
                $message = "Category deleted successfully.";
                $messageType = "success";
            } else {
                $message = "Failed to delete category.";
                $messageType = "danger";
            }
            $stmt->close();
        }
    }
}

// Handle form submission for adding/editing categories
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'], $conn);
    $description = sanitizeInput($_POST['description'], $conn);
    $status = sanitizeInput($_POST['status'], $conn);
    
    // If editing existing category
    if (isset($_POST['category_id']) && !empty($_POST['category_id'])) {
        $categoryId = (int)$_POST['category_id'];
        
        $updateQuery = "UPDATE categories SET name = ?, description = ?, status = ? WHERE category_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("sssi", $name, $description, $status, $categoryId);
        
        if ($stmt->execute()) {
            $message = "Category updated successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to update category. Error: " . $stmt->error;
            $messageType = "danger";
        }
        $stmt->close();
    }
    // If adding new category
    else {
        $insertQuery = "INSERT INTO categories (name, description, status) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("sss", $name, $description, $status);
        
        if ($stmt->execute()) {
            $message = "Category added successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to add category. Error: " . $stmt->error;
            $messageType = "danger";
        }
        $stmt->close();
    }
}

// Get category data for editing if ID is provided
$categoryData = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $categoryId = (int)$_GET['id'];
    $categoryQuery = "SELECT * FROM categories WHERE category_id = ?";
    $stmt = $conn->prepare($categoryQuery);
    $stmt->bind_param("i", $categoryId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $categoryData = $result->fetch_assoc();
    }
    $stmt->close();
}

// Fetch all categories
$categories = [];
$categoriesQuery = "SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.category_id) as product_count 
                   FROM categories c 
                   ORDER BY c.name ASC";
$result = mysqli_query($conn, $categoriesQuery);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
    mysqli_free_result($result);
}

// Include header
include '../includes/header/header.php';
?>

<div class="page-header">
    <h2><i class="fas fa-tags"></i> Categories Management</h2>
    <div class="page-actions">
        <a href="?action=add" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Add New Category</a>
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
            <h3><?php echo $_GET['action'] === 'add' ? 'Add New Category' : 'Edit Category'; ?></h3>
        </div>
        <div class="card-body">
            <form action="categories.php" method="post">
                <?php if (isset($categoryData)): ?>
                    <input type="hidden" name="category_id" value="<?php echo $categoryData['category_id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="name">Category Name</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($categoryData) ? $categoryData['name'] : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo isset($categoryData) ? $categoryData['description'] : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select class="form-control" id="status" name="status" required>
                        <option value="active" <?php echo (isset($categoryData) && $categoryData['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo (isset($categoryData) && $categoryData['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save</button>
                    <a href="categories.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                </div>
            </form>
        </div>
    </div>
<?php elseif (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && !isset($_GET['confirm'])): ?>
    <div class="alert alert-warning">
        <p>Are you sure you want to delete this category? This action cannot be undone.</p>
        <a href="?action=delete&id=<?php echo (int)$_GET['id']; ?>&confirm=yes" class="btn btn-danger">Yes, Delete</a>
        <a href="categories.php" class="btn btn-secondary">Cancel</a>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Products Count</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($categories) > 0): ?>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?php echo $category['category_id']; ?></td>
                                <td><?php echo $category['name']; ?></td>
                                <td><?php echo substr($category['description'], 0, 50) . (strlen($category['description']) > 50 ? '...' : ''); ?></td>
                                <td><?php echo $category['product_count']; ?></td>
                                <td>
                                    <span class="badge <?php echo $category['status'] === 'active' ? 'badge-success' : 'badge-secondary'; ?>">
                                        <?php echo ucfirst($category['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($category['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?action=edit&id=<?php echo $category['category_id']; ?>" class="btn btn-info" title="Edit"><i class="fas fa-edit"></i></a>
                                        
                                        <?php if ($category['status'] === 'active'): ?>
                                            <a href="?action=deactivate&id=<?php echo $category['category_id']; ?>" class="btn btn-warning" title="Deactivate"><i class="fas fa-ban"></i></a>
                                        <?php else: ?>
                                            <a href="?action=activate&id=<?php echo $category['category_id']; ?>" class="btn btn-success" title="Activate"><i class="fas fa-check"></i></a>
                                        <?php endif; ?>
                                        
                                        <?php if ($category['product_count'] == 0): ?>
                                            <a href="?action=delete&id=<?php echo $category['category_id']; ?>" class="btn btn-danger" title="Delete"><i class="fas fa-trash-alt"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No categories found.</td>
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
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    
    textarea.form-control {
        height: auto;
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
    
    .badge-success {
        color: #fff;
        background-color: #28a745;
    }
    
    .badge-secondary {
        color: #fff;
        background-color: #6c757d;
    }
</style>

<?php
// Close connection
mysqli_close($conn);

// Include footer
include '../includes/footer/footer.php';
?>