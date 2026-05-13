<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../lib/tfpdf/tfpdf.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../lib/PHPMailer/src/Exception.php';
require __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../lib/PHPMailer/src/SMTP.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/* =========================
   Helpers
========================= */

function resolveImagePath($img) {
  if (!$img) return null;

  // absolute/relative disk path
  if (is_readable($img)) return $img;

  $candidates = [
    __DIR__ . '/../' . ltrim($img, '/'),
    __DIR__ . '/../uploads/' . basename($img),
    __DIR__ . '/../assets/img/' . basename($img),
    __DIR__ . '/../admin/uploads/' . basename($img),
    __DIR__ . '/../admin/uploads/p_' . basename($img),
  ];

  foreach ($candidates as $p) {
    if (is_readable($p)) return $p;
  }
  return null;
}

/**
 * FPDF/tFPDF supports JPG + PNG by default.
 * WEBP is NOT supported unless we convert it (needs GD).
 * This function returns a safe image path or null (no crash).
 */
function pdfImageSafe($path) {
  if (!$path || !is_readable($path)) return null;

  $info = @getimagesize($path);
  if (!$info) return null;

  $mime = $info['mime'] ?? '';

  // Works in FPDF
  if ($mime === 'image/jpeg' || $mime === 'image/png') return $path;

  // WEBP -> convert ONLY if GD webp exists
  if ($mime === 'image/webp') {
    if (!function_exists('imagecreatefromwebp')) {
      // GD not enabled => just skip image
      return null;
    }

    $im = @imagecreatefromwebp($path);
    if (!$im) return null;

    $tmp = sys_get_temp_dir() . '/ddpdf_' . md5($path) . '.jpg';

    // white background (avoid black transparency)
    $bg = imagecreatetruecolor(imagesx($im), imagesy($im));
    $white = imagecolorallocate($bg, 255, 255, 255);
    imagefill($bg, 0, 0, $white);
    imagecopy($bg, $im, 0, 0, 0, 0, imagesx($im), imagesy($im));

    imagejpeg($bg, $tmp, 92);
    imagedestroy($im);
    imagedestroy($bg);

    return $tmp;
  }

  return null;
}

function moneyTk($n) {
  return "Tk " . number_format((float)$n, 2);
}

/* =========================
   Validate order
========================= */
$order_id = (int)($_GET["order_id"] ?? 0);
if ($order_id <= 0) {
  $_SESSION["flash_success"] = "❌ Invalid order id.";
  header("Location: /DreamDealer/admin/orders.php");
  exit;
}

/* =========================
   Fetch order
========================= */
$stmt = $conn->prepare("
  SELECT o.id, o.total, o.status, o.created_at, o.address, o.phone,
         u.full_name, u.email
  FROM orders o
  JOIN users u ON u.id = o.user_id
  WHERE o.id=?
  LIMIT 1
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
  $_SESSION["flash_success"] = "❌ Order not found.";
  header("Location: /DreamDealer/admin/orders.php");
  exit;
}

/* =========================
   Fetch items + product image
========================= */
$stmt2 = $conn->prepare("
  SELECT oi.title, oi.price, oi.qty, oi.line_total,
         p.image AS product_image
  FROM order_items oi
  LEFT JOIN products p ON p.id = oi.product_id
  WHERE oi.order_id=?
  ORDER BY oi.id ASC
");
$stmt2->bind_param("i", $order_id);
$stmt2->execute();
$items = $stmt2->get_result();

/* =========================
   PDF init
========================= */
$pdf = new tFPDF();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 18);

/* ===== Font ===== */
$fontDir  = __DIR__ . '/../lib/tfpdf/font/';
$fontFile = $fontDir . 'DejaVuSans.ttf';
if (!file_exists($fontFile)) {
  $_SESSION["flash_success"] = "❌ Font not found: {$fontFile}";
  header("Location: /DreamDealer/admin/orders.php");
  exit;
}
if (!defined('FPDF_FONTPATH')) define('FPDF_FONTPATH', $fontDir);
$pdf->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
$pdf->SetFont('DejaVu', '', 12);

/* =========================
   Company info
========================= */
$companyName  = "DreamDealer";
$companyTag   = "Buy | Sell | Repeat";
$companyPhone = "+880 1XXXXXXXXX";
$companyEmail = "support@dreamdealer.local";
$companyAddr  = "Basundhara, Dhaka, Bangladesh";

