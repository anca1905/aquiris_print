<?php
// laporan.php
require_once __DIR__ . '/config/app.php';
requireAdmin();

$pageTitle = 'Laporan Laba Rugi Usaha';
$pageSubtitle = 'Rekapitulasi berkala pendapatan kotor, beban operasional & keuntungan bersih';
require_once __DIR__ . '/includes/header.php';

$pdo = Database::getConnection();

// Date range filters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// 1. Pendapatan Cetak
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_orders,
    COALESCE(SUM(total_amount), 0) as gross_sales,
    COALESCE(SUM(discount), 0) as total_discounts,
    COALESCE(SUM(grand_total), 0) as net_sales
    FROM orders 
    WHERE order_status != 'dibatalkan' AND DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$startDate, $endDate]);
$printStats = $stmt->fetch();

// 2. Pendapatan Sewa Akrilik
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_rentals,
    COALESCE(SUM(rental_price), 0) as rental_income,
    COALESCE(SUM(custom_sticker_fee), 0) as sticker_income,
    COALESCE(SUM(late_fine), 0) as fine_income,
    COALESCE(SUM(total_cost + late_fine), 0) as total_acrylic_income
    FROM acrylic_rentals 
    WHERE status != 'cancelled' AND DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$startDate, $endDate]);
$acrylicStats = $stmt->fetch();

$totalRevenue = (float)$printStats['net_sales'] + (float)$acrylicStats['total_acrylic_income'];

// 3. Pengeluaran per Kategori
$stmt = $pdo->prepare("SELECT 
    c.name as category_name,
    COUNT(e.id) as tx_count,
    COALESCE(SUM(e.amount), 0) as total_amount
    FROM expense_categories c
    LEFT JOIN expenses e ON c.id = e.category_id AND e.date BETWEEN ? AND ?
    GROUP BY c.id, c.name
    HAVING total_amount > 0
    ORDER BY total_amount DESC");
$stmt->execute([$startDate, $endDate]);
$expenseBreakdown = $stmt->fetchAll();

$totalExpenses = array_sum(array_column($expenseBreakdown, 'total_amount'));

// 4. Laba Bersih
$netProfit = $totalRevenue - $totalExpenses;
$profitMargin = $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 1) : 0;

// 5. Skema Bagi Hasil (Profit Sharing)
$ownerPct = (float)getSetting('profit_share_owner', '40');
$workerPct = (float)getSetting('profit_share_worker', '40');
$reservePct = (float)getSetting('profit_share_reserve', '20');

$ownerAmount = $netProfit > 0 ? round(($netProfit * $ownerPct) / 100) : 0;
$workerAmount = $netProfit > 0 ? round(($netProfit * $workerPct) / 100) : 0;
$reserveAmount = $netProfit > 0 ? max(0, $netProfit - $ownerAmount - $workerAmount) : 0;

// Export CSV handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Laporan_Keuangan_' . $startDate . '_sd_' . $endDate . '.csv');
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

    fputcsv($output, ['LAPORAN LABA RUGI - ' . getSetting('shop_name', 'Aquiris Print')]);
    fputcsv($output, ['Periode:', $startDate . ' s/d ' . $endDate]);
    fputcsv($output, []);

    fputcsv($output, ['1. PENDAPATAN USAHA']);
    fputcsv($output, ['Pendapatan Percetakan & Fotocopy', $printStats['net_sales']]);
    fputcsv($output, ['Pendapatan Sewa Papan Akrilik', $acrylicStats['rental_income']]);
    fputcsv($output, ['Pendapatan Cetak Stiker Akrilik', $acrylicStats['sticker_income']]);
    fputcsv($output, ['Pendapatan Denda Keterlambatan Sewa', $acrylicStats['fine_income']]);
    fputcsv($output, ['TOTAL PENDAPATAN', $totalRevenue]);
    fputcsv($output, []);

    fputcsv($output, ['2. BEBAN OPERASIONAL TOKO']);
    foreach ($expenseBreakdown as $exp) {
        fputcsv($output, [$exp['category_name'], $exp['total_amount']]);
    }
    fputcsv($output, ['TOTAL BEBAN OPERASIONAL', $totalExpenses]);
    fputcsv($output, []);

    fputcsv($output, ['3. HASIL KEUNTUNGAN BERSIH']);
    fputcsv($output, ['LABA BERSIH', $netProfit]);
    fputcsv($output, ['Margin Keuntungan', $profitMargin . '%']);
    fputcsv($output, []);

    fputcsv($output, ['4. ALOKASI BAGI HASIL USAHA']);
    fputcsv($output, ['Bagian Pemilik Modal (' . $ownerPct . '%)', $ownerAmount]);
    fputcsv($output, ['Bagian Pekerja / Operator (' . $workerPct . '%)', $workerAmount]);
    fputcsv($output, ['Bagian Kas Usaha & Cadangan (' . $reservePct . '%)', $reserveAmount]);

    fclose($output);
    exit;
}
?>

