<?php
// transaksi_cetak.php
require_once __DIR__ . '/config/app.php';
requireAuth();

$pdo = Database::getConnection();

// Handle Status Update Action
if (isset($_POST['update_status'])) {
    $orderId = (int)$_POST['order_id'];
    $newStatus = $_POST['payment_status'];
    $paidAmount = (float)$_POST['paid_amount'];

    $stmt = $pdo->prepare("UPDATE orders SET payment_status = ?, paid_amount = ? WHERE id = ?");
    $stmt->execute([$newStatus, $paidAmount, $orderId]);
    setFlash('success', 'Status pembayaran berhasil diperbarui!');
    header('Location: transaksi_cetak.php');
    exit;
}

// Handle Delete Action
if (isset($_GET['delete'])) {
    if (!isAdmin()) {
        setFlash('error', 'Hanya administrator yang memiliki izin untuk menghapus transaksi.');
        header('Location: transaksi_cetak.php');
        exit;
    }
    $delId = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$delId]);
    $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$delId]);
    setFlash('success', 'Transaksi berhasil dihapus.');
    header('Location: transaksi_cetak.php');
    exit;
}

$pageTitle = 'Riwayat Transaksi Cetak';
$pageSubtitle = 'Kelola pesanan cetak dokumen, pelunasan tagihan & cetak ulang nota';
require_once __DIR__ . '/includes/header.php';

// Filter parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$status = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT o.*, u.name as cashier_name FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE 1=1";
$params = [];

if (!empty($startDate) && !empty($endDate)) {
    $sql .= " AND DATE(o.created_at) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
}

