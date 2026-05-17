<?php
require __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: /DreamDealer/auth/login.php");
    exit;
}

$errors = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST["title"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $price = trim($_POST["price"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $seller_id = (int)$_SESSION["user_id"];

    $size = null;

    // Handle Multiple Sizes for Clothes
    if ($category === "Clothes") {
        $selected_sizes = $_POST["clothes_size"] ?? [];
        if (empty($selected_sizes)) {
            $errors[] = "At least one clothes size is required.";
        } else {
            // Convert array to string: "M,L,XL"
            $size = implode(",", $selected_sizes); 
        }
    }

    // Handle Multiple Sizes for Shoes
    if ($category === "Shoes") {
        $selected_sizes = $_POST["shoes_size"] ?? [];
        if (empty($selected_sizes)) {
            $errors[] = "At least one shoe size is required.";
        } else {
            $size = implode(",", $selected_sizes);
        }
    }

    if ($title === "") $errors[] = "Title is required.";
    if ($category === "") $errors[] = "Category is required.";
    if ($price === "" || !is_numeric($price) || (float)$price <= 0) $errors[] = "Valid price is required.";

    // Image upload
    $imageName = null;
    if (isset($_FILES["image"]) && $_FILES["image"]["error"] === UPLOAD_ERR_OK) {
        $tmp = $_FILES["image"]["tmp_name"];
        $original = $_FILES["image"]["name"];
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowed = ["jpg","jpeg","png","webp"];

        if (!in_array($ext, $allowed)) {
            $errors[] = "Image must be jpg, jpeg, png, or webp.";
        } else {
            $imageName = "p_" . time() . "_" . rand(1000,9999) . "." . $ext;
            $dest = __DIR__ . "/../uploads/" . $imageName;
            if (!move_uploaded_file($tmp, $dest)) {
                $errors[] = "Image upload failed.";
            }
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO products (seller_id,title,category,price,description,image,size) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("issdsss", $seller_id, $title, $category, $price, $description, $imageName, $size);

        if ($stmt->execute()) {
            $success = "✅ Product added successfully!";
        } else {
            $errors[] = "Database error. Try again.";
        }
        $stmt->close();
    }
}

include __DIR__ . '/../partials/header.php';
?>

<div class="container" style="padding:30px 0 70px;">
  <div class="card" style="max-width:700px;margin:0 auto;">
    <h2 style="margin-top:0;">Add Product</h2>
    <p class="muted">Upload product info  </p>

    <?php if (!empty($errors)): ?>
      <div class="card" style="border-color: rgba(255,80,80,0.35); margin-bottom: 20px;">
        <b>Fix these:</b>
        <ul class="muted">
          <?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="card" style="border-color: rgba(86,242,255,0.45); margin-bottom: 20px;">
        <?php echo htmlspecialchars($success); ?>
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" style="margin-top:14px;">

      <!-- Title -->
      <div style="margin-bottom:10px;">
        <div class="muted" style="margin-bottom:6px;">Title</div>
        <input name="title" required
          style="width:100%;padding:12px;border-radius:14px;border:1px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.04);color:rgba(255,255,255,0.92);outline:0;">
      </div>

      <!-- Category -->
      <div style="margin-bottom:10px;">
        <div class="muted" style="margin-bottom:6px;">Category</div>
        <select name="category" id="category" required
          style="width:100%;padding:12px;border-radius:14px;border:1px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.04);color:rgba(142, 134, 255, 0.92);outline:0;">
          <option value="">Select Category</option>
          <option value="Electronics">Electronics</option>
          <option value="Books">Books</option>
          <option value="Clothes">Clothes</option>
          <option value="Shoes">Shoes</option>
        </select>
      </div>
      <!-- Clothes Sizes -->
<div id="clothesSizeDiv" style="margin-bottom:10px; display:none;">

  <div class="muted" style="margin-bottom:6px;">Clothes Sizes</div>

  <div style="
    background:	#DE3163;
    color:black;
    padding:12px;
    border-radius:14px;
    display:flex;
    gap:15px;
    flex-wrap:wrap;
  ">

    <label><input type="checkbox" name="clothes_size[]" value="S"> S</label>

    <label><input type="checkbox" name="clothes_size[]" value="M"> M</label>

    <label><input type="checkbox" name="clothes_size[]" value="L"> L</label>

    <label><input type="checkbox" name="clothes_size[]" value="XL"> XL</label>

    <label><input type="checkbox" name="clothes_size[]" value="XXL"> XXL</label>

  </div>

</div>
     
      <!-- Shoe Sizes -->
<div id="shoesSizeDiv" style="margin-bottom:10px; display:none;">

  <div class="muted" style="margin-bottom:6px;">Shoe Sizes</div>

  <div style="
    background:	#DE3163;
    color:black;
    padding:12px;
    border-radius:14px;
    display:flex;
    gap:15px;
    flex-wrap:wrap;
  ">

    <label><input type="checkbox" name="shoes_size[]" value="38"> 38</label>

    <label><input type="checkbox" name="shoes_size[]" value="39"> 39</label>

    <label><input type="checkbox" name="shoes_size[]" value="40"> 40</label>

    <label><input type="checkbox" name="shoes_size[]" value="41"> 41</label>

    <label><input type="checkbox" name="shoes_size[]" value="42"> 42</label>

    <label><input type="checkbox" name="shoes_size[]" value="43"> 43</label>

  </div>

</div>

      <!-- Price -->
      <div style="margin-bottom:10px;">
        <div class="muted" style="margin-bottom:6px;">Price (৳)</div>
        <input name="price" required
          style="width:100%;padding:12px;border-radius:14px;border:1px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.04);color:rgba(255,255,255,0.92);outline:0;">
      </div>

      <!-- Description -->
      <div style="margin-bottom:10px;">
        <div class="muted" style="margin-bottom:6px;">Description</div>
        <textarea name="description" rows="4"
          style="width:100%;padding:12px;border-radius:14px;border:1px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.04);color:rgba(255,255,255,0.92);outline:0;"></textarea>
      </div>

      <!-- Image -->
      <div style="margin-bottom:14px;">
        <div class="muted" style="margin-bottom:6px;">Product Photo (optional)</div>
        <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"
          style="width:100%;padding:10px;border-radius:14px;border:1px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.04);color:rgba(255,255,255,0.92);outline:0;">
      </div>

      <button class="btn" style="width:100%;">Add Product</button>
    </form>
  </div>
</div>

<script>
document.getElementById("category").addEventListener("change", function() {
    let value = this.value;

    let clothesDiv = document.getElementById("clothesSizeDiv");
    let shoesDiv = document.getElementById("shoesSizeDiv");

    clothesDiv.style.display = (value === "Clothes") ? "block" : "none";
    shoesDiv.style.display = (value === "Shoes") ? "block" : "none";
});
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>