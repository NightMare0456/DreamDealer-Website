<?php
require __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Admin only
if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    header("Location: /DreamDealer/index.php");
    exit;
}

// --- Stats queries ---
$stats = [
    "users" => 0,
    "products" => 0,
    "orders" => 0,
    "revenue" => 0.0
];

// total users
$r = $conn->query("SELECT COUNT(*) AS c FROM users");
if ($r) $stats["users"] = (int)$r->fetch_assoc()["c"];

// total products
$r = $conn->query("SELECT COUNT(*) AS c FROM products");
if ($r) $stats["products"] = (int)$r->fetch_assoc()["c"];

// total orders
$r = $conn->query("SELECT COUNT(*) AS c FROM orders");
if ($r) $stats["orders"] = (int)$r->fetch_assoc()["c"];

// revenue (only confirmed)
$r = $conn->query("SELECT COALESCE(SUM(total),0) AS s FROM orders WHERE status='confirmed'");
if ($r) $stats["revenue"] = (float)$r->fetch_assoc()["s"];

// --- Latest 5 orders ---
$stmt = $conn->prepare("
    SELECT o.id, o.total, o.status, o.created_at, u.full_name
    FROM orders o
    JOIN users u ON u.id = o.user_id
    ORDER BY o.id DESC
    LIMIT 5
");
$stmt->execute();
$latest = $stmt->get_result();

// Orders today
$qToday = $conn->query("
    SELECT COUNT(*) AS c 
    FROM orders 
    WHERE DATE(created_at) = CURDATE()
")->fetch_assoc();

// Orders last 7 days
$q7 = $conn->query("
    SELECT COUNT(*) AS c 
    FROM orders 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
")->fetch_assoc();

// Orders last 30 days
$q30 = $conn->query("
    SELECT COUNT(*) AS c 
    FROM orders 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetch_assoc();

// Top selling products
$topProducts = $conn->query("
    SELECT 
        oi.title,
        SUM(oi.qty) AS total_qty,
        SUM(oi.line_total) AS revenue
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE o.status = 'confirmed'
    GROUP BY oi.title
    ORDER BY revenue DESC
    LIMIT 5
");

// --- Sales per day (last 7 days) with 10% commission ---
$labels = [];
$data = [];

$query = $conn->query("
    SELECT DATE(created_at) AS day, COALESCE(SUM(total),0) AS total_sales
    FROM orders
    WHERE status='confirmed'
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY day
    ORDER BY day ASC
");

// Prepare last 7 days with default 0
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $labels[$date] = 0;
}

while ($row = $query->fetch_assoc()) {
    $labels[$row['day']] = $row['total_sales'] * 0.02; // 0.2% admin commission
}

$chartLabels = array_keys($labels);
$chartData = array_values($labels);

include __DIR__ . '/../partials/header.php';
?>

<div class="container" style="padding:30px 0 70px;">
    <h2 style="margin:0;">Admin Dashboard</h2>
    <p class="muted" style="margin-top:6px;">Manage DreamDealer system.</p>

    <!-- Quick stats -->
    <div class="grid" style="grid-template-columns:repeat(4,1fr); padding:18px 0 0;">
        <div class="card">
            <div class="muted">Orders Today</div>
            <h2><?= $qToday['c'] ?></h2>
        </div>
        <div class="card">
            <div class="muted">Last 7 Days</div>
            <h2><?= $q7['c'] ?></h2>
        </div>
        <div class="card">
            <div class="muted">Last 30 Days</div>
            <h2><?= $q30['c'] ?></h2>
        </div>
        <div class="card">
            <div class="muted">Total Users</div>
            <div style="font-size:26px;font-weight:900;margin-top:6px;"><?= $stats["users"] ?></div>
        </div>
        <div class="card">
            <div class="muted">Total Products</div>
            <div style="font-size:26px;font-weight:900;margin-top:6px;"><?= $stats["products"] ?></div>
        </div>
        <div class="card">
            <div class="muted">Total Orders</div>
            <div style="font-size:26px;font-weight:900;margin-top:6px;"><?= $stats["orders"] ?></div>
        </div>
        <div class="card">
            <div class="muted">Revenue (Confirmed)</div>
            <div style="font-size:26px;font-weight:900;margin-top:6px;">৳ <?= number_format($stats["revenue"],2) ?></div>
        </div>
        <div class="card">
            <div class="muted">Admin Commission (2%)</div>
            <div style="font-size:26px;font-weight:900;margin-top:6px;">৳ <?= number_format(array_sum($chartData),2) ?></div>
        </div>
    </div>
  <!-- Admin Actions -->
  <div class="card" style="margin-top:18px;">
    <h3 style="margin:0 0 10px;">Admin Actions</h3>

    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <a class="btn" href="/DreamDealer/admin/orders.php" style="text-decoration:none;">Manage Orders</a>
      <a class="btn" href="/DreamDealer/admin/products.php" style="text-decoration:none;">Manage Products</a>
      <a class="btn" href="/DreamDealer/admin/users.php" style="text-decoration:none;">Manage Users</a>
    </div>
  </div>
    <!-- Sales Chart -->
    <div class="card" style="margin-top:20px;">
        <h3 style="margin-bottom:12px;">Sales Last 7 Days (Admin 2%)</h3>
        <canvas id="salesChart"></canvas>
    </div>


    <!-- Latest Orders -->
    <div class="card" style="margin-top:18px;">
        <h3 style="margin:0 0 10px;">Latest Orders</h3>
        <?php if ($latest->num_rows === 0): ?>
            <div class="muted">No orders yet.</div>
        <?php else: ?>
            <?php while($o = $latest->fetch_assoc()): ?>
                <div style="display:flex;justify-content:space-between;gap:10px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.06);">
                    <div>
                        <b>#<?= (int)$o["id"] ?></b>
                        <span class="muted" style="margin-left:10px;"><?= htmlspecialchars($o["full_name"]) ?></span>
                        <div class="muted"><?= htmlspecialchars($o["created_at"]) ?> • <?= htmlspecialchars($o["status"]) ?></div>
                    </div>
                    <div class="price">৳ <?= number_format((float)$o["total"],2) ?></div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>

    <!-- Top Selling Products -->
    <div class="card" style="margin-top:20px;">
        <h3 style="margin-bottom:12px;">Top Selling Products</h3>
        <?php if ($topProducts->num_rows === 0): ?>
            <div class="muted">No sales yet.</div>
        <?php else: ?>
            <?php while($p = $topProducts->fetch_assoc()): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.06);">
                    <div>
                        <b><?= htmlspecialchars($p['title']) ?></b><br>
                        <span class="muted"><?= (int)$p['total_qty'] ?> sold</span>
                    </div>
                    <div class="price">৳ <?= number_format($p['revenue'],2) ?></div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const salesLabels = <?= json_encode($chartLabels) ?>;
const salesData   = <?= json_encode($chartData) ?>;

const ctx = document.getElementById('salesChart').getContext('2d');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: salesLabels,
        datasets: [{
            label: 'Admin Revenue (2%)',
            data: salesData,
            borderColor: '#00e5ff',
            backgroundColor: 'rgba(0,229,255,0.15)',
            borderWidth: 2,
            tension: 0.4,
            fill: true,
            pointRadius: 4,
            pointBackgroundColor: '#00e5ff'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { labels: { color: '#ccc' } },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return '৳ ' + parseFloat(context.raw).toFixed(2);
                    }
                }
            }
        },
        scales: {
            x: { ticks: { color: '#aaa' }, grid: { display: false } },
            y: {
                beginAtZero: true,
                ticks: { 
                    color: '#aaa',
                    callback: function(value) { return '৳ ' + value; }
                }
            }
        }
    }
});
</script>