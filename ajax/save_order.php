<?php
// ajax/save_order.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/app.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['items'])) {
    echo json_encode(['success' => false, 'message' => 'Data pesanan tidak boleh kosong']);
    exit;
}

$pdo = Database::getConnection();

try {
    $pdo->beginTransaction();

    $customerName = trim($input['customer_name'] ?? 'Pelanggan Umum');
    $customerPhone = trim($input['customer_phone'] ?? '');
    $discount = (float)($input['discount'] ?? 0);
    $paymentMethod = $input['payment_method'] ?? 'tunai';
    $paymentStatus = $input['payment_status'] ?? 'lunas';
    $paidAmount = (float)($input['paid_amount'] ?? 0);
    $notes = trim($input['notes'] ?? '');
    $userId = $_SESSION['user_id'] ?? null;

    // Check or insert customer
    $customerId = null;
    if (!empty($customerPhone) && $customerName !== 'Pelanggan Umum') {
        $custStmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ? LIMIT 1");
        $custStmt->execute([$customerPhone]);
        $customerId = $custStmt->fetchColumn();

        if (!$customerId) {
            $insertCust = $pdo->prepare("INSERT INTO customers (name, phone) VALUES (?, ?)");
            $insertCust->execute([$customerName, $customerPhone]);
            $customerId = $pdo->lastInsertId();
        }
    }

    // Calculate total
    $subtotal = 0;
    foreach ($input['items'] as $item) {
        $qty = (float)($item['qty'] ?? 1);
        $price = (float)($item['price'] ?? 0);
        $subtotal += ($qty * $price);
    }

    $grandTotal = max(0, $subtotal - $discount);
    $changeAmount = max(0, $paidAmount - $grandTotal);

    $invoiceNumber = generateInvoiceNumber($pdo);
    $now = date('Y-m-d H:i:s');

    // Insert order
    $orderStmt = $pdo->prepare("INSERT INTO orders 
        (invoice_number, customer_id, customer_name, customer_phone, total_amount, discount, grand_total, paid_amount, change_amount, payment_status, payment_method, order_status, notes, user_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'selesai', ?, ?, ?)");
    
    $orderStmt->execute([
        $invoiceNumber,
        $customerId,
        $customerName,
        $customerPhone,
        $subtotal,
        $discount,
        $grandTotal,
        $paidAmount,
        $changeAmount,
        $paymentStatus,
        $paymentMethod,
        $notes,
        $userId,
        $now
    ]);

    $orderId = $pdo->lastInsertId();

    // Insert order items
    $itemStmt = $pdo->prepare("INSERT INTO order_items 
        (order_id, product_id, item_name, quantity, unit, unit_price, subtotal, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    $itemSummaryList = [];
    foreach ($input['items'] as $item) {
        $pId = !empty($item['productId']) ? (int)$item['productId'] : null;
        $iName = trim($item['name'] ?? 'Item');
        $qty = (float)($item['qty'] ?? 1);
        $unit = trim($item['unit'] ?? 'pcs');
        $unitPrice = (float)($item['price'] ?? 0);
        $itemSubtotal = $qty * $unitPrice;
        $itemNotes = trim($item['notes'] ?? '');

        $itemStmt->execute([
            $orderId,
            $pId,
            $iName,
            $qty,
            $unit,
            $unitPrice,
            $itemSubtotal,
            $itemNotes
        ]);

        $itemSummaryList[] = "- {$iName} ({$qty} {$unit}): " . formatRupiah($itemSubtotal);
    }

    $pdo->commit();

    // Build WA link if phone is provided
    $waLink = '';
    if (!empty($customerPhone)) {
        $shopName = getSetting('shop_name', 'Aquiris Print');
        $waMsg = "Halo kak *{$customerName}*,\n";
        $waMsg .= "Terima kasih telah bertransaksi di *{$shopName}*!\n\n";
        $waMsg .= "🧾 *NOTA TRANSAKSI*\n";
        $waMsg .= "No. Invoice: *{$invoiceNumber}*\n";
        $waMsg .= "Tanggal: " . date('d/m/Y H:i') . "\n";
        $waMsg .= "------------------------\n";
        $waMsg .= implode("\n", $itemSummaryList) . "\n";
        $waMsg .= "------------------------\n";
        if ($discount > 0) {
            $waMsg .= "Diskon: " . formatRupiah($discount) . "\n";
        }
        $waMsg .= "Total: *" . formatRupiah($grandTotal) . "*\n";
        $waMsg .= "Bayar: " . formatRupiah($paidAmount) . " (" . strtoupper($paymentMethod) . ")\n";
        if ($changeAmount > 0) {
            $waMsg .= "Kembali: " . formatRupiah($changeAmount) . "\n";
        }
        $waMsg .= "Status: *" . strtoupper($paymentStatus) . "*\n\n";
        $waMsg .= "Semoga puas dengan layanan kami! 🙏";

        $waLink = buildWaLink($customerPhone, $waMsg);
    }

    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'invoice_number' => $invoiceNumber,
        'grand_total' => $grandTotal,
        'payment_status' => $paymentStatus,
        'wa_link' => $waLink
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
