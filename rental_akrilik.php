<?php
// rental_akrilik.php
require_once __DIR__ . '/config/app.php';
requireAuth();

$pdo = Database::getConnection();

// Handle cancellation or deletion
if (isset($_GET['delete'])) {
    if (!isAdmin()) {
        setFlash('error', 'Hanya administrator yang memiliki izin untuk menghapus data sewa.');
        header('Location: rental_akrilik.php');
        exit;
    }
    $delId = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT acrylic_id FROM acrylic_rentals WHERE id = ?");
    $stmt->execute([$delId]);
    $acrId = $stmt->fetchColumn();

    if ($acrId) {
        $pdo->prepare("UPDATE acrylic_items SET status = 'available' WHERE id = ?")->execute([$acrId]);
    }
    $pdo->prepare("DELETE FROM acrylic_rentals WHERE id = ?")->execute([$delId]);
    setFlash('success', 'Data penyewaan berhasil dihapus.');
    header('Location: rental_akrilik.php');
    exit;
}

// Auto update status to late if end_date has passed and status is still active
$today = date('Y-m-d');
$pdo->exec("UPDATE acrylic_rentals SET status = 'late' WHERE status = 'active' AND end_date < '$today'");

$pageTitle = 'Penyewaan Papan Akrilik & Wedding Sign';
$pageSubtitle = 'Jadwal sewa welcome board, standing tripod, easel kayu & status pengembalian';
require_once __DIR__ . '/includes/header.php';

// Filter parameters
$status = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT r.*, a.name as acrylic_name, a.code as acrylic_code, a.size, a.stand_type
        FROM acrylic_rentals r
        JOIN acrylic_items a ON r.acrylic_id = a.id
        WHERE 1=1";
$params = [];

