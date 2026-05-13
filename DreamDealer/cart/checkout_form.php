<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION["user_id"])) {
  header("Location: /DreamDealer/auth/login.php");
  exit;
}

$cart = $_SESSION["cart"] ?? [];
if (empty($cart)) {
  header("Location: /DreamDealer/cart/index.php");
  exit;
}

include __DIR__ . '/../partials/header.php';
?>

<div class="container" style="padding:30px 0 70px;max-width:700px;">
  <h2>Checkout</h2>
  <p class="muted">Enter your delivery details</p>

  <form method="POST" action="/DreamDealer/cart/checkout.php" class="card" style="margin-top:16px;">
    
    <label>Delivery Address</label>
    <textarea name="address" required
      style="width:100%;padding:10px;margin-top:6px;"><?php echo htmlspecialchars($_POST["address"] ?? ""); ?></textarea>

    <label style="margin-top:12px;display:block;">Phone Number</label>
    <input type="text" name="phone" required
      style="width:100%;padding:10px;margin-top:6px;"
      placeholder="01XXXXXXXXX">

    <button class="btn" style="margin-top:14px;">Place Order</button>
  </form>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
