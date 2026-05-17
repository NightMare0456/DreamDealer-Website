<?php
require __DIR__ . '/config/db.php';
include __DIR__ . '/partials/header.php';

$q   = trim($_GET["q"] ?? "");
$cat = trim($_GET["cat"] ?? "");
?>

<div class="container">
  <div class="hero">
    <h1>DreamDealer Marketplace</h1>
    <p>
      A clean, futuristic buying &amp; selling platform. Search products, explore categories,
      and list your items to sell — all with a modern UI.
    </p>

    <form class="searchbar" method="GET">
      <input name="q" value="<?= htmlspecialchars($q) ?>"
             placeholder="Search in DreamDealer… (phone, laptop, book)" />

      <?php if ($cat !== ""): ?>
        <input type="hidden" name="cat" value="<?= htmlspecialchars($cat) ?>">
      <?php endif; ?>

      <button class="btn" type="submit">Search</button>
    </form>

    <?php if ($cat !== ""): ?>
      <div class="muted" style="margin-top:10px;">
        Showing category: <b><?= htmlspecialchars($cat) ?></b>
        &nbsp;•&nbsp;
        <a href="/DreamDealer/index.php" style="color:var(--accent);text-decoration:none;">Clear</a>
      </div>
    <?php endif; ?>
  </div>

  <div class="grid">
    <?php
      $sql = "SELECT id,title,category,price,image
              FROM products
              WHERE is_approved = 1";
      $types = "";
      $params = [];

      if ($cat !== "") {
        $sql .= " AND category = ?";
        $types .= "s";
        $params[] = $cat;
      }

      if ($q !== "") {
        $sql .= " AND (title LIKE ? OR category LIKE ?)";
        $types .= "ss";
        $like = "%".$q."%";
        $params[] = $like;
        $params[] = $like;
      }

      $sql .= " ORDER BY id DESC";

      $stmt = $conn->prepare($sql);

      if ($types !== "") {
        $stmt->bind_param($types, ...$params);
      }

      $stmt->execute();
      $res = $stmt->get_result();

      if ($res->num_rows === 0) {
        echo '<div class="card" style="grid-column:1/-1;">No products found.</div>';
      } else {
        while ($p = $res->fetch_assoc()) {
          if (!empty($p["image"])) {
            $imgHtml = '<img src="/DreamDealer/uploads/'.rawurlencode($p["image"]).'"
                             alt="Product"
                             style="width:100%;height:100%;object-fit:contain;object-position:center;display:block;">';
          } else {
            $imgHtml = '<div style="width:100%;height:100%;
              background:linear-gradient(135deg,rgba(86,242,255,.18),rgba(180,108,255,.12));"></div>';
          }

          echo '
            <div class="card">
              <div class="img" style="background:rgba(255,255,255,0.04);overflow:hidden;
                   display:flex;align-items:center;justify-content:center;">
                '.$imgHtml.'
              </div>

              <div style="margin-top:10px;"><b>'.htmlspecialchars($p["title"]).'</b></div>
              <div class="muted">'.htmlspecialchars($p["category"]).'</div>

              <div style="flex:1;"></div>

              <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:10px;">
                <div class="price" style="margin:0;">৳ '.number_format((float)$p["price"], 2).'</div>
                <a class="btn"
                   style="width:auto;padding:8px 18px;text-decoration:none;white-space:nowrap;"
                   href="/DreamDealer/product.php?id='.(int)$p["id"].'">View</a>
              </div>
            </div>
          ';
        }
      }

      $stmt->close();
    ?>
  </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>