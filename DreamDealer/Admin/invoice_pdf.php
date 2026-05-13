<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../lib/fpdf/fpdf.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$order_id = (int)($_GET["order_id"] ?? 0);
if ($order_id <= 0) die("Invalid order");

// Fetch order + user
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

if (!$order) die("Order not found");

// Fetch items
$stmt2 = $conn->prepare("
  SELECT title, price, qty, line_total, image
  FROM order_items
  WHERE order_id=?
");
$stmt2->bind_param("i", $order_id);
$stmt2->execute();
$items = $stmt2->get_result();

// ---------- PDF ----------
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);

/* ===== HEADER ===== */
if (file_exists(__DIR__ . '/../assets/img/logo.png')) {
  $pdf->Image(__DIR__ . '/../assets/img/logo.png', 10, 10, 28);
}
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'DreamDealer Invoice',0,1,'R');

$pdf->SetFont('Arial','',11);
$pdf->Cell(0,6,'Buy • Sell • Repeat',0,1,'R');
$pdf->Ln(8);

/* ===== INVOICE INFO ===== */
$pdf->SetFont('Arial','',11);
$pdf->Cell(100,6,"Invoice No: DD-{$order['id']}",0,0);
$pdf->Cell(0,6,"Date: {$order['created_at']}",0,1,'R');
$pdf->Cell(100,6,"Order ID: #{$order['id']}",0,0);
$pdf->Cell(0,6,"Status: ".strtoupper($order['status']),0,1,'R');

$pdf->Ln(6);

/* ===== BILL TO ===== */
$pdf->SetFont('Arial','B',11);
$pdf->Cell(0,6,'Bill To:',0,1);

$pdf->SetFont('Arial','',11);
$pdf->Cell(0,6,$order['full_name'],0,1);
$pdf->Cell(0,6,'Email: '.$order['email'],0,1);
$pdf->Cell(0,6,'Phone: '.$order['phone'],0,1);
$pdf->MultiCell(0,6,'Address: '.$order['address']);

$pdf->Ln(6);

/* ===== ITEMS TABLE HEADER ===== */
$pdf->SetFont('Arial','B',11);
$pdf->Cell(15,8,'#',1);
$pdf->Cell(80,8,'Item',1);
$pdf->Cell(25,8,'Price (Tk)',1,0,'R');
$pdf->Cell(15,8,'Qty',1,0,'C');
$pdf->Cell(30,8,'Total (Tk)',1,1,'R');

/* ===== ITEMS ===== */
$pdf->SetFont('Arial','',11);
$sl = 1;
while ($it = $items->fetch_assoc()) {
  $pdf->Cell(15,8,$sl++,1);
  $pdf->Cell(80,8,substr($it['title'],0,45),1);
  $pdf->Cell(25,8,number_format($it['price'],2),1,0,'R');
  $pdf->Cell(15,8,$it['qty'],1,0,'C');
  $pdf->Cell(30,8,number_format($it['line_total'],2),1,1,'R');
}
$stmt2->close();

/* ===== GRAND TOTAL ===== */
$pdf->Ln(4);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(135,10,'Grand Total',0,0,'R');
$pdf->Cell(30,10,'Tk '.number_format($order['total'],2),0,1,'R');

/* ===== FOOTER ===== */
$pdf->Ln(10);
$pdf->SetFont('Arial','I',10);
$pdf->Cell(0,6,'Thank you for shopping with DreamDealer!',0,1,'C');
$pdf->Cell(0,6,'Your order has been confirmed by admin.',0,1,'C');

/* ===== OUTPUT ===== */
$pdf->Output('I', 'invoice_order_'.$order_id.'.pdf');
