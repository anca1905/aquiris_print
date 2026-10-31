<?php
// akrilik_master.php
require_once __DIR__ . '/config/app.php';
requireAuth();

$pdo = Database::getConnection();

// Handle Add/Edit Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isAdmin()) {
        setFlash('error', 'Hanya administrator yang memiliki izin untuk menambah atau mengedit papan akrilik.');
        header('Location: akrilik_master.php');
        exit;
    }
    $id = (int)($_POST['id'] ?? 0);
    $code = trim($_POST['code']);
    $name = trim($_POST['name']);
    $size = trim($_POST['size']);
    $standType = trim($_POST['stand_type']);
    $rentalPrice = (float)$_POST['rental_price_per_day'];
    $deposit = (float)$_POST['deposit_amount'];
    $status = $_POST['status'] ?? 'available';
    $notes = trim($_POST['notes'] ?? '');

    if (empty($code) || empty($name) || $rentalPrice <= 0) {
        setFlash('error', 'Kode, nama papan, dan tarif sewa wajib diisi dengan benar.');
    } else {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE acrylic_items SET code = ?, name = ?, size = ?, stand_type = ?, rental_price_per_day = ?, deposit_amount = ?, status = ?, notes = ? WHERE id = ?");
            $stmt->execute([$code, $name, $size, $standType, $rentalPrice, $deposit, $status, $notes, $id]);
            setFlash('success', 'Data papan akrilik berhasil diperbarui!');
        } else {
            $chk = $pdo->prepare("SELECT id FROM acrylic_items WHERE code = ?");
            $chk->execute([$code]);
            if ($chk->fetch()) {
                setFlash('error', 'Kode papan akrilik sudah terdaftar. Gunakan kode lain.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO acrylic_items (code, name, size, stand_type, rental_price_per_day, deposit_amount, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$code, $name, $size, $standType, $rentalPrice, $deposit, $status, $notes]);
                setFlash('success', 'Papan akrilik baru berhasil ditambahkan!');
            }
        }
        header('Location: akrilik_master.php');
        exit;
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    if (!isAdmin()) {
        setFlash('error', 'Hanya administrator yang memiliki izin untuk menghapus papan akrilik.');
        header('Location: akrilik_master.php');
        exit;
    }
    $delId = (int)$_GET['delete'];
    $chk = $pdo->prepare("SELECT COUNT(*) FROM acrylic_rentals WHERE acrylic_id = ?");
    $chk->execute([$delId]);
    if ($chk->fetchColumn() > 0) {
        setFlash('error', 'Papan akrilik ini memiliki riwayat penyewaan dan tidak dapat dihapus.');
    } else {
        $pdo->prepare("DELETE FROM acrylic_items WHERE id = ?")->execute([$delId]);
        setFlash('success', 'Papan akrilik berhasil dihapus.');
    }
    header('Location: akrilik_master.php');
    exit;
}

$acrylics = $pdo->query("SELECT * FROM acrylic_items ORDER BY id DESC")->fetchAll();

$pageTitle = 'Katalog Papan Akrilik & Standing';
$pageSubtitle = 'Kelola koleksi papan welcome sign, easel kayu pinus, tripod besi & stok galeri';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Action -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
    <div>
        <h3 class="text-base font-extrabold text-stone-900 serif-title">Koleksi Papan Akrilik & Standing Event</h3>
        <p class="text-xs text-stone-500">Total <?= count($acrylics) ?> unit papan dan stand terdaftar di galeri</p>
    </div>
    <?php if (isAdmin()): ?>
    <button onclick="openModal()" class="px-4 py-2 bg-stone-900 hover:bg-stone-800 text-amber-300 rounded-2xl text-xs font-bold shadow transition flex items-center gap-1.5 self-start">
        <i class="fa-solid fa-plus"></i>
        <span>Tambah Papan Baru</span>
    </button>
    <?php else: ?>
    <a href="rental_tambah.php" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-2xl text-xs font-bold shadow transition flex items-center gap-1.5 self-start">
        <i class="fa-solid fa-circle-plus"></i>
        <span>Catat Sewa Pelanggan</span>
    </a>
    <?php endif; ?>
</div>

