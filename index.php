<?php
// index.php
require_once __DIR__ . '/config/app.php';
requireAuth();

$pageTitle = 'Beranda & Ikhtisar Toko';
$pageSubtitle = 'Pantau omset percetakan, jadwal sewa papan akrilik & antrian kasir';
require_once __DIR__ . '/includes/header.php';

$pdo = Database::getConnection();
$isAdmin = isAdmin();

// Current dates
$currentMonth = date('Y-m');
$todayDate = date('Y-m-d');

// 1. Pemasukan Hari Ini (Shared)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(grand_total), 0) FROM orders WHERE order_status != 'dibatalkan' AND DATE(created_at) = ?");
$stmt->execute([$todayDate]);
$incomePrintToday = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_cost + late_fine), 0) FROM acrylic_rentals WHERE status != 'cancelled' AND DATE(start_date) = ?");
$stmt->execute([$todayDate]);
$incomeRentalToday = (float)$stmt->fetchColumn();

$incomeToday = $incomePrintToday + $incomeRentalToday;

// 2. Transaksi Cetak Hari Ini (Shared)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ? AND order_status != 'dibatalkan'");
$stmt->execute([$todayDate]);
$ordersCountToday = (int)$stmt->fetchColumn();

// 3. Papan Akrilik Stats (Shared)
$stmt = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
    SUM(CASE WHEN status = 'rented' THEN 1 ELSE 0 END) as rented,
    SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance
    FROM acrylic_items");
$acrylicStats = $stmt->fetch();

// 4. Rentals Due Soon or Overdue (Shared)
$stmt = $pdo->prepare("SELECT r.*, a.name as acrylic_name, a.code as acrylic_code
    FROM acrylic_rentals r
    JOIN acrylic_items a ON r.acrylic_id = a.id
    WHERE r.status IN ('booked', 'active', 'late')
    ORDER BY r.end_date ASC LIMIT 6");
$stmt->execute();
$activeRentals = $stmt->fetchAll();

// 5. Recent Print Orders (Shared)
$stmt = $pdo->query("SELECT * FROM orders ORDER BY id DESC LIMIT 5");
$recentOrders = $stmt->fetchAll();

// 6. Recent Online Orders (For Kasir view)
$stmt = $pdo->query("SELECT * FROM online_orders ORDER BY id DESC LIMIT 5");
$recentOnlineOrders = $stmt->fetchAll();

// 7. Data Khusus Admin (Buku Keuangan & Laba Rugi Bulanan)
if ($isAdmin) {
    // Pemasukan Bulanan
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(grand_total), 0) FROM orders WHERE order_status != 'dibatalkan' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([$currentMonth]);
    $incomePrintMonth = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_cost + late_fine), 0) FROM acrylic_rentals WHERE status != 'cancelled' AND DATE_FORMAT(start_date, '%Y-%m') = ?");
    $stmt->execute([$currentMonth]);
    $incomeRentalMonth = (float)$stmt->fetchColumn();

    $totalIncomeMonth = $incomePrintMonth + $incomeRentalMonth;

    // Pengeluaran Bulanan
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE DATE_FORMAT(date, '%Y-%m') = ?");
    $stmt->execute([$currentMonth]);
    $totalExpenseMonth = (float)$stmt->fetchColumn();

    // Laba Bersih Bulanan
    $netProfitMonth = $totalIncomeMonth - $totalExpenseMonth;

    // Recent Expenses
    $stmt = $pdo->query("SELECT e.*, c.name as category_name FROM expenses e JOIN expense_categories c ON e.category_id = c.id ORDER BY e.id DESC LIMIT 5");
    $recentExpenses = $stmt->fetchAll();

    // 7 Days Chart Data
    $chartDays = [];
    $chartIncome = [];
    $chartExpense = [];

    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $dLabel = date('d M', strtotime($d));
        $chartDays[] = $dLabel;

        $s1 = $pdo->prepare("SELECT COALESCE(SUM(grand_total), 0) FROM orders WHERE order_status != 'dibatalkan' AND DATE(created_at) = ?");
        $s1->execute([$d]);
        $pIncome = (float)$s1->fetchColumn();

        $s2 = $pdo->prepare("SELECT COALESCE(SUM(total_cost + late_fine), 0) FROM acrylic_rentals WHERE status != 'cancelled' AND DATE(start_date) = ?");
        $s2->execute([$d]);
        $rIncome = (float)$s2->fetchColumn();

        $chartIncome[] = $pIncome + $rIncome;

        $s3 = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE date = ?");
        $s3->execute([$d]);
        $chartExpense[] = (float)$s3->fetchColumn();
    }
}
?>

