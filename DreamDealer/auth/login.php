<?php
require __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $email    = trim($_POST["email"] ?? "");
  $password = $_POST["password"] ?? "";

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
  if ($password === "") $errors[] = "Password is required.";

  if (empty($errors)) {
    $stmt = $conn->prepare("SELECT id, full_name, email, password_hash, role FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
      $errors[] = "Account not found. Please register first.";
    } else {
      if (password_verify($password, $user["password_hash"])) {
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["name"]    = $user["full_name"];
        $_SESSION["email"]   = $user["email"];
        $_SESSION["role"]    = $user["role"];
        header("Location: ../index.php");
        exit;
      } else {
        $errors[] = "Wrong password. Try again.";
      }
    }
  }
}
?>

<?php include __DIR__ . '/../partials/header.php'; ?>

<div class="container" style="padding: 30px 0 70px;">
  <div class="card" style="max-width:520px;margin:0 auto;">
    <h2 style="margin-top:0;">Login</h2>
    <p class="muted">Welcome back to DreamDealer.</p>

    <?php if (!empty($errors)): ?>
      <div class="card" style="border-color: rgba(255,80,80,0.35);">
        <b>Fix these:</b>
        <ul class="muted">
          <?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" style="margin-top:14px;">

      <!-- Email -->
      <div style="margin-bottom:10px;">
        <div class="muted" style="margin-bottom:6px;">Email</div>
        <input name="email" type="email" required
               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
               style="width:100%;padding:12px;border-radius:14px;border:1px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.04);color:rgba(255,255,255,0.92);outline:0;">
      </div>

      <!-- Password with eye toggle -->
      <div style="margin-bottom:14px;">
        <div class="muted" style="margin-bottom:6px;">Password</div>
        <div style="position:relative;">
          <input id="passwordInput" name="password" type="password" required
                 style="width:100%;padding:12px 46px 12px 12px;border-radius:14px;
                        border:1px solid rgba(255,255,255,0.12);
                        background:rgba(255,255,255,0.04);
                        color:rgba(255,255,255,0.92);outline:0;box-sizing:border-box;">

          <!-- Eye button -->
          <button type="button" id="eyeBtn"
                  onclick="togglePassword()"
                  style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                         background:none;border:none;cursor:pointer;
                         color:rgba(255,255,255,0.5);padding:4px;
                         display:flex;align-items:center;justify-content:center;
                         transition:color .2s;"
                  onmouseover="this.style.color='rgba(86,242,255,0.9)'"
                  onmouseout="this.style.color='rgba(255,255,255,0.5)'"
                  title="Show/hide password">
            <!-- Eye open icon (default) -->
            <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
            <!-- Eye closed icon (hidden by default) -->
            <svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 style="display:none;">
              <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8
                       a18.45 18.45 0 0 1 5.06-5.94"/>
              <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8
                       a18.5 18.5 0 0 1-2.16 3.19"/>
              <line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
          </button>
        </div>
      </div>

      <button class="btn" style="width:100%;">Login</button>
    </form>

    <p class="muted" style="margin-top:14px;">
      Don't have an account?
      <a href="register.php" style="color:rgba(86,242,255,0.95);">Register</a>
    </p>
    <p class="muted" style="margin-top:10px;">
      <a href="/DreamDealer/auth/forgot_password.php">Forgot password?</a>
    </p>
  </div>
</div>

<script>
function togglePassword() {
  const input     = document.getElementById('passwordInput');
  const eyeOpen   = document.getElementById('eyeOpen');
  const eyeClosed = document.getElementById('eyeClosed');

  if (input.type === 'password') {
    input.type      = 'text';
    eyeOpen.style.display   = 'none';
    eyeClosed.style.display = 'block';
  } else {
    input.type      = 'password';
    eyeOpen.style.display   = 'block';
    eyeClosed.style.display = 'none';
  }
}
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>