/* =========================
   Header
========================= */
$pdf->SetFillColor(15, 20, 35);
$pdf->Rect(0, 0, 210, 34, 'F');

// ✅ Logo (PNG works, WEBP will be skipped if GD missing)
$logoRaw = resolveImagePath('assets/img/logo.png');
$logoOk  = pdfImageSafe($logoRaw);
if ($logoOk) $pdf->Image($logoOk, 10, 8, 18);

$pdf->SetTextColor(255,255,255);
$pdf->SetFont('DejaVu','', 16);
$pdf->SetXY(32, 7);
$pdf->Cell(0, 8, $companyName, 0, 1);

$pdf->SetFont('DejaVu','', 9.5);
$pdf->SetXY(32, 16);
$pdf->Cell(0, 6, $companyTag, 0, 1);

$pdf->SetFont('DejaVu','', 9);
$pdf->SetXY(32, 22);
$pdf->Cell(0, 5, "Phone: {$companyPhone} | Email: {$companyEmail}", 0, 1);

$pdf->SetFont('DejaVu','', 9);
$pdf->SetXY(32, 27);
$pdf->Cell(0, 5, $companyAddr, 0, 1);

$pdf->SetFont('DejaVu','', 18);
$pdf->SetXY(150, 11);
$pdf->Cell(50, 10, "INVOICE", 0, 1, 'R');

$pdf->SetTextColor(0,0,0);
$pdf->Ln(20);

/* =========================
   Invoice meta box
========================= */
$pdf->SetDrawColor(220,225,232);
$pdf->SetFillColor(245,247,250);
$pdf->Rect(10, 40, 190, 18, 'DF');

$pdf->SetFont('DejaVu','', 11);
$pdf->SetXY(14, 44);
$pdf->Cell(90, 6, "Invoice No: DD-" . $order['id'], 0, 0);

$pdf->SetXY(110, 44);
$pdf->Cell(86, 6, "Date: " . $order['created_at'], 0, 1, 'R');

$pdf->SetXY(14, 50);
$pdf->Cell(90, 6, "Order ID: #" . $order['id'], 0, 0);

$pdf->SetXY(110, 50);
$pdf->Cell(86, 6, "Status: " . strtoupper($order['status']), 0, 1, 'R');

$pdf->Ln(10);

/* =========================
   Bill To / Ship To
========================= */
$y = $pdf->GetY();

$pdf->SetFont('DejaVu','', 11);
$pdf->Cell(95, 6, "BILL TO", 0, 0);
$pdf->Cell(95, 6, "SHIP TO", 0, 1);

$pdf->SetFont('DejaVu','', 10.5);
$pdf->SetXY(10, $y + 6);
$pdf->MultiCell(95, 5.8,
  $order['full_name'] . "\n" .
  "Email: " . $order['email'] . "\n" .
  "Phone: " . $order['phone']
);

$pdf->SetXY(110, $y + 6);
$pdf->MultiCell(95, 5.8,
  "Address:\n" . $order['address']
);

$pdf->Ln(6);

/* =========================
   Items table
========================= */
$pdf->SetFillColor(235,240,245);
$pdf->SetDrawColor(200,205,210);
$pdf->SetFont('DejaVu','', 11);

$pdf->Cell(10, 9, "#", 1, 0, 'C', true);
$pdf->Cell(18, 9, "Photo", 1, 0, 'C', true);
$pdf->Cell(78, 9, "Item", 1, 0, 'L', true);
$pdf->Cell(26, 9, "Price", 1, 0, 'R', true);
$pdf->Cell(16, 9, "Qty", 1, 0, 'C', true);
$pdf->Cell(42, 9, "Line Total", 1, 1, 'R', true);

$pdf->SetFont('DejaVu','', 10.5);

$sl = 1;
$subtotal = 0;
$rowFill = false;