<!-- Friendly Warm Hero Greeting -->
<div class="bg-gradient-to-r from-stone-900 via-stone-850 to-stone-900 text-white rounded-3xl p-6 sm:p-7 shadow-lg shadow-stone-900/10 mb-7 relative overflow-hidden border border-stone-800">
    <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-5">
        <div>
            <div class="flex items-center gap-2.5 mb-3">
                <div class="w-8 h-8 rounded-xl bg-white p-1 flex items-center justify-center shadow-xs">
                    <img src="assets/images/icon-mark.png" alt="Aquiris" class="w-full h-full object-contain" />
                </div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-stone-800 border border-stone-700 text-amber-300 text-xs font-semibold">
                    <i class="fa-solid fa-circle-dot text-emerald-400 text-[9px]"></i>
                    <span><?= $isAdmin ? '👑 Mode Administrator (Owner)' : '💼 Shift Kasir Front Office: ' . htmlspecialchars($currentUser['name']) ?></span>
                </div>
            </div>
            <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight serif-title">
                <?= $isAdmin ? 'Ringkasan Eksekutif & Omset Toko ✨' : 'Selamat bertugas di kasir, kak! Siap melayani order hari ini? ✨' ?>
            </h2>
            <p class="text-xs text-stone-300 mt-1 max-w-xl">
                <?= $isAdmin ? 'Pantau arus kas, laba bersih, beban operasional percetakan, dan inventaris sewa papan akrilik.' : 'Fokus pada pelayanan kasir POS, proses cetak antrian online, dan pengembalian sewa papan akrilik.' ?>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <?php $dashPendingCount = countPendingOnlineOrders(); ?>
            <?php if ($dashPendingCount > 0): ?>
                <a href="pesanan_online.php" class="px-4 py-2.5 bg-amber-500 hover:bg-amber-400 text-stone-950 rounded-2xl text-xs font-black shadow-md transition flex items-center gap-2 animate-pulse">
                    <i class="fa-solid fa-cloud-arrow-down"></i>
                    <span><?= $dashPendingCount ?> Order Online Baru!</span>
                </a>
            <?php endif; ?>
            <a href="kasir.php" class="px-4 py-2.5 bg-amber-400 hover:bg-amber-300 text-stone-950 rounded-2xl text-xs font-extrabold shadow-md transition flex items-center gap-2">
                <i class="fa-solid fa-cash-register"></i>
                <span>Kasir Cetak</span>
            </a>
            <a href="rental_tambah.php" class="px-4 py-2.5 bg-stone-800 hover:bg-stone-700 text-white rounded-2xl text-xs font-bold border border-stone-700 transition flex items-center gap-2">
                <i class="fa-solid fa-shapes text-amber-300"></i>
                <span>Sewa Akrilik</span>
            </a>
            <a href="landing.php" target="_blank" class="px-3.5 py-2.5 bg-stone-800/80 hover:bg-stone-700 text-stone-300 hover:text-white rounded-2xl text-xs font-bold border border-stone-700/80 transition flex items-center gap-1.5" title="Buka Landing Page Pemesanan Pelanggan">
                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                <span class="hidden sm:inline">Landing Page</span>
            </a>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
    <!-- ==================== DASHBOARD KHUSUS ADMINISTRATOR (OWNER) ==================== -->
    <!-- Executive Financial Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-7">
        <!-- Card 1: Total Pemasukan -->
        <div class="bg-white rounded-2xl p-5 border border-stone-200/90 shadow-sm hover:border-stone-300 transition">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-stone-500 uppercase tracking-wider">Total Omset Masuk</span>
                <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-sm font-bold">
                    <i class="fa-solid fa-arrow-down-left"></i>
                </div>
            </div>
            <div class="text-2xl font-black text-stone-900 tracking-tight">
                <?= formatRupiah($totalIncomeMonth) ?>
            </div>
            <div class="mt-2.5 pt-2 border-t border-stone-100 flex items-center justify-between text-xs text-stone-500">
                <span>Bulan Ini (<?= date('M Y') ?>)</span>
                <span class="font-bold text-emerald-700">Hari ini: <?= formatRupiah($incomeToday) ?></span>
            </div>
        </div>

        <!-- Card 2: Breakdown Pemasukan -->
        <div class="bg-white rounded-2xl p-5 border border-stone-200/90 shadow-sm hover:border-stone-300 transition">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-stone-500 uppercase tracking-wider">Sumber Pendapatan</span>
                <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center text-sm font-bold">
                    <i class="fa-solid fa-pie-chart"></i>
                </div>
            </div>
            <div class="space-y-1.5 text-xs">
                <div class="flex justify-between items-center">
                    <span class="text-stone-600 flex items-center gap-1.5"><i class="fa-solid fa-print text-stone-400"></i> Cetak & FC:</span>
                    <span class="font-extrabold text-stone-900"><?= formatRupiah($incomePrintMonth) ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-stone-600 flex items-center gap-1.5"><i class="fa-solid fa-shapes text-amber-600"></i> Sewa Akrilik:</span>
                    <span class="font-extrabold text-amber-800"><?= formatRupiah($incomeRentalMonth) ?></span>
                </div>
            </div>
            <div class="mt-2.5 pt-2 border-t border-stone-100 flex items-center justify-between text-[11px] text-stone-400">
                <span>Rekap Omset Usaha</span>
                <a href="arus_kas.php" class="text-amber-700 hover:underline font-bold">Buku Kas &rarr;</a>
            </div>
        </div>

        <!-- Card 3: Total Pengeluaran -->
        <div class="bg-white rounded-2xl p-5 border border-stone-200/90 shadow-sm hover:border-stone-300 transition">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-stone-500 uppercase tracking-wider">Total Biaya Operasional</span>
                <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-700 flex items-center justify-center text-sm font-bold">
                    <i class="fa-solid fa-arrow-up-right"></i>
                </div>
            </div>
            <div class="text-2xl font-black text-rose-600 tracking-tight">
                <?= formatRupiah($totalExpenseMonth) ?>
            </div>
            <div class="mt-2.5 pt-2 border-t border-stone-100 flex items-center justify-between text-xs text-stone-500">
                <span>Beli kertas, tinta, listrik</span>
                <a href="pengeluaran.php" class="font-bold text-rose-600 hover:underline">Rincian &rarr;</a>
            </div>
        </div>

        <!-- Card 4: Laba Bersih -->
        <div class="bg-stone-900 text-white rounded-2xl p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-stone-300 uppercase tracking-wider">Laba Bersih Toko</span>
                <div class="w-9 h-9 rounded-xl bg-stone-800 text-amber-300 flex items-center justify-center text-sm font-bold">
                    <i class="fa-solid fa-coins"></i>
                </div>
            </div>
            <div class="text-2xl font-black tracking-tight text-amber-300">
                <?= formatRupiah($netProfitMonth) ?>
            </div>
            <div class="mt-2.5 pt-2 border-t border-stone-800 flex items-center justify-between text-xs text-stone-300">
                <span>Omset dikurangi Beban</span>
                <span class="font-extrabold text-amber-300 text-xs"><?= $totalIncomeMonth > 0 ? round(($netProfitMonth / $totalIncomeMonth) * 100, 1) . '% Margin' : '-' ?></span>
            </div>
        </div>
    </div>

    <?php 
    $ownerPct = (float)getSetting('profit_share_owner', '40');
    $workerPct = (float)getSetting('profit_share_worker', '40');
    $reservePct = (float)getSetting('profit_share_reserve', '20');

    $estOwner = $netProfitMonth > 0 ? round(($netProfitMonth * $ownerPct) / 100) : 0;
    $estWorker = $netProfitMonth > 0 ? round(($netProfitMonth * $workerPct) / 100) : 0;
    $estReserve = $netProfitMonth > 0 ? max(0, $netProfitMonth - $estOwner - $estWorker) : 0;
    ?>
    <!-- Estimasi Bagi Hasil Widget (Khusus Admin / Owner) -->
    <div class="bg-gradient-to-r from-stone-900 to-stone-800 text-white rounded-2xl p-4 sm:p-5 mb-7 border border-stone-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-xl bg-amber-400/20 text-amber-300 flex items-center justify-center text-lg font-bold shrink-0">
                <i class="fa-solid fa-scale-balanced"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-black uppercase text-amber-300 tracking-wider">Estimasi Bagi Hasil Bulan Ini (<?= date('M Y') ?>)</span>
                    <span class="text-[10px] bg-stone-700 text-stone-300 px-2 py-0.5 rounded-full font-bold"><?= $ownerPct ?>% : <?= $workerPct ?>% : <?= $reservePct ?>%</span>
                </div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs mt-1">
                    <span class="text-stone-300"><i class="fa-solid fa-user-tie text-blue-400 mr-1"></i> Pemilik: <strong class="text-white"><?= formatRupiah($estOwner) ?></strong></span>
                    <span class="text-stone-400">&bull;</span>
                    <span class="text-stone-300"><i class="fa-solid fa-users text-emerald-400 mr-1"></i> Pekerja: <strong class="text-white"><?= formatRupiah($estWorker) ?></strong></span>
                    <span class="text-stone-400">&bull;</span>
                    <span class="text-stone-300"><i class="fa-solid fa-vault text-amber-400 mr-1"></i> Kas Usaha: <strong class="text-white"><?= formatRupiah($estReserve) ?></strong></span>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="bagi_hasil.php" class="px-4 py-2 bg-amber-400 hover:bg-amber-300 text-stone-950 font-bold rounded-xl text-xs shadow transition flex items-center gap-1.5">
                <i class="fa-solid fa-hand-holding-dollar"></i>
                <span>Kelola Bagi Hasil &rarr;</span>
            </a>
        </div>
    </div>

    <!-- Charts & Acrylic Availability Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-7">
        <!-- Chart: Cashflow 7 Hari Terakhir -->
        <div class="lg:col-span-2 bg-white rounded-2xl p-6 border border-stone-200/90 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-6">
                <div>
                    <h3 class="font-extrabold text-stone-800 text-sm">Grafik Mutasi Kas (7 Hari Terakhir)</h3>
                    <p class="text-xs text-stone-500">Perbandingan uang masuk vs uang keluar harian</p>
                </div>
                <div class="flex items-center gap-4 text-xs font-bold">
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                        <span class="text-stone-600">Pemasukan</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-full bg-rose-500"></span>
                        <span class="text-stone-600">Pengeluaran</span>
                    </div>
                </div>
            </div>
            <div class="h-64">
                <canvas id="cashflowChart"></canvas>
            </div>
        </div>

        <!-- Papan Akrilik Studio Status -->
        <div class="bg-white rounded-2xl p-6 border border-stone-200/90 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-extrabold text-stone-800 text-sm flex items-center gap-2">
                        <i class="fa-solid fa-shapes text-amber-600"></i>
                        <span>Status Galeri Akrilik</span>
                    </h3>
                    <a href="akrilik_master.php" class="text-xs text-amber-700 font-bold hover:underline">Katalog &rarr;</a>
                </div>
                <p class="text-xs text-stone-500 mb-5">Ketersediaan papan welcome sign & easel standing saat ini:</p>

                <div class="space-y-3">
                    <div class="flex items-center justify-between p-3 rounded-xl bg-emerald-50/80 border border-emerald-100">
                        <div class="flex items-center gap-2.5">
                            <div class="w-2.5 h-2.5 rounded-full bg-emerald-500"></div>
                            <span class="text-xs font-bold text-emerald-950">Tersedia di Galeri</span>
                        </div>
                        <span class="text-sm font-black text-emerald-800"><?= (int)($acrylicStats['available'] ?? 0) ?> Unit</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-amber-50/80 border border-amber-100">
                        <div class="flex items-center gap-2.5">
                            <div class="w-2.5 h-2.5 rounded-full bg-amber-500"></div>
                            <span class="text-xs font-bold text-amber-950">Sedang Disewa Pelanggan</span>
                        </div>
                        <span class="text-sm font-black text-amber-800"><?= (int)($acrylicStats['rented'] ?? 0) ?> Unit</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-stone-50 border border-stone-200">
                        <div class="flex items-center gap-2.5">
                            <div class="w-2.5 h-2.5 rounded-full bg-stone-400"></div>
                            <span class="text-xs font-semibold text-stone-600">Perawatan / Poles</span>
                        </div>
                        <span class="text-sm font-bold text-stone-700"><?= (int)($acrylicStats['maintenance'] ?? 0) ?> Unit</span>
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-stone-100 mt-4 flex items-center justify-between text-xs">
                <span class="text-stone-500">Total Koleksi: <strong><?= (int)($acrylicStats['total'] ?? 0) ?> Unit</strong></span>
                <a href="rental_tambah.php" class="px-3 py-1.5 bg-stone-900 text-amber-300 rounded-xl font-bold hover:bg-stone-800 transition">
                    + Catat Sewa
                </a>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- ==================== DASHBOARD KHUSUS KASIR (OPERATOR SHIFT) ==================== -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-7">
        <!-- Card 1: Pemasukan Kasir Hari Ini -->
        <div class="bg-white rounded-2xl p-5 border border-stone-200/90 shadow-sm hover:border-amber-400 transition">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-stone-500 uppercase tracking-wider">Kasir Shift Hari Ini</span>
                <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center text-sm font-bold">
                    <i class="fa-solid fa-cash-register"></i>
                </div>
            </div>
            <div class="text-2xl font-black text-stone-900 tracking-tight">
                <?= formatRupiah($incomeToday) ?>
            </div>
            <div class="mt-2.5 pt-2 border-t border-stone-100 flex items-center justify-between text-xs text-stone-500">
                <span>Total Uang Laci Kasir</span>
                <span class="font-bold text-emerald-700"><?= date('d M Y') ?></span>
            </div>
        </div>

        <!-- Card 2: Transaksi Cetak Selesai -->
        <div class="bg-white rounded-2xl p-5 border border-stone-200/90 shadow-sm hover:border-amber-400 transition">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-stone-500 uppercase tracking-wider">Transaksi Cetak</span>
                <div class="w-9 h-9 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center text-sm font-bold">
                    <i class="fa-solid fa-receipt"></i>
                </div>
            </div>
            <div class="text-2xl font-black text-amber-800 tracking-tight">
                <?= $ordersCountToday ?> Transaksi
            </div>
            <div class="mt-2.5 pt-2 border-t border-stone-100 flex items-center justify-between text-xs text-stone-500">
                <span>Nota Sukses Hari Ini</span>
                <a href="kasir.php" class="font-bold text-amber-700 hover:underline">+ Kasir POS</a>
            </div>
        </div>

        <!-- Card 3: Antrian Pesanan Online -->
        <div class="bg-white rounded-2xl p-5 border border-stone-200/90 shadow-sm hover:border-blue-400 transition">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-stone-500 uppercase tracking-wider">Antrian Cetak Online</span>
                <div class="w-9 h-9 rounded-xl bg-blue-100 text-blue-800 flex items-center justify-center text-sm font-bold">
                    <i class="fa-solid fa-cloud-arrow-down"></i>
                </div>
            </div>
            <div class="text-2xl font-black text-blue-700 tracking-tight">
                <?= $dashPendingCount ?> File Menunggu
            </div>
            <div class="mt-2.5 pt-2 border-t border-stone-100 flex items-center justify-between text-xs text-stone-500">
                <span>Dari Landing Page</span>
                <a href="pesanan_online.php" class="font-bold text-blue-600 hover:underline">Buka Antrian &rarr;</a>
            </div>
        </div>

        <!-- Card 4: Papan Akrilik Siap Sewa -->
        <div class="bg-white rounded-2xl p-5 border border-stone-200/90 shadow-sm hover:border-stone-300 transition">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-stone-500 uppercase tracking-wider">Stok Papan Akrilik</span>
                <div class="w-9 h-9 rounded-xl bg-stone-100 text-stone-800 flex items-center justify-center text-sm font-bold">
                    <i class="fa-solid fa-shapes"></i>
                </div>
            </div>
            <div class="text-2xl font-black text-stone-900 tracking-tight">
                <?= (int)($acrylicStats['available'] ?? 0) ?> <span class="text-sm font-semibold text-stone-500">Siap</span> / <?= (int)($acrylicStats['rented'] ?? 0) ?> <span class="text-sm font-semibold text-amber-700">Disewa</span>
            </div>
            <div class="mt-2.5 pt-2 border-t border-stone-100 flex items-center justify-between text-xs text-stone-500">
                <span>Total Galeri: <?= (int)($acrylicStats['total'] ?? 0) ?> Papan</span>
                <a href="rental_akrilik.php" class="font-bold text-amber-800 hover:underline">Jadwal &rarr;</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Acrylic Schedule Table (Shared for both Admin & Kasir) -->
