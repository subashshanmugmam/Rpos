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

// Handle product actions
$message = '';
$messageType = '';

// Handle product activation/deactivation/deletion
if (isset($_GET['action']) && isset($_GET['id'])) {
    $productId = (int)$_GET['id'];
    
    if ($_GET['action'] === 'activate') {
        $updateQuery = "UPDATE products SET status = 'active' WHERE product_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("i", $productId);
        
        if ($stmt->execute()) {
            $message = "Product activated successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to activate product.";
            $messageType = "danger";
        }
        $stmt->close();
    } elseif ($_GET['action'] === 'deactivate') {
        $updateQuery = "UPDATE products SET status = 'inactive' WHERE product_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("i", $productId);
        
        if ($stmt->execute()) {
            $message = "Product deactivated successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to deactivate product.";
            $messageType = "danger";
        }
        $stmt->close();
    } elseif ($_GET['action'] === 'delete' && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
        // Check if product is used in sales before deletion
        $checkQuery = "SELECT COUNT(*) as sales_count FROM sales_items WHERE product_id = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row['sales_count'] > 0) {
            $message = "Cannot delete product as it is used in sales. Consider deactivating it instead.";
            $messageType = "danger";
        } else {
            $deleteQuery = "DELETE FROM products WHERE product_id = ?";
            $stmt = $conn->prepare($deleteQuery);
            $stmt->bind_param("i", $productId);
            
            if ($stmt->execute()) {
                $message = "Product deleted successfully.";
                $messageType = "success";
            } else {
                $message = "Failed to delete product.";
                $messageType = "danger";
            }
            $stmt->close();
        }
    }
}

// Handle form submission for adding/editing products
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'], $conn);
    $sku = sanitizeInput($_POST['sku'], $conn);
    $description = sanitizeInput($_POST['description'], $conn);
    $categoryId = (int)$_POST['category_id'];
    $supplierId = (int)$_POST['supplier_id'];
    $costPrice = (float)$_POST['cost_price'];
    $sellingPrice = (float)$_POST['selling_price'];
    $stockQuantity = (int)$_POST['stock_quantity'];
    $minimumStock = (int)$_POST['minimum_stock'];
    $status = sanitizeInput($_POST['status'], $conn);
    
    // If editing existing product
    if (isset($_POST['product_id']) && !empty($_POST['product_id'])) {
        $productId = (int)$_POST['product_id'];
        
        $updateQuery = "UPDATE products SET 
            name = ?, 
            sku = ?, 
            description = ?, 
            category_id = ?, 
            supplier_id = ?, 
            cost_price = ?, 
            selling_price = ?, 
            stock_quantity = ?, 
            minimum_stock = ?, 
            status = ? 
            WHERE product_id = ?";
        
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("sssiiddiisi", $name, $sku, $description, $categoryId, $supplierId, $costPrice, $sellingPrice, $stockQuantity, $minimumStock, $status, $productId);
        
        if ($stmt->execute()) {
            $message = "Product updated successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to update product. Error: " . $stmt->error;
            $messageType = "danger";
        }
        $stmt->close();
    }
    // If adding new product
    else {
        $insertQuery = "INSERT INTO products (name, sku, description, category_id, supplier_id, cost_price, selling_price, stock_quantity, minimum_stock, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("sssiiddiis", $name, $sku, $description, $categoryId, $supplierId, $costPrice, $sellingPrice, $stockQuantity, $minimumStock, $status);
        
        if ($stmt->execute()) {
            $message = "Product added successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to add product. Error: " . $stmt->error;
            $messageType = "danger";
        }
        $stmt->close();
    }
}

// Get product data for editing if ID is provided
$productData = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $productId = (int)$_GET['id'];
    $productQuery = "SELECT * FROM products WHERE product_id = ?";
    $stmt = $conn->prepare($productQuery);
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $productData = $result->fetch_assoc();
    }
    $stmt->close();
}

// Get all categories for dropdown
$categories = [];
$categoriesQuery = "SELECT category_id, name FROM categories WHERE status = 'active' ORDER BY name ASC";
$result = mysqli_query($conn, $categoriesQuery);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
    mysqli_free_result($result);
}

// Get all suppliers for dropdown
$suppliers = [];
$suppliersQuery = "SELECT supplier_id, name FROM suppliers WHERE status = 'active' ORDER BY name ASC";
$result = mysqli_query($conn, $suppliersQuery);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $suppliers[] = $row;
    }
    mysqli_free_result($result);
}

// Fetch all products
$products = [];
$productsQuery = "SELECT p.*, c.name as category_name, s.name as supplier_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.category_id 
                LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id 
                ORDER BY p.name ASC";
$result = mysqli_query($conn, $productsQuery);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }
    mysqli_free_result($result);
}

// Include header
include '../includes/header/header.php';
?>

