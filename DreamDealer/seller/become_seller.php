<?php
require __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION["user_id"])) {
  header("Location: /DreamDealer/auth/login.php");
  exit;
}

$user_id = (int)$_SESSION["user_id"];
$currentRole = $_SESSION["role"] ?? "user";

// If already seller or admin
if ($currentRole === "seller" || $currentRole === "admin") {
  $_SESSION["flash_success"] = "✅ You are already a seller.";
  header("Location: /DreamDealer/index.php");
  exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

  $stmt = $conn->prepare("UPDATE users SET role='seller' WHERE id=?");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $stmt->close();

  $_SESSION["role"] = "seller";

  $_SESSION["flash_success"] = "🎉 Congratulations! You are now a Seller.";
  header("Location: /DreamDealer/index.php");
  exit;
}

include __DIR__ . '/../partials/header.php';
?>

<div class="container" style="padding:40px 0 80px;">
  <div class="card" style="max-width:500px;margin:auto;text-align:center;">
    
    <h2>Become a Seller</h2>
    <p class="muted" style="margin-top:8px;">
      Start selling your products on DreamDealer marketplace.
    </p>

    <ul style="text-align:left;margin-top:20px;" class="muted">
      <li>✔ Add unlimited products</li>
      <li>✔ Manage your own listings</li>
      <li>✔ Track your sales</li>
      <li>✔ Reach thousands of buyers</li>
    </ul>

    <form method="POST" style="margin-top:25px;">
      <button class="btn" style="width:100%;">
        Become Seller Now
      </button>
    </form>

  </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
