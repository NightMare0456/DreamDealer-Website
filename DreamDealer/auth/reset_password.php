<?php
require __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$token = trim($_GET["token"] ?? "");
if ($token === "") {
  $_SESSION["flash_success"] = "Invalid reset link.";
  header("Location: /DreamDealer/auth/login.php");
  exit;
}

include __DIR__ . '/../partials/header.php';
?>

<div class="container" style="padding:40px 0 70px;">
  <div class="card" style="max-width:520px;margin:0 auto;">
    <h2 style="margin:0 0 6px;">Reset Password</h2>
    <p class="muted" style="margin:0 0 14px;">Enter your new password.</p>

    <form method="POST" action="/DreamDealer/auth/reset_password_save.php">
      <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

      <input name="password" type="password" required minlength="6" placeholder="New password"
             style="width:100%;padding:12px;border-radius:14px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.04);color:#fff;">
      <input name="password2" type="password" required minlength="6" placeholder="Confirm new password"
             style="width:100%;margin-top:10px;padding:12px;border-radius:14px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.04);color:#fff;">

      <button class="btn" type="submit" style="margin-top:12px;width:100%;">Update Password</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
