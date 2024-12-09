<?php
// Start the session only if it hasn't been started yet
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

if (!isset($_GET['station']) || !isset($_GET['date'])) {
    die("Invalid tracking request.");
}

$station_name = urldecode($_GET['station']);
$order_date = urldecode($_GET['date']);

// Fetch orders based on station and date
$stmt = $conn->prepare("
    SELECT o.order_id, o.quantity, o.total_price, o.order_status,
           p.product_name, p.description, p.price, p.image, p.product_type, p.item_stock 
    FROM orders o 
    JOIN products p ON o.product_id = p.product_id
    JOIN stations s ON o.station_id = s.station_id
    WHERE s.station_name = ? AND DATE(o.order_date) = ?
");
$stmt->bind_param("ss", $station_name, $order_date);
$stmt->execute();
$result = $stmt->get_result();

?>
<?php include 'customernavbar.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Tracking</title>
    <link rel="stylesheet" href="ordertracking.css" />
</head>
<body>
    <h1>Order Tracking</h1>
    <p>Station: <?= htmlspecialchars($station_name); ?></p>
    <p>Date: <?= htmlspecialchars($order_date); ?></p>

    <div class="tracking-container">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="tracking-item">
                    <!-- Display product image -->
                    <?php if ($row['image']): ?>
                        <img src="data:image/jpeg;base64,<?= base64_encode($row['image']); ?>" alt="Product Image" width="80" height="80" />
                    <?php else: ?>
                        <img src="default_image.jpg" alt="No Image Available" width="80" height="80" />
                    <?php endif; ?>

                    <!-- Product details -->
                    <p><strong>Product Name:</strong> <?= htmlspecialchars($row['product_name']); ?></p>
                    <p><strong>Description:</strong> <?= htmlspecialchars($row['description']); ?></p>
                    <p><strong>Price:</strong> ₱<?= number_format($row['price'], 2); ?></p>
                    <p><strong>Quantity:</strong> <?= $row['quantity']; ?></p>
                    <p><strong>Total Price:</strong> ₱<?= number_format($row['total_price'], 2); ?></p>
                    <p><strong>Status:</strong> 
                        <?php 
                        switch($row['order_status']) {
                            case 'P': echo "Order Pending"; break;
                            case 'A': echo "Accepted"; break;
                            case 'F': echo "For Pickup"; break;
                            case 'Q': echo "Processing"; break;
                            case 'S': echo "Shipping"; break;
                            case 'D': echo "Delivered"; break;
                            default: echo "Unknown";
                        }
                        ?>
                    </p>
                    <p><strong>Product Type:</strong> <?= $row['product_type'] === 'R' ? 'Refill' : 'Item'; ?></p>
                    <?php if ($row['product_type'] === 'I'): ?>
                        <p><strong>Stock Remaining:</strong> <?= $row['item_stock'] ?? 'N/A'; ?></p>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No orders found for this station and date.</p>
        <?php endif; ?>
    </div>
</body>
</html>