if ($status !== 'all') {
    $sql .= " AND o.payment_status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $sql .= " AND (o.invoice_number LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY o.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Summary counts
$totalOrders = count($orders);
$totalSum = array_sum(array_column($orders, 'grand_total'));
?>

<!-- Filter & Search Toolbar -->
<div class="bg-white p-5 rounded-3xl border border-stone-200/90 shadow-sm mb-6">
    <form method="GET" action="transaksi_cetak.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end text-xs">
        <div>
            <label class="block font-bold text-stone-600 mb-1">Mulai Tanggal</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="w-full px-3 py-2 bg-stone-50 border border-stone-200 rounded-xl outline-none font-bold" />
        </div>
        <div>
            <label class="block font-bold text-stone-600 mb-1">Sampai Tanggal</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="w-full px-3 py-2 bg-stone-50 border border-stone-200 rounded-xl outline-none font-bold" />
        </div>
        <div>
            <label class="block font-bold text-stone-600 mb-1">Status Bayar</label>
            <select name="status" class="w-full px-3 py-2 bg-stone-50 border border-stone-200 rounded-xl outline-none font-semibold">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Semua Status</option>
                <option value="lunas" <?= $status === 'lunas' ? 'selected' : '' ?>>Lunas</option>
                <option value="dp" <?= $status === 'dp' ? 'selected' : '' ?>>Uang Muka (DP)</option>
                <option value="belum_lunas" <?= $status === 'belum_lunas' ? 'selected' : '' ?>>Belum Lunas</option>
            </select>
        </div>
        <div>
            <label class="block font-bold text-stone-600 mb-1">Cari Invoice / Pelanggan</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="INV / Nama..." class="w-full px-3 py-2 bg-stone-50 border border-stone-200 rounded-xl outline-none" />
        </div>
        <div class="flex gap-2">
            <button type="submit" class="flex-1 py-2 px-3 bg-stone-900 hover:bg-stone-800 text-amber-300 font-bold rounded-xl shadow-sm transition flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-filter"></i> Filter
            </button>
            <a href="transaksi_cetak.php" class="py-2 px-3 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold rounded-xl transition" title="Reset Filter">
                <i class="fa-solid fa-rotate-right"></i>
            </a>
        </div>
    </form>
</div>

<!-- Summary Bar -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 text-xs font-semibold text-stone-600">
    <div>
        Menampilkan <strong><?= $totalOrders ?></strong> pesanan cetak • Total Omset: <strong class="text-stone-900 text-sm"><?= formatRupiah($totalSum) ?></strong>
    </div>
    <div>
        <a href="kasir.php" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-stone-900 hover:bg-stone-800 text-amber-300 rounded-xl text-xs font-bold transition">
            <i class="fa-solid fa-plus"></i> Transaksi Kasir Baru
        </a>
    </div>
</div>

<!-- Transactions Table -->
<div class="bg-white rounded-3xl border border-stone-200/90 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
            <thead>
                <tr class="bg-stone-50 text-stone-600 border-b border-stone-200">
                    <th class="py-3.5 px-4 font-bold">No. Invoice</th>
                    <th class="py-3.5 px-4 font-bold">Waktu Transaksi</th>
                    <th class="py-3.5 px-4 font-bold">Pelanggan</th>
                    <th class="py-3.5 px-4 font-bold">Metode Bayar</th>
                    <th class="py-3.5 px-4 font-bold text-right">Total Tagihan</th>
                    <th class="py-3.5 px-4 font-bold text-center">Status</th>
                    <th class="py-3.5 px-4 font-bold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="7" class="py-10 text-center text-stone-400">
                            <i class="fa-solid fa-folder-open text-3xl mb-2 text-stone-300"></i>
                            <p>Tidak ada riwayat transaksi pada filter ini.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $ord): ?>
                        <tr class="hover:bg-stone-50/80 transition">
                            <td class="py-3.5 px-4">
                                <span class="font-bold font-mono text-stone-900 block"><?= htmlspecialchars($ord['invoice_number']) ?></span>
                                <span class="text-[10px] text-stone-400">Kasir: <?= htmlspecialchars($ord['cashier_name'] ?? 'Admin') ?></span>
                            </td>
                            <td class="py-3.5 px-4 text-stone-600">
                                <?= formatDate($ord['created_at'], true) ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-stone-800"><?= htmlspecialchars($ord['customer_name']) ?></div>
                                <?php if (!empty($ord['customer_phone'])): ?>
                                    <span class="text-[10px] text-stone-400 flex items-center gap-1">
                                        <i class="fa-brands fa-whatsapp text-emerald-600"></i> <?= htmlspecialchars($ord['customer_phone']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 uppercase text-[10px] font-bold text-stone-500">
                                <?= htmlspecialchars($ord['payment_method']) ?>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <span class="font-black text-stone-900 block text-xs"><?= formatRupiah($ord['grand_total']) ?></span>
                                <?php if ($ord['discount'] > 0): ?>
                                    <span class="text-[10px] text-rose-500 font-medium">Disc: <?= formatRupiah($ord['discount']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <?php
                                $badgeColor = 'bg-emerald-100 text-emerald-800';
                                if ($ord['payment_status'] === 'dp') $badgeColor = 'bg-amber-100 text-amber-800';
                                if ($ord['payment_status'] === 'belum_lunas') $badgeColor = 'bg-rose-100 text-rose-800';
                                ?>
                                <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase <?= $badgeColor ?>">
                                    <?= htmlspecialchars($ord['payment_status']) ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right space-x-1 whitespace-nowrap">
                                <a href="print_nota.php?id=<?= $ord['id'] ?>&type=thermal" target="_blank" class="p-1.5 bg-stone-100 hover:bg-stone-200 text-stone-700 rounded-lg inline-block text-xs" title="Cetak Struk Thermal">
                                    <i class="fa-solid fa-receipt"></i>
                                </a>
                                <a href="print_nota.php?id=<?= $ord['id'] ?>&type=standard" target="_blank" class="p-1.5 bg-stone-100 hover:bg-stone-200 text-stone-700 rounded-lg inline-block text-xs" title="Cetak Nota A4/A5">
                                    <i class="fa-solid fa-file-invoice"></i>
                                </a>
                                <?php if ($ord['payment_status'] !== 'lunas'): ?>
                                    <button onclick="openPayModal(<?= $ord['id'] ?>, '<?= $ord['invoice_number'] ?>', <?= $ord['grand_total'] ?>, <?= $ord['paid_amount'] ?>)" class="p-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 rounded-lg inline-block text-xs font-bold" title="Lunasi Pembayaran">
                                        <i class="fa-solid fa-money-bill-wave"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if (isAdmin()): ?>
                                <button onclick="confirmDelete('transaksi_cetak.php?delete=<?= $ord['id'] ?>', 'Yakin ingin menghapus transaksi ini?')" class="p-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg inline-block text-xs" title="Hapus Transaksi">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Update Status Pelunasan -->
<div id="payModal" class="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-stone-200">
        <div class="flex items-center justify-between pb-3 border-b border-stone-100">
            <h3 class="font-extrabold text-stone-800 text-sm">Update Pelunasan Nota</h3>
            <button onclick="closePayModal()" class="text-stone-400 hover:text-stone-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="transaksi_cetak.php" class="space-y-3 py-4 text-xs">
            <input type="hidden" name="update_status" value="1" />
            <input type="hidden" name="order_id" id="modalOrderId" />
            
            <div>
                <span class="text-stone-500">Invoice:</span>
                <span id="modalInvoiceText" class="font-bold text-stone-900 ml-1 font-mono"></span>
            </div>
            <div>
                <span class="text-stone-500">Total Tagihan:</span>
                <span id="modalTotalText" class="font-black text-stone-900 ml-1"></span>
            </div>
            <div>
                <label class="block font-bold text-stone-700 mb-1">Status Baru</label>
                <select name="payment_status" id="modalPaymentStatus" class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none font-bold">
                    <option value="lunas">Lunas</option>
                    <option value="dp">Uang Muka (DP)</option>
                    <option value="belum_lunas">Belum Bayar</option>
                </select>
            </div>
            <div>
                <label class="block font-bold text-stone-700 mb-1">Jumlah Sudah Dibayar (Rp)</label>
                <input type="number" name="paid_amount" id="modalPaidAmount" class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none font-black text-stone-900" />
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-stone-100">
                <button type="button" onclick="closePayModal()" class="px-4 py-2 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold rounded-xl">Batal</button>
                <button type="submit" class="px-4 py-2 bg-stone-900 hover:bg-stone-800 text-amber-300 font-bold rounded-xl">Simpan Pelunasan</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openPayModal(orderId, inv, grandTotal, paidAmount) {
        document.getElementById('modalOrderId').value = orderId;
        document.getElementById('modalInvoiceText').innerText = inv;
        document.getElementById('modalTotalText').innerText = 'Rp ' + grandTotal.toLocaleString('id-ID');
        document.getElementById('modalPaidAmount').value = grandTotal;
        document.getElementById('modalPaymentStatus').value = 'lunas';
        document.getElementById('payModal').classList.remove('hidden');
    }
    function closePayModal() {
        document.getElementById('payModal').classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
