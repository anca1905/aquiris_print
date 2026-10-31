<?php
// bagi_hasil.php
require_once __DIR__ . '/config/app.php';
requireAdmin();

$pdo = Database::getConnection();

// Quick AJAX calculation endpoint
if (isset($_GET['ajax']) && $_GET['ajax'] === 'calc_profit') {
    header('Content-Type: application/json');
    $sDate = $_GET['start_date'] ?? date('Y-m-01');
    $eDate = $_GET['end_date'] ?? date('Y-m-d');

    // 1. Print revenue
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(grand_total), 0) FROM orders WHERE order_status != 'dibatalkan' AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$sDate, $eDate]);
    $printRev = (float)$stmt->fetchColumn();

    // 2. Acrylic revenue
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_cost + late_fine), 0) FROM acrylic_rentals WHERE status != 'cancelled' AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$sDate, $eDate]);
    $acrylicRev = (float)$stmt->fetchColumn();

    $grossRevenue = $printRev + $acrylicRev;

    // 3. Expenses
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE date BETWEEN ? AND ?");
    $stmt->execute([$sDate, $eDate]);
    $totalExpenses = (float)$stmt->fetchColumn();

    $netProfit = $grossRevenue - $totalExpenses;

    $ownerPct = (float)getSetting('profit_share_owner', '40');
    $workerPct = (float)getSetting('profit_share_worker', '40');
    $reservePct = (float)getSetting('profit_share_reserve', '20');

    $ownerAmount = $netProfit > 0 ? round(($netProfit * $ownerPct) / 100) : 0;
    $workerAmount = $netProfit > 0 ? round(($netProfit * $workerPct) / 100) : 0;
    $reserveAmount = $netProfit > 0 ? max(0, $netProfit - $ownerAmount - $workerAmount) : 0;

    echo json_encode([
        'gross_revenue' => $grossRevenue,
        'total_expenses' => $totalExpenses,
        'net_profit' => $netProfit,
        'owner_pct' => $ownerPct,
        'owner_amount' => $ownerAmount,
        'worker_pct' => $workerPct,
        'worker_amount' => $workerAmount,
        'reserve_pct' => $reservePct,
        'reserve_amount' => $reserveAmount
    ]);
    exit;
}

// Handle Delete Distribution
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM profit_distributions WHERE id = ?");
    $stmt->execute([$delId]);
    setFlash('success', 'Catatan pembagian hasil berhasil dihapus.');
    header('Location: bagi_hasil.php');
    exit;
}

// Handle Save New Distribution
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_distribution'])) {
    $periodStart = trim($_POST['period_start'] ?? '');
    $periodEnd = trim($_POST['period_end'] ?? '');
    $distDate = trim($_POST['distribution_date'] ?? date('Y-m-d'));
    $payMethod = $_POST['payment_method'] ?? 'tunai';
    $notes = trim($_POST['notes'] ?? '');

    $grossRevenue = (float)($_POST['gross_revenue'] ?? 0);
    $totalExpenses = (float)($_POST['total_expenses'] ?? 0);
    $netProfit = (float)($_POST['net_profit'] ?? 0);

    $ownerPct = (float)($_POST['owner_percent'] ?? 40);
    $ownerAmount = (float)($_POST['owner_amount'] ?? 0);

    $workerPct = (float)($_POST['worker_percent'] ?? 40);
    $workerAmount = (float)($_POST['worker_amount'] ?? 0);

    $reservePct = (float)($_POST['reserve_percent'] ?? 20);
    $reserveAmount = (float)($_POST['reserve_amount'] ?? 0);

    if (empty($periodStart) || empty($periodEnd)) {
        setFlash('error', 'Rentang periode pembukuan wajib ditentukan.');
    } elseif ($netProfit <= 0) {
        setFlash('error', 'Laba bersih harus bernilai lebih dari Rp 0 untuk dapat dibagikan.');
    } else {
        $code = generateDistributionCode($pdo);
        $userId = $_SESSION['user_id'] ?? null;
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("INSERT INTO profit_distributions 
            (distribution_code, period_start, period_end, gross_revenue, total_expenses, net_profit,
             owner_percent, owner_amount, worker_percent, worker_amount, reserve_percent, reserve_amount,
             distribution_date, payment_method, notes, user_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $code, $periodStart, $periodEnd, $grossRevenue, $totalExpenses, $netProfit,
            $ownerPct, $ownerAmount, $workerPct, $workerAmount, $reservePct, $reserveAmount,
            $distDate, $payMethod, $notes, $userId, $now
        ]);

        $newId = $pdo->lastInsertId();
        setFlash('success', "Pembagian hasil nomor {$code} berhasil dicatat dan dieksekusi!");
        header('Location: print_bagi_hasil.php?id=' . $newId);
        exit;
    }
    header('Location: bagi_hasil.php');
    exit;
}

