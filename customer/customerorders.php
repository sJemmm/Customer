<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include('dwos.php');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the logged-in customer's user_id
if (!isset($_SESSION['user_id'])) {
    die("User not logged in.");
}
$user_id = $_SESSION['user_id'];

// Fetch Customer Details from the database
$stmt = $conn->prepare("SELECT user_name, image, address, phone_number FROM users WHERE user_id = ? AND user_type = 'C'");
if (!$stmt) {
    echo "Prepare failed: (" . $conn->errno . ") " . $conn->error;
    exit();
}
$stmt->bind_param("s", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();

if ($user_result && $user_result->num_rows > 0) {
    $user = $user_result->fetch_assoc();
} else {
    echo "Error fetching customer details: " . $conn->error;
    exit();
}

// Extract user details
$user_name = $user['user_name'] ?? 'No name available';
$user_address = $user['address'] ?? 'No address available';
$user_phone = $user['phone_number'] ?? 'No phone number available';

// Fetch orders with product image and description
$sql = "SELECT o.order_id, p.product_name, p.description, p.image, o.quantity, o.total_price, p.price,
               o.order_date, o.order_status, s.station_name, o.user_address, o.payment_method 
        FROM orders o 
        JOIN products p ON o.product_id = p.product_id 
        JOIN stations s ON o.station_id = s.station_id
        WHERE o.user_id = ? 
        ORDER BY s.station_name, o.order_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Group orders by station name and order date
$grouped_items = [];
while ($row = $result->fetch_assoc()) {
    $station_and_date = $row['station_name'] . '|' . date("F j, Y", strtotime($row['order_date']));
    $grouped_items[$station_and_date][] = $row;
}

$stmt->close();
$conn->close();
?>

<?php include 'customernavbar.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="customerorders.css" />
    <title>Order History</title>
</head>
<body>

<div class="order-history-container">
    <div class="order-container">
        <div class="order-header">
            <h1>Order History</h1>
            <div class="user-info" onclick="openEditModal()">
                <p><?php echo htmlspecialchars($user_address); ?></p>
                <p><?php echo htmlspecialchars($user_name); ?></p>
                <p><?php echo htmlspecialchars($user_phone); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div id="edit-modal" class="modal">
        <div class="modal-container">
            <div class="modal-content">
                <span class="close-btn" onclick="closeEditModal()">&times;</span>
                <h2 class="modal-title">EDIT ADDRESS</h2>
                <form id="edit-form" method="post" action="update_user_details.php">
                    <div class="form-group-container">
                        <div class="form-group-section form-group-user-info">
                            <label class="username-title">Contact Information</label>
                            <div class="form-group-username">
                                <label for="new-user-name">Name:</label>
                                <input type="text" id="new-user-name" name="new_user_name" 
                                    value="<?php echo htmlspecialchars($user_name); ?>" required />
                            </div>
                            <div class="form-group-phonenum">
                                <label for="new-phone">Phone Number:</label>
                                <input type="tel" id="new-phone" name="new_phone" 
                                    value="<?php echo htmlspecialchars($user_phone); ?>" placeholder="Enter new phone number" 
                                    pattern="[0-9]{10}" title="Enter a 10-digit phone number" required />
                            </div>
                        </div>
                        <div class="form-group-section form-group-address-container">
                            <label class="address-title">Address Information</label>
                            <div class="form-group-address">
                                <label for="new-address">Provide the complete Address(Street, Purok, Municipality,Region).</label>
                                <textarea id="new-address" name="new_address" required><?php echo htmlspecialchars($user_address); ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="save-btn">Done</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View All Orders Modal -->
    <div id="view-all-modal" class="modal">
        <div class="modal-container">
            <div class="modal-content">
                <span class="close-btn" onclick="closeViewAllModal()">&times;</span>
                <h2 class="modal-title">All Orders</h2>
                <div id="all-orders-content" class="order-grid">
                    <!-- Dynamic order content will be injected here -->
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($grouped_items)): ?>
        <?php foreach ($grouped_items as $station_and_date => $items): ?>
            <?php list($station_name, $order_date) = explode('|', $station_and_date); ?>
            
            <!-- Station Header -->
            <div class="station-group">
                <h2 class="station-header"><?= htmlspecialchars($station_name); ?></h2>

                <!-- Order Items Container -->
                <div class="order-items-container">
                    <?php
                    $item_count = count($items);
                    $view_all = isset($_GET['view_all']) && $_GET['station_and_date'] === $station_and_date;
                    $items_to_show = $view_all ? $item_count : min($item_count, 3);
                    ?>
                    <?php for ($i = 0; $i < $items_to_show; $i++): ?>
                        <div class="order-item">
                            <div class="order-item-list">
                                <div class="order-image">
                                    <?php if (!empty($items[$i]['image'])): ?>
                                        <img src="../owner/<?= htmlspecialchars($items[$i]['image']); ?>" 
                                             alt="<?= htmlspecialchars($items[$i]['product_name']); ?>" 
                                             class="product-image" />
                                    <?php else: ?>
                                        <p><em>No image available.</em></p>
                                    <?php endif; ?>
                                </div>

                                <div class="order-details">
                                    <p class="product-name"><?= htmlspecialchars($items[$i]['product_name']); ?> (<?= htmlspecialchars($items[$i]['description']); ?>)</p>
                                    <p><strong class="price-label">Price: </strong><strong><span class="price-value">₱ <?= number_format($items[$i]['price'], 2); ?></span></strong></p>
                                    <p><strong class="Quantity">Quantity: </strong><strong><span class="quantity-value"> <?= $items[$i]['quantity']; ?></span></strong></p>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>

                    <!-- Payment Method (Displayed Once Inside the Container) -->
                    <div class="payment-method">
                        <p><strong>Payment Method:</strong> <span><?= htmlspecialchars($items[0]['payment_method']); ?></span></p>
                    </div>
                </div>

                <!-- View All Button inside the Container -->
                <div class="view-all-container">
                    <?php if ($item_count > 3 && !$view_all): ?>
                        <a href="javascript:void(0);" class="view-all-link" onclick="openViewAllModal(<?= htmlspecialchars(json_encode($items)); ?>)">View All</a>
                    <?php elseif ($item_count > 3 && $view_all): ?>
                        <a href="customerorders.php" class="view-all-link">View Less</a>
                    <?php endif; ?>
                </div>

                <!-- Order Date and Status -->
                <div class="order-info">
                    <h3 class="order-date"><?= htmlspecialchars($order_date); ?> - 
                         <span class="status"><?php 
                         switch($items[0]['order_status']) {
                            case 'P': echo "Order Pending"; break;
                            case 'A': echo "Accepted"; break;
                            case 'F': echo "For Pickup"; break;
                            case 'Q': echo "Processing"; break;
                            case 'S': echo "Shipping"; break;
                            case 'D': echo "Delivered"; break;
                            default: echo "Unknown";
                         }
                         ?>
                    </h3>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No orders found.</p>
    <?php endif; ?>