if ($status !== 'all') {
    $sql .= " AND r.status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $sql .= " AND (r.rental_code LIKE ? OR r.customer_name LIKE ? OR r.customer_phone LIKE ? OR r.event_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY r.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rentals = $stmt->fetchAll();

// Quick counters
$cntActive = $pdo->query("SELECT COUNT(*) FROM acrylic_rentals WHERE status = 'active'")->fetchColumn();
$cntLate = $pdo->query("SELECT COUNT(*) FROM acrylic_rentals WHERE status = 'late'")->fetchColumn();
$cntBooked = $pdo->query("SELECT COUNT(*) FROM acrylic_rentals WHERE status = 'booked'")->fetchColumn();
$cntReturned = $pdo->query("SELECT COUNT(*) FROM acrylic_rentals WHERE status = 'returned'")->fetchColumn();
?>

<!-- Status KPI Row with Warm Tactile Cards -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <a href="rental_akrilik.php?status=active" class="p-4 bg-white rounded-2xl border border-stone-200/90 shadow-sm hover:border-amber-400 transition flex items-center justify-between">
        <div>
            <span class="text-[11px] font-bold text-stone-500 uppercase">Sedang Disewa</span>
            <div class="text-xl font-black text-amber-700"><?= $cntActive ?></div>
        </div>
        <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center text-sm font-bold">
            <i class="fa-solid fa-truck-moving"></i>
        </div>
    </a>
    <a href="rental_akrilik.php?status=late" class="p-4 bg-white rounded-2xl border border-stone-200/90 shadow-sm hover:border-rose-400 transition flex items-center justify-between">
        <div>
            <span class="text-[11px] font-bold text-stone-500 uppercase">Terlambat Kembali</span>
            <div class="text-xl font-black text-rose-600"><?= $cntLate ?></div>
        </div>
        <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-sm font-bold">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
    </a>
    <a href="rental_akrilik.php?status=booked" class="p-4 bg-white rounded-2xl border border-stone-200/90 shadow-sm hover:border-stone-400 transition flex items-center justify-between">
        <div>
            <span class="text-[11px] font-bold text-stone-500 uppercase">Dipesan / Booking</span>
            <div class="text-xl font-black text-stone-800"><?= $cntBooked ?></div>
        </div>
        <div class="w-9 h-9 rounded-xl bg-stone-100 text-stone-700 flex items-center justify-center text-sm font-bold">
            <i class="fa-solid fa-calendar-check"></i>
        </div>
    </a>
    <a href="rental_akrilik.php?status=returned" class="p-4 bg-white rounded-2xl border border-stone-200/90 shadow-sm hover:border-emerald-400 transition flex items-center justify-between">
        <div>
            <span class="text-[11px] font-bold text-stone-500 uppercase">Selesai / Kembali</span>
            <div class="text-xl font-black text-emerald-700"><?= $cntReturned ?></div>
        </div>
        <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-sm font-bold">
            <i class="fa-solid fa-circle-check"></i>
        </div>
    </a>
</div>

<!-- Filter & Search Bar -->
<div class="bg-white p-4 rounded-2xl border border-stone-200/90 shadow-sm mb-6 flex flex-col md:flex-row gap-3 items-center justify-between">
    <div class="flex items-center gap-1.5 overflow-x-auto w-full md:w-auto text-xs pb-1 md:pb-0">
        <a href="rental_akrilik.php" class="px-3.5 py-1.5 rounded-xl font-bold transition whitespace-nowrap <?= $status === 'all' ? 'bg-stone-900 text-amber-300' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">Semua</a>
        <a href="rental_akrilik.php?status=active" class="px-3.5 py-1.5 rounded-xl font-bold transition whitespace-nowrap <?= $status === 'active' ? 'bg-amber-600 text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">Aktif Disewa</a>
        <a href="rental_akrilik.php?status=late" class="px-3.5 py-1.5 rounded-xl font-bold transition whitespace-nowrap <?= $status === 'late' ? 'bg-rose-600 text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">Terlambat</a>
        <a href="rental_akrilik.php?status=booked" class="px-3.5 py-1.5 rounded-xl font-bold transition whitespace-nowrap <?= $status === 'booked' ? 'bg-stone-800 text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">Booking</a>
        <a href="rental_akrilik.php?status=returned" class="px-3.5 py-1.5 rounded-xl font-bold transition whitespace-nowrap <?= $status === 'returned' ? 'bg-emerald-600 text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">Selesai</a>
    </div>

    <div class="flex items-center gap-2 w-full md:w-auto">
        <form method="GET" action="rental_akrilik.php" class="relative flex-1 md:w-64">
            <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>" />
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama, acara, WA..." 
                   class="w-full pl-8 pr-3 py-1.5 bg-stone-50 border border-stone-200 rounded-xl text-xs outline-none focus:ring-1 focus:ring-amber-500 font-medium" />
            <i class="fa-solid fa-magnifying-glass text-stone-400 absolute left-2.5 top-2.5 text-xs"></i>
        </form>
        <a href="rental_tambah.php" class="px-3.5 py-1.5 bg-stone-900 hover:bg-stone-800 text-amber-300 rounded-xl text-xs font-bold transition flex items-center gap-1.5 whitespace-nowrap">
            <i class="fa-solid fa-plus"></i>
            <span>Sewa Baru</span>
        </a>
    </div>
</div>

<!-- Rentals Table -->
<div class="bg-white rounded-2xl border border-stone-200/90 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
            <thead>
                <tr class="bg-stone-50 text-stone-600 border-b border-stone-200">
                    <th class="py-3.5 px-4 font-bold">Kode Sewa</th>
                    <th class="py-3.5 px-4 font-bold">Penyewa & Acara</th>
                    <th class="py-3.5 px-4 font-bold">Papan Akrilik & Stand</th>
                    <th class="py-3.5 px-4 font-bold">Jadwal Sewa</th>
                    <th class="py-3.5 px-4 font-bold text-right">Total Biaya</th>
                    <th class="py-3.5 px-4 font-bold text-center">Status</th>
                    <th class="py-3.5 px-4 font-bold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                <?php if (empty($rentals)): ?>
                    <tr>
                        <td colspan="7" class="py-10 text-center text-stone-400">
                            <i class="fa-solid fa-shapes text-3xl mb-2 text-stone-300"></i>
                            <p>Tidak ada data penyewaan papan akrilik.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rentals as $r): 
                        $waMsg = "Halo kak {$r['customer_name']}, kami dari " . getSetting('shop_name', 'Aquiris Print') . " ingin mengonfirmasi penyewaan papan akrilik *{$r['acrylic_name']}* ({$r['rental_code']}). Batas waktu pengembalian adalah tanggal " . formatDate($r['end_date']) . ". Semoga acaranya lancar ya kak! 🙏";
                        $waUrl = buildWaLink($r['customer_phone'], $waMsg);
                    ?>
                        <tr class="hover:bg-stone-50/80 transition">
                            <td class="py-3.5 px-4">
                                <a href="rental_detail.php?id=<?= $r['id'] ?>" class="font-bold text-stone-900 hover:text-amber-700 hover:underline block font-mono">
                                    <?= htmlspecialchars($r['rental_code']) ?>
                                </a>
                                <span class="text-[10px] text-stone-400"><?= date('d/m/Y', strtotime($r['created_at'])) ?></span>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-stone-800"><?= htmlspecialchars($r['customer_name']) ?></div>
                                <div class="text-[11px] text-stone-500 font-medium"><?= htmlspecialchars($r['event_name'] ?: 'Acara Personal') ?></div>
                                <span class="text-[10px] text-stone-400 flex items-center gap-1 mt-0.5">
                                    <i class="fa-solid fa-phone text-[9px]"></i> <?= htmlspecialchars($r['customer_phone']) ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="font-bold text-stone-700 block"><?= htmlspecialchars($r['acrylic_name']) ?></span>
                                <span class="text-[10px] text-stone-400"><?= htmlspecialchars($r['stand_type'] ?: 'Standard') ?> • <?= htmlspecialchars($r['size']) ?></span>
                            </td>
                            <td class="py-3.5 px-4">
                                <div><?= formatDate($r['start_date']) ?> s/d <strong><?= formatDate($r['end_date']) ?></strong></div>
                                <span class="text-[10px] text-stone-500 font-medium"><?= (int)$r['duration_days'] ?> Hari sewa</span>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <span class="font-bold text-stone-900 block"><?= formatRupiah($r['total_cost'] + $r['late_fine']) ?></span>
                                <span class="text-[10px] text-stone-400">Jaminan: <?= formatRupiah($r['deposit_amount']) ?></span>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <?php
                                $badge = 'bg-stone-100 text-stone-700';
                                $lbl = $r['status'];
                                if ($r['status'] === 'active') { $badge = 'bg-amber-100 text-amber-800 border border-amber-200'; $lbl = 'Sedang Disewa'; }
                                elseif ($r['status'] === 'late') { $badge = 'bg-rose-100 text-rose-800 border border-rose-200 font-black animate-pulse'; $lbl = 'Terlambat!'; }
                                elseif ($r['status'] === 'booked') { $badge = 'bg-stone-100 text-stone-800 border border-stone-200'; $lbl = 'Dipesan (Booked)'; }
                                elseif ($r['status'] === 'returned') { $badge = 'bg-emerald-100 text-emerald-800 border border-emerald-200'; $lbl = 'Selesai / Kembali'; }
                                elseif ($r['status'] === 'cancelled') { $badge = 'bg-stone-100 text-stone-500'; $lbl = 'Dibatalkan'; }
                                ?>
                                <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold <?= $badge ?>">
                                    <?= $lbl ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right space-x-1 whitespace-nowrap">
                                <a href="<?= $waUrl ?>" target="_blank" class="p-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg inline-block text-xs" title="Chat WhatsApp Pelanggan">
                                    <i class="fa-brands fa-whatsapp"></i>
                                </a>
                                <a href="print_sewa.php?id=<?= $r['id'] ?>" target="_blank" class="p-1.5 bg-stone-100 hover:bg-stone-200 text-stone-700 rounded-lg inline-block text-xs" title="Cetak Surat Perjanjian / Bukti Sewa">
                                    <i class="fa-solid fa-file-contract"></i>
                                </a>
                                <a href="rental_detail.php?id=<?= $r['id'] ?>" class="p-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 rounded-lg inline-block text-xs font-bold" title="Detail & Pengembalian">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <?php if (isAdmin()): ?>
                                <button onclick="confirmDelete('rental_akrilik.php?delete=<?= $r['id'] ?>', 'Yakin ingin menghapus data sewa ini?')" class="p-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg inline-block text-xs" title="Hapus Data">
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