<div class="page-header">
    <h2><i class="fas fa-box"></i> Products Management</h2>
    <div class="page-actions">
        <a href="?action=add" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Add New Product</a>
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
            <h3><?php echo $_GET['action'] === 'add' ? 'Add New Product' : 'Edit Product'; ?></h3>
        </div>
        <div class="card-body">
            <form action="products.php" method="post">
                <?php if (isset($productData)): ?>
                    <input type="hidden" name="product_id" value="<?php echo $productData['product_id']; ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="name">Product Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($productData) ? $productData['name'] : ''; ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="sku">SKU</label>
                        <input type="text" class="form-control" id="sku" name="sku" value="<?php echo isset($productData) ? $productData['sku'] : ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo isset($productData) ? $productData['description'] : ''; ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="category_id">Category</label>
                        <select class="form-control" id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['category_id']; ?>" <?php echo (isset($productData) && $productData['category_id'] == $category['category_id']) ? 'selected' : ''; ?>>
                                    <?php echo $category['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="supplier_id">Supplier</label>
                        <select class="form-control" id="supplier_id" name="supplier_id" required>
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['supplier_id']; ?>" <?php echo (isset($productData) && $productData['supplier_id'] == $supplier['supplier_id']) ? 'selected' : ''; ?>>
                                    <?php echo $supplier['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="cost_price">Cost Price</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="cost_price" name="cost_price" value="<?php echo isset($productData) ? $productData['cost_price'] : '0.00'; ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="selling_price">Selling Price</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="selling_price" name="selling_price" value="<?php echo isset($productData) ? $productData['selling_price'] : '0.00'; ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="stock_quantity">Stock Quantity</label>
                        <input type="number" min="0" class="form-control" id="stock_quantity" name="stock_quantity" value="<?php echo isset($productData) ? $productData['stock_quantity'] : '0'; ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="minimum_stock">Minimum Stock Level</label>
                        <input type="number" min="0" class="form-control" id="minimum_stock" name="minimum_stock" value="<?php echo isset($productData) ? $productData['minimum_stock'] : '10'; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select class="form-control" id="status" name="status" required>
                        <option value="active" <?php echo (isset($productData) && $productData['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo (isset($productData) && $productData['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save</button>
                    <a href="products.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                </div>
            </form>
        </div>
    </div>
<?php elseif (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && !isset($_GET['confirm'])): ?>
    <div class="alert alert-warning">
        <p>Are you sure you want to delete this product? This action cannot be undone.</p>
        <a href="?action=delete&id=<?php echo (int)$_GET['id']; ?>&confirm=yes" class="btn btn-danger">Yes, Delete</a>
        <a href="products.php" class="btn btn-secondary">Cancel</a>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>SKU</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Supplier</th>
                        <th>Stock</th>
                        <th>Cost</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($products) > 0): ?>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo $product['product_id']; ?></td>
                                <td><?php echo $product['sku']; ?></td>
                                <td><?php echo $product['name']; ?></td>
                                <td><?php echo $product['category_name']; ?></td>
                                <td><?php echo $product['supplier_name']; ?></td>
                                <td>
                                    <?php 
                                        if ($product['stock_quantity'] <= $product['minimum_stock'] && $product['stock_quantity'] > 0) {
                                            echo '<span class="badge badge-warning">' . $product['stock_quantity'] . '</span>';
                                        } elseif ($product['stock_quantity'] == 0) {
                                            echo '<span class="badge badge-danger">Out of stock</span>';
                                        } else {
                                            echo $product['stock_quantity'];
                                        }
                                    ?>
                                </td>
                                <td>$<?php echo number_format($product['cost_price'], 2); ?></td>
                                <td>$<?php echo number_format($product['selling_price'], 2); ?></td>
                                <td>
                                    <span class="badge <?php echo $product['status'] === 'active' ? 'badge-success' : 'badge-secondary'; ?>">
                                        <?php echo ucfirst($product['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?action=edit&id=<?php echo $product['product_id']; ?>" class="btn btn-info" title="Edit"><i class="fas fa-edit"></i></a>
                                        
                                        <?php if ($product['status'] === 'active'): ?>
                                            <a href="?action=deactivate&id=<?php echo $product['product_id']; ?>" class="btn btn-warning" title="Deactivate"><i class="fas fa-ban"></i></a>
                                        <?php else: ?>
                                            <a href="?action=activate&id=<?php echo $product['product_id']; ?>" class="btn btn-success" title="Activate"><i class="fas fa-check"></i></a>
                                        <?php endif; ?>
                                        
                                        <a href="?action=delete&id=<?php echo $product['product_id']; ?>" class="btn btn-danger" title="Delete"><i class="fas fa-trash-alt"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center">No products found.</td>
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
</style>

<?php
// Close connection
mysqli_close($conn);

// Include footer
include '../includes/footer/footer.php';
?>