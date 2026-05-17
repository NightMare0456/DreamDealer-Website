<?php
session_start();
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /DreamDealer/auth/login.php");
    exit;
}

$seller_id = (int)$_SESSION['user_id'];
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch order info
$stmt = $conn->prepare("
    SELECT o.id, o.user_id, o.total, o.status, o.created_at, u.full_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

// Fetch seller's items in this order
$items_stmt = $conn->prepare("
    SELECT p.title, oi.qty, oi.price, oi.line_total
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ? AND p.seller_id = ?
");
$items_stmt->bind_param("ii", $order_id, $seller_id);
$items_stmt->execute();
$items = $items_stmt->get_result();

include __DIR__ . '/../partials/header.php';
?>

<div class="container" style="padding:30px 0;">
    <h2>Order #<?php echo $order['id']; ?></h2>
    <p><b>Buyer:</b> <?php echo htmlspecialchars($order['full_name']); ?></p>
    <p><b>Date:</b> <?php echo $order['created_at']; ?></p>
    <p><b>Status:</b> <?php echo $order['status']; ?></p>

    <table class="table" border="1" cellpadding="8" style="width:100%; border-collapse:collapse;">
        <tr>
            <th>Product</th>
            <th>Qty</th>
            <th>Price</th>
            <th>Line Total</th>
        </tr>
        <?php 
        $total = 0;
        while($row = $items->fetch_assoc()):
            $total += $row['line_total'];
        ?>
        <tr>
            <td><?php echo htmlspecialchars($row['title']); ?></td>
            <td><?php echo $row['qty']; ?></td>
            <td>৳ <?php echo number_format($row['price'], 2); ?></td>
            <td>৳ <?php echo number_format($row['line_total'], 2); ?></td>
        </tr>
        <?php endwhile; ?>
    </table>

    <h3>Total Amount: ৳ <?php echo number_format($total,2); ?></h3>
</div>

<?php
$stmt->close();
$items_stmt->close();
include __DIR__ . '/../partials/footer.php';
?>