<!-- Filter Toolbar -->
<div class="bg-white p-5 rounded-3xl border border-stone-200/90 shadow-sm mb-6 no-print">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <form method="GET" action="laporan.php" class="grid grid-cols-1 sm:grid-cols-3 gap-3 flex-1 text-xs">
            <div>
                <label class="block font-bold text-stone-600 mb-1">Mulai Tanggal</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="w-full px-3 py-2 bg-stone-50 border border-stone-200 rounded-xl outline-none font-bold text-stone-800" />
            </div>
            <div>
                <label class="block font-bold text-stone-600 mb-1">Sampai Tanggal</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="w-full px-3 py-2 bg-stone-50 border border-stone-200 rounded-xl outline-none font-bold text-stone-800" />
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 px-3 bg-stone-900 hover:bg-stone-800 text-amber-300 font-bold rounded-xl shadow-sm transition flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-filter"></i>
                    <span>Tampilkan</span>
                </button>
            </div>
        </form>

        <div class="flex items-center gap-2">
            <a href="laporan.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&export=csv" class="px-4 py-2 bg-stone-100 hover:bg-stone-200 text-stone-800 rounded-xl text-xs font-bold transition flex items-center gap-1.5 border border-stone-300">
                <i class="fa-solid fa-file-excel text-emerald-600"></i>
                <span>Ekspor Excel/CSV</span>
            </a>
            <button onclick="window.print()" class="px-4 py-2 bg-stone-900 hover:bg-stone-800 text-amber-300 rounded-xl text-xs font-bold shadow transition flex items-center gap-1.5">
                <i class="fa-solid fa-print"></i>
                <span>Cetak Laporan</span>
            </button>
        </div>
    </div>
</div>

