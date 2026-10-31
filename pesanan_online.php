<?php
// pesanan_online.php
require_once __DIR__ . '/config/app.php';
requireAuth();

$pdo = Database::getConnection();

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId = (int)$_POST['order_id'];
    $newStatus = $_POST['new_status'];
    $allowed = ['menunggu', 'diproses', 'selesai', 'dibatalkan'];

    if (in_array($newStatus, $allowed)) {
        $stmt = $pdo->prepare("UPDATE online_orders SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $orderId]);
        setFlash('success', "Status pesanan berhasil diubah menjadi: " . strtoupper($newStatus));
    }
    header('Location: pesanan_online.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
    exit;
}

// Handle Delete Order (Admin only)
if (isset($_GET['delete'])) {
    if (isAdmin()) {
        $delId = (int)$_GET['delete'];
        // Fetch file to delete if exists
        $stmt = $pdo->prepare("SELECT file_path FROM online_orders WHERE id = ?");
        $stmt->execute([$delId]);
        $filePath = $stmt->fetchColumn();
        if ($filePath && file_exists(__DIR__ . '/' . $filePath)) {
            @unlink(__DIR__ . '/' . $filePath);
        }
        $pdo->prepare("DELETE FROM online_orders WHERE id = ?")->execute([$delId]);
        setFlash('success', 'Pesanan online berhasil dihapus.');
    } else {
        setFlash('error', 'Hanya admin yang dapat menghapus pesanan.');
    }
    header('Location: pesanan_online.php');
    exit;
}

$pageTitle = 'Antrian Pesanan Cetak Online';
$pageSubtitle = 'Kelola file dan pesanan yang masuk dari Landing Page pelanggan';
require_once __DIR__ . '/includes/header.php';

// Status filter
$filterStatus = $_GET['status'] ?? 'semua';
$search = trim($_GET['q'] ?? '');

$query = "SELECT * FROM online_orders WHERE 1=1";
$params = [];

if ($filterStatus !== 'semua' && in_array($filterStatus, ['menunggu', 'diproses', 'selesai', 'dibatalkan'])) {
    $query .= " AND status = ?";
    $params[] = $filterStatus;
}

if (!empty($search)) {
    $query .= " AND (order_code LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ? OR service_type LIKE ?)";
    $sParam = "%$search%";
    $params = array_merge($params, [$sParam, $sParam, $sParam, $sParam]);
}

$query .= " ORDER BY id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Counts for tabs
$counts = [
    'semua' => (int)$pdo->query("SELECT COUNT(*) FROM online_orders")->fetchColumn(),
    'menunggu' => (int)$pdo->query("SELECT COUNT(*) FROM online_orders WHERE status = 'menunggu'")->fetchColumn(),
    'diproses' => (int)$pdo->query("SELECT COUNT(*) FROM online_orders WHERE status = 'diproses'")->fetchColumn(),
    'selesai' => (int)$pdo->query("SELECT COUNT(*) FROM online_orders WHERE status = 'selesai'")->fetchColumn(),
    'dibatalkan' => (int)$pdo->query("SELECT COUNT(*) FROM online_orders WHERE status = 'dibatalkan'")->fetchColumn(),
];

$operatorPhone = getSetting('print_operator_wa', '081234567890');
$fonnteToken = getSetting('fonnte_token', '');
?>

<div class="space-y-6">
    <!-- Top Filter & Stats Bar -->
    <div class="bg-white rounded-3xl border border-stone-200/90 shadow-sm p-4 sm:p-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <!-- Tabs -->
            <div class="flex flex-wrap items-center gap-1.5 text-xs">
                <a href="pesanan_online.php" class="px-3.5 py-2 rounded-xl font-bold transition flex items-center gap-1.5 <?= $filterStatus === 'semua' ? 'bg-stone-900 text-amber-300 shadow-sm' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">
                    <span>Semua</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] <?= $filterStatus === 'semua' ? 'bg-amber-400 text-stone-900 font-extrabold' : 'bg-stone-200 text-stone-700' ?>">
                        <?= $counts['semua'] ?>
                    </span>
                </a>
                <a href="pesanan_online.php?status=menunggu" class="px-3.5 py-2 rounded-xl font-bold transition flex items-center gap-1.5 <?= $filterStatus === 'menunggu' ? 'bg-amber-500 text-white shadow-sm' : 'bg-amber-50 text-amber-800 hover:bg-amber-100 border border-amber-200/60' ?>">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <span>Menunggu Cetak</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-white text-amber-800 font-extrabold shadow-xs">
                        <?= $counts['menunggu'] ?>
                    </span>
                </a>
                <a href="pesanan_online.php?status=diproses" class="px-3.5 py-2 rounded-xl font-bold transition flex items-center gap-1.5 <?= $filterStatus === 'diproses' ? 'bg-blue-600 text-white shadow-sm' : 'bg-blue-50 text-blue-800 hover:bg-blue-100 border border-blue-200/60' ?>">
                    <i class="fa-solid fa-gear"></i>
                    <span>Sedang Diproses</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-white text-blue-800 font-extrabold">
                        <?= $counts['diproses'] ?>
                    </span>
                </a>
                <a href="pesanan_online.php?status=selesai" class="px-3.5 py-2 rounded-xl font-bold transition flex items-center gap-1.5 <?= $filterStatus === 'selesai' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100 border border-emerald-200/60' ?>">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Selesai Siap Ambil</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-white text-emerald-800 font-extrabold">
                        <?= $counts['selesai'] ?>
                    </span>
                </a>
                <a href="pesanan_online.php?status=dibatalkan" class="px-3.5 py-2 rounded-xl font-bold transition flex items-center gap-1.5 <?= $filterStatus === 'dibatalkan' ? 'bg-stone-600 text-white shadow-sm' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">
                    <span>Dibatalkan</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-stone-200 text-stone-700">
                        <?= $counts['dibatalkan'] ?>
                    </span>
                </a>
            </div>

            <!-- Quick Action: Open Landing Page & Search -->
            <div class="flex items-center gap-2">
                <form method="GET" action="pesanan_online.php" class="relative">
                    <?php if ($filterStatus !== 'semua'): ?>
                        <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>" />
                    <?php endif; ?>
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari kode/nama/WA..."
                           class="w-48 sm:w-56 pl-8 pr-3 py-1.5 bg-stone-50 border border-stone-200 rounded-xl text-xs outline-none focus:bg-white focus:ring-1 focus:ring-amber-500 font-medium" />
                    <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-2.5 text-stone-400 text-xs"></i>
                </form>
                <a href="landing.php" target="_blank" class="px-3.5 py-1.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm" title="Buka Landing Page Pemesanan Cetak">
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                    <span class="hidden sm:inline">Landing Page</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Info Banner for Print Operator on Laptop -->
    <div class="bg-stone-900 text-stone-200 rounded-3xl p-4 sm:p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-sm border border-stone-800">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-amber-400/20 text-amber-300 flex items-center justify-center text-lg font-bold shrink-0">
                <i class="fa-solid fa-laptop-code"></i>
            </div>
            <div>
                <h4 class="text-xs font-extrabold text-white flex items-center gap-2">
                    <span>Laptop Operator Print Aktif</span>
                    <span class="px-2 py-0.5 rounded-full text-[9px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-bold uppercase tracking-wider">
                        WA: <?= htmlspecialchars($operatorPhone) ?>
                    </span>
                </h4>
                <p class="text-[11px] text-stone-400 mt-0.5">
                    File cetak otomatis terkirim ke WhatsApp operator via Fonnte. Klik tombol <strong>"Download File"</strong> di bawah untuk langsung membuka & mencetak di printer toko.
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="pengaturan.php" class="px-3 py-1.5 bg-stone-800 hover:bg-stone-700 text-stone-200 border border-stone-700 rounded-xl text-xs font-semibold transition flex items-center gap-1.5">
                <i class="fa-solid fa-gear text-xs text-amber-400"></i>
                <span>Setting Fonnte</span>
            </a>
            <button onclick="window.location.reload();" class="px-3 py-1.5 bg-stone-800 hover:bg-stone-700 text-stone-200 border border-stone-700 rounded-xl text-xs font-semibold transition flex items-center gap-1.5">
                <i class="fa-solid fa-rotate-right text-xs"></i>
                <span>Refresh Antrian</span>
            </button>
        </div>
    </div>

    <!-- Orders List -->
    <?php if (empty($orders)): ?>
        <div class="bg-white rounded-3xl border border-stone-200/90 shadow-sm p-12 text-center">
            <div class="w-16 h-16 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-2xl mx-auto mb-3">
                <i class="fa-solid fa-inbox"></i>
            </div>
            <h3 class="font-extrabold text-stone-800 text-sm">Tidak Ada Pesanan Cetak Online</h3>
            <p class="text-xs text-stone-500 mt-1 max-w-md mx-auto">
                Belum ada pesanan masuk dengan status yang dipilih. Bagikan link landing page toko kepada pelanggan untuk menerima pesanan dan file cetak online.
            </p>
            <div class="mt-4">
                <a href="landing.php" target="_blank" class="inline-flex items-center gap-1.5 px-4 py-2 bg-stone-900 text-amber-300 rounded-xl text-xs font-bold shadow transition">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    <span>Buka Landing Page Pemesanan</span>
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            <?php foreach ($orders as $ord): ?>
                <?php
                    $statusColors = [
                        'menunggu' => 'bg-amber-50 text-amber-800 border-amber-200',
                        'diproses' => 'bg-blue-50 text-blue-800 border-blue-200',
                        'selesai' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                        'dibatalkan' => 'bg-stone-100 text-stone-600 border-stone-200'
                    ];
                    $badgeClass = $statusColors[$ord['status']] ?? 'bg-stone-100 text-stone-600';

                    $waDirectMsg = "Halo kak *{$ord['customer_name']}*, pesanan cetak online Anda di *Aquiris Print* (Kode: *#{$ord['order_code']}*) ";
                    if ($ord['status'] === 'diproses') {
                        $waDirectMsg .= "saat ini sedang kami proses cetak. Mohon ditunggu ya! Terima kasih.";
                    } elseif ($ord['status'] === 'selesai') {
                        $waDirectMsg .= "sudah SELESAI dicetak dan siap diambil di toko! Ditunggu kedatangannya ya.";
                    } else {
                        $waDirectMsg .= "sudah kami terima di operator print. Kami akan segera konfirmasi.";
                    }
                    $waCustomerLink = buildWaLink($ord['customer_phone'], $waDirectMsg);
                ?>
                <div class="bg-white rounded-3xl border border-stone-200/90 shadow-sm hover:shadow-md transition flex flex-col justify-between overflow-hidden">
                    <!-- Card Header -->
                    <div class="p-5 border-b border-stone-100">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-mono font-black text-xs text-stone-900 bg-stone-100 px-2.5 py-1 rounded-lg">
                                #<?= htmlspecialchars($ord['order_code']) ?>
                            </span>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold border <?= $badgeClass ?> uppercase tracking-wider">
                                <?= htmlspecialchars($ord['status']) ?>
                            </span>
                        </div>
                        <div class="text-[11px] text-stone-400 flex items-center gap-1.5">
                            <i class="fa-regular fa-clock"></i>
                            <span><?= formatDate($ord['created_at'], true) ?></span>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="p-5 space-y-4 flex-1 text-xs">
                        <!-- Customer Info -->
                        <div class="bg-[#FAF8F5] p-3 rounded-2xl border border-stone-200/60">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold text-stone-400 uppercase tracking-wider">Pemesan</span>
                                <a href="<?= $waCustomerLink ?>" target="_blank" class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 hover:text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200">
                                    <i class="fa-brands fa-whatsapp"></i>
                                    <span>Chat WA</span>
                                </a>
                            </div>
                            <h4 class="font-bold text-stone-900 text-sm mt-1"><?= htmlspecialchars($ord['customer_name']) ?></h4>
                            <p class="text-stone-500 font-mono text-[11px]"><?= htmlspecialchars($ord['customer_phone']) ?></p>
                        </div>

                        <!-- Service & Specifications -->
                        <div>
                            <span class="text-[10px] font-bold text-stone-400 uppercase tracking-wider block mb-1">Rincian Cetak</span>
                            <div class="font-bold text-stone-800 flex items-center gap-1.5">
                                <i class="fa-solid fa-print text-amber-600"></i>
                                <span><?= htmlspecialchars($ord['service_type']) ?></span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 mt-2 text-[11px] text-stone-600 bg-stone-50 p-2.5 rounded-xl border border-stone-100">
                                <div>
                                    <span class="text-stone-400 block text-[10px]">Kertas:</span>
                                    <span class="font-semibold text-stone-800"><?= htmlspecialchars($ord['paper_type'] ?: '-') ?></span>
                                </div>
                                <div>
                                    <span class="text-stone-400 block text-[10px]">Warna:</span>
                                    <span class="font-semibold text-stone-800"><?= htmlspecialchars($ord['color_mode'] ?: '-') ?></span>
                                </div>
                                <div>
                                    <span class="text-stone-400 block text-[10px]">Jumlah:</span>
                                    <span class="font-semibold text-stone-800"><?= htmlspecialchars($ord['quantity']) ?> Rangkap/Eks</span>
                                </div>
                                <div>
                                    <span class="text-stone-400 block text-[10px]">Jilid/Finishing:</span>
                                    <span class="font-semibold text-stone-800"><?= htmlspecialchars($ord['finishing'] ?: '-') ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Customer Notes -->
                        <?php if (!empty($ord['notes'])): ?>
                            <div class="bg-amber-50/70 border border-amber-200/60 p-2.5 rounded-xl text-[11px] text-stone-700">
                                <span class="font-bold text-amber-900 block text-[10px] mb-0.5">Catatan Khusus Pelanggan:</span>
                                <p class="italic">"<?= nl2br(htmlspecialchars($ord['notes'])) ?>"</p>
                            </div>
                        <?php endif; ?>

                        <!-- File Uploaded by Customer -->
                        <div>
                            <span class="text-[10px] font-bold text-stone-400 uppercase tracking-wider block mb-1">File Dokumen Siap Cetak</span>
                            <?php if (!empty($ord['file_path']) && file_exists(__DIR__ . '/' . $ord['file_path'])): ?>
                                <?php
                                    $fileSizeKb = round(($ord['file_size'] ?? filesize(__DIR__ . '/' . $ord['file_path'])) / 1024, 1);
                                    $sizeLabel = ($fileSizeKb > 1024) ? round($fileSizeKb / 1024, 2) . ' MB' : $fileSizeKb . ' KB';
                                ?>
                                <a href="<?= htmlspecialchars($ord['file_path']) ?>" download="<?= htmlspecialchars($ord['file_name']) ?>" class="group flex items-center justify-between p-3 bg-stone-900 hover:bg-stone-800 text-white rounded-2xl transition shadow-sm">
                                    <div class="flex items-center gap-2.5 min-w-0 pr-2">
                                        <div class="w-8 h-8 rounded-xl bg-amber-400 text-stone-900 flex items-center justify-center font-bold text-xs shrink-0">
                                            <i class="fa-solid fa-file-arrow-down"></i>
                                        </div>
                                        <div class="truncate">
                                            <p class="font-bold text-xs text-amber-300 group-hover:text-amber-200 truncate"><?= htmlspecialchars($ord['file_name']) ?></p>
                                            <span class="text-[10px] text-stone-400"><?= $sizeLabel ?> • Klik untuk download</span>
                                        </div>
                                    </div>
                                    <div class="w-7 h-7 rounded-lg bg-stone-800 group-hover:bg-amber-400 group-hover:text-stone-900 text-stone-300 flex items-center justify-center shrink-0 transition">
                                        <i class="fa-solid fa-download text-xs"></i>
                                    </div>
                                </a>
                            <?php else: ?>
                                <div class="p-2.5 bg-stone-50 border border-stone-200 rounded-xl text-stone-400 text-center italic text-[11px]">
                                    File tidak dilampirkan atau telah dihapus
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Card Actions -->
                    <div class="p-4 bg-stone-50 border-t border-stone-100 flex items-center justify-between gap-2">
                        <!-- Update Status Form -->
                        <form method="POST" action="pesanan_online.php" class="flex-1 flex items-center gap-1.5">
                            <input type="hidden" name="update_status" value="1" />
                            <input type="hidden" name="order_id" value="<?= $ord['id'] ?>" />
                            <select name="new_status" onchange="this.form.submit()" class="w-full px-2.5 py-1.5 bg-white border border-stone-300 rounded-xl text-xs font-bold text-stone-800 outline-none cursor-pointer focus:ring-1 focus:ring-amber-500">
                                <option value="menunggu" <?= $ord['status'] === 'menunggu' ? 'selected' : '' ?>>⏳ Menunggu Cetak</option>
                                <option value="diproses" <?= $ord['status'] === 'diproses' ? 'selected' : '' ?>>⚙️ Sedang Diproses</option>
                                <option value="selesai" <?= $ord['status'] === 'selesai' ? 'selected' : '' ?>>✅ Selesai Siap Ambil</option>
                                <option value="dibatalkan" <?= $ord['status'] === 'dibatalkan' ? 'selected' : '' ?>>❌ Dibatalkan</option>
                            </select>
                        </form>

                        <!-- Delete button (admin) -->
                        <?php if (isAdmin()): ?>
                            <button type="button" onclick="confirmDelete('pesanan_online.php?delete=<?= $ord['id'] ?>', 'Pesanan online #<?= htmlspecialchars($ord['order_code']) ?> beserta file terkait akan dihapus permanen.')" class="p-2 text-stone-400 hover:text-rose-600 rounded-xl hover:bg-stone-200 transition shrink-0 cursor-pointer" title="Hapus Pesanan">
                                <i class="fa-solid fa-trash-can text-xs"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