<div class="bg-white rounded-2xl border border-stone-200/90 shadow-sm p-6 mb-7">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
        <div>
            <h3 class="font-extrabold text-stone-800 text-sm flex items-center gap-2">
                <i class="fa-solid fa-calendar-check text-amber-600"></i>
                <span>Jadwal Pengembalian Papan Akrilik Terdekat</span>
            </h3>
            <p class="text-xs text-stone-500">Pantau batas waktu sewa agar jaminan dapat diproses tepat waktu</p>
        </div>
        <a href="rental_akrilik.php" class="text-xs font-bold text-amber-800 hover:underline">Lihat Semua Jadwal &rarr;</a>
    </div>

    <?php if (empty($activeRentals)): ?>
        <div class="py-8 text-center text-stone-400 text-xs">
            <i class="fa-solid fa-circle-check text-emerald-500 text-xl mb-1.5 block"></i>
            <p>Semua papan akrilik aman berada di workshop.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-stone-50 text-stone-600 border-y border-stone-200">
                        <th class="py-3 px-4 font-bold">Kode Sewa</th>
                        <th class="py-3 px-4 font-bold">Penyewa & Acara</th>
                        <th class="py-3 px-4 font-bold">Papan yang Disewa</th>
                        <th class="py-3 px-4 font-bold">Batas Pengembalian</th>
                        <th class="py-3 px-4 font-bold">Status</th>
                        <th class="py-3 px-4 font-bold text-right">Aksi Cepat</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <?php foreach ($activeRentals as $rent): 
                        $isOverdue = ($rent['end_date'] < date('Y-m-d') && $rent['status'] !== 'returned');
                        $waMsg = "Halo kak {$rent['customer_name']}, kami dari " . getSetting('shop_name', 'Aquiris Print') . " ingin mengonfirmasi penyewaan papan akrilik *{$rent['acrylic_name']}* ({$rent['rental_code']}). Batas pengembalian adalah tanggal " . formatDate($rent['end_date']) . ". Apakah ada yang bisa kami bantu kak? Terima kasih 🙏";
                        $waUrl = buildWaLink($rent['customer_phone'], $waMsg);
                    ?>
                        <tr class="hover:bg-stone-50/80 transition">
                            <td class="py-3 px-4 font-bold text-stone-800">
                                <a href="rental_detail.php?id=<?= $rent['id'] ?>" class="text-amber-800 hover:underline">
                                    <?= htmlspecialchars($rent['rental_code']) ?>
                                </a>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-bold text-stone-800"><?= htmlspecialchars($rent['customer_name']) ?></div>
                                <div class="text-[11px] text-stone-500"><?= htmlspecialchars($rent['event_name'] ?: 'Acara Pribadi') ?></div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-bold text-stone-700"><?= htmlspecialchars($rent['acrylic_name']) ?></span>
                            </td>
                            <td class="py-3 px-4">
                                <div><?= formatDate($rent['start_date']) ?> s/d <strong><?= formatDate($rent['end_date']) ?></strong></div>
                                <?php if ($isOverdue): ?>
                                    <span class="inline-block mt-0.5 px-2 py-0.5 bg-rose-100 text-rose-800 font-extrabold text-[10px] rounded-full">
                                        <i class="fa-solid fa-triangle-exclamation"></i> Lewat Batas Waktu
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold <?= $rent['status'] === 'active' ? 'bg-amber-100 text-amber-800' : 'bg-stone-100 text-stone-700' ?>">
                                    <?= $rent['status'] === 'active' ? 'Sedang Disewa' : 'Dipesan (Booked)' ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right space-x-1 whitespace-nowrap">
                                <a href="<?= $waUrl ?>" target="_blank" class="inline-flex items-center gap-1 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition">
                                    <i class="fa-brands fa-whatsapp"></i>
                                    <span class="hidden sm:inline">WhatsApp</span>
                                </a>
                                <a href="rental_detail.php?id=<?= $rent['id'] ?>" class="inline-flex items-center gap-1 px-3 py-1.5 bg-stone-100 hover:bg-stone-200 text-stone-800 rounded-xl text-xs font-bold transition">
                                    <i class="fa-solid fa-arrow-rotate-left"></i>
                                    <span>Kembalikan</span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Bottom Row Tables -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Recent Print Orders (Shared) -->
    <div class="bg-white rounded-2xl border border-stone-200/90 shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-extrabold text-stone-800 text-sm flex items-center gap-2">
                    <i class="fa-solid fa-print text-stone-600"></i>
                    <span>Order Cetak Kasir Terbaru</span>
                </h3>
                <p class="text-xs text-stone-500">Fotocopy, print dokumen, skripsi, kartu ujian FTI</p>
            </div>
            <a href="transaksi_cetak.php" class="text-xs font-bold text-stone-700 hover:underline">Lihat Semua &rarr;</a>
        </div>

        <?php if (empty($recentOrders)): ?>
            <p class="text-xs text-stone-400 py-4 text-center">Belum ada transaksi cetak hari ini.</p>
        <?php else: ?>
            <div class="divide-y divide-stone-100">
                <?php foreach ($recentOrders as $ord): ?>
                    <div class="py-3 flex items-center justify-between gap-3 text-xs">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-stone-900"><?= htmlspecialchars($ord['invoice_number']) ?></span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $ord['payment_status'] === 'lunas' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                                    <?= strtoupper($ord['payment_status']) ?>
                                </span>
                            </div>
                            <div class="text-stone-500 mt-0.5"><?= htmlspecialchars($ord['customer_name']) ?> • <?= formatDate($ord['created_at'], true) ?></div>
                        </div>
                        <div class="text-right">
                            <span class="font-extrabold text-stone-900 block"><?= formatRupiah($ord['grand_total']) ?></span>
                            <a href="print_nota.php?id=<?= $ord['id'] ?>" target="_blank" class="text-[11px] text-amber-700 hover:underline font-bold">
                                <i class="fa-solid fa-receipt"></i> Nota
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($isAdmin): ?>
        <!-- Recent Expenses (Admin only) -->
        <div class="bg-white rounded-2xl border border-stone-200/90 shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-extrabold text-stone-800 text-sm flex items-center gap-2">
                        <i class="fa-solid fa-wallet text-rose-500"></i>
                        <span>Belanja / Beban Toko Terbaru</span>
                    </h3>
                    <p class="text-xs text-stone-500">Pembelian stok kertas, tinta, listrik & operasional</p>
                </div>
                <a href="pengeluaran.php" class="text-xs font-bold text-rose-600 hover:underline">Lihat Semua &rarr;</a>
            </div>

            <?php if (empty($recentExpenses)): ?>
                <p class="text-xs text-stone-400 py-4 text-center">Belum ada pengeluaran tercatat bulan ini.</p>
            <?php else: ?>
                <div class="divide-y divide-stone-100">
                    <?php foreach ($recentExpenses as $exp): ?>
                        <div class="py-3 flex items-center justify-between gap-3 text-xs">
                            <div>
                                <span class="font-bold text-stone-900 block"><?= htmlspecialchars($exp['title']) ?></span>
                                <div class="text-stone-500 text-[11px] mt-0.5"><?= htmlspecialchars($exp['category_name']) ?> • <?= formatDate($exp['date']) ?></div>
                            </div>
                            <span class="font-extrabold text-rose-600 text-xs">-<?= formatRupiah($exp['amount']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Recent Online Orders (For Kasir) -->
        <div class="bg-white rounded-2xl border border-stone-200/90 shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-extrabold text-stone-800 text-sm flex items-center gap-2">
                        <i class="fa-solid fa-cloud-arrow-down text-blue-600"></i>
                        <span>Pesanan Cetak Online Masuk</span>
                    </h3>
                    <p class="text-xs text-stone-500">File kiriman pelanggan dari landing page</p>
                </div>
                <a href="pesanan_online.php" class="text-xs font-bold text-blue-700 hover:underline">Buka Antrian &rarr;</a>
            </div>

            <?php if (empty($recentOnlineOrders)): ?>
                <p class="text-xs text-stone-400 py-4 text-center">Belum ada pesanan online masuk.</p>
            <?php else: ?>
                <div class="divide-y divide-stone-100">
                    <?php foreach ($recentOnlineOrders as $onl): ?>
                        <div class="py-3 flex items-center justify-between gap-3 text-xs">
                            <div class="min-w-0 pr-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-stone-900">#<?= htmlspecialchars($onl['order_code']) ?></span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $onl['status'] === 'menunggu' ? 'bg-amber-100 text-amber-800' : ($onl['status'] === 'diproses' ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800') ?>">
                                        <?= strtoupper($onl['status']) ?>
                                    </span>
                                </div>
                                <p class="text-stone-700 font-semibold truncate mt-0.5"><?= htmlspecialchars($onl['customer_name']) ?> • <?= htmlspecialchars($onl['service_type']) ?></p>
                            </div>
                            <?php if (!empty($onl['file_path'])): ?>
                                <a href="<?= htmlspecialchars($onl['file_path']) ?>" download class="px-2.5 py-1.5 bg-stone-900 hover:bg-stone-800 text-amber-300 rounded-lg text-[11px] font-bold shrink-0 flex items-center gap-1">
                                    <i class="fa-solid fa-download"></i>
                                    <span>File</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($isAdmin): ?>
<script>
    // Initialize Cashflow Chart for Administrator
    const chartCanvas = document.getElementById('cashflowChart');
    if (chartCanvas) {
        const ctx = chartCanvas.getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartDays) ?>,
                datasets: [
                    {
                        label: 'Pemasukan (Rp)',
                        data: <?= json_encode($chartIncome) ?>,
                        backgroundColor: 'rgba(16, 185, 129, 0.85)',
                        borderRadius: 6,
                        barPercentage: 0.6,
                    },
                    {
                        label: 'Pengeluaran (Rp)',
                        data: <?= json_encode($chartExpense) ?>,
                        backgroundColor: 'rgba(244, 63, 94, 0.85)',
                        borderRadius: 6,
                        barPercentage: 0.6,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                if (context.parsed.y !== null) {
                                    label += new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + (value / 1000).toLocaleString('id-ID') + 'k';
                            },
                            font: { size: 10 }
                        },
                        grid: { color: '#f5f0e6' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10 } }
                    }
                }
            }
        });
    }
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
