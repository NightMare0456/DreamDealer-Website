<?php
require __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Admin only
if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
  header("Location: /DreamDealer/index.php");
  exit;
}

// ACTIONS (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = $_POST["action"] ?? "";
  $pid = (int)($_POST["product_id"] ?? 0);

  if ($pid <= 0) {
    $_SESSION["flash_success"] = "❌ Invalid product.";
    header("Location: /DreamDealer/admin/products.php");
    exit;
  }

  // Approve / Unapprove
  if ($action === "approve" || $action === "unapprove") {
    $val = ($action === "approve") ? 1 : 0;
    $stmt = $conn->prepare("UPDATE products SET is_approved=? WHERE id=?");
    $stmt->bind_param("ii", $val, $pid);
    $stmt->execute();
    $stmt->close();
    $_SESSION["flash_success"] = "✅ Product #{$pid} updated.";
  }

  // Delete product
  if ($action === "delete") {
    // Get image to delete file too
    $stmt = $conn->prepare("SELECT image FROM products WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    $imgRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Delete from DB
    $stmt2 = $conn->prepare("DELETE FROM products WHERE id=?");
    $stmt2->bind_param("i", $pid);
    $stmt2->execute();
    $stmt2->close();

    // Delete image file (optional)
    $img = $imgRow["image"] ?? "";
    if ($img !== "") {
      $path = __DIR__ . "/../uploads/" . $img;
      if (file_exists($path)) @unlink($path);
    }

    $_SESSION["flash_success"] = "🗑️ Product #{$pid} deleted.";
  }

  header("Location: /DreamDealer/admin/products.php");
  exit;
}

// SEARCH
$q = trim($_GET["q"] ?? "");

// FETCH products + seller name
if ($q !== "") {
  $like = "%".$q."%";
  $stmt = $conn->prepare("
    SELECT p.id, p.title, p.category, p.price, p.image, p.is_approved, p.created_at,
           u.full_name AS seller_name
    FROM products p
    LEFT JOIN users u ON u.id = p.user_id
    WHERE p.title LIKE ? OR p.category LIKE ?
    ORDER BY p.id DESC
  ");
  $stmt->bind_param("ss", $like, $like);
} else {
  $stmt = $conn->prepare("
    SELECT p.id, p.title, p.category, p.price, p.image, p.is_approved, p.created_at,
           u.full_name AS seller_name
    FROM products p
    LEFT JOIN users u ON u.id = p.user_id
    ORDER BY p.id DESC
    LIMIT 200
  ");
}

$stmt->execute();
$res = $stmt->get_result();

include __DIR__ . '/../partials/header.php';
?>

<div class="container" style="padding:30px 0 70px;">
  <h2 style="margin:0;">Admin — Products</h2>
  <p class="muted" style="margin-top:6px;">Approve/unapprove or delete products.</p>

  <form method="GET" style="margin-top:12px; display:flex; gap:10px;">
    <input
      name="q"
      value="<?php echo htmlspecialchars($q); ?>"
      placeholder="Search title or category..."
      style="flex:1; padding:12px; border-radius:14px; border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.04); color:#fff;"
    />
    <button class="btn" type="submit">Search</button>
    <a class="btn" href="/DreamDealer/admin/products.php" style="text-decoration:none;">Clear</a>
  </form>

  <div style="margin-top:14px;">
    <?php if ($res->num_rows === 0): ?>
      <div class="card">No products found.</div>
    <?php else: ?>
      <?php while($p = $res->fetch_assoc()): ?>
        <?php
          $pid = (int)$p["id"];
          $approved = (int)($p["is_approved"] ?? 1);
          $img = $p["image"] ?? "";
        ?>

        <div class="card" style="margin-bottom:12px;">
          <div style="display:flex; gap:14px; align-items:flex-start; flex-wrap:wrap;">

            <!-- Image -->
            <div style="width:120px; height:90px; border-radius:14px; overflow:hidden; border:1px solid rgba(255,255,255,.12);">
              <?php if ($img !== ""): ?>
                <img src="/DreamDealer/uploads/<?php echo htmlspecialchars($img); ?>"
                     style="width:100%; height:100%; object-fit:cover; display:block;">
              <?php else: ?>
                <div style="width:100%;height:100%;background:linear-gradient(135deg, rgba(86,242,255,.18), rgba(180,108,255,.12));"></div>
              <?php endif; ?>
            </div>

            <!-- Info -->
            <div style="flex:1; min-width:240px;">
              <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                <div>
                  <b><?php echo htmlspecialchars($p["title"]); ?></b>
                  <div class="muted"><?php echo htmlspecialchars($p["category"]); ?></div>
                  <div class="muted">Seller: <?php echo htmlspecialchars($p["seller_name"] ?? "Unknown"); ?></div>
                  <div class="muted">Added: <?php echo htmlspecialchars($p["created_at"]); ?></div>
                </div>

                <div style="text-align:right;">
                  <div class="price">৳ <?php echo number_format((float)$p["price"], 2); ?></div>
                  <div class="muted" style="margin-top:4px;">
                    Status:
                    <?php if ($approved === 1): ?>
                      <span style="color:rgba(86,242,255,.95);font-weight:700;">Approved</span>
                    <?php else: ?>
                      <span style="color:rgba(255,180,80,.95);font-weight:700;">Pending</span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <!-- Actions -->
              <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="product_id" value="<?php echo $pid; ?>">
                  <?php if ($approved === 1): ?>
                    <button class="btn" name="action" value="unapprove" type="submit">Unapprove</button>
                  <?php else: ?>
                    <button class="btn" name="action" value="approve" type="submit">Approve</button>
                  <?php endif; ?>
                </form>

                <form method="POST" style="display:inline;"
                      onsubmit="return confirm('Delete this product? This cannot be undone.');">
                  <input type="hidden" name="product_id" value="<?php echo $pid; ?>">
                  <button class="btn" name="action" value="delete" type="submit"
                          style="border-color:rgba(255,80,80,.35);">
                    Delete
                  </button>
                </form>
              </div>

            </div>

          </div>
        </div>

      <?php endwhile; ?>
    <?php endif; ?>
  </div>
</div>

<?php
$stmt->close();
include __DIR__ . '/../partials/footer.php';
?>
