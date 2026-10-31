<?php
// print_nota.php
require_once __DIR__ . '/config/app.php';
requireAuth();

$pdo = Database::getConnection();
$orderId = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? 'thermal'; // 'thermal' or 'standard'

$stmt = $pdo->prepare("SELECT o.*, u.name as cashier_name FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    die("Nota tidak ditemukan.");
}

$stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();

$shopName = getSetting('shop_name', 'Aquiris Print');
$shopTagline = getSetting('shop_tagline', 'Percetakan, Fotocopy & Sewa Akrilik');
$shopAddress = getSetting('shop_address', 'Jl. Percetakan');
$shopPhone = getSetting('shop_phone', '081234567890');
$receiptFooter = getSetting('receipt_footer', "Terima kasih atas kunjungan Anda!\nBarang yang sudah diambil mohon diperiksa kembali.");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk <?= htmlspecialchars($order['invoice_number']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; }
            @page { margin: 0; }
        }
        .thermal-font {
            font-family: 'Space Mono', 'Courier New', monospace;
        }
    </style>
</head>
<body class="bg-stone-100 min-h-screen py-8 flex flex-col items-center">

    <!-- Action Toolbar (No Print) -->
    <div class="no-print max-w-md w-full mb-4 flex items-center justify-between gap-2 px-4">
        <a href="kasir.php" class="px-3.5 py-1.5 bg-stone-200 hover:bg-stone-300 text-stone-800 rounded-xl text-xs font-bold flex items-center gap-1.5">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Kasir
        </a>
        <div class="flex items-center gap-2">
            <a href="print_nota.php?id=<?= $orderId ?>&type=<?= $type === 'thermal' ? 'standard' : 'thermal' ?>" class="px-3 py-1.5 bg-white border border-stone-300 hover:bg-stone-50 text-stone-700 rounded-xl text-xs font-bold">
                <?= $type === 'thermal' ? 'Ubah Format Nota A4' : 'Ubah Format Thermal' ?>
            </a>
            <button onclick="window.print()" class="px-4 py-1.5 bg-stone-900 hover:bg-stone-800 text-amber-300 rounded-xl text-xs font-bold shadow flex items-center gap-1.5">
                <i class="fa-solid fa-print"></i> Cetak Sekarang
            </button>
        </div>
    </div>

    <?php if ($type === 'thermal'): ?>
        <!-- Authentic Thermal Cashier Receipt (58mm - 80mm) -->
        <div class="bg-white p-6 w-80 shadow-md border border-stone-200 thermal-font text-[11px] text-stone-900 leading-tight">
            <!-- Header -->
            <div class="text-center pb-3 border-b border-dashed border-stone-400">
                <h2 class="text-sm font-bold uppercase tracking-wider"><?= htmlspecialchars($shopName) ?></h2>
                <p class="text-[10px] text-stone-600 mt-0.5"><?= htmlspecialchars($shopTagline) ?></p>
                <p class="text-[10px] text-stone-600"><?= htmlspecialchars($shopAddress) ?></p>
                <p class="text-[10px] text-stone-600 font-bold">WA: <?= htmlspecialchars($shopPhone) ?></p>
            </div>

            <!-- Meta Info -->
            <div class="py-2.5 border-b border-dashed border-stone-400 space-y-1 text-[10px]">
                <div class="flex justify-between">
                    <span>No: <?= htmlspecialchars($order['invoice_number']) ?></span>
                    <span><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></span>
                </div>
                <div class="flex justify-between">
                    <span>Kasir: <?= htmlspecialchars($order['cashier_name'] ?? 'Admin') ?></span>
                    <span>Plg: <?= htmlspecialchars(substr($order['customer_name'], 0, 16)) ?></span>
                </div>
            </div>

            <!-- Items -->
            <div class="py-2.5 border-b border-dashed border-stone-400 space-y-2">
                <?php foreach ($items as $item): ?>
                    <div>
                        <div class="font-bold"><?= htmlspecialchars($item['item_name']) ?></div>
                        <div class="flex justify-between text-[10px] text-stone-700 pl-2">
                            <span><?= (float)$item['quantity'] ?> <?= htmlspecialchars($item['unit']) ?> x <?= number_format($item['unit_price'], 0, ',', '.') ?></span>
                            <span><?= number_format($item['subtotal'], 0, ',', '.') ?></span>
                        </div>
                        <?php if (!empty($item['notes'])): ?>
                            <div class="text-[9px] text-stone-500 italic pl-2">- <?= htmlspecialchars($item['notes']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Total Calculation -->
            <div class="py-2.5 border-b border-dashed border-stone-400 space-y-1 text-[11px]">
                <div class="flex justify-between">
                    <span>Subtotal:</span>
                    <span>Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span>
                </div>
                <?php if ($order['discount'] > 0): ?>
                    <div class="flex justify-between text-rose-600 font-bold">
                        <span>Diskon:</span>
                        <span>- Rp <?= number_format($order['discount'], 0, ',', '.') ?></span>
                    </div>
                <?php endif; ?>
                <div class="flex justify-between font-bold text-xs pt-1 border-t border-stone-300">
                    <span>TOTAL:</span>
                    <span>Rp <?= number_format($order['grand_total'], 0, ',', '.') ?></span>
                </div>
                <div class="flex justify-between text-[10px]">
                    <span>Bayar (<?= strtoupper($order['payment_method']) ?>):</span>
                    <span>Rp <?= number_format($order['paid_amount'], 0, ',', '.') ?></span>
                </div>
                <?php if ($order['change_amount'] > 0): ?>
                    <div class="flex justify-between text-[10px]">
                        <span>Kembali:</span>
                        <span>Rp <?= number_format($order['change_amount'], 0, ',', '.') ?></span>
                    </div>
                <?php endif; ?>
                <div class="flex justify-between text-[10px] font-bold mt-1">
                    <span>Status:</span>
                    <span class="uppercase"><?= htmlspecialchars($order['payment_status']) ?></span>
                </div>
            </div>

            <!-- Footer Message -->
            <div class="text-center pt-3 text-[10px] text-stone-600 space-y-1">
                <p><?= nl2br(htmlspecialchars($receiptFooter)) ?></p>
            </div>
        </div>

    <?php else: ?>
        <!-- Standard Invoice A4/A5 Format with Warm Studio Touch -->
        <div class="bg-white p-8 max-w-2xl w-full shadow-md rounded-3xl border border-stone-200 text-xs">
            <!-- Header & Logo -->
            <div class="flex justify-between items-start pb-6 border-b-2 border-stone-900">
                <div class="flex items-center gap-4">
                    <img src="assets/images/logo-light.jpg" alt="Aquiris Print" class="h-12 w-auto object-contain" />
                    <div class="border-l-2 border-stone-200 pl-3.5">
                        <h2 class="text-base font-black text-stone-900"><?= htmlspecialchars($shopName) ?></h2>
                        <p class="text-xs text-stone-500 font-medium"><?= htmlspecialchars($shopTagline) ?></p>
                        <p class="text-[11px] text-stone-400 mt-0.5"><?= htmlspecialchars($shopAddress) ?> • WA: <?= htmlspecialchars($shopPhone) ?></p>
                    </div>
                </div>
                <div class="text-right">
                    <span class="inline-block px-3 py-1 rounded-full text-xs font-bold uppercase <?= $order['payment_status'] === 'lunas' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                        <?= strtoupper($order['payment_status']) ?>
                    </span>
                    <h3 class="text-sm font-black text-stone-900 mt-2 font-mono"><?= htmlspecialchars($order['invoice_number']) ?></h3>
                    <p class="text-[11px] text-stone-500"><?= date('d F Y, H:i', strtotime($order['created_at'])) ?> WIB</p>
                </div>
            </div>

            <!-- Customer Info -->
            <div class="grid grid-cols-2 gap-4 py-4 border-b border-stone-100">
                <div>
                    <span class="text-[10px] uppercase font-bold text-stone-400 tracking-wider">Kepada Pelanggan:</span>
                    <h4 class="font-extrabold text-stone-800 text-sm mt-0.5"><?= htmlspecialchars($order['customer_name']) ?></h4>
                    <p class="text-stone-500 text-xs"><?= htmlspecialchars($order['customer_phone'] ?: '-') ?></p>
                </div>
                <div class="text-right">
                    <span class="text-[10px] uppercase font-bold text-stone-400 tracking-wider">Metode Pembayaran:</span>
                    <p class="font-bold text-stone-700 text-sm mt-0.5 uppercase"><?= htmlspecialchars($order['payment_method']) ?></p>
                    <p class="text-stone-500 text-xs">Petugas Kasir: <?= htmlspecialchars($order['cashier_name'] ?? 'Admin') ?></p>
                </div>
            </div>

            <!-- Items Table -->
            <table class="w-full my-6 text-left border-collapse">
                <thead>
                    <tr class="border-b-2 border-stone-300 text-stone-600 uppercase text-[10px] font-bold">
                        <th class="py-2.5">No</th>
                        <th class="py-2.5">Deskripsi Layanan / Barang Cetak</th>
                        <th class="py-2.5 text-center">Jumlah</th>
                        <th class="py-2.5 text-right">Harga Satuan</th>
                        <th class="py-2.5 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <?php $no = 1; foreach ($items as $item): ?>
                        <tr>
                            <td class="py-2.5 text-stone-400"><?= $no++ ?></td>
                            <td class="py-2.5">
                                <span class="font-bold text-stone-800 block"><?= htmlspecialchars($item['item_name']) ?></span>
                                <?php if (!empty($item['notes'])): ?>
                                    <span class="text-[11px] text-stone-400 italic"><?= htmlspecialchars($item['notes']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="py-2.5 text-center font-bold text-stone-700"><?= (float)$item['quantity'] ?> <?= htmlspecialchars($item['unit']) ?></td>
                            <td class="py-2.5 text-right"><?= formatRupiah($item['unit_price']) ?></td>
                            <td class="py-2.5 text-right font-black text-stone-900"><?= formatRupiah($item['subtotal']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Calculation Box -->
            <div class="flex justify-end pt-2">
                <div class="w-64 space-y-1.5 text-xs">
                    <div class="flex justify-between text-stone-600">
                        <span>Subtotal:</span>
                        <span class="font-bold text-stone-800"><?= formatRupiah($order['total_amount']) ?></span>
                    </div>
                    <?php if ($order['discount'] > 0): ?>
                        <div class="flex justify-between text-rose-600 font-bold">
                            <span>Potongan Diskon:</span>
                            <span>- <?= formatRupiah($order['discount']) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="flex justify-between text-sm font-black text-stone-900 pt-2 border-t-2 border-stone-800">
                        <span>Total Tagihan:</span>
                        <span><?= formatRupiah($order['grand_total']) ?></span>
                    </div>
                    <div class="flex justify-between text-stone-600 pt-1">
                        <span>Bayar Diterima:</span>
                        <span><?= formatRupiah($order['paid_amount']) ?></span>
                    </div>
                    <?php if ($order['change_amount'] > 0): ?>
                        <div class="flex justify-between text-emerald-700 font-bold">
                            <span>Kembalian:</span>
                            <span><?= formatRupiah($order['change_amount']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Signatures -->
            <div class="grid grid-cols-2 gap-8 pt-12 mt-8 border-t border-stone-200 text-center text-xs">
                <div>
                    <p class="text-stone-500 mb-14">Tanda Terima Pelanggan,</p>
                    <p class="font-bold text-stone-800 border-t border-stone-300 inline-block px-8 pt-1"><?= htmlspecialchars($order['customer_name']) ?></p>
                </div>
                <div>
                    <p class="text-stone-500 mb-14">Hormat Kami,</p>
                    <p class="font-bold text-stone-800 border-t border-stone-300 inline-block px-8 pt-1"><?= htmlspecialchars($shopName) ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

</body>
</html>
