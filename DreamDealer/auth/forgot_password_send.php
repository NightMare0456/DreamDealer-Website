<?php
require __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../lib/PHPMailer/src/Exception.php';
require __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../lib/PHPMailer/src/SMTP.php';

$email = trim($_POST["email"] ?? "");
if ($email === "") {
  $_SESSION["flash_success"] = "Please enter your email.";
  header("Location: /DreamDealer/auth/forgot_password.php");
  exit;
}

// Always show same message (don’t reveal if email exists)
$genericMsg = "If that email exists, a reset link has been sent.";

// Find user
$stmt = $conn->prepare("SELECT id, full_name, email FROM users WHERE email=? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
  $_SESSION["flash_success"] = $genericMsg;
  header("Location: /DreamDealer/auth/forgot_password.php");
  exit;
}

$user_id = (int)$user["id"];

// Create token (raw) + store hash
$token = bin2hex(random_bytes(32));           // 64 chars
$token_hash = hash("sha256", $token);
$expires_at = date("Y-m-d H:i:s", time() + 30 * 60); // 30 minutes

// Invalidate old unused tokens (mark used)
$stmtOld = $conn->prepare("UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL");
$stmtOld->bind_param("i", $user_id);
$stmtOld->execute();
$stmtOld->close();

// Insert new reset record
$stmt2 = $conn->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?,?,?)");
$stmt2->bind_param("iss", $user_id, $token_hash, $expires_at);
$stmt2->execute();
$stmt2->close();

// Build reset link
// NOTE: On localhost, receiver can't open localhost link unless they are on your PC.
// For hosting, set a fixed BASE_URL instead of using HTTP_HOST.
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host   = $_SERVER['HTTP_HOST'];
$link   = "{$scheme}://{$host}/DreamDealer/auth/reset_password.php?token={$token}";

// Send email
try {
  $mail = new PHPMailer(true);
  $mail->isSMTP();
  $mail->Host       = 'smtp.gmail.com';
  $mail->SMTPAuth   = true;

  // ✅ Use the EXACT gmail that created the app password
  $mail->Username   = 'asifzyan816@gmail.com';   // <-- check this carefully
  $mail->Password   = 'nqgpracylmkerpjc';     // <-- 16-char app password (no spaces)

  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port       = 587;

  // Optional: helps on some local setups
  $mail->SMTPOptions = [
    'ssl' => [
      'verify_peer'       => false,
      'verify_peer_name'  => false,
      'allow_self_signed' => true,
    ],
  ];

  $mail->setFrom($mail->Username, 'DreamDealer');
  $mail->addAddress($user["email"], $user["full_name"]);

  $mail->Subject = "DreamDealer Password Reset";
  $mail->Body =
    "Hello {$user["full_name"]},\n\n" .
    "We received a request to reset your password.\n\n" .
    "Reset link (valid for 30 minutes):\n{$link}\n\n" .
    "If you did not request this, ignore this email.\n\n" .
    "— DreamDealer";

  $mail->send();

  $_SESSION["flash_success"] = $genericMsg;
  header("Location: /DreamDealer/auth/forgot_password.php");
  exit;

} catch (Exception $e) {
  $_SESSION["flash_success"] = "❌ Mail error: " . $e->getMessage();
  header("Location: /DreamDealer/auth/forgot_password.php");
  exit;
}