<!-- Printable Financial Statement Container -->
<div class="bg-white rounded-3xl border border-stone-200/90 shadow-sm p-8 sm:p-9 max-w-4xl mx-auto text-xs text-stone-800 space-y-6">
    <!-- Header of Report -->
    <div class="flex justify-between items-center pb-6 border-b-2 border-stone-900">
        <div class="flex items-center gap-4">
            <img src="assets/images/logo-light.jpg" alt="Aquiris Print" class="h-12 w-auto object-contain" />
            <div class="border-l-2 border-stone-200 pl-3.5">
                <h2 class="text-base font-black text-stone-900 uppercase serif-title"><?= htmlspecialchars(getSetting('shop_name', 'Aquiris Print')) ?></h2>
                <h3 class="text-xs font-bold text-stone-600">Laporan Laba Rugi Komprehensif</h3>
                <p class="text-[11px] text-stone-400"><?= htmlspecialchars(getSetting('shop_address', 'Jl. Percetakan')) ?></p>
            </div>
        </div>
        <div class="text-right">
            <span class="text-[10px] text-stone-400 block uppercase font-bold tracking-wider">Periode Buku:</span>
            <span class="font-bold text-stone-800 text-xs block"><?= formatDate($startDate) ?> s/d <?= formatDate($endDate) ?></span>
            <span class="text-[10px] text-stone-400">Dicetak: <?= date('d M Y, H:i') ?> WIB</span>
        </div>
    </div>

    <!-- 1. PENDAPATAN USAHA (REVENUE) -->
    <div>
        <div class="p-2.5 bg-stone-100 rounded-xl font-bold text-stone-800 uppercase tracking-wider text-xs mb-3 flex justify-between">
            <span>1. PENDAPATAN USAHA (REVENUE)</span>
            <span>NOMINAL (IDR)</span>
        </div>
        <div class="space-y-2 px-3">
            <div class="flex justify-between items-center py-1.5 border-b border-stone-100">
                <div>
                    <span class="font-bold text-stone-900">Pendapatan Percetakan & Fotocopy</span>
                    <span class="text-[10px] text-stone-400 block"><?= (int)$printStats['total_orders'] ?> Transaksi selesai</span>
                </div>
                <span class="font-bold text-stone-900"><?= formatRupiah($printStats['net_sales']) ?></span>
            </div>

            <div class="flex justify-between items-center py-1.5 border-b border-stone-100">
                <div>
                    <span class="font-bold text-stone-900">Pendapatan Sewa Papan Akrilik & Standing</span>
                    <span class="text-[10px] text-stone-400 block"><?= (int)$acrylicStats['total_rentals'] ?> Kontrak sewa event</span>
                </div>
                <span class="font-bold text-stone-900"><?= formatRupiah($acrylicStats['rental_income']) ?></span>
            </div>

            <?php if ($acrylicStats['sticker_income'] > 0): ?>
                <div class="flex justify-between items-center py-1.5 border-b border-stone-100">
                    <div>
                        <span class="font-bold text-stone-900">Pendapatan Jasa Cetak & Pasang Cutting Stiker Tulisan</span>
                        <span class="text-[10px] text-stone-400 block">Kustomisasi ucapan welcome sign</span>
                    </div>
                    <span class="font-bold text-stone-900"><?= formatRupiah($acrylicStats['sticker_income']) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($acrylicStats['fine_income'] > 0): ?>
                <div class="flex justify-between items-center py-1.5 border-b border-stone-100">
                    <div>
                        <span class="font-bold text-stone-900">Pendapatan Denda Keterlambatan Sewa</span>
                        <span class="text-[10px] text-stone-400 block">Kompensasi lewat waktu sewa</span>
                    </div>
                    <span class="font-bold text-stone-900"><?= formatRupiah($acrylicStats['fine_income']) ?></span>
                </div>
            <?php endif; ?>

            <div class="flex justify-between items-center pt-2.5 font-black text-sm text-emerald-800">
                <span>TOTAL PENDAPATAN KOTOR</span>
                <span><?= formatRupiah($totalRevenue) ?></span>
            </div>
        </div>
    </div>

    <!-- 2. BEBAN OPERASIONAL (OPERATING EXPENSES) -->
    <div>
        <div class="p-2.5 bg-stone-100 rounded-xl font-bold text-stone-800 uppercase tracking-wider text-xs mb-3 flex justify-between">
            <span>2. BEBAN OPERASIONAL & BIAYA USAHA (EXPENSES)</span>
            <span>NOMINAL (IDR)</span>
        </div>
        <div class="space-y-2 px-3">
            <?php if (empty($expenseBreakdown)): ?>
                <p class="text-stone-400 italic py-2">Tidak ada catatan beban operasional pada periode ini.</p>
            <?php else: ?>
                <?php foreach ($expenseBreakdown as $eb): ?>
                    <div class="flex justify-between items-center py-1.5 border-b border-stone-100">
                        <div>
                            <span class="font-bold text-stone-900"><?= htmlspecialchars($eb['category_name']) ?></span>
                            <span class="text-[10px] text-stone-400 block"><?= (int)$eb['tx_count'] ?> kali transaksi</span>
                        </div>
                        <span class="font-bold text-rose-600"><?= formatRupiah($eb['total_amount']) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="flex justify-between items-center pt-2.5 font-black text-sm text-rose-700">
                <span>TOTAL BEBAN OPERASIONAL</span>
                <span><?= formatRupiah($totalExpenses) ?></span>
            </div>
        </div>
    </div>

    <!-- 3. LABA / RUGI BERSIH (NET PROFIT) -->
    <div class="p-4 rounded-2xl <?= $netProfit >= 0 ? 'bg-amber-50/80 border-2 border-amber-600' : 'bg-rose-50 border-2 border-rose-500' ?>">
        <div class="flex justify-between items-center">
            <div>
                <span class="text-xs uppercase font-extrabold <?= $netProfit >= 0 ? 'text-amber-950' : 'text-rose-950' ?> tracking-wider block">
                    3. HASIL BERSIH (KEUNTUNGAN USAHA)
                </span>
                <span class="text-[11px] <?= $netProfit >= 0 ? 'text-amber-800' : 'text-rose-700' ?>">
                    Margin Keuntungan Bersih: <strong><?= $profitMargin ?>%</strong>
                </span>
            </div>
            <div class="text-2xl font-black <?= $netProfit >= 0 ? 'text-amber-900' : 'text-rose-700' ?>">
                <?= formatRupiah($netProfit) ?>
            </div>
        </div>
    </div>

    <!-- 4. ALOKASI BAGI HASIL USAHA (PROFIT SHARING) -->
    <div class="space-y-3">
        <div class="p-2.5 bg-stone-100 rounded-xl font-bold text-stone-800 uppercase tracking-wider text-xs flex justify-between items-center">
            <span>4. ALOKASI BAGI HASIL USAHA (PROFIT SHARING)</span>
            <span class="text-[11px] text-stone-600 font-bold bg-white px-2.5 py-0.5 rounded-md border border-stone-200">
                Rasio: <?= $ownerPct ?>% : <?= $workerPct ?>% : <?= $reservePct ?>%
            </span>
        </div>

        <?php if ($netProfit <= 0): ?>
            <div class="p-4 bg-stone-50 border border-stone-200 rounded-2xl text-stone-500 text-xs italic text-center">
                <i class="fa-solid fa-circle-exclamation text-stone-400 mr-1.5"></i>
                Tidak ada alokasi pembagian hasil karena laba bersih periode ini bernilai nol atau mengalami defisit/beban lebih besar.
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                <!-- 1. Pemilik Modal -->
                <div class="p-4 rounded-2xl bg-blue-50/80 border border-blue-200/90 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[11px] font-extrabold text-blue-900 uppercase tracking-wide flex items-center gap-1.5">
                                <i class="fa-solid fa-user-tie text-blue-600"></i>
                                <span>Pemilik Modal</span>
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-blue-200 text-blue-900"><?= $ownerPct ?>%</span>
                        </div>
                        <p class="text-[10px] text-blue-700/80 mb-2">Hak dividen / return of capital modal toko</p>
                    </div>
                    <div class="text-xl font-black text-blue-950 pt-2 border-t border-blue-200/60">
                        <?= formatRupiah($ownerAmount) ?>
                    </div>
                </div>

                <!-- 2. Pekerja / Tim Operasional -->
                <div class="p-4 rounded-2xl bg-emerald-50/80 border border-emerald-200/90 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[11px] font-extrabold text-emerald-900 uppercase tracking-wide flex items-center gap-1.5">
                                <i class="fa-solid fa-users text-emerald-600"></i>
                                <span>Pekerja / Tim</span>
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-200 text-emerald-900"><?= $workerPct ?>%</span>
                        </div>
                        <p class="text-[10px] text-emerald-700/80 mb-2">Insentif & bagi hasil tenaga pengelola</p>
                    </div>
                    <div class="text-xl font-black text-emerald-950 pt-2 border-t border-emerald-200/60">
                        <?= formatRupiah($workerAmount) ?>
                    </div>
                </div>

                <!-- 3. Kas Usaha & Cadangan -->
                <div class="p-4 rounded-2xl bg-amber-50/80 border border-amber-200/90 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[11px] font-extrabold text-amber-950 uppercase tracking-wide flex items-center gap-1.5">
                                <i class="fa-solid fa-vault text-amber-700"></i>
                                <span>Kas Usaha</span>
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-200 text-amber-950"><?= $reservePct ?>%</span>
                        </div>
                        <p class="text-[10px] text-amber-800/80 mb-2">Dana cadangan, darurat & reinvestasi toko</p>
                    </div>
                    <div class="text-xl font-black text-amber-950 pt-2 border-t border-amber-200/60">
                        <?= formatRupiah($reserveAmount) ?>
                    </div>
                </div>
            </div>

            <!-- Toolbar Action Bagi Hasil (No-Print) -->
            <div class="no-print pt-2 flex flex-col sm:flex-row items-center justify-between gap-3 bg-stone-50 p-4 rounded-2xl border border-stone-200">
                <div class="text-xs text-stone-600 flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-amber-100 text-amber-800 flex items-center justify-center text-xs shrink-0">
                        <i class="fa-solid fa-hand-holding-dollar"></i>
                    </div>
                    <span>Ingin mencairkan & mencatat pembagian ini ke Buku Kas?</span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="pengaturan.php" class="px-3 py-2 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold rounded-xl text-xs transition">
                        <i class="fa-solid fa-gear"></i>
                        <span>Ubah Rasio</span>
                    </a>
                    <a href="bagi_hasil.php?action=new&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="px-4 py-2 bg-stone-900 hover:bg-stone-800 text-amber-300 font-bold rounded-xl text-xs shadow transition flex items-center gap-1.5 shrink-0">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                        <span>Catat Eksekusi Bagi Hasil &rarr;</span>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Signature Area: 3 Pihak (Pembukuan, Pekerja, Pemilik Modal) -->
    <div class="grid grid-cols-3 gap-4 pt-10 mt-10 border-t border-stone-200 text-center text-xs">
        <div>
            <p class="text-stone-500 mb-16">Disiapkan Oleh (Pembukuan/Kasir),</p>
            <p class="font-bold text-stone-800 border-t border-stone-300 inline-block px-4 pt-1"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Petugas Kasir') ?></p>
        </div>
        <div>
            <p class="text-stone-500 mb-16">Perwakilan Pekerja (Tenaga Kerja),</p>
            <p class="font-bold text-stone-800 border-t border-stone-300 inline-block px-4 pt-1">( Tenaga Operator / Kerja )</p>
        </div>
        <div>
            <p class="text-stone-500 mb-16">Mengetahui & Menyetujui,</p>
            <p class="font-bold text-stone-800 border-t border-stone-300 inline-block px-4 pt-1">Pemilik Modal (Owner Usaha)</p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
