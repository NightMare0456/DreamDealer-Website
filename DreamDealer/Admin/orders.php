<?php
require __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

/* =========================
   Admin Only
========================= */
if (!isset($_SESSION["user_id"])) {
  header("Location: /DreamDealer/auth/login.php");
  exit;
}
if (($_SESSION["role"] ?? "") !== "admin") {
  header("Location: /DreamDealer/index.php");
  exit;
}

/*=========================
   POST Actions
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

  if (isset($_POST["status"])) {

    $order_id = (int)($_POST["order_id"] ?? 0);
    $status   = $_POST["status"] ?? "";

    $allowed = ["pending","confirmed","cancelled"];

    if ($order_id > 0 && in_array($status, $allowed, true)) {

      $stmtU = $conn->prepare("UPDATE orders SET status=? WHERE id=?");
      $stmtU->bind_param("si", $status, $order_id);
      $stmtU->execute();
      $stmtU->close();

      // If confirmed → auto send invoice
      if ($status === "confirmed") {
        $_SESSION["flash_success"] = "✅ Order #{$order_id} confirmed. Sending invoice...";
        header("Location: /DreamDealer/admin/send_invoice.php?order_id=" . $order_id);
        exit;
      }

      $_SESSION["flash_success"] = "✅ Order #{$order_id} updated.";

    } else {
      $_SESSION["flash_success"] = "❌ Invalid status update.";
    }

    header("Location: /DreamDealer/admin/orders.php");
    exit;
  }

  // Mark COD Paid
  if (isset($_POST["mark_cod_paid"])) {

    $order_id = (int)($_POST["order_id"] ?? 0);

    if ($order_id > 0) {

      $stmtP = $conn->prepare("
        UPDATE orders
        SET payment_status='paid',
            paid_at=NOW()
        WHERE id=?
          AND payment_method='cod'
          AND payment_status='pending'
      ");
      $stmtP->bind_param("i", $order_id);
      $stmtP->execute();
      $affected = $stmtP->affected_rows;
      $stmtP->close();

      $_SESSION["flash_success"] = $affected > 0
        ? "✅ COD marked as PAID for Order #{$order_id}"
        : "⚠️ Cannot mark as paid.";

    }

    header("Location: /DreamDealer/admin/orders.php");
    exit;
  }
}

/* =========================
   Fetch Orders
========================= */
$stmt = $conn->prepare("
  SELECT o.id, o.total, o.status, o.created_at, o.address, o.phone,
         o.payment_method, o.payment_status, o.paid_at,
         u.full_name, u.email
  FROM orders o
  JOIN users u ON u.id = o.user_id
  ORDER BY o.id DESC
");
$stmt->execute();
$res = $stmt->get_result();

include __DIR__ . '/../partials/header.php';
?>

<div class="container" style="padding:30px 0 70px;">
  <h2>Admin — Orders</h2>
  <p class="muted">Manage orders, payments, and invoices.</p>

  <?php if ($res->num_rows === 0): ?>
    <div class="card">No orders yet.</div>
  <?php else: ?>

    <?php while($o = $res->fetch_assoc()): ?>
      <?php
        $oid = (int)$o["id"];
        $status = $o["status"];
        $payMethod = $o["payment_method"] ?? "cod";
        $payStatus = $o["payment_status"] ?? "pending";
        $paidAt    = $o["paid_at"] ?? "";

        // Fetch items
        $stmt2 = $conn->prepare("
          SELECT title, qty, line_total
          FROM order_items
          WHERE order_id=?
        ");
        $stmt2->bind_param("i", $oid);
        $stmt2->execute();
        $items = $stmt2->get_result();
      ?>

      <div class="card" style="margin-bottom:15px;">
        <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:20px;">

          <!-- LEFT SIDE -->
          <div style="flex:1;min-width:260px;">
            <b>Order #<?php echo $oid; ?></b>
            <div class="muted">Status: <?php echo htmlspecialchars($status); ?></div>
            <div class="muted">Date: <?php echo htmlspecialchars($o["created_at"]); ?></div>

            <div class="muted" style="margin-top:8px;">
              Buyer: <?php echo htmlspecialchars($o["full_name"]); ?>
              (<?php echo htmlspecialchars($o["email"]); ?>)
            </div>

            <div class="muted">Phone: <?php echo htmlspecialchars($o["phone"]); ?></div>
            <div class="muted">Address: <?php echo nl2br(htmlspecialchars($o["address"])); ?></div>

            <div class="muted" style="margin-top:8px;">
              Payment: <b><?php echo strtoupper($payMethod); ?></b>
              — <b><?php echo strtoupper($payStatus); ?></b>
              <?php if ($paidAt): ?>
                (Paid at: <?php echo htmlspecialchars($paidAt); ?>)
              <?php endif; ?>
            </div>
          </div>

          <!-- RIGHT SIDE -->
          <div style="text-align:right;min-width:260px;">
            <div class="price">Tk <?php echo number_format((float)$o["total"], 2); ?></div>

            <form method="POST" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
              <input type="hidden" name="order_id" value="<?php echo $oid; ?>">

              <!-- PENDING -->
              <?php if ($status === "pending"): ?>
                <button class="btn" name="status" value="confirmed" type="submit">
                  Confirm + Send Invoice
                </button>

                <button class="btn" name="status" value="cancelled" type="submit"
                        style="border-color:rgba(255,80,80,0.35);">
                  Cancel
                </button>
              <?php endif; ?>

              <!-- CONFIRMED -->
              <?php if ($status === "confirmed"): ?>
                <button class="btn" type="button" disabled
                        style="background:#28a745;color:#fff;cursor:not-allowed;">
                  ✅ Confirmed
                </button>
              <?php endif; ?>

              <!-- CANCELLED -->
              <?php if ($status === "cancelled"): ?>
                <button class="btn" type="button" disabled
                        style="background:#dc3545;color:#fff;cursor:not-allowed;">
                  ❌ Cancelled
                </button>
              <?php endif; ?>
            </form>

            <!-- Mark COD Paid -->
            <?php if ($payMethod === "cod" && $payStatus === "pending"): ?>
              <form method="POST" style="margin-top:10px;">
                <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
                <button class="btn" name="mark_cod_paid" value="1" type="submit" style="width:100%;">
                  Mark COD Paid
                </button>
              </form>
            <?php endif; ?>

            <!-- Send Invoice Again -->
            <?php if ($status === "confirmed"): ?>
              <a class="btn"
                 style="margin-top:10px;display:inline-block;width:100%;text-align:center;text-decoration:none;"
                 href="/DreamDealer/admin/send_invoice.php?order_id=<?php echo $oid; ?>">
                Send Invoice Again
              </a>
            <?php endif; ?>

          </div>
        </div>

        <!-- ITEMS -->
        <div style="margin-top:12px;">
          <div class="muted">Items:</div>
          <?php while($it = $items->fetch_assoc()): ?>
            <div class="muted" style="display:flex;justify-content:space-between;">
              <span><?php echo htmlspecialchars($it["title"]); ?> (x<?php echo (int)$it["qty"]; ?>)</span>
              <span>Tk <?php echo number_format((float)$it["line_total"], 2); ?></span>
            </div>
          <?php endwhile; ?>
        </div>

      </div>

      <?php $stmt2->close(); ?>
    <?php endwhile; ?>

  <?php endif; ?>
</div>

<?php
$stmt->close();
include __DIR__ . '/../partials/footer.php';
?>
