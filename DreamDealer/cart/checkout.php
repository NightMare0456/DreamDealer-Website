<?php
require __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION["user_id"])) {
  header("Location: /DreamDealer/auth/login.php");
  exit;
}

$cart = $_SESSION["cart"] ?? [];

if (empty($cart)) {
  $_SESSION["flash_success"] = "Cart is empty.";
  header("Location: /DreamDealer/cart/index.php");
  exit;
}

$address = trim($_POST["address"] ?? "");
$phone   = trim($_POST["phone"] ?? "");

if ($address === "" || $phone === "") {
  $_SESSION["flash_success"] = "Please fill all delivery details.";
  header("Location: /DreamDealer/cart/checkout_form.php");
  exit;
}

$user_id = (int)$_SESSION["user_id"];

// calculate total
$total = 0.0;
foreach ($cart as $item) {
  $total += ((float)$item["price"] * (int)$item["qty"]);
}

$conn->begin_transaction();

try {
  // ✅ FIXED INSERT (placeholders match)
  $stmt = $conn->prepare(
    "INSERT INTO orders (user_id, total, status, address, phone)
     VALUES (?, ?, 'pending', ?, ?)"
  );
  $stmt->bind_param("idss", $user_id, $total, $address, $phone);

  $stmt->execute();
  $order_id = $stmt->insert_id;
  $stmt->close();

  // insert items
  $stmt2 = $conn->prepare(
    "INSERT INTO order_items (order_id, product_id, title, price, qty, line_total)
     VALUES (?, ?, ?, ?, ?, ?)"
  );

  foreach ($cart as $item) {
    $product_id = (int)$item["id"];
    $title = $item["title"];
    $price = (float)$item["price"];
    $qty = (int)$item["qty"];
    $line_total = $price * $qty;

    $stmt2->bind_param("iisdid", $order_id, $product_id, $title, $price, $qty, $line_total);
    $stmt2->execute();
  }
  $stmt2->close();

  $conn->commit();

  // clear cart
  unset($_SESSION["cart"]);

  $_SESSION["flash_success"] = "✅ Order placed successfully! Order ID: #".$order_id;
  header("Location: /DreamDealer/orders/my_orders.php");
  exit;

} catch (Exception $e) {
  $conn->rollback();
  $_SESSION["flash_success"] = "❌ Order failed. Try again.";
  header("Location: /DreamDealer/cart/index.php");
  exit;
}