</div>

<script>
// Function to open edit address modal
function openEditModal() {
    document.getElementById('edit-modal').style.display = 'block';
}

// Function to close the edit modal
function closeEditModal() {
    document.getElementById('edit-modal').style.display = 'none';
}

// Function to open the view all orders modal
function openViewAllModal(orders) {
    const allOrdersContent = document.getElementById('all-orders-content');
    allOrdersContent.innerHTML = ''; // Clear previous content

    // Create order items dynamically
    orders.forEach(order => {
        const orderItem = `
            <div class="order-item">
                <div class="order-item-list">
                    <div class="order-image">
                        <img src="../owner/${order.image || 'placeholder.png'}" 
                             alt="${order.product_name}" 
                             class="product-image" />
                    </div>
                    <div class="order-details">
                        <p class="product-name">${order.product_name} (${order.description})</p>
                        <p><strong class="price-label">Price: </strong><strong><span class="price-value">₱ ${Number(order.price).toFixed(2)}</span></strong></p>
                        <p><strong class="Quantity">Quantity: </strong><strong><span class="quantity-value"> ${order.quantity}</span></strong></p>
                    </div>
                </div>
            </div>`;
        allOrdersContent.innerHTML += orderItem;
    });

    document.getElementById('view-all-modal').style.display = 'block';
}

// Function to close the view all orders modal
function closeViewAllModal() {
    document.getElementById('view-all-modal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('view-all-modal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
};
</script>

</body>
</html>
