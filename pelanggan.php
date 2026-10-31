<?php
// pelanggan.php
require_once __DIR__ . '/config/app.php';
requireAuth();

$pdo = Database::getConnection();

// Handle Add/Edit Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_customer'])) {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address'] ?? '');

    if (empty($name)) {
        setFlash('error', 'Nama pelanggan wajib diisi.');
    } else {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE customers SET name = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $address, $id]);
            setFlash('success', 'Data pelanggan berhasil diperbarui!');
        } else {
            $stmt = $pdo->prepare("INSERT INTO customers (name, phone, address) VALUES (?, ?, ?)");
            $stmt->execute([$name, $phone, $address]);
            setFlash('success', 'Pelanggan baru berhasil ditambahkan!');
        }
        header('Location: pelanggan.php');
        exit;
    }
}

// Handle Delete Customer (Admin only)
if (isset($_GET['delete'])) {
    if (!isAdmin()) {
        setFlash('error', 'Hanya administrator yang memiliki izin untuk menghapus pelanggan.');
        header('Location: pelanggan.php');
        exit;
    }
    $delId = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM customers WHERE id = ?")->execute([$delId]);
    setFlash('success', 'Data pelanggan berhasil dihapus.');
    header('Location: pelanggan.php');
    exit;
}

$pageTitle = 'Buku Kontak Pelanggan';
$pageSubtitle = 'Database kontak pelanggan percetakan & penyewa papan akrilik event';
require_once __DIR__ . '/includes/header.php';

$search = trim($_GET['search'] ?? '');
$sql = "SELECT c.*, 
        (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) as total_orders,
        (SELECT COUNT(*) FROM acrylic_rentals r WHERE r.customer_id = c.id) as total_rentals
        FROM customers c 
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (c.name LIKE ? OR c.phone LIKE ? OR c.address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY c.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();
?>

<!-- Header Toolbar -->
<div class="bg-white p-5 rounded-3xl border border-stone-200/90 shadow-sm mb-6 flex flex-col sm:flex-row gap-4 items-center justify-between">
    <form method="GET" action="pelanggan.php" class="relative w-full sm:w-80 text-xs">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama, nomor WhatsApp..." 
               class="w-full pl-9 pr-3 py-2 bg-stone-50 border border-stone-200 rounded-xl outline-none focus:ring-1 focus:ring-amber-500 font-medium" />
        <i class="fa-solid fa-magnifying-glass text-stone-400 absolute left-3 top-3"></i>
    </form>

    <button onclick="openModal()" class="w-full sm:w-auto px-4 py-2 bg-stone-900 hover:bg-stone-800 text-amber-300 font-bold rounded-xl text-xs shadow transition flex items-center justify-center gap-1.5">
        <i class="fa-solid fa-user-plus"></i>
        <span>Tambah Pelanggan</span>
    </button>
</div>

<!-- Table of Customers -->
<div class="bg-white rounded-3xl border border-stone-200/90 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
            <thead>
                <tr class="bg-stone-50 text-stone-600 border-b border-stone-200">
                    <th class="py-3.5 px-4 font-bold">Nama Pelanggan</th>
                    <th class="py-3.5 px-4 font-bold">Nomor WhatsApp</th>
                    <th class="py-3.5 px-4 font-bold">Alamat</th>
                    <th class="py-3.5 px-4 font-bold text-center">Riwayat Order</th>
                    <th class="py-3.5 px-4 font-bold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="5" class="py-10 text-center text-stone-400">
                            <i class="fa-solid fa-address-book text-3xl mb-2 text-stone-300"></i>
                            <p>Belum ada data pelanggan.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $c): 
                        $waUrl = !empty($c['phone']) ? buildWaLink($c['phone'], "Halo kak {$c['name']}, dari " . getSetting('shop_name', 'Aquiris Print') . "...") : '#';
                    ?>
                        <tr class="hover:bg-stone-50/80 transition">
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-stone-800 text-xs flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-stone-100 text-stone-800 font-bold flex items-center justify-center text-xs border border-stone-200">
                                        <?= strtoupper(substr($c['name'], 0, 1)) ?>
                                    </div>
                                    <span><?= htmlspecialchars($c['name']) ?></span>
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                <?php if (!empty($c['phone'])): ?>
                                    <a href="<?= $waUrl ?>" target="_blank" class="inline-flex items-center gap-1.5 text-emerald-700 hover:underline font-bold">
                                        <i class="fa-brands fa-whatsapp text-sm"></i>
                                        <span><?= htmlspecialchars($c['phone']) ?></span>
                                    </a>
                                <?php else: ?>
                                    <span class="text-stone-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-stone-600">
                                <?= htmlspecialchars($c['address'] ?: '-') ?>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <div class="flex items-center justify-center gap-2 text-[11px]">
                                    <span class="px-2.5 py-0.5 rounded-full bg-stone-100 text-stone-800 font-bold" title="Order Cetak">
                                        <i class="fa-solid fa-print"></i> <?= $c['total_orders'] ?>
                                    </span>
                                    <span class="px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-900 font-bold" title="Sewa Akrilik">
                                        <i class="fa-solid fa-shapes"></i> <?= $c['total_rentals'] ?>
                                    </span>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-right space-x-1 whitespace-nowrap">
                                <button onclick='editCustomer(<?= json_encode($c) ?>)' class="p-1.5 bg-stone-100 hover:bg-stone-200 text-stone-700 rounded-lg text-xs" title="Edit">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <?php if (isAdmin()): ?>
                                    <button onclick="confirmDelete('pelanggan.php?delete=<?= $c['id'] ?>', 'Yakin ingin menghapus data pelanggan ini?')" class="p-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg text-xs" title="Hapus">
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

