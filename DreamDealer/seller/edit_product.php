<?php
require __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION["user_id"])) {
  header("Location: /DreamDealer/auth/login.php");
  exit;
}

$seller_id = (int)$_SESSION["user_id"];
$id = (int)($_GET["id"] ?? 0);

$errors = [];
$success = "";

// Load product
$stmt = $conn->prepare("SELECT * FROM products WHERE id=? AND seller_id=? LIMIT 1");
$stmt->bind_param("ii", $id, $seller_id);
$stmt->execute();
$res = $stmt->get_result();
$product = $res->fetch_assoc();
$stmt->close();

if (!$product) {
  header("Location: /DreamDealer/seller/my_products.php");
  exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $title = trim($_POST["title"] ?? "");
  $category = trim($_POST["category"] ?? "");
  $price = trim($_POST["price"] ?? "");
  $description = trim($_POST["description"] ?? "");

  if ($title === "") $errors[] = "Title is required.";
  if ($category === "") $errors[] = "Category is required.";
  if ($price === "" || !is_numeric($price) || (float)$price <= 0) $errors[] = "Valid price is required.";

  // optional new image
  $newImageName = $product["image"];

  if (isset($_FILES["image"]) && $_FILES["image"]["error"] === UPLOAD_ERR_OK) {
    $tmp = $_FILES["image"]["tmp_name"];
    $original = $_FILES["image"]["name"];
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = ["jpg","jpeg","png","webp"];

    if (!in_array($ext, $allowed)) {
      $errors[] = "Image must be jpg, jpeg, png, or webp.";
    } else {
      $newImageName = "p_" . time() . "_" . rand(1000,9999) . "." . $ext;
      $dest = __DIR__ . "/../uploads/" . $newImageName;

      if (!move_uploaded_file($tmp, $dest)) {
        $errors[] = "Image upload failed.";
      } else {
        // remove old image file (if exists)
        if (!empty($product["image"])) {
          $oldPath = __DIR__ . "/../uploads/" . $product["image"];
          if (is_file($oldPath)) @unlink($oldPath);
        }
      }
    }
  }

  if (empty($errors)) {
    $stmt2 = $conn->prepare("UPDATE products
                             SET title=?, category=?, price=?, description=?, image=?
                             WHERE id=? AND seller_id=?");
    $stmt2->bind_param("ssdssii", $title, $category, $price, $description, $newImageName, $id, $seller_id);

    if ($stmt2->execute()) {
      $success = "✅ Updated successfully!";
      // reload product data
      $product["title"] = $title;
      $product["category"] = $category;
      $product["price"] = $price;
      $product["description"] = $description;
      $product["image"] = $newImageName;
    } else {
      $errors[] = "Database update failed.";
    }
    $stmt2->close();
  }
}

include __DIR__ . '/../partials/header.php';
?>

<div class="container" style="padding:30px 0 70px;">
  <div class="card" style="max-width:750px;margin:0 auto;">
    <h2 style="margin-top:0;">Edit Product</h2>
    <p class="muted">Update details and optionally change photo.</p>

    <?php if (!empty($errors)): ?>
      <div class="card" style="border-color: rgba(255,80,80,0.35);">
        <b>Fix these:</b>
        <ul class="muted">
          <?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="card" style="border-color: rgba(86,242,255,0.45);">
        <?php echo htmlspecialchars($success); ?>
      </div>
    <?php endif; ?>

    <?php
      if (!empty($product["image"])) {
        $imgSrc = "/DreamDealer/uploads/" . rawurlencode($product["image"]);
        $imgHtml = '<img src="'.$imgSrc.'" style="width:100%;height:100%;object-fit:cover;display:block;">';
      } else {
        $imgHtml = '<div style="width:100%;height:100%;
          background:linear-gradient(135deg, rgba(86,242,255,.18), rgba(180,108,255,.12));"></div>';
      }
    ?>

    <div class="img" style="height:260px;background:none;overflow:hidden;margin-bottom:14px;">
      <?php echo $imgHtml; ?>
    </div>

    <form method="POST" enctype="multipart/form-data">
      <div style="margin-bottom:10px;">
        <div class="muted" style="margin-bottom:6px;">Title</div>
        <input name="title" required
          value="<?php echo htmlspecialchars($product['title']); ?>"
          style="width:100%;padding:12px;border-radius:14px;border:1px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.04);color:rgba(255,255,255,0.92);outline:0;">
      </div>

      <div style="margin-bottom:10px;">
        <div class="muted" style="margin-bottom:6px;">Category</div>
        <input name="category" required
          value="<?php echo htmlspecialchars($product['category']); ?>"
          style="width:100%;padding:12px;border-radius:14px;border:1px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.04);color:rgba(255,255,255,0.92);outline:0;">
      </div>

      <div style="margin-bottom:10px;">
        <div class="muted" style="margin-bottom:6px;">Price (৳)</div>
        <input name="price" required
          value="<?php echo htmlspecialchars($product['price']); ?>"
          style="width:100%;padding:12px;border-radius:14px;border:1px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.04);color:rgba(255,255,255,0.92);outline:0;">
      </div>

      <div style="margin-bottom:10px;">
        <div class="muted" style="margin-bottom:6px;">Description</div>
        <textarea name="description" rows="4"
          style="width:100%;padding:12px;border-radius:14px;border:1px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.04);color:rgba(255,255,255,0.92);outline:0;"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
      </div>

      <div style="margin-bottom:14px;">
        <div class="muted" style="margin-bottom:6px;">Change Photo (optional)</div>
        <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"
          style="width:100%;padding:10px;border-radius:14px;border:1px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.04);color:rgba(255,255,255,0.92);outline:0;">
      </div>

      <div style="display:flex; gap:10px;">
        <button class="btn" style="flex:1;">Save Changes</button>
        <a class="btn" style="flex:1;text-decoration:none;text-align:center;" href="/DreamDealer/seller/my_products.php">Back</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
