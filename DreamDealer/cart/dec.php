<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$id = (int)($_GET["id"] ?? 0);

if ($id > 0 && isset($_SESSION["cart"])) {
  if (isset($_SESSION["cart"][$id])) {
    $_SESSION["cart"][$id]["qty"] -= 1;
    if ($_SESSION["cart"][$id]["qty"] <= 0) unset($_SESSION["cart"][$id]);
  } else {
    $sid = (string)$id;
    if (isset($_SESSION["cart"][$sid])) {
      $_SESSION["cart"][$sid]["qty"] -= 1;
      if ($_SESSION["cart"][$sid]["qty"] <= 0) unset($_SESSION["cart"][$sid]);
    }
  }
}

header("Location: /DreamDealer/cart/index.php");
exit;
