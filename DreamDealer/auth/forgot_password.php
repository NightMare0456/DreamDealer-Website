<?php
require __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/../partials/header.php';
?>

<div class="container" style="padding:40px 0 70px;">
  <div class="card" style="max-width:520px;margin:0 auto;">
    <h2 style="margin:0 0 6px;">Forgot Password</h2>
    <p class="muted" style="margin:0 0 14px;">Enter your email. We’ll send a reset link.</p>

    <form method="POST" action="/DreamDealer/auth/forgot_password_send.php">
      <input name="email" type="email" required placeholder="Your email"
             style="width:100%;padding:12px;border-radius:14px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.04);color:#fff;">
      <button class="btn" type="submit" style="margin-top:12px;width:100%;">Send Reset Link</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
