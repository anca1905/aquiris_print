<?php
// arus_kas.php
require_once __DIR__ . '/config/app.php';
requireAdmin();

$pageTitle = 'Buku Kas & Arus Kas Mutasi';
$pageSubtitle = 'Rekapitulasi seluruh mutasi uang masuk (cetak & sewa) serta uang keluar (belanja toko)';
require_once __DIR__ . '/includes/header.php';

$pdo = Database::getConnection();

// Filter parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$typeFilter = $_GET['type'] ?? 'all';

$entries = [];

// 1. Pemasukan Cetak
if ($typeFilter === 'all' || $typeFilter === 'print') {
    $stmt = $pdo->prepare("SELECT 
        'print' as entry_type,
        'Pemasukan Cetak' as category_label,
        invoice_number as ref_code,
        created_at as tx_date,
        CONCAT('Cetak & Fotocopy - ', customer_name) as description,
        grand_total as amount,
        payment_method,
        payment_status as status,
        id as record_id
        FROM orders 
        WHERE order_status != 'dibatalkan' 
        AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$startDate, $endDate]);
    while ($row = $stmt->fetch()) {
        $entries[] = $row;
    }
}

// 2. Pemasukan Sewa Akrilik
if ($typeFilter === 'all' || $typeFilter === 'acrylic') {
    $stmt = $pdo->prepare("SELECT 
        'acrylic' as entry_type,
        'Pemasukan Sewa Akrilik' as category_label,
        r.rental_code as ref_code,
        r.created_at as tx_date,
        CONCAT('Sewa Akrilik: ', a.name, ' (', r.customer_name, IF(r.event_name != '', CONCAT(' - ', r.event_name), ''), ')') as description,
        (r.total_cost + r.late_fine) as amount,
        r.payment_method,
        r.status as status,
        r.id as record_id
        FROM acrylic_rentals r
        JOIN acrylic_items a ON r.acrylic_id = a.id
        WHERE r.status != 'cancelled'
        AND DATE(r.created_at) BETWEEN ? AND ?");
    $stmt->execute([$startDate, $endDate]);
    while ($row = $stmt->fetch()) {
        $entries[] = $row;
    }
}

// 3. Pengeluaran Toko
if ($typeFilter === 'all' || $typeFilter === 'expense') {
    $stmt = $pdo->prepare("SELECT 
        'expense' as entry_type,
        c.name as category_label,
        e.expense_code as ref_code,
        CONCAT(e.date, ' 12:00:00') as tx_date,
        CONCAT('Beban: ', e.title) as description,
        e.amount as amount,
        e.payment_method,
        'selesai' as status,
        e.id as record_id
        FROM expenses e
        JOIN expense_categories c ON e.category_id = c.id
        WHERE e.date BETWEEN ? AND ?");
    $stmt->execute([$startDate, $endDate]);
    while ($row = $stmt->fetch()) {
        $entries[] = $row;
    }
}

// 4. Pembagian Hasil (Prive Pemilik & Pekerja)
if ($typeFilter === 'all' || $typeFilter === 'distribution') {
    $stmt = $pdo->prepare("SELECT 
        'distribution' as entry_type,
        'Bagi Hasil / Prive' as category_label,
        distribution_code as ref_code,
        CONCAT(distribution_date, ' 17:00:00') as tx_date,
        CONCAT('Pencairan Bagi Hasil (Owner + Tim) Periode ', DATE_FORMAT(period_start, '%d/%m/%y'), ' s/d ', DATE_FORMAT(period_end, '%d/%m/%y')) as description,
        (owner_amount + worker_amount) as amount,
        payment_method,
        'selesai' as status,
        id as record_id
        FROM profit_distributions
        WHERE distribution_date BETWEEN ? AND ?");
    $stmt->execute([$startDate, $endDate]);
    while ($row = $stmt->fetch()) {
        $entries[] = $row;
    }
}

// Sort chronological
usort($entries, function($a, $b) {
    return strcmp($a['tx_date'], $b['tx_date']);
});

$totalPrintIncome = 0;
$totalAcrylicIncome = 0;
$totalExpenses = 0;
$totalDistribution = 0;

$runningBalance = 0;
foreach ($entries as &$entry) {
    if ($entry['entry_type'] === 'print') {
        $totalPrintIncome += (float)$entry['amount'];
        $runningBalance += (float)$entry['amount'];
    } elseif ($entry['entry_type'] === 'acrylic') {
        $totalAcrylicIncome += (float)$entry['amount'];
        $runningBalance += (float)$entry['amount'];
    } elseif ($entry['entry_type'] === 'expense') {
        $totalExpenses += (float)$entry['amount'];
        $runningBalance -= (float)$entry['amount'];
    } elseif ($entry['entry_type'] === 'distribution') {
        $totalDistribution += (float)$entry['amount'];
        $runningBalance -= (float)$entry['amount'];
    }
    $entry['balance_after'] = $runningBalance;
}
unset($entry);

$displayEntries = array_reverse($entries);

$totalIncome = $totalPrintIncome + $totalAcrylicIncome;
$totalOut = $totalExpenses + $totalDistribution;
$netBalance = $totalIncome - $totalOut;
?>

<!-- Filter Toolbar -->
<div class="bg-white p-5 rounded-3xl border border-stone-200/90 shadow-sm mb-6 no-print">
    <form method="GET" action="arus_kas.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end text-xs">
        <div>
            <label class="block font-bold text-stone-600 mb-1">Mulai Tanggal</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="w-full px-3 py-2 bg-stone-50 border border-stone-200 rounded-xl outline-none font-bold text-stone-800" />
        </div>
        <div>
            <label class="block font-bold text-stone-600 mb-1">Sampai Tanggal</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="w-full px-3 py-2 bg-stone-50 border border-stone-200 rounded-xl outline-none font-bold text-stone-800" />
        </div>
        <div>
            <label class="block font-bold text-stone-600 mb-1">Filter Jenis Mutasi</label>
            <select name="type" class="w-full px-3 py-2 bg-stone-50 border border-stone-200 rounded-xl outline-none font-semibold">
                <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>Semua (Masuk & Keluar)</option>
                <option value="print" <?= $typeFilter === 'print' ? 'selected' : '' ?>>Hanya Pemasukan Percetakan</option>
                <option value="acrylic" <?= $typeFilter === 'acrylic' ? 'selected' : '' ?>>Hanya Pemasukan Sewa Akrilik</option>
                <option value="expense" <?= $typeFilter === 'expense' ? 'selected' : '' ?>>Hanya Beban Belanja Toko</option>
                <option value="distribution" <?= $typeFilter === 'distribution' ? 'selected' : '' ?>>Hanya Bagi Hasil / Prive</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="flex-1 py-2 px-3 bg-stone-900 hover:bg-stone-800 text-amber-300 font-bold rounded-xl shadow-sm transition flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-filter"></i>
                <span>Terapkan</span>
            </button>
            <a href="arus_kas.php" class="py-2 px-3 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold rounded-xl transition" title="Reset">
                <i class="fa-solid fa-rotate-right"></i>
            </a>
        </div>
        <div class="flex gap-2">
            <button type="button" onclick="window.print()" class="flex-1 py-2 px-3 bg-stone-100 hover:bg-stone-200 text-stone-800 font-bold rounded-xl transition flex items-center justify-center gap-1.5 border border-stone-300">
                <i class="fa-solid fa-print"></i>
                <span>Cetak Buku Kas</span>
            </button>
        </div>
    </form>
</div>

<!-- Financial Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white p-4 rounded-2xl border border-stone-200 shadow-sm">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-bold text-stone-500 uppercase tracking-wider">Pemasukan Cetak</span>
            <div class="w-8 h-8 rounded-lg bg-stone-100 text-stone-700 flex items-center justify-center text-xs font-bold">
                <i class="fa-solid fa-print"></i>
            </div>
        </div>
        <div class="text-xl font-black text-stone-900"><?= formatRupiah($totalPrintIncome) ?></div>
        <span class="text-[10px] text-stone-500 mt-1 block">Fotocopy & order dokumen</span>
    </div>

    <div class="bg-white p-4 rounded-2xl border border-stone-200 shadow-sm">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-bold text-stone-500 uppercase tracking-wider">Pemasukan Sewa Akrilik</span>
            <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center text-xs font-bold">
                <i class="fa-solid fa-shapes"></i>
            </div>
        </div>
        <div class="text-xl font-black text-amber-800"><?= formatRupiah($totalAcrylicIncome) ?></div>
        <span class="text-[10px] text-stone-500 mt-1 block">Sewa welcome sign & stiker</span>
    </div>

    <div class="bg-white p-4 rounded-2xl border border-stone-200 shadow-sm">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-bold text-stone-500 uppercase tracking-wider">Total Kas Keluar</span>
            <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-700 flex items-center justify-center text-xs font-bold">
                <i class="fa-solid fa-arrow-up-right"></i>
            </div>
        </div>
        <div class="text-xl font-black text-rose-600"><?= formatRupiah($totalOut) ?></div>
        <span class="text-[10px] text-stone-500 mt-1 block">Beban: <?= formatRupiah($totalExpenses) ?><?= $totalDistribution > 0 ? ' &bull; Prive: ' . formatRupiah($totalDistribution) : '' ?></span>
    </div>

    <div class="bg-stone-900 text-white p-4 rounded-2xl shadow-sm">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-bold text-stone-400 uppercase tracking-wider">Sisa Saldo Mutasi</span>
            <div class="w-8 h-8 rounded-lg bg-stone-800 text-amber-300 flex items-center justify-center text-xs font-bold">
                <i class="fa-solid fa-coins"></i>
            </div>
        </div>
        <div class="text-xl font-black <?= $netBalance >= 0 ? 'text-amber-300' : 'text-rose-400' ?>">
            <?= formatRupiah($netBalance) ?>
        </div>
        <span class="text-[10px] text-stone-400 mt-1 block">Pemasukan dikurangi Kas Keluar</span>
    </div>
</div>

<!-- Ledger Table (Buku Kas Umum) -->
<div class="bg-white rounded-3xl border border-stone-200/90 shadow-sm overflow-hidden">
    <div class="p-4 sm:p-5 bg-stone-50 border-b border-stone-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <h3 class="font-black text-stone-900 text-sm">Buku Kas & Mutasi Keuangan</h3>
            <p class="text-[11px] text-stone-500">Seluruh uang masuk dan uang keluar yang tercatat berurutan</p>
        </div>
        <span class="text-xs font-bold text-stone-600">
            Total <strong><?= count($displayEntries) ?></strong> Catatan Transaksi
        </span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
            <thead>
                <tr class="bg-stone-100 text-stone-700 border-b border-stone-200">
                    <th class="py-3 px-4 font-bold">Tanggal</th>
                    <th class="py-3 px-4 font-bold">No. Bukti</th>
                    <th class="py-3 px-4 font-bold">Sumber / Kategori</th>
                    <th class="py-3 px-4 font-bold">Keterangan Transaksi</th>
                    <th class="py-3 px-4 font-bold">Metode</th>
                    <th class="py-3 px-4 font-bold text-right text-emerald-700">Uang Masuk (+)</th>
                    <th class="py-3 px-4 font-bold text-right text-rose-700">Uang Keluar (-)</th>
                    <th class="py-3 px-4 font-bold text-right">Saldo Kas</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                <?php if (empty($displayEntries)): ?>
                    <tr>
                        <td colspan="8" class="py-12 text-center text-stone-400">
                            <i class="fa-solid fa-book-open text-3xl mb-2 text-stone-300"></i>
                            <p>Tidak ada catatan transaksi pada filter tanggal ini.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($displayEntries as $item): 
                        $isExpense = ($item['entry_type'] === 'expense');
                        $isDistribution = ($item['entry_type'] === 'distribution');
                        $isMoneyOut = ($isExpense || $isDistribution);
                    ?>
                        <tr class="hover:bg-stone-50/80 transition">
                            <td class="py-3 px-4 text-stone-600 whitespace-nowrap">
                                <?= formatDate($item['tx_date']) ?>
                            </td>
                            <td class="py-3 px-4 font-mono font-bold text-stone-800">
                                <?php if ($item['entry_type'] === 'print'): ?>
                                    <a href="print_nota.php?id=<?= $item['record_id'] ?>" target="_blank" class="text-stone-900 hover:underline">
                                        <?= htmlspecialchars($item['ref_code']) ?>
                                    </a>
                                <?php elseif ($item['entry_type'] === 'acrylic'): ?>
                                    <a href="rental_detail.php?id=<?= $item['record_id'] ?>" class="text-amber-800 hover:underline">
                                        <?= htmlspecialchars($item['ref_code']) ?>
                                    </a>
                                <?php elseif ($item['entry_type'] === 'distribution'): ?>
                                    <a href="print_bagi_hasil.php?id=<?= $item['record_id'] ?>" target="_blank" class="text-amber-900 hover:underline flex items-center gap-1 font-bold">
                                        <i class="fa-solid fa-receipt text-amber-700"></i>
                                        <span><?= htmlspecialchars($item['ref_code']) ?></span>
                                    </a>
                                <?php else: ?>
                                    <span class="text-stone-600"><?= htmlspecialchars($item['ref_code']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4">
                                <?php if ($item['entry_type'] === 'print'): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-stone-100 text-stone-800">Cetak & FC</span>
                                <?php elseif ($item['entry_type'] === 'acrylic'): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-900">Sewa Akrilik</span>
                                <?php elseif ($item['entry_type'] === 'distribution'): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-950 border border-amber-300">Bagi Hasil / Prive</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800"><?= htmlspecialchars($item['category_label']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-stone-800 font-medium">
                                <?= htmlspecialchars($item['description']) ?>
                            </td>
                            <td class="py-3 px-4 uppercase text-[10px] font-bold text-stone-500">
                                <?= htmlspecialchars($item['payment_method']) ?>
                            </td>
                            <!-- Pemasukan -->
                            <td class="py-3 px-4 text-right font-black text-emerald-700">
                                <?= !$isMoneyOut ? '+ ' . formatRupiah($item['amount']) : '-' ?>
                            </td>
                            <!-- Pengeluaran / Prive -->
                            <td class="py-3 px-4 text-right font-black text-rose-600">
                                <?= $isMoneyOut ? '- ' . formatRupiah($item['amount']) : '-' ?>
                            </td>
                            <!-- Running Saldo -->
                            <td class="py-3 px-4 text-right font-black text-stone-900">
                                <?= formatRupiah($item['balance_after']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="bg-stone-100 text-stone-900 font-bold border-t-2 border-stone-300">
                    <td colspan="5" class="py-3.5 px-4 text-right uppercase text-[10px]">TOTAL MUTASI PERIODE INI:</td>
                    <td class="py-3.5 px-4 text-right text-emerald-800 font-black">+ <?= formatRupiah($totalIncome) ?></td>
                    <td class="py-3.5 px-4 text-right text-rose-700 font-black">- <?= formatRupiah($totalOut) ?></td>
                    <td class="py-3.5 px-4 text-right text-stone-900 font-black text-sm"><?= formatRupiah($netBalance) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
