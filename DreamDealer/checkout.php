<?php
require __DIR__ . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION["user_id"])) {
  header("Location: /DreamDealer/auth/login.php");
  exit;
}

$user_id = (int)$_SESSION["user_id"];

// ✅ if you use cart from session/db, keep your existing cart loading logic here
include __DIR__ . '/partials/header.php';
?>

<div class="container" style="padding:30px 0 70px;">
  <h2 style="margin:0;">Checkout</h2>
  <p class="muted" style="margin-top:6px;">Enter your delivery details</p>

  <div class="card" style="max-width:720px;margin-top:14px;">
    <form method="POST" action="/DreamDealer/place_order.php">
      <div style="display:grid;gap:12px;">

        <div>
          <label class="muted">Delivery Address</label>
          <textarea name="address" required style="width:100%;padding:10px;border-radius:10px;"></textarea>
        </div>

        <div>
          <label class="muted">Phone Number</label>
          <input name="phone" required style="width:100%;padding:10px;border-radius:10px;">
        </div>

        <!-- ✅ Payment method -->
        <div>
          <label class="muted">Payment Method</label>
          <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:8px;">
            <label class="btn" style="display:flex;gap:8px;align-items:center;cursor:pointer;">
              <input type="radio" name="payment_method" value="cod" checked>
              Cash on Delivery
            </label>

            <label class="btn" style="display:flex;gap:8px;align-items:center;cursor:pointer;border-color:rgba(86,242,255,0.55);">
              <input type="radio" name="payment_method" value="online">
              Pay Online (SSLCOMMERZ TEST)
            </label>
          </div>
          <div class="muted" style="margin-top:6px;font-size:13px;">
            If you choose online payment, you’ll be redirected to the payment page after placing the order.
          </div>
        </div>

        <button class="btn" type="submit" style="width:160px;">
          Place Order
        </button>

      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
