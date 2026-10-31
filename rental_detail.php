<?php
// rental_detail.php
require_once __DIR__ . '/config/app.php';
requireAuth();

$pdo = Database::getConnection();
$rentalId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT r.*, a.name as acrylic_name, a.code as acrylic_code, a.size, a.stand_type, u.name as staff_name
    FROM acrylic_rentals r
    JOIN acrylic_items a ON r.acrylic_id = a.id
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.id = ?");
$stmt->execute([$rentalId]);
$rental = $stmt->fetch();

if (!$rental) {
    setFlash('error', 'Data penyewaan akrilik tidak ditemukan.');
    header('Location: rental_akrilik.php');
    exit;
}

$defaultFinePerDay = (float)getSetting('fine_per_day', '25000');

// Handle Return Process POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_return'])) {
    $actualReturnDate = $_POST['actual_return_date'] ?: date('Y-m-d');
    $lateFine = (float)($_POST['late_fine'] ?? 0);
    $depositReturned = isset($_POST['deposit_returned']) ? 1 : 0;
    $notes = trim($_POST['notes'] ?? '');

    try {
        $pdo->beginTransaction();

        $upd = $pdo->prepare("UPDATE acrylic_rentals SET 
            status = 'returned',
            actual_return_date = ?,
            late_fine = ?,
            deposit_returned = ?,
            notes = CONCAT(IFNULL(notes, ''), '\n[PENGEMBALIAN]: ', ?)
            WHERE id = ?");
        
        $upd->execute([
            $actualReturnDate,
            $lateFine,
            $depositReturned,
            $notes ?: 'Papan telah diterima kembali dalam kondisi baik.',
            $rentalId
        ]);

        // Set acrylic status back to available
        $pdo->prepare("UPDATE acrylic_items SET status = 'available' WHERE id = ?")->execute([$rental['acrylic_id']]);

        $pdo->commit();
        setFlash('success', 'Pengembalian papan akrilik selesai! Unit kini tersedia kembali di katalog.');
        header("Location: rental_detail.php?id={$rentalId}");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('error', 'Gagal memproses pengembalian: ' . $e->getMessage());
    }
}

// Calculate overdue days if still active
$isLate = false;
$daysLate = 0;
$suggestedFine = 0;
if ($rental['status'] !== 'returned' && $rental['status'] !== 'cancelled') {
    $todayTs = strtotime(date('Y-m-d'));
    $endTs = strtotime($rental['end_date']);
    if ($todayTs > $endTs) {
        $isLate = true;
        $daysLate = (int)ceil(($todayTs - $endTs) / 86400);
        $suggestedFine = $daysLate * $defaultFinePerDay;
    }
}

$waReminderMsg = "Halo kak {$rental['customer_name']},\nKami dari " . getSetting('shop_name', 'Aquiris Print') . " ingin mengonfirmasi jadwal pengembalian sewa papan akrilik *{$rental['acrylic_name']}*.\n\nKode Sewa: *{$rental['rental_code']}*\nBatas Pengembalian: *" . formatDate($rental['end_date']) . "*\n\nMohon konfirmasinya ya kak. Terima kasih banyak! 🙏";
$waReminderUrl = buildWaLink($rental['customer_phone'], $waReminderMsg);