<!-- Grid of Acrylic Cards with Aesthetic Studio Feel -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
    <?php foreach ($acrylics as $acr): ?>
        <div class="bg-white rounded-3xl border border-stone-200/90 shadow-sm p-5 sm:p-6 flex flex-col justify-between hover:border-amber-400 hover:shadow-md transition">
            <div>
                <div class="flex items-center justify-between mb-2.5">
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-stone-100 text-stone-700 uppercase">
                        <?= htmlspecialchars($acr['code']) ?>
                    </span>
                    <?php
                    $badge = 'bg-emerald-100 text-emerald-800';
                    $lbl = 'Tersedia di Galeri';
                    if ($acr['status'] === 'rented') { $badge = 'bg-amber-100 text-amber-800'; $lbl = 'Sedang Disewa'; }
                    if ($acr['status'] === 'maintenance') { $badge = 'bg-stone-100 text-stone-600'; $lbl = 'Perawatan'; }
                    ?>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold <?= $badge ?>">
                        <?= $lbl ?>
                    </span>
                </div>

                <h4 class="font-extrabold text-stone-900 text-sm mb-1.5 leading-snug"><?= htmlspecialchars($acr['name']) ?></h4>
                <div class="space-y-1.5 text-xs text-stone-500 mb-4">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-ruler-combined text-stone-400 w-4 text-center"></i>
                        <span>Ukuran: <strong class="text-stone-700"><?= htmlspecialchars($acr['size'] ?: '-') ?></strong></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-gem text-stone-400 w-4 text-center"></i>
                        <span>Standing: <strong class="text-stone-700"><?= htmlspecialchars($acr['stand_type'] ?: 'Standard') ?></strong></span>
                    </div>
                </div>

                <?php if (!empty($acr['notes'])): ?>
                    <p class="text-[11px] text-stone-500 italic mb-4 line-clamp-2">"<?= htmlspecialchars($acr['notes']) ?>"</p>
                <?php endif; ?>
            </div>

            <div class="pt-3.5 border-t border-stone-100 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-stone-400 block uppercase font-bold">Tarif Sewa:</span>
                    <span class="text-sm font-black text-amber-800"><?= formatRupiah($acr['rental_price_per_day']) ?> <span class="text-[10px] font-normal text-stone-400">/hari</span></span>
                    <span class="text-[10px] text-stone-400 block">Jaminan: <?= formatRupiah($acr['deposit_amount']) ?></span>
                </div>

                <div class="flex items-center gap-1.5">
                    <?php if (isAdmin()): ?>
                        <button onclick='editAcrylic(<?= json_encode($acr) ?>)' class="p-2 bg-stone-100 hover:bg-stone-200 text-stone-700 rounded-xl text-xs transition" title="Edit Data Papan">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button onclick="confirmDelete('akrilik_master.php?delete=<?= $acr['id'] ?>', 'Yakin ingin menghapus papan ini?')" class="p-2 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-xs transition" title="Hapus Data">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    <?php else: ?>
                        <?php if ($acr['status'] === 'available'): ?>
                            <a href="rental_tambah.php?acrylic_id=<?= $acr['id'] ?>" class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1">
                                <i class="fa-solid fa-handshake"></i>
                                <span>Sewa</span>
                            </a>
                        <?php else: ?>
                            <span class="px-2.5 py-1 bg-stone-100 text-stone-400 rounded-xl text-[10px] font-bold">Tidak Siap</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal Add/Edit Acrylic Item -->
<div id="acrylicModal" class="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-stone-200">
        <div class="flex items-center justify-between pb-3 border-b border-stone-100">
            <h3 id="modalTitle" class="font-extrabold text-stone-800 text-sm">Tambah Papan Akrilik</h3>
            <button onclick="closeModal()" class="text-stone-400 hover:text-stone-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="akrilik_master.php" class="space-y-3 py-4 text-xs">
            <input type="hidden" name="id" id="acrylicId" value="0" />

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Kode Papan *</label>
                    <input type="text" name="code" id="modalCode" required placeholder="AKR-06"
                           class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none uppercase font-bold" />
                </div>
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Status Papan</label>
                    <select name="status" id="modalStatus" class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none font-semibold">
                        <option value="available">Tersedia di Galeri</option>
                        <option value="rented">Sedang Disewa</option>
                        <option value="maintenance">Perawatan / Poles</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block font-bold text-stone-700 mb-1">Nama Papan Akrilik *</label>
                <input type="text" name="name" id="modalName" required placeholder="Contoh: Papan Akrilik Bulat Frosted D60 + Stand Besi Gold"
                       class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none focus:ring-1 focus:ring-amber-500 font-semibold" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Ukuran / Dimensi</label>
                    <input type="text" name="size" id="modalSize" placeholder="Contoh: 60x80 cm / D60 cm"
                           class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none" />
                </div>
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Tipe Stand / Easel</label>
                    <input type="text" name="stand_type" id="modalStandType" placeholder="Contoh: Easel Kayu Pinus Rustic"
                           class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none" />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Tarif Sewa / Hari (Rp) *</label>
                    <input type="number" name="rental_price_per_day" id="modalPrice" required min="0" placeholder="75000"
                           class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none font-black text-amber-800" />
                </div>
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Uang Jaminan / Deposit (Rp)</label>
                    <input type="number" name="deposit_amount" id="modalDeposit" min="0" placeholder="50000"
                           class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none font-bold text-stone-700" />
                </div>
            </div>

            <div>
                <label class="block font-bold text-stone-700 mb-1">Keterangan / Keunggulan</label>
                <textarea name="notes" id="modalNotes" rows="2" placeholder="Cocok untuk tema rustic, wedding minimalis..."
                          class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-stone-100">
                <button type="button" onclick="closeModal()" class="px-4 py-2 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold rounded-xl">Batal</button>
                <button type="submit" class="px-5 py-2 bg-stone-900 hover:bg-stone-800 text-amber-300 font-bold rounded-xl shadow-md">Simpan Papan</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('modalTitle').innerText = 'Tambah Papan Akrilik';
        document.getElementById('acrylicId').value = '0';
        document.getElementById('modalCode').value = '';
        document.getElementById('modalName').value = '';
        document.getElementById('modalSize').value = '';
        document.getElementById('modalStandType').value = '';
        document.getElementById('modalPrice').value = '';
        document.getElementById('modalDeposit').value = '';
        document.getElementById('modalStatus').value = 'available';
        document.getElementById('modalNotes').value = '';
        document.getElementById('acrylicModal').classList.remove('hidden');
    }

    function editAcrylic(item) {
        document.getElementById('modalTitle').innerText = 'Edit Papan Akrilik';
        document.getElementById('acrylicId').value = item.id;
        document.getElementById('modalCode').value = item.code;
        document.getElementById('modalName').value = item.name;
        document.getElementById('modalSize').value = item.size || '';
        document.getElementById('modalStandType').value = item.stand_type || '';
        document.getElementById('modalPrice').value = item.rental_price_per_day;
        document.getElementById('modalDeposit').value = item.deposit_amount;
        document.getElementById('modalStatus').value = item.status;
        document.getElementById('modalNotes').value = item.notes || '';
        document.getElementById('acrylicModal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('acrylicModal').classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
