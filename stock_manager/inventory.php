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

// Initialize variables
$message = '';
$messageType = '';

// Handle stock updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_stock'])) {
        $productId = (int)$_POST['product_id'];
        $quantity = (int)$_POST['quantity'];
        $movementType = sanitizeInput($_POST['movement_type'], $conn);
        $notes = sanitizeInput($_POST['notes'], $conn);
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update product stock quantity based on movement type
            if ($movementType === 'in') {
                $updateQuery = "UPDATE products SET stock_quantity = stock_quantity + ? WHERE product_id = ?";
            } else {
                // Check if there's enough stock
                $checkQuery = "SELECT stock_quantity FROM products WHERE product_id = ?";
                $stmt = $conn->prepare($checkQuery);
                $stmt->bind_param("i", $productId);
                $stmt->execute();
                $result = $stmt->get_result();
                $product = $result->fetch_assoc();
                $stmt->close();
                
                if ($product['stock_quantity'] < $quantity) {
                    throw new Exception("Not enough stock available!");
                }
                
                $updateQuery = "UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?";
            }
            
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("ii", $quantity, $productId);
            $stmt->execute();
            $stmt->close();
            
            // Record stock movement
            $movementQuery = "INSERT INTO stock_movements (product_id, quantity, movement_type, notes, performed_by) 
                             VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($movementQuery);
            $stmt->bind_param("iissi", $productId, $quantity, $movementType, $notes, $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();
            
            // Commit the transaction
            $conn->commit();
            
            $message = "Stock updated successfully!";
            $messageType = "success";
            
        } catch (Exception $e) {
            // Roll back the transaction in case of error
            $conn->rollback();
            $message = "Error updating stock: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Get product categories for filtering
$categories = [];
$categoryQuery = "SELECT category_id, name FROM categories WHERE status = 'active' ORDER BY name";
$categoryResult = $conn->query($categoryQuery);
if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Include header
include '../includes/header/header.php';
?>

<div class="inventory-container">
    <div class="page-header">
        <h2><i class="fas fa-boxes"></i> Inventory Management</h2>
        <div class="page-actions">
            <a href="stock_reports.php" class="btn btn-info">
                <i class="fas fa-chart-bar"></i> Stock Report
            </a>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3>Product Inventory</h3>
                    <div class="filters">
                        <div class="filter-group">
                            <label for="categoryFilter">Category:</label>
                            <select id="categoryFilter" class="form-control">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['name']; ?>">
                                        <?php echo $category['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="stockFilter">Stock Level:</label>
                            <select id="stockFilter" class="form-control">
                                <option value="">All Levels</option>
                                <option value="low">Low Stock</option>
                                <option value="out">Out of Stock</option>
                                <option value="normal">Normal Stock</option>
                            </select>
                        </div>
                        <div class="filter-group search">
                            <label for="searchInput">Search:</label>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search products...">
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="inventoryTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Product Name</th>
                                    <th>SKU</th>
                                    <th>Category</th>
                                    <th>Current Stock</th>
                                    <th>Min. Stock</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $productsQuery = "SELECT p.*, c.name as category_name 
                                                FROM products p
                                                LEFT JOIN categories c ON p.category_id = c.category_id
                                                ORDER BY p.name";
                                $productsResult = $conn->query($productsQuery);
                                
                                if ($productsResult && $productsResult->num_rows > 0) {
                                    while ($product = $productsResult->fetch_assoc()) {
                                        // Determine stock status
                                        $stockStatus = '';
                                        $statusClass = '';
                                        
                                        if ($product['stock_quantity'] <= 0) {
                                            $stockStatus = 'Out of Stock';
                                            $statusClass = 'badge-danger';
                                        } elseif ($product['stock_quantity'] < $product['minimum_stock']) {
                                            $stockStatus = 'Low Stock';
                                            $statusClass = 'badge-warning';
                                        } else {
                                            $stockStatus = 'Normal';
                                            $statusClass = 'badge-success';
                                        }
                                        ?>
                                        <tr data-category="<?php echo $product['category_name']; ?>" 
                                            data-stock="<?php echo strtolower($stockStatus); ?>">
                                            <td><?php echo $product['product_id']; ?></td>
                                            <td><?php echo $product['name']; ?></td>
                                            <td><?php echo $product['sku']; ?></td>
                                            <td><?php echo $product['category_name']; ?></td>
                                            <td><?php echo $product['stock_quantity']; ?></td>
                                            <td><?php echo $product['minimum_stock']; ?></td>
                                            <td><span class="badge <?php echo $statusClass; ?>"><?php echo $stockStatus; ?></span></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary update-stock-btn" 
                                                        data-id="<?php echo $product['product_id']; ?>"
                                                        data-name="<?php echo $product['name']; ?>"
                                                        data-stock="<?php echo $product['stock_quantity']; ?>">
                                                    <i class="fas fa-edit"></i> Update
                                                </button>
                                                <button type="button" class="btn btn-sm btn-info view-history-btn"
                                                        data-id="<?php echo $product['product_id']; ?>"
                                                        data-name="<?php echo $product['name']; ?>">
                                                    <i class="fas fa-history"></i> History
                                                </button>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                } else {
                                    echo '<tr><td colspan="8" class="text-center">No products found</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update Stock Modal -->
<div class="modal fade" id="updateStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="">
                <div class="modal-header">
                    <h5 class="modal-title">Update Stock</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="updateProductId" name="product_id">
                    
                    <div class="form-group">
                        <label>Product Name</label>
                        <input type="text" id="updateProductName" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Current Stock</label>
                        <input type="text" id="updateCurrentStock" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="movement_type">Movement Type</label>
                        <select class="form-control" id="movement_type" name="movement_type" required>
                            <option value="in">Stock In</option>
                            <option value="out">Stock Out</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="quantity">Quantity</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        <small class="form-text text-muted">e.g., New shipment received, Damaged items, etc.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_stock" class="btn btn-primary">Update Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Stock History Modal -->
<div class="modal fade" id="stockHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Stock Movement History</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <h6 id="historyProductName" class="mb-3"></h6>
                
                <div class="table-responsive">
                    <table class="table table-sm table-striped" id="historyTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Movement Type</th>
                                <th>Quantity</th>
                                <th>Reference</th>
                                <th>Notes</th>
                                <th>Performed By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Stock history will be loaded here via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
    .inventory-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .filters {
        display: flex;
        gap: 15px;
    }
    
    .filter-group {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .filter-group label {
        margin: 0;
        color: white;
        white-space: nowrap;
    }
    
    .filter-group.search {
        flex-grow: 1;
    }
    
    .filter-group select, 
    .filter-group input {
        min-width: 150px;
    }
    
    .badge {
        padding: .25em .6em;
        font-size: 85%;
    }
    
    .badge-success {
        background-color: #28a745;
    }
    
    .badge-warning {
        background-color: #ffc107;
        color: #212529;
    }
    
    .badge-danger {
        background-color: #dc3545;
    }
    
    @media (max-width: 992px) {
        .filters {
            flex-direction: column;
            gap: 10px;
        }
        
        .filter-group {
            width: 100%;
        }
        
        .filter-group select, 
        .filter-group input {
            width: 100%;
        }
    }
</style>

<script>
    // Update Stock Modal
    document.querySelectorAll('.update-stock-btn').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-id');
            const productName = this.getAttribute('data-name');
            const currentStock = this.getAttribute('data-stock');
            
            document.getElementById('updateProductId').value = productId;
            document.getElementById('updateProductName').value = productName;
            document.getElementById('updateCurrentStock').value = currentStock;
            
            $('#updateStockModal').modal('show');
        });
    });
    
    // Stock History Modal
    document.querySelectorAll('.view-history-btn').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-id');
            const productName = this.getAttribute('data-name');
            
            document.getElementById('historyProductName').textContent = 'Product: ' + productName;
            
            // Fetch and display stock history via AJAX
            fetch(`get_stock_history.php?product_id=${productId}`)
                .then(response => response.json())
                .then(history => {
                    const tableBody = document.querySelector('#historyTable tbody');
                    tableBody.innerHTML = '';
                    
                    if (history.length === 0) {
                        tableBody.innerHTML = '<tr><td colspan="6" class="text-center">No history found</td></tr>';
                        return;
                    }
                    
                    history.forEach(item => {
                        const row = document.createElement('tr');
                        
                        // Set row class based on movement type
                        if (item.movement_type === 'in') {
                            row.classList.add('table-success');
                        } else {
                            row.classList.add('table-danger');
                        }
                        
                        row.innerHTML = `
                            <td>${formatDate(item.movement_date)}</td>
                            <td>${item.movement_type === 'in' ? 'Stock In' : 'Stock Out'}</td>
                            <td>${item.quantity}</td>
                            <td>${item.reference_id || '-'}</td>
                            <td>${item.notes || '-'}</td>
                            <td>${item.user_name}</td>
                        `;
                        tableBody.appendChild(row);
                    });
                })
                .catch(error => {
                    console.error('Error fetching stock history:', error);
                });
            
            $('#stockHistoryModal').modal('show');
        });
    });
    
    // Format date for display
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString();
    }
    
    // Filter by category
    document.getElementById('categoryFilter').addEventListener('change', filterTable);
    
    // Filter by stock level
    document.getElementById('stockFilter').addEventListener('change', filterTable);
    
    // Search functionality
    document.getElementById('searchInput').addEventListener('input', filterTable);
    
    // Combined filter function
    function filterTable() {
        const categoryFilter = document.getElementById('categoryFilter').value.toLowerCase();
        const stockFilter = document.getElementById('stockFilter').value.toLowerCase();
        const searchFilter = document.getElementById('searchInput').value.toLowerCase();
        
        document.querySelectorAll('#inventoryTable tbody tr').forEach(row => {
            const category = row.getAttribute('data-category').toLowerCase();
            const stockLevel = row.getAttribute('data-stock').toLowerCase();
            const rowText = row.textContent.toLowerCase();
            
            const categoryMatch = !categoryFilter || category === categoryFilter;
            const stockMatch = !stockFilter || stockLevel === stockFilter;
            const searchMatch = !searchFilter || rowText.includes(searchFilter);
            
            if (categoryMatch && stockMatch && searchMatch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
</script>

<?php
// Create get_stock_history.php file for AJAX stock history fetching
$stock_history_file = '../stock_manager/get_stock_history.php';
$stock_history_content = '<?php
session_start();
require_once "../config/database.php";

// Check if user is logged in and is a stock manager
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "stock_manager") {
    header("Content-Type: application/json");
    echo json_encode(["error" => "Unauthorized access"]);
    exit;
}

// Get database connection
$conn = getConnection();

// Get product ID
$productId = isset($_GET["product_id"]) ? (int)$_GET["product_id"] : 0;

if ($productId <= 0) {
    header("Content-Type: application/json");
    echo json_encode(["error" => "Invalid product ID"]);
    exit;
}

// Get stock movement history
$query = "SELECT sm.*, 
          CONCAT(u.full_name, \' (\', u.username, \')\') as user_name
          FROM stock_movements sm
          LEFT JOIN users u ON sm.performed_by = u.user_id
          WHERE sm.product_id = ?
          ORDER BY sm.movement_date DESC
          LIMIT 50";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $productId);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}

$stmt->close();
mysqli_close($conn);

// Return JSON response
header("Content-Type: application/json");
echo json_encode($history);
?>';

// Check if the file exists, if not create it
if (!file_exists($stock_history_file)) {
    file_put_contents($stock_history_file, $stock_history_content);
}

// Close connection
mysqli_close($conn);

// Include footer
include '../includes/footer/footer.php';
?>