$pageTitle = 'Rincian Sewa & Pengembalian Akrilik';
$pageSubtitle = 'Rincian jadwal sewa, kondisi fisik, uang jaminan & denda keterlambatan';
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <!-- Top Action Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white p-4 sm:p-5 rounded-3xl border border-stone-200/90 shadow-sm">
        <div class="flex items-center gap-3">
            <a href="rental_akrilik.php" class="p-2.5 bg-stone-100 hover:bg-stone-200 text-stone-700 rounded-2xl transition text-xs">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h3 class="font-black text-stone-900 text-base flex items-center gap-2 font-mono">
                    <span><?= htmlspecialchars($rental['rental_code']) ?></span>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase font-sans <?= $rental['status'] === 'returned' ? 'bg-emerald-100 text-emerald-800' : ($isLate ? 'bg-rose-100 text-rose-800 animate-pulse' : 'bg-amber-100 text-amber-800') ?>">
                        <?= $rental['status'] === 'returned' ? 'Selesai / Dikembalikan' : ($isLate ? 'Terlambat (' . $daysLate . ' Hari)' : 'Aktif Disewa') ?>
                    </span>
                </h3>
                <p class="text-[11px] text-stone-400">Didaftarkan pada <?= formatDate($rental['created_at'], true) ?> oleh <?= htmlspecialchars($rental['staff_name'] ?? 'Admin') ?></p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="<?= $waReminderUrl ?>" target="_blank" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center gap-1.5">
                <i class="fa-brands fa-whatsapp text-sm"></i>
                <span>Chat WhatsApp</span>
            </a>
            <a href="print_sewa.php?id=<?= $rental['id'] ?>" target="_blank" class="px-3.5 py-2 bg-stone-900 hover:bg-stone-800 text-amber-300 rounded-xl text-xs font-bold shadow-sm transition flex items-center gap-1.5">
                <i class="fa-solid fa-print"></i>
                <span>Cetak Perjanjian</span>
            </a>
        </div>
    </div>

    <!-- Overdue Alert Banner if Late -->
    <?php if ($isLate): ?>
        <div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl flex items-start gap-3">
            <i class="fa-solid fa-triangle-exclamation text-rose-600 text-lg mt-0.5"></i>
            <div class="text-xs text-rose-900">
                <h4 class="font-extrabold text-xs text-rose-950">Perhatian: Waktu Sewa Lewat Tempo</h4>
                <p class="mt-0.5">Papan akrilik terlambat dikembalikan selama <strong><?= $daysLate ?> hari</strong> (Batas kembali: <?= formatDate($rental['end_date']) ?>). Estimasi denda: <strong><?= formatRupiah($suggestedFine) ?></strong> (Tarif: <?= formatRupiah($defaultFinePerDay) ?>/hari).</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Contract Details Card -->
    <div class="bg-white rounded-3xl border border-stone-200/90 shadow-sm p-6 sm:p-7 text-xs space-y-6">
        <!-- 2 Column: Item Info vs Customer Info -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-6 border-b border-stone-200">
            <!-- Left: Rental Item Info -->
            <div class="space-y-3">
                <h4 class="font-extrabold text-stone-800 text-xs flex items-center gap-2 uppercase tracking-wider text-amber-700">
                    <i class="fa-solid fa-shapes"></i>
                    <span>Informasi Papan Akrilik</span>
                </h4>
                <div class="bg-stone-50 p-4 rounded-2xl border border-stone-200 space-y-2">
                    <div class="font-bold text-sm text-stone-900"><?= htmlspecialchars($rental['acrylic_name']) ?></div>
                    <div class="grid grid-cols-2 gap-2 text-[11px] text-stone-600">
                        <div>
                            <span class="text-stone-400 block">Kode Barang:</span>
                            <span class="font-mono font-bold text-stone-800"><?= htmlspecialchars($rental['acrylic_code']) ?></span>
                        </div>
                        <div>
                            <span class="text-stone-400 block">Ukuran / Dimensi:</span>
                            <span class="font-bold text-stone-800"><?= htmlspecialchars($rental['size']) ?></span>
                        </div>
                        <div class="col-span-2">
                            <span class="text-stone-400 block">Tipe Standing / Easel:</span>
                            <span class="font-bold text-stone-800"><?= htmlspecialchars($rental['stand_type'] ?: 'Standard') ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Customer Info -->
            <div class="space-y-3">
                <h4 class="font-extrabold text-stone-800 text-xs flex items-center gap-2 uppercase tracking-wider text-stone-700">
                    <i class="fa-solid fa-user-check"></i>
                    <span>Informasi Penyewa & Acara</span>
                </h4>
                <div class="bg-stone-50 p-4 rounded-2xl border border-stone-200 space-y-2 text-[11px]">
                    <div>
                        <span class="text-stone-400 block">Nama Penyewa:</span>
                        <span class="font-bold text-stone-900 text-xs"><?= htmlspecialchars($rental['customer_name']) ?></span>
                    </div>
                    <div>
                        <span class="text-stone-400 block">WhatsApp:</span>
                        <span class="font-bold text-emerald-700"><?= htmlspecialchars($rental['customer_phone']) ?></span>
                    </div>
                    <div>
                        <span class="text-stone-400 block">Acara:</span>
                        <span class="font-bold text-stone-800"><?= htmlspecialchars($rental['event_name'] ?: 'Acara Pribadi') ?></span>
                        <?php if ($rental['event_date']): ?>
                            <span class="text-[10px] text-stone-500 block">Hari H: <?= formatDate($rental['event_date']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schedule & Timeline -->
        <div class="pb-6 border-b border-stone-200">
            <h4 class="font-extrabold text-stone-800 text-xs mb-3 uppercase tracking-wider text-stone-700 flex items-center gap-2">
                <i class="fa-solid fa-calendar-check text-stone-500"></i>
                <span>Jadwal Pengambilan & Pengembalian</span>
            </h4>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="p-3.5 bg-stone-50 rounded-2xl border border-stone-200">
                    <span class="text-[10px] text-stone-400 block uppercase font-bold">Mulai Sewa (Ambil)</span>
                    <span class="font-bold text-stone-900 text-xs mt-0.5 block"><?= formatDate($rental['start_date']) ?></span>
                </div>
                <div class="p-3.5 bg-stone-50 rounded-2xl border border-stone-200">
                    <span class="text-[10px] text-stone-400 block uppercase font-bold">Batas Pengembalian</span>
                    <span class="font-bold text-stone-900 text-xs mt-0.5 block"><?= formatDate($rental['end_date']) ?></span>
                </div>
                <div class="p-3.5 bg-stone-50 rounded-2xl border border-stone-200">
                    <span class="text-[10px] text-stone-400 block uppercase font-bold">Durasi Disepakati</span>
                    <span class="font-bold text-stone-900 text-xs mt-0.5 block"><?= (int)$rental['duration_days'] ?> Hari</span>
                </div>
                <div class="p-3.5 bg-stone-50 rounded-2xl border border-stone-200">
                    <span class="text-[10px] text-stone-400 block uppercase font-bold">Tanggal Riil Kembali</span>
                    <span class="font-bold text-stone-900 text-xs mt-0.5 block"><?= $rental['actual_return_date'] ? formatDate($rental['actual_return_date']) : '<em class="text-amber-700 font-normal">Belum Kembali</em>' ?></span>
                </div>
            </div>
        </div>

        <!-- Financial Breakdown -->
        <div class="pb-6 border-b border-stone-200">
            <h4 class="font-extrabold text-stone-800 text-xs mb-3 uppercase tracking-wider text-stone-700 flex items-center gap-2">
                <i class="fa-solid fa-file-invoice-dollar text-stone-500"></i>
                <span>Rincian Biaya Sewa & Uang Jaminan</span>
            </h4>
            <div class="bg-stone-50 p-4 rounded-2xl border border-stone-200 space-y-2">
                <div class="flex justify-between items-center">
                    <span class="text-stone-600">Biaya Sewa Papan (<?= (int)$rental['duration_days'] ?> Hari):</span>
                    <span class="font-bold text-stone-800"><?= formatRupiah($rental['rental_price']) ?></span>
                </div>
                <?php if ($rental['custom_sticker_fee'] > 0): ?>
                    <div class="flex justify-between items-center">
                        <span class="text-stone-600">Jasa Cetak Cutting Stiker Tulisan:</span>
                        <span class="font-bold text-stone-800"><?= formatRupiah($rental['custom_sticker_fee']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($rental['late_fine'] > 0): ?>
                    <div class="flex justify-between items-center text-rose-600 font-bold">
                        <span>Denda Keterlambatan:</span>
                        <span>+ <?= formatRupiah($rental['late_fine']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="flex justify-between items-center pt-2 border-t border-stone-200 text-sm font-black">
                    <span class="text-stone-900">Total Pendapatan Sewa Toko:</span>
                    <span class="text-amber-800"><?= formatRupiah($rental['total_cost'] + $rental['late_fine']) ?></span>
                </div>

                <!-- Deposit Row -->
                <div class="flex justify-between items-center pt-3 border-t border-dashed border-stone-300 text-xs">
                    <div>
                        <span class="font-bold text-amber-900">Uang Jaminan (Deposit):</span>
                        <span class="text-[10px] text-stone-500 block"><?= $rental['deposit_returned'] ? 'Status: Sudah Dikembalikan ke Penyewa' : 'Status: Masih Dipegang Toko' ?></span>
                    </div>
                    <span class="font-black text-amber-800 text-sm"><?= formatRupiah($rental['deposit_amount']) ?></span>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <?php if (!empty($rental['notes'])): ?>
            <div>
                <h4 class="font-bold text-stone-700 text-xs mb-1">Catatan Tambahan / Desain Tulisan Stiker:</h4>
                <div class="bg-stone-50 p-3.5 rounded-2xl border border-stone-200 text-stone-700 whitespace-pre-line text-xs font-mono">
                    <?= htmlspecialchars($rental['notes']) ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Return Process Form (Only if not returned yet) -->
    <?php if ($rental['status'] !== 'returned' && $rental['status'] !== 'cancelled'): ?>
        <div class="bg-white rounded-3xl border-2 border-amber-500/70 shadow-sm p-6 sm:p-7 text-xs">
            <div class="flex items-center gap-3 pb-4 border-b border-stone-100 mb-4">
                <div class="w-10 h-10 rounded-2xl bg-amber-100 text-amber-900 flex items-center justify-center text-base font-bold">
                    <i class="fa-solid fa-box-open"></i>
                </div>
                <div>
                    <h4 class="font-black text-stone-900 text-sm">Proses Pengembalian Papan Akrilik</h4>
                    <p class="text-stone-500 text-[11px]">Cek kondisi fisik akrilik & standing, periksa denda, dan kembalikan deposit jaminan</p>
                </div>
            </div>

            <form id="returnForm" method="POST" action="rental_detail.php?id=<?= $rental['id'] ?>" class="space-y-4">
                <input type="hidden" name="process_return" value="1" />

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Tanggal Riil Pengembalian *</label>
                        <input type="date" name="actual_return_date" value="<?= date('Y-m-d') ?>" required
                               class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none font-bold text-xs" />
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Denda Keterlambatan / Kerusakan (Rp)</label>
                        <input type="number" name="late_fine" value="<?= $suggestedFine ?>" min="0"
                               class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none font-bold text-rose-600 text-xs" />
                        <span class="text-[10px] text-stone-400 mt-1 block">Tarif denda standar: <?= formatRupiah($defaultFinePerDay) ?>/hari keterlambatan</span>
                    </div>
                </div>

                <div class="p-4 bg-amber-50/80 border border-amber-200 rounded-2xl">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="deposit_returned" value="1" checked class="mt-1 text-emerald-600 focus:ring-emerald-500 rounded" />
                        <div>
                            <span class="font-bold text-amber-950 block">Kembalikan Uang Jaminan (Deposit) sebesar <?= formatRupiah($rental['deposit_amount']) ?></span>
                            <span class="text-[11px] text-amber-800">Centang jika papan akrilik dan stand dikembalikan dalam kondisi mulus tanpa pecah/retak.</span>
                        </div>
                    </label>
                </div>

                <div>
                    <label class="block font-bold text-stone-700 mb-1">Catatan Kondisi Barang Saat Kembali</label>
                    <input type="text" name="notes" placeholder="Contoh: Akrilik dan easel lengkap dan bersih tanpa goresan."
                           class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none text-xs" />
                </div>

                <div class="pt-2 flex justify-end">
                    <button type="button" onclick="confirmReturnProcess(event)"
                            class="px-6 py-2.5 bg-stone-900 hover:bg-stone-800 text-amber-300 font-bold rounded-2xl shadow-md transition flex items-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-check"></i>
                        <span>Selesaikan Pengembalian Papan</span>
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
function confirmReturnProcess(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Selesaikan Pengembalian?',
        text: 'Pastikan fisik papan akrilik, easel kayu, atau tripod besi telah dicek lengkap.',
        icon: 'question',
        iconColor: '#d97706',
        showCancelButton: true,
        confirmButtonColor: '#1c1917',
        cancelButtonColor: '#78716c',
        confirmButtonText: '<i class="fa-solid fa-check mr-1.5"></i> Ya, Selesai Pengembalian',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        customClass: {
            popup: 'rounded-3xl border border-stone-200/90 shadow-2xl p-6 font-sans bg-white',
            title: 'text-base font-extrabold text-stone-900',
            htmlContainer: 'text-xs text-stone-500 font-medium leading-relaxed mt-2',
            confirmButton: 'rounded-xl text-xs font-bold px-5 py-2.5 bg-stone-900 text-amber-300 shadow-sm border-0',
            cancelButton: 'rounded-xl text-xs font-bold px-4 py-2.5 bg-stone-100 hover:bg-stone-200 text-stone-700 border-0'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('returnForm').submit();
        }
    });
    return false;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
