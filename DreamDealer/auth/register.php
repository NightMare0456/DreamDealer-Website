<?php
require __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$errors = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $full_name = trim($_POST["full_name"] ?? "");
  $email     = trim($_POST["email"] ?? "");
  $password  = $_POST["password"] ?? "";
  $role      = $_POST["role"] ?? "buyer";

  if ($full_name === "") $errors[] = "Full name is required.";
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
  if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
  if (!in_array($role, ["buyer","seller"])) $errors[] = "Invalid role selected.";

  if (empty($errors)) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
      $errors[] = "Email already registered. Try login.";
    } else {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt2 = $conn->prepare("INSERT INTO users (full_name,email,password_hash,role) VALUES (?,?,?,?)");
      $stmt2->bind_param("ssss", $full_name, $email, $hash, $role);
      if ($stmt2->execute()) {
        $success = "✅ Registration successful! Now you can login.";
      } else {
        $errors[] = "Something went wrong. Try again.";
      }
      $stmt2->close();
    }
    $stmt->close();
  }
}
?>

<?php include __DIR__ . '/../partials/header.php'; ?>

<div class="container" style="padding: 30px 0 70px;">
  <div class="card" style="max-width:520px;margin:0 auto;">
    <h2 style="margin-top:0;">Create account</h2>
    <p class="muted">Join DreamDealer and start buying/selling.</p>

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

    <form method="POST" style="margin-top:14px;">

      <!-- Full Name -->
      <div style="margin-bottom:10px;">
        <div class="muted" style="margin-bottom:6px;">Full Name</div>
        <input name="full_name" required
               value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
               style="width:100%;padding:12px;border-radius:14px;border:1px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.04);color:rgba(255,255,255,0.92);outline:0;">
      </div>

      <!-- Email -->
      <div style="margin-bottom:10px;">
        <div class="muted" style="margin-bottom:6px;">Email</div>
        <input name="email" type="email" required
               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
               style="width:100%;padding:12px;border-radius:14px;border:1px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.04);color:rgba(255,255,255,0.92);outline:0;">
      </div>

      <!-- Password with eye toggle -->
      <div style="margin-bottom:10px;">
        <div class="muted" style="margin-bottom:6px;">Password</div>
        <div style="position:relative;">
          <input id="passwordInput" name="password" type="password" required
                 style="width:100%;padding:12px 46px 12px 12px;border-radius:14px;
                        border:1px solid rgba(255,255,255,0.12);
                        background:rgba(255,255,255,0.04);
                        color:rgba(255,255,255,0.92);outline:0;box-sizing:border-box;">

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
            <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
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

      <!-- Role -->
      <div style="margin-bottom:14px;">
        <div class="muted" style="margin-bottom:6px;">I want to</div>
        <select name="role"
                style="width:100%;padding:12px;border-radius:14px;border:1px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.04);color:rgba(37, 123, 140, 0.92);outline:0;">
          <option value="buyer"  <?php echo (($_POST['role'] ?? '')==='buyer') ?'selected':''; ?>>Buy</option>
          <option value="seller" <?php echo (($_POST['role'] ?? '')==='seller')?'selected':''; ?>>Sell</option>
        </select>
      </div>

      <button class="btn" style="width:100%;">Create Account</button>
    </form>

    <p class="muted" style="margin-top:14px;">
      Already have an account?
      <a href="login.php" style="color:rgba(86,242,255,0.95);">Login</a>
    </p>
  </div>
</div>

<script>
function togglePassword() {
  const input     = document.getElementById('passwordInput');
  const eyeOpen   = document.getElementById('eyeOpen');
  const eyeClosed = document.getElementById('eyeClosed');

  if (input.type === 'password') {
    input.type              = 'text';
    eyeOpen.style.display   = 'none';
    eyeClosed.style.display = 'block';
  } else {
    input.type              = 'password';
    eyeOpen.style.display   = 'block';
    eyeClosed.style.display = 'none';
  }
}
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>