// Filter query
$filterYear = $_GET['year'] ?? date('Y');
$stmt = $pdo->prepare("SELECT * FROM profit_distributions WHERE YEAR(distribution_date) = ? ORDER BY distribution_date DESC, id DESC");
$stmt->execute([$filterYear]);
$distributions = $stmt->fetchAll();

// Lifetime / Annual Totals
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_count,
    COALESCE(SUM(net_profit), 0) as total_profit_shared,
    COALESCE(SUM(owner_amount), 0) as total_owner_payout,
    COALESCE(SUM(worker_amount), 0) as total_worker_payout,
    COALESCE(SUM(reserve_amount), 0) as total_reserve_saved
    FROM profit_distributions WHERE YEAR(distribution_date) = ?");
$stmt->execute([$filterYear]);
$stats = $stmt->fetch();

$curOwnerPct = (float)getSetting('profit_share_owner', '40');
$curWorkerPct = (float)getSetting('profit_share_worker', '40');
$curReservePct = (float)getSetting('profit_share_reserve', '20');

// Prefill values for modal
$defStart = $_GET['start_date'] ?? date('Y-m-01');
$defEnd = $_GET['end_date'] ?? date('Y-m-d');
$autoOpenModal = isset($_GET['action']) && $_GET['action'] === 'new';

$pageTitle = 'Bagi Hasil & Kas Usaha';
$pageSubtitle = 'Distribusi keuntungan bersih untuk Pemilik Modal, Tim Pekerja, dan Kas Cadangan Toko';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Toolbar -->
<div class="bg-white p-5 rounded-3xl border border-stone-200/90 shadow-sm mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-100 text-amber-900">
                    Sistem Mudharabah / Profit Sharing
                </span>
                <span class="text-xs text-stone-400 font-semibold">Tahun <?= htmlspecialchars($filterYear) ?></span>
            </div>
            <h2 class="text-lg font-black text-stone-900 serif-title">Manajemen Pembagian Hasil & Prive</h2>
            <p class="text-xs text-stone-500">Skema aktif saat ini: <strong><?= $curOwnerPct ?>%</strong> Pemilik Modal &bull; <strong><?= $curWorkerPct ?>%</strong> Pekerja &bull; <strong><?= $curReservePct ?>%</strong> Kas Usaha</p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <form method="GET" action="bagi_hasil.php" class="flex items-center gap-1.5 text-xs">
                <select name="year" onchange="this.form.submit()" class="px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl font-bold text-stone-800 outline-none">
                    <?php 
                    $currentYearNum = (int)date('Y');
                    for ($y = $currentYearNum; $y >= $currentYearNum - 3; $y--): ?>
                        <option value="<?= $y ?>" <?= $filterYear == $y ? 'selected' : '' ?>>Tahun <?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </form>

            <a href="pengaturan.php" class="px-3 py-2 bg-stone-100 hover:bg-stone-200 text-stone-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5 border border-stone-200">
                <i class="fa-solid fa-gear"></i>
                <span class="hidden sm:inline">Ubah Rasio</span>
            </a>

            <button onclick="openDistModal()" class="px-4 py-2 bg-stone-900 hover:bg-stone-800 text-amber-300 font-bold rounded-xl text-xs shadow-md transition flex items-center gap-2">
                <i class="fa-solid fa-hand-holding-dollar"></i>
                <span>+ Catat Pembagian Hasil</span>
            </button>
        </div>
    </div>
</div>

