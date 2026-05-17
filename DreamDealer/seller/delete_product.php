<?php
require __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION["user_id"])) {
  header("Location: /DreamDealer/auth/login.php");
  exit;
}

$seller_id = (int)$_SESSION["user_id"];
$id = (int)($_GET["id"] ?? 0);

// fetch product (only if belongs to this seller)
$stmt = $conn->prepare("SELECT image FROM products WHERE id=? AND seller_id=? LIMIT 1");
$stmt->bind_param("ii", $id, $seller_id);
$stmt->execute();
$res = $stmt->get_result();
$p = $res->fetch_assoc();
$stmt->close();

if (!$p) {
  header("Location: /DreamDealer/seller/my_products.php");
  exit;
}

// delete from DB
$stmt2 = $conn->prepare("DELETE FROM products WHERE id=? AND seller_id=?");
$stmt2->bind_param("ii", $id, $seller_id);
$stmt2->execute();
$stmt2->close();

// delete file if exists
if (!empty($p["image"])) {
  $path = __DIR__ . "/../uploads/" . $p["image"];
  if (is_file($path)) @unlink($path);
}

header("Location: /DreamDealer/seller/my_products.php");
exit;
