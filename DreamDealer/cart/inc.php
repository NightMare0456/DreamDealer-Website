<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$id = (int)($_GET["id"] ?? 0);

if ($id > 0 && isset($_SESSION["cart"])) {
  // handle int or string keys
  if (isset($_SESSION["cart"][$id])) {
    $_SESSION["cart"][$id]["qty"] += 1;
  } else {
    $sid = (string)$id;
    if (isset($_SESSION["cart"][$sid])) {
      $_SESSION["cart"][$sid]["qty"] += 1;
    }
  }
}

header("Location: /DreamDealer/cart/index.php");
exit;
