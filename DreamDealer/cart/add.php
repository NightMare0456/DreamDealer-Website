<?php
require __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) {
  header("Location: /DreamDealer/index.php");
  exit;
}

// check product exists
$stmt = $conn->prepare("SELECT id, title, price, image FROM products WHERE id=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$p = $res->fetch_assoc();
$stmt->close();

if (!$p) {
  header("Location: /DreamDealer/index.php");
  exit;
}

// init cart
if (!isset($_SESSION["cart"])) $_SESSION["cart"] = [];

// if exists, increase qty
if (isset($_SESSION["cart"][$id])) {
  $_SESSION["cart"][$id]["qty"] += 1;
} else {
  $_SESSION["cart"][$id] = [
    "id" => (int)$p["id"],
    "title" => $p["title"],
    "price" => (float)$p["price"],
    "image" => $p["image"],
    "qty" => 1
  ];
}

// back to previous page

$_SESSION["flash_success"] = "✅ Product added to cart";

$back = $_SERVER["HTTP_REFERER"] ?? "/DreamDealer/index.php";
header("Location: " . $back);
exit;
