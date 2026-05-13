<?php
require __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Use separate flash keys (optional but recommended)
function flash_error($msg) {
  $_SESSION["flash_error"] = $msg;
}
function flash_success($msg) {
  $_SESSION["flash_success"] = $msg;
}

// Token can come from POST (form submit) or GET (direct open)
$token = trim($_POST["token"] ?? ($_GET["token"] ?? ""));
$pass1 = $_POST["password"] ?? "";
$pass2 = $_POST["password2"] ?? "";

// Fix common issue: plus-sign in URL becomes space in some cases
$token = str_replace(' ', '+', $token);

// Basic validation
if ($token === "" || $pass1 === "" || $pass2 === "") {
  flash_error("Please fill all fields.");
  header("Location: /DreamDealer/auth/reset_password.php?token=" . urlencode($token));
  exit;
}
if ($pass1 !== $pass2) {
  flash_error("Passwords do not match.");
  header("Location: /DreamDealer/auth/reset_password.php?token=" . urlencode($token));
  exit;
}
if (strlen($pass1) < 6) {
  flash_error("Password must be at least 6 characters.");
  header("Location: /DreamDealer/auth/reset_password.php?token=" . urlencode($token));
  exit;
}

$token_hash = hash("sha256", $token);

/**
 * DEBUG (TEMPORARY):
 * If reset keeps failing, uncomment this block once, submit again,
 * then check your browser/network or add an echo to see values.
 */
// flash_error("DEBUG token=$token | hash=$token_hash"); header("Location: /DreamDealer/auth/login.php"); exit;

// Find valid reset request
$stmt = $conn->prepare("
  SELECT id, user_id, expires_at, used_at
  FROM password_resets
  WHERE token_hash = ?
    AND used_at IS NULL
    AND expires_at > NOW()
  LIMIT 1
");
$stmt->bind_param("s", $token_hash);
$stmt->execute();
$reset = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$reset) {
  // Helpful diagnostics: check if token exists but expired/used
  $stmtX = $conn->prepare("
    SELECT id, user_id, expires_at, used_at
    FROM password_resets
    WHERE token_hash = ?
    LIMIT 1
  ");
  $stmtX->bind_param("s", $token_hash);
  $stmtX->execute();
  $any = $stmtX->get_result()->fetch_assoc();
  $stmtX->close();

  if ($any) {
    // Token found but not valid
    if (!empty($any["used_at"])) {
      flash_error("This reset link was already used. Please request a new one.");
    } else {
      flash_error("Reset link expired. Please request a new one.");
    }
  } else {
    flash_error("Reset link invalid. Please request a new one.");
  }

  header("Location: /DreamDealer/auth/login.php");
  exit;
}

$user_id = (int)$reset["user_id"];
$newHash = password_hash($pass1, PASSWORD_DEFAULT);

$conn->begin_transaction();
try {
  // Update user password
  $stmt2 = $conn->prepare("UPDATE users SET password=? WHERE id=?");
  $stmt2->bind_param("si", $newHash, $user_id);
  $stmt2->execute();
  $stmt2->close();

  // Mark token used
  $stmt3 = $conn->prepare("UPDATE password_resets SET used_at=NOW() WHERE id=?");
  $rid = (int)$reset["id"];
  $stmt3->bind_param("i", $rid);
  $stmt3->execute();
  $stmt3->close();

  $conn->commit();
} catch (Exception $e) {
  $conn->rollback();
  flash_error("Failed to reset password. Try again.");
  header("Location: /DreamDealer/auth/login.php");
  exit;
}

flash_success("✅ Password updated. Please login.");
header("Location: /DreamDealer/auth/login.php");
exit;
