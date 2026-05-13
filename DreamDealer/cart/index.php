<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/../partials/header.php';

$cart  = $_SESSION["cart"] ?? [];
$total = 0.0;
foreach ($cart as $item) {
  $total += ((float)$item["price"] * (int)$item["qty"]);
}
?>

<div class="container" style="padding:36px 0 80px;">

  <!-- ===== PAGE HEADER ===== -->
  <div class="cart-header">
    <div>
      <h2 class="cart-header">My Cart
        <?php if (!empty($cart)): ?>
          <span style="font-size:14px;font-weight:500;color:var(--muted);margin-left:8px;">
            (<?= array_sum(array_column($cart,'qty')) ?> item<?= array_sum(array_column($cart,'qty')) != 1 ? 's' : '' ?>)
          </span>
        <?php endif; ?>
      </h2>
      <p class="muted" style="margin:4px 0 0;">Session-based cart &mdash; no payment yet.</p>
    </div>

    <div class="cart-actions">
      <a class="btn" href="/DreamDealer/index.php">← Continue Shopping</a>
      <?php if (!empty($cart)): ?>
        <a class="btn btn-danger" href="/DreamDealer/cart/clear.php"
           onclick="return confirm('Clear entire cart?');">🗑 Clear Cart</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- ===== EMPTY STATE ===== -->
  <?php if (empty($cart)): ?>
    <div class="card" style="text-align:center;padding:52px 24px;">
      <div style="font-size:48px;margin-bottom:12px;">🛒</div>
      <div style="font-size:18px;font-weight:700;margin-bottom:8px;">Your cart is empty</div>
      <p class="muted" style="margin:0 0 20px;">Browse our products and add something you love.</p>
      <a class="btn" href="/DreamDealer/index.php" style="margin:0 auto;">Shop Now</a>
    </div>

  <?php else: ?>

    <!-- ===== CART ITEMS ===== -->
    <?php foreach ($cart as $item):
      $line   = (float)$item["price"] * (int)$item["qty"];
      $imgSrc = !empty($item["image"])
        ? "/DreamDealer/uploads/" . rawurlencode($item["image"])
        : null;
    ?>
    <div class="cart-item">

      <!-- Image -->
      <div class="cart-item-img">
        <?php if ($imgSrc): ?>
          <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($item['title']) ?>">
        <?php else: ?>
          <div style="font-size:28px;">📦</div>
        <?php endif; ?>
      </div>

      <!-- Info -->
      <div class="cart-item-info">
        <div class="cart-item-title"><?= htmlspecialchars($item["title"]) ?></div>
        <div class="cart-item-price-unit">Unit price: ৳ <?= number_format((float)$item["price"], 2) ?></div>

        <!-- Qty Controls -->
        <div class="qty-controls">
          <a class="qty-btn" href="/DreamDealer/cart/dec.php?id=<?= (int)$item['id'] ?>"
             title="Decrease">−</a>
          <span class="qty-value"><?= (int)$item["qty"] ?></span>
          <a class="qty-btn" href="/DreamDealer/cart/inc.php?id=<?= (int)$item['id'] ?>"
             title="Increase">+</a>
        </div>
      </div>

      <!-- Right: line total + remove -->
      <div class="cart-item-right">
        <div class="cart-line-total">৳ <?= number_format($line, 2) ?></div>
        <a class="btn btn-danger"
           href="/DreamDealer/cart/remove.php?id=<?= (int)$item['id'] ?>"
           onclick="return confirm('Remove this item?');">Remove</a>
      </div>

    </div>
    <?php endforeach; ?>

    <!-- ===== ORDER SUMMARY ===== -->
    <div class="cart-summary">
      <div>
        <div class="cart-summary-label">Order Total</div>
        <div class="cart-total-amount">৳ <?= number_format($total, 2) ?></div>
      </div>
      <a class="btn btn-checkout" href="/DreamDealer/cart/checkout_form.php">
        Proceed to Checkout →
      </a>
    </div>

  <?php endif; ?>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>