<!-- Modal Add/Edit Customer -->
<div id="customerModal" class="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-stone-200">
        <div class="flex items-center justify-between pb-3 border-b border-stone-100">
            <h3 id="modalTitle" class="font-extrabold text-stone-800 text-sm">Tambah Pelanggan Baru</h3>
            <button onclick="closeModal()" class="text-stone-400 hover:text-stone-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="pelanggan.php" class="space-y-3 py-4 text-xs">
            <input type="hidden" name="save_customer" value="1" />
            <input type="hidden" name="id" id="customerId" value="0" />

            <div>
                <label class="block font-bold text-stone-700 mb-1">Nama Lengkap Pelanggan *</label>
                <input type="text" name="name" id="modalName" required placeholder="Contoh: Dian Puspitasari"
                       class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none focus:ring-1 focus:ring-amber-500 font-semibold" />
            </div>

            <div>
                <label class="block font-bold text-stone-700 mb-1">Nomor WhatsApp Aktif</label>
                <input type="text" name="phone" id="modalPhone" placeholder="081234567890"
                       class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none font-medium" />
            </div>

            <div>
                <label class="block font-bold text-stone-700 mb-1">Alamat Domisili / Kampus</label>
                <textarea name="address" id="modalAddress" rows="2" placeholder="Alamat lengkap customer..."
                          class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-stone-100">
                <button type="button" onclick="closeModal()" class="px-4 py-2 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold rounded-xl">Batal</button>
                <button type="submit" class="px-5 py-2 bg-stone-900 hover:bg-stone-800 text-amber-300 font-bold rounded-xl shadow-md">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('modalTitle').innerText = 'Tambah Pelanggan Baru';
        document.getElementById('customerId').value = '0';
        document.getElementById('modalName').value = '';
        document.getElementById('modalPhone').value = '';
        document.getElementById('modalAddress').value = '';
        document.getElementById('customerModal').classList.remove('hidden');
    }

    function editCustomer(c) {
        document.getElementById('modalTitle').innerText = 'Edit Pelanggan';
        document.getElementById('customerId').value = c.id;
        document.getElementById('modalName').value = c.name;
        document.getElementById('modalPhone').value = c.phone || '';
        document.getElementById('modalAddress').value = c.address || '';
        document.getElementById('customerModal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('customerModal').classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