while ($it = $items->fetch_assoc()) {
  $rowFill = !$rowFill;
  $pdf->SetFillColor($rowFill ? 250 : 255, $rowFill ? 252 : 255, 255);

  $rowH = 14;
  $x = $pdf->GetX();
  $yy = $pdf->GetY();

  $pdf->Cell(10, $rowH, (string)$sl++, 1, 0, 'C', true);
  $pdf->Cell(18, $rowH, "", 1, 0, 'C', true);

  $imgRaw = resolveImagePath($it['product_image'] ?? null);
  $imgOk  = pdfImageSafe($imgRaw);
  if ($imgOk) {
    $pdf->Image($imgOk, $x + 10 + 4, $yy + 2, 10, 10);
  }

  $title = mb_substr($it['title'], 0, 45);
  $pdf->Cell(78, $rowH, $title, 1, 0, 'L', true);

  $pdf->Cell(26, $rowH, moneyTk($it['price']), 1, 0, 'R', true);
  $pdf->Cell(16, $rowH, (string)(int)$it['qty'], 1, 0, 'C', true);
  $pdf->Cell(42, $rowH, moneyTk($it['line_total']), 1, 1, 'R', true);

  $subtotal += (float)$it['line_total'];
}
$stmt2->close();

/* =========================
   Totals box
========================= */
$shipping = 0;
$discount = 0;
$grandTotal = (float)$order['total'];

$pdf->Ln(6);
$y0 = $pdf->GetY();

$pdf->SetDrawColor(220,225,232);
$pdf->SetFillColor(245,247,250);
$pdf->Rect(120, $y0, 80, 30, 'DF');

$pdf->SetXY(122, $y0 + 4);
$pdf->SetFont('DejaVu','', 10.5);
$pdf->Cell(35, 6, "Subtotal", 0, 0, 'L');
$pdf->Cell(40, 6, moneyTk($subtotal), 0, 1, 'R');

$pdf->SetX(122);
$pdf->Cell(35, 6, "Shipping", 0, 0, 'L');
$pdf->Cell(40, 6, moneyTk($shipping), 0, 1, 'R');

$pdf->SetX(122);
$pdf->Cell(35, 6, "Discount", 0, 0, 'L');
$pdf->Cell(40, 6, "-" . moneyTk($discount), 0, 1, 'R');

$pdf->Line(122, $pdf->GetY()+1, 198, $pdf->GetY()+1);

$pdf->Ln(2);
$pdf->SetX(122);
$pdf->SetFont('DejaVu','', 12);
$pdf->Cell(35, 7, "Grand Total", 0, 0, 'L');
$pdf->Cell(40, 7, moneyTk($grandTotal), 0, 1, 'R');

/* =========================
   Footer
========================= */
$pdf->Ln(16);
$pdf->SetFont('DejaVu','', 9.8);
$pdf->SetTextColor(90, 90, 90);
$pdf->Cell(0, 5, "Thank you for shopping with {$companyName}!", 0, 1, 'C');
$pdf->Cell(0, 5, "Support: {$companyEmail} | {$companyPhone}", 0, 1, 'C');
$pdf->Cell(0, 5, "This invoice is valid without signature.", 0, 1, 'C');

/* =========================
   PDF as string
========================= */
$pdfData = $pdf->Output('S');

/* =========================
   Email
========================= */
$mail = new PHPMailer(true);

try {
  $mail->isSMTP();
  $mail->Host = 'smtp.gmail.com';
  $mail->SMTPAuth = true;

  $mail->Username = 'asifzyan816@gmail.com';
  $mail->Password = 'nqgpracylmkerpjc'; // ✅ your APP PASSWORD

  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port = 587;

  $mail->setFrom($mail->Username, 'DreamDealer');
  $mail->addAddress($order['email'], $order['full_name']);

  $mail->Subject = 'Your DreamDealer Invoice';
  $mail->Body =
    "Hello {$order['full_name']},\n\n" .
    "Thanks for your order! Your invoice is attached.\n\n" .
    "Order ID: #{$order_id}\n" .
    "Total: " . moneyTk($grandTotal) . "\n\n" .
    "DreamDealer";

  $mail->addStringAttachment(
    $pdfData,
    'invoice_order_'.$order_id.'_'.date('Ymd_His').'.pdf',
    'base64',
    'application/pdf'
  );

  $mail->send();

  $_SESSION["flash_success"] = "✅ Invoice emailed successfully to: " . $order["email"];
  header("Location: /DreamDealer/admin/orders.php");
  exit;

} catch (Exception $e) {
  $_SESSION["flash_success"] = "❌ Mail failed: " . $mail->ErrorInfo;
  header("Location: /DreamDealer/admin/orders.php");
  exit;
}  