<!-- 4 Summary Metric Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- Card 1: Total Laba Bersih yang Telah Dibagikan -->
    <div class="bg-white p-5 rounded-2xl border border-stone-200 shadow-sm flex flex-col justify-between">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-bold text-stone-500 uppercase tracking-wider">Total Laba Dibagikan</span>
            <div class="w-8 h-8 rounded-lg bg-stone-100 text-stone-800 flex items-center justify-center text-xs font-bold">
                <i class="fa-solid fa-coins"></i>
            </div>
        </div>
        <div class="text-xl font-black text-stone-900"><?= formatRupiah($stats['total_profit_shared']) ?></div>
        <span class="text-[10px] text-stone-400 mt-2 block"><?= (int)$stats['total_count'] ?> kali pembagian di <?= $filterYear ?></span>
    </div>

    <!-- Card 2: Prive / Hak Pemilik Modal -->
    <div class="bg-blue-50/70 p-5 rounded-2xl border border-blue-200 shadow-sm flex flex-col justify-between">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-bold text-blue-900 uppercase tracking-wider">Prive Pemilik Modal</span>
            <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-800 flex items-center justify-center text-xs font-bold">
                <i class="fa-solid fa-user-tie"></i>
            </div>
        </div>
        <div class="text-xl font-black text-blue-950"><?= formatRupiah($stats['total_owner_payout']) ?></div>
        <span class="text-[10px] text-blue-700/80 mt-2 block">Hak pengembalian modal & dividen</span>
    </div>

    <!-- Card 3: Bagi Hasil Pekerja / Tim -->
    <div class="bg-emerald-50/70 p-5 rounded-2xl border border-emerald-200 shadow-sm flex flex-col justify-between">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-bold text-emerald-900 uppercase tracking-wider">Bagi Hasil Pekerja</span>
            <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center text-xs font-bold">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>
        <div class="text-xl font-black text-emerald-950"><?= formatRupiah($stats['total_worker_payout']) ?></div>
        <span class="text-[10px] text-emerald-700/80 mt-2 block">Insentif & pembagian untuk operator</span>
    </div>

    <!-- Card 4: Kas Usaha / Cadangan -->
    <div class="bg-amber-50/70 p-5 rounded-2xl border border-amber-200 shadow-sm flex flex-col justify-between">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-bold text-amber-950 uppercase tracking-wider">Kas Cadangan Usaha</span>
            <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-900 flex items-center justify-center text-xs font-bold">
                <i class="fa-solid fa-vault"></i>
            </div>
        </div>
        <div class="text-xl font-black text-amber-950"><?= formatRupiah($stats['total_reserve_saved']) ?></div>
        <span class="text-[10px] text-amber-800/80 mt-2 block">Dana cadangan tetap di kas toko</span>
    </div>
</div>

