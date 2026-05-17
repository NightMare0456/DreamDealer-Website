<?php
require __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: /DreamDealer/auth/login.php");
    exit;
}

$seller_id = (int)$_SESSION["user_id"];

/* ==============================
   FETCH SELLER PRODUCTS
============================== */
$stmt = $conn->prepare("
    SELECT id, title, category, price, image, created_at
    FROM products
    WHERE seller_id=?
    ORDER BY id DESC
");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$res = $stmt->get_result();

$products = [];
while($row = $res->fetch_assoc()){
    $products[] = $row;
}
$product_count = count($products);

/* ==============================
   SELLER ORDER STATS
============================== */
// Total Confirmed Orders + Revenue
$order_stmt = $conn->prepare("
    SELECT COUNT(*) as total_orders,
           IFNULL(SUM(total),0) as total_revenue
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.seller_id=? AND o.status='confirmed'
");
$order_stmt->bind_param("i", $seller_id);
$order_stmt->execute();
$order_data = $order_stmt->get_result()->fetch_assoc();

$total_orders = $order_data["total_orders"] ?? 0;
$total_revenue = $order_data["total_revenue"] ?? 0;

// Orders Today
$today_stmt = $conn->prepare("
    SELECT COUNT(*) as today_orders
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.seller_id=? AND DATE(o.created_at) = CURDATE()
");
$today_stmt->bind_param("i", $seller_id);
$today_stmt->execute();
$today_orders = $today_stmt->get_result()->fetch_assoc()["today_orders"] ?? 0;

// Last 7 Days Orders
$week_stmt = $conn->prepare("
    SELECT COUNT(*) as week_orders
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.seller_id=? AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$week_stmt->bind_param("i", $seller_id);
$week_stmt->execute();
$week_orders = $week_stmt->get_result()->fetch_assoc()["week_orders"] ?? 0;

/* ==============================
   LATEST ORDERS
============================== */
$latest_stmt = $conn->prepare("
    SELECT o.id, o.total, o.status, o.created_at, u.full_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.seller_id=?
    GROUP BY o.id
    ORDER BY o.id DESC
    LIMIT 5
");
$latest_stmt->bind_param("i", $seller_id);
$latest_stmt->execute();
$latest_orders = $latest_stmt->get_result();

/* ==============================
   SALES LAST 7 DAYS
============================== */
$sales_stmt = $conn->prepare("
    SELECT DATE(o.created_at) AS sale_date,
           SUM(oi.line_total) AS total
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.seller_id=? AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(o.created_at)
");
$sales_stmt->bind_param("i", $seller_id);
$sales_stmt->execute();
$sales_result = $sales_stmt->get_result();

$dates = [];
$totals = [];
while($row = $sales_result->fetch_assoc()){
    $dates[] = $row['sale_date'];
    $totals[] = $row['total'];
}

/* ==============================
   COMMISSION CALCULATION
============================== */
$commission_percent = 2;
$gross = $total_revenue;
$admin_cut = ($gross * $commission_percent)/100;
$net_earnings = $gross - $admin_cut;

include __DIR__ . '/../partials/header.php';
?>

<div class="container" style="padding:30px 0 70px;">

  <h2 style="margin-bottom:15px;">Seller Dashboard</h2>

  <div class="grid" style="grid-template-columns: repeat(4, 1fr); gap:20px; margin-bottom:30px;">
    <div class="card">
      <div class="muted">Total Products</div>
      <h2><?php echo $product_count; ?></h2>
    </div>
    <div class="card">
      <div class="muted">Total Orders</div>
      <h2><?php echo $total_orders; ?></h2>
    </div>
    <div class="card">
      <div class="muted">Orders Today</div>
      <h2><?php echo $today_orders; ?></h2>
    </div>
    <div class="card">
      <div class="muted">Last 7 Days</div>
      <h2><?php echo $week_orders; ?></h2>
    </div>

    <div class="card">
      <div class="muted">Gross Revenue</div>
      <h2>৳ <?php echo number_format($gross,2); ?></h2>
    </div>
    <div class="card">
      <div class="muted">Admin Commission (<?php echo $commission_percent; ?>%)</div>
      <h2>৳ <?php echo number_format($admin_cut,2); ?></h2>
    </div>
    <div class="card">
      <div class="muted">Net Earnings</div>
      <h2>৳ <?php echo number_format($net_earnings,2); ?></h2>
    </div>
  </div>

  <div class="card" style="margin-bottom:30px;">
    <h3>Latest Orders</h3>
    <?php if($latest_orders->num_rows == 0): ?>
      <p class="muted">No orders yet.</p>
    <?php else: ?>
      <?php while($o = $latest_orders->fetch_assoc()): ?>
        <div style="display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
            <div>
                <b>#<?php echo $o["id"]; ?></b>
                <div class="muted" style="font-size:13px;">
                    <?php echo htmlspecialchars($o["full_name"]); ?> • <?php echo $o["created_at"]; ?> • <?php echo $o["status"]; ?>
                </div>
            </div>
            <div>৳ <?php echo number_format($o["total"],2); ?></div>
        </div>
      <?php endwhile; ?>
    <?php endif; ?>
  </div>

  <div class="card" style="margin-bottom:30px;">
    <h3>Sales Last 7 Days</h3>
    <canvas id="salesChart" width="400" height="150"></canvas>
  </div>

  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
    <div>
      <h2 style="margin:0;">My Products</h2>
      <p class="muted" style="margin:6px 0 0;">Manage your listings (edit / delete).</p>
    </div>
    <a class="btn" style="text-decoration:none;" href="/DreamDealer/seller/add_product.php">+ Add Product</a>
  </div>

  <div style="margin-top:16px;">
    <?php if ($product_count === 0): ?>
      <div class="card">No products yet. Click <b>Add Product</b> to create your first listing.</div>
    <?php else: ?>
      <div class="grid" style="grid-template-columns: repeat(3, 1fr); gap:20px;">
        <?php foreach($products as $p): ?>
          <?php
            $imgHtml = !empty($p["image"]) 
                ? '<img src="/DreamDealer/uploads/'.rawurlencode($p["image"]).'" style="width:100%;height:100%;object-fit:cover;display:block;">'
                : '<div style="width:100%;height:100%;background:linear-gradient(135deg, rgba(86,242,255,.18), rgba(180,108,255,.12));"></div>';
          ?>
          <div class="card">
            <div class="img" style="background:none;overflow:hidden;"><?php echo $imgHtml; ?></div>
            <div><b><?php echo htmlspecialchars($p["title"]); ?></b></div>
            <div class="muted"><?php echo htmlspecialchars($p["category"]); ?></div>
            <div class="price">৳ <?php echo number_format((float)$p["price"], 2); ?></div>
            <div style="display:flex; gap:10px; margin-top:10px;">
              <a class="btn" style="flex:1;text-decoration:none;text-align:center;" href="/DreamDealer/seller/edit_product.php?id=<?php echo (int)$p["id"]; ?>">Edit</a>
              <a class="btn" style="flex:1;text-decoration:none;text-align:center;border-color:rgba(255,80,80,0.35);" href="/DreamDealer/seller/delete_product.php?id=<?php echo (int)$p["id"]; ?>" onclick="return confirm('Delete this product?');">Delete</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('salesChart');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($dates); ?>,
        datasets: [{
            label: 'Sales Last 7 Days',
            data: <?php echo json_encode($totals); ?>,
            borderWidth: 2,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)'
        }]
    },
    options: {
        responsive: true,
        scales: { y: { beginAtZero: true } }
    }
});
</script>

<?php
$stmt->close();
$order_stmt->close();
$today_stmt->close();
$week_stmt->close();
$latest_stmt->close();
$sales_stmt->close();
include __DIR__ . '/../partials/footer.php';
?>