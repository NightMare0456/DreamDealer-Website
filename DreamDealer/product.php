<?php
require __DIR__ . '/config/db.php';
include __DIR__ . '/partials/header.php';

$id = (int)($_GET["id"] ?? 0);

$stmt = $conn->prepare("SELECT p.*, u.full_name AS seller_name
                        FROM products p
                        JOIN users u ON u.id = p.seller_id
                        WHERE p.id=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$p   = $res->fetch_assoc();
$stmt->close();

// Size options per category
$clothesSizes = ["XS", "S", "M", "L", "XL", "XXL"];
$shoesSizes   = ["38", "39", "40", "41", "42", "43", "44", "45"];

$category = strtolower(trim($p["category"] ?? ""));
$isClothes = str_contains($category, "cloth") || str_contains($category, "shirt")
          || str_contains($category, "pant")  || str_contains($category, "dress")
          || str_contains($category, "jacket")|| str_contains($category, "wear");
$isShoes   = str_contains($category, "shoe")  || str_contains($category, "boot")
          || str_contains($category, "sandal") || str_contains($category, "sneaker")
          || str_contains($category, "footwear");

$needsSize = $isClothes || $isShoes;
$sizes     = $isClothes ? $clothesSizes : ($isShoes ? $shoesSizes : []);
?>

<style>
/* ── Size Selector ── */
.size-label {
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 10px;
  color: rgba(255,255,255,.85);
}

.size-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 18px;
}

.size-pill {
  min-width: 48px;
  height: 44px;
  padding: 0 14px;
  border-radius: 12px;
  border: 1px solid rgba(255,255,255,.18);
  background: rgba(255,255,255,.04);
  color: rgba(255,255,255,.85);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all .18s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  user-select: none;
}

.size-pill:hover {
  border-color: rgba(86,242,255,.5);
  background: rgba(86,242,255,.08);
  color: #fff;
}

.size-pill.selected {
  border-color: rgba(86,242,255,.9);
  background: rgba(86,242,255,.18);
  color: #56F2FF;
  box-shadow: 0 0 18px rgba(86,242,255,.2);
}

.size-error {
  color: rgba(255,100,100,.9);
  font-size: 13px;
  margin-top: -10px;
  margin-bottom: 12px;
  display: none;
}
</style>

<div class="container" style="padding:30px 0 70px;">
  <?php if (!$p): ?>
    <div class="card">Product not found.</div>
  <?php else: ?>
    <div class="card" style="max-width:900px;margin:0 auto;">

      <h2 style="margin-top:0;"><?php echo htmlspecialchars($p["title"]); ?></h2>
      <p class="muted" style="margin-top:-8px;">
        Category: <?php echo htmlspecialchars($p["category"]); ?>
        &bull; Seller: <?php echo htmlspecialchars($p["seller_name"]); ?>
      </p>

      <!-- Product Image -->
      <div style="width:100%;height:420px;border-radius:14px;border:1px solid rgba(255,255,255,.12);
                  background:linear-gradient(135deg,rgba(86,242,255,.06),rgba(180,108,255,.04));
                  overflow:hidden;display:flex;align-items:center;justify-content:center;margin-bottom:6px;">
        <?php if (!empty($p["image"])): ?>
          <img src="/DreamDealer/uploads/<?php echo htmlspecialchars($p["image"]); ?>"
               style="width:100%;height:100%;object-fit:contain;object-position:center;display:block;">
        <?php else: ?>
          <div style="width:100%;height:100%;background:linear-gradient(135deg,rgba(86,242,255,.18),rgba(180,108,255,.12));"></div>
        <?php endif; ?>
      </div>

      <!-- Price -->
      <div class="price" style="font-size:22px;margin-top:14px;">
        ৳ <?php echo number_format((float)$p["price"], 2); ?>
      </div>

      <!-- Description -->
      <p class="muted" style="margin-top:10px;">
        <?php echo nl2br(htmlspecialchars($p["description"] ?? "")); ?>
      </p>

      <!-- ── SIZE SELECTOR ── -->
      <?php if ($needsSize && !empty($sizes)): ?>
        <div style="margin-top:18px;">
          <div class="size-label">
            <?php echo $isShoes ? '👟 Select Shoe Size (EU)' : '👕 Select Size'; ?>
          </div>
          <div class="size-grid" id="sizeGrid">
            <?php foreach ($sizes as $sz): ?>
              <div class="size-pill" data-size="<?php echo $sz; ?>"
                   onclick="selectSize(this)">
                <?php echo $sz; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="size-error" id="sizeError">
            ⚠️ Please select a size before adding to cart.
          </div>
        </div>
      <?php endif; ?>

      <!-- ── ACTION BUTTONS ── -->
      <div style="display:flex;gap:10px;margin-top:6px;flex-wrap:wrap;align-items:center;">
        <button class="btn" id="addToCartBtn"
                onclick="addToCart(<?php echo (int)$p['id']; ?>)"
                style="text-decoration:none;display:inline-block;">
          Add to Cart
        </button>

        <a class="btn" style="text-decoration:none;display:inline-block;"
           href="/DreamDealer/index.php">
          Back
        </a>
      </div>

    </div>
  <?php endif; ?>
</div>

<script>
let selectedSize = null;
const needsSize  = <?php echo $needsSize ? 'true' : 'false'; ?>;

function selectSize(el) {
  // deselect all
  document.querySelectorAll('.size-pill').forEach(p => p.classList.remove('selected'));
  // select clicked
  el.classList.add('selected');
  selectedSize = el.dataset.size;
  // hide error if shown
  document.getElementById('sizeError').style.display = 'none';
}

function addToCart(productId) {
  if (needsSize && !selectedSize) {
    // Shake the size grid and show error
    const grid = document.getElementById('sizeGrid');
    grid.style.animation = 'none';
    grid.offsetHeight; // reflow
    grid.style.animation = 'shake .35s ease';
    document.getElementById('sizeError').style.display = 'block';
    return;
  }

  let url = '/DreamDealer/cart/add.php?id=' + productId;
  if (selectedSize) url += '&size=' + encodeURIComponent(selectedSize);
  window.location.href = url;
}
</script>

<style>
@keyframes shake {
  0%,100%{ transform:translateX(0); }
  20%    { transform:translateX(-6px); }
  40%    { transform:translateX(6px); }
  60%    { transform:translateX(-4px); }
  80%    { transform:translateX(4px); }
}
</style>

<?php include __DIR__ . '/partials/footer.php'; ?>