<!-- History Table -->
<div class="bg-white rounded-3xl border border-stone-200/90 shadow-sm overflow-hidden">
    <div class="p-4 sm:p-5 bg-stone-50 border-b border-stone-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <h3 class="font-black text-stone-900 text-sm">Riwayat Pembagian Keuntungan Usaha</h3>
            <p class="text-[11px] text-stone-500">Daftar transaksi pencairan bagi hasil yang telah dieksekusi secara resmi</p>
        </div>
        <span class="text-xs font-bold text-stone-600">
            Total <strong><?= count($distributions) ?></strong> Catatan
        </span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
            <thead>
                <tr class="bg-stone-100 text-stone-700 border-b border-stone-200">
                    <th class="py-3 px-4 font-bold">No. Dokumen</th>
                    <th class="py-3 px-4 font-bold">Tgl Cair</th>
                    <th class="py-3 px-4 font-bold">Periode Buku</th>
                    <th class="py-3 px-4 font-bold text-right">Laba Bersih</th>
                    <th class="py-3 px-4 font-bold text-right text-blue-900">Pemilik Modal</th>
                    <th class="py-3 px-4 font-bold text-right text-emerald-800">Pekerja / Tim</th>
                    <th class="py-3 px-4 font-bold text-right text-amber-900">Kas Usaha</th>
                    <th class="py-3 px-4 font-bold text-center">Metode</th>
                    <th class="py-3 px-4 font-bold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                <?php if (empty($distributions)): ?>
                    <tr>
                        <td colspan="9" class="py-12 text-center text-stone-400">
                            <i class="fa-solid fa-scale-balanced text-3xl mb-2 text-stone-300"></i>
                            <p class="font-bold text-stone-600">Belum Ada Riwayat Pembagian Hasil di Tahun <?= htmlspecialchars($filterYear) ?></p>
                            <p class="text-[11px] text-stone-400 mt-1 max-w-sm mx-auto">Klik tombol <strong>+ Catat Pembagian Hasil</strong> untuk menghitung keuntungan bersih dan mencatat distribusi untuk pemilik modal, pekerja & kas usaha.</p>
                            <button onclick="openDistModal()" class="mt-4 px-4 py-2 bg-stone-900 hover:bg-stone-800 text-amber-300 font-bold rounded-xl text-xs shadow inline-flex items-center gap-1.5">
                                <i class="fa-solid fa-plus"></i>
                                <span>Mulai Catat Pembagian</span>
                            </button>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($distributions as $d): ?>
                        <tr class="hover:bg-stone-50/80 transition">
                            <td class="py-3.5 px-4 font-mono font-bold text-stone-900">
                                <a href="print_bagi_hasil.php?id=<?= $d['id'] ?>" target="_blank" class="hover:underline flex items-center gap-1.5 text-stone-900">
                                    <i class="fa-solid fa-receipt text-amber-700"></i>
                                    <span><?= htmlspecialchars($d['distribution_code']) ?></span>
                                </a>
                            </td>
                            <td class="py-3.5 px-4 text-stone-600 whitespace-nowrap">
                                <?= formatDate($d['distribution_date']) ?>
                            </td>
                            <td class="py-3.5 px-4 text-stone-700 whitespace-nowrap font-medium">
                                <?= formatDate($d['period_start']) ?> s/d <?= formatDate($d['period_end']) ?>
                            </td>
                            <td class="py-3.5 px-4 text-right font-black text-stone-900">
                                <?= formatRupiah($d['net_profit']) ?>
                            </td>
                            <!-- Owner -->
                            <td class="py-3.5 px-4 text-right font-bold text-blue-900">
                                <div><?= formatRupiah($d['owner_amount']) ?></div>
                                <span class="text-[10px] text-blue-600 font-normal"><?= (float)$d['owner_percent'] ?>%</span>
                            </td>
                            <!-- Worker -->
                            <td class="py-3.5 px-4 text-right font-bold text-emerald-800">
                                <div><?= formatRupiah($d['worker_amount']) ?></div>
                                <span class="text-[10px] text-emerald-600 font-normal"><?= (float)$d['worker_percent'] ?>%</span>
                            </td>
                            <!-- Reserve -->
                            <td class="py-3.5 px-4 text-right font-bold text-amber-900">
                                <div><?= formatRupiah($d['reserve_amount']) ?></div>
                                <span class="text-[10px] text-amber-700 font-normal"><?= (float)$d['reserve_percent'] ?>%</span>
                            </td>
                            <!-- Method -->
                            <td class="py-3.5 px-4 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase <?= $d['payment_method'] === 'transfer' ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800' ?>">
                                    <?= htmlspecialchars($d['payment_method']) ?>
                                </span>
                            </td>
                            <!-- Actions -->
                            <td class="py-3.5 px-4 text-right space-x-1 whitespace-nowrap">
                                <a href="print_bagi_hasil.php?id=<?= $d['id'] ?>" target="_blank" class="p-1.5 bg-stone-100 hover:bg-stone-200 text-stone-800 rounded-lg text-xs font-bold inline-flex items-center gap-1" title="Cetak Voucher">
                                    <i class="fa-solid fa-print"></i>
                                    <span>Cetak</span>
                                </a>
                                <button onclick="confirmDelete('bagi_hasil.php?delete_id=<?= $d['id'] ?>', 'Yakin ingin menghapus catatan pembagian hasil ini?')" class="p-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg text-xs" title="Hapus">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Catat Pembagian Hasil Baru -->
<div id="distModal" class="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-2xl w-full p-6 sm:p-7 shadow-2xl border border-stone-200 my-8">
        <div class="flex items-center justify-between pb-4 border-b border-stone-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-amber-100 text-amber-900 flex items-center justify-center text-lg font-bold">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                </div>
                <div>
                    <h3 class="font-extrabold text-stone-900 text-base">Catat Pembagian Hasil Usaha</h3>
                    <p class="text-xs text-stone-500">Hitung otomatis laba bersih dan alokasikan ke Pemilik, Pekerja & Kas Usaha</p>
                </div>
            </div>
            <button onclick="closeDistModal()" class="text-stone-400 hover:text-stone-600 p-1.5 rounded-lg">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="bagi_hasil.php" id="formDist" class="space-y-4 pt-4 text-xs">
            <input type="hidden" name="save_distribution" value="1" />
            <input type="hidden" name="gross_revenue" id="modalGrossRevenue" value="0" />
            <input type="hidden" name="total_expenses" id="modalTotalExpenses" value="0" />
            <input type="hidden" name="net_profit" id="modalNetProfit" value="0" />
            <input type="hidden" name="owner_percent" id="modalOwnerPct" value="<?= $curOwnerPct ?>" />
            <input type="hidden" name="owner_amount" id="modalOwnerAmount" value="0" />
            <input type="hidden" name="worker_percent" id="modalWorkerPct" value="<?= $curWorkerPct ?>" />
            <input type="hidden" name="worker_amount" id="modalWorkerAmount" value="0" />
            <input type="hidden" name="reserve_percent" id="modalReservePct" value="<?= $curReservePct ?>" />
            <input type="hidden" name="reserve_amount" id="modalReserveAmount" value="0" />

            <!-- Periode Filter Section -->
            <div class="p-3.5 bg-stone-50 rounded-2xl border border-stone-200/80">
                <span class="block font-bold text-stone-700 mb-2 uppercase tracking-wide text-[10px]">Tentukan Periode Buku Keuangan:</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-stone-600 mb-1">Dari Tanggal *</label>
                        <input type="date" name="period_start" id="modalPeriodStart" value="<?= htmlspecialchars($defStart) ?>" required onchange="fetchCalculation()"
                               class="w-full px-3 py-2 bg-white border border-stone-300 rounded-xl outline-none font-bold text-stone-800" />
                    </div>
                    <div>
                        <label class="block font-semibold text-stone-600 mb-1">Sampai Tanggal *</label>
                        <input type="date" name="period_end" id="modalPeriodEnd" value="<?= htmlspecialchars($defEnd) ?>" required onchange="fetchCalculation()"
                               class="w-full px-3 py-2 bg-white border border-stone-300 rounded-xl outline-none font-bold text-stone-800" />
                    </div>
                </div>
            </div>

            <!-- Live Calculation Preview Container -->
            <div id="calcLoading" class="py-6 text-center text-stone-400 hidden">
                <i class="fa-solid fa-circle-notch fa-spin text-xl text-amber-600"></i>
                <p class="text-xs mt-1">Mengambil kalkulasi buku keuangan...</p>
            </div>

            <div id="calcResult" class="space-y-3">
                <!-- Summary Omset & Beban -->
                <div class="grid grid-cols-3 gap-2.5 text-center p-3 rounded-2xl bg-stone-100/70 border border-stone-200">
                    <div>
                        <span class="text-[10px] text-stone-500 uppercase font-bold block">Total Omzet</span>
                        <span class="font-black text-stone-800 text-xs sm:text-sm" id="lblGrossRevenue">Rp 0</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-stone-500 uppercase font-bold block">Total Beban</span>
                        <span class="font-black text-rose-600 text-xs sm:text-sm" id="lblTotalExpenses">Rp 0</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-stone-500 uppercase font-bold block">Laba Bersih</span>
                        <span class="font-black text-emerald-800 text-xs sm:text-sm" id="lblNetProfit">Rp 0</span>
                    </div>
                </div>

                <!-- 3 Allocation Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <!-- Owner -->
                    <div class="p-3.5 rounded-2xl bg-blue-50/80 border border-blue-200">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-blue-900 text-xs flex items-center gap-1">
                                <i class="fa-solid fa-user-tie text-blue-600"></i>
                                <span>Pemilik Modal</span>
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-blue-200 text-blue-900" id="lblOwnerPct"><?= $curOwnerPct ?>%</span>
                        </div>
                        <div class="text-base font-black text-blue-950 mt-1" id="lblOwnerAmount">Rp 0</div>
                        <span class="text-[10px] text-blue-700/80 block mt-0.5">Prive / modal investasi</span>
                    </div>

                    <!-- Worker -->
                    <div class="p-3.5 rounded-2xl bg-emerald-50/80 border border-emerald-200">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-emerald-900 text-xs flex items-center gap-1">
                                <i class="fa-solid fa-users text-emerald-600"></i>
                                <span>Pekerja / Tim</span>
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-200 text-emerald-900" id="lblWorkerPct"><?= $curWorkerPct ?>%</span>
                        </div>
                        <div class="text-base font-black text-emerald-950 mt-1" id="lblWorkerAmount">Rp 0</div>
                        <span class="text-[10px] text-emerald-700/80 block mt-0.5">Hak kerja & operator</span>
                    </div>

                    <!-- Reserve -->
                    <div class="p-3.5 rounded-2xl bg-amber-50/80 border border-amber-200">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-amber-950 text-xs flex items-center gap-1">
                                <i class="fa-solid fa-vault text-amber-700"></i>
                                <span>Kas Usaha</span>
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-200 text-amber-950" id="lblReservePct"><?= $curReservePct ?>%</span>
                        </div>
                        <div class="text-base font-black text-amber-950 mt-1" id="lblReserveAmount">Rp 0</div>
                        <span class="text-[10px] text-amber-800/80 block mt-0.5">Cadangan kas toko</span>
                    </div>
                </div>
            </div>

            <!-- Execution Date & Method & Notes -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Tanggal Eksekusi / Pencairan *</label>
                    <input type="date" name="distribution_date" value="<?= date('Y-m-d') ?>" required
                           class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none font-bold" />
                </div>
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Metode Pencairan Prive/Pekerja</label>
                    <select name="payment_method" class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none font-bold">
                        <option value="tunai">Tunai (Cash dari Kasir)</option>
                        <option value="transfer">Transfer Bank (Rekening)</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block font-bold text-stone-700 mb-1">Catatan Tambahan (Opsional)</label>
                    <input type="text" name="notes" placeholder="Contoh: Pembagian hasil bulan Oktober 2026 diserahkan tunai"
                           class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none" />
                </div>
            </div>

            <div class="flex justify-end gap-2.5 pt-3 border-t border-stone-100">
                <button type="button" onclick="closeDistModal()" class="px-4 py-2 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold rounded-xl transition">Batal</button>
                <button type="submit" id="btnSubmitDist" class="px-5 py-2.5 bg-stone-900 hover:bg-stone-800 text-amber-300 font-bold rounded-xl shadow-md transition flex items-center gap-2">
                    <i class="fa-solid fa-check"></i>
                    <span>Eksekusi & Simpan Pembagian</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openDistModal() {
        document.getElementById('distModal').classList.remove('hidden');
        fetchCalculation();
    }

    function closeDistModal() {
        document.getElementById('distModal').classList.add('hidden');
    }

    function formatRupiahJs(number) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(number);
    }

    function fetchCalculation() {
        const s = document.getElementById('modalPeriodStart').value;
        const e = document.getElementById('modalPeriodEnd').value;
        if (!s || !e) return;

        document.getElementById('calcLoading').classList.remove('hidden');
        document.getElementById('calcResult').classList.add('opacity-40');

        fetch(`bagi_hasil.php?ajax=calc_profit&start_date=${encodeURIComponent(s)}&end_date=${encodeURIComponent(e)}`)
            .then(res => res.json())
            .then(data => {
                document.getElementById('calcLoading').classList.add('hidden');
                document.getElementById('calcResult').classList.remove('opacity-40');

                // Hidden values
                document.getElementById('modalGrossRevenue').value = data.gross_revenue;
                document.getElementById('modalTotalExpenses').value = data.total_expenses;
                document.getElementById('modalNetProfit').value = data.net_profit;

                document.getElementById('modalOwnerPct').value = data.owner_pct;
                document.getElementById('modalOwnerAmount').value = data.owner_amount;

                document.getElementById('modalWorkerPct').value = data.worker_pct;
                document.getElementById('modalWorkerAmount').value = data.worker_amount;

                document.getElementById('modalReservePct').value = data.reserve_pct;
                document.getElementById('modalReserveAmount').value = data.reserve_amount;

                // Labels
                document.getElementById('lblGrossRevenue').innerText = formatRupiahJs(data.gross_revenue);
                document.getElementById('lblTotalExpenses').innerText = formatRupiahJs(data.total_expenses);
                document.getElementById('lblNetProfit').innerText = formatRupiahJs(data.net_profit);

                document.getElementById('lblOwnerPct').innerText = `${data.owner_pct}%`;
                document.getElementById('lblOwnerAmount').innerText = formatRupiahJs(data.owner_amount);

                document.getElementById('lblWorkerPct').innerText = `${data.worker_pct}%`;
                document.getElementById('lblWorkerAmount').innerText = formatRupiahJs(data.worker_amount);

                document.getElementById('lblReservePct').innerText = `${data.reserve_pct}%`;
                document.getElementById('lblReserveAmount').innerText = formatRupiahJs(data.reserve_amount);

                const btn = document.getElementById('btnSubmitDist');
                if (data.net_profit <= 0) {
                    btn.disabled = true;
                    btn.classList.add('opacity-50', 'cursor-not-allowed');
                    btn.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> <span>Laba Bersih Kosong / Negatif</span>';
                } else {
                    btn.disabled = false;
                    btn.classList.remove('opacity-50', 'cursor-not-allowed');
                    btn.innerHTML = '<i class="fa-solid fa-check"></i> <span>Eksekusi & Simpan Pembagian</span>';
                }
            })
            .catch(err => {
                console.error(err);
                document.getElementById('calcLoading').classList.add('hidden');
                document.getElementById('calcResult').classList.remove('opacity-40');
            });
    }

    <?php if ($autoOpenModal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            openDistModal();
        });
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
