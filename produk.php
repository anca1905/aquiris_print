<?php
// produk.php
require_once __DIR__ . '/config/app.php';
requireAdmin();

$pdo = Database::getConnection();

// Fetch categories
$categories = $pdo->query("SELECT * FROM categories_product ORDER BY id ASC")->fetchAll();

// Handle Add/Edit Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $id = (int)($_POST['id'] ?? 0);
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $code = trim($_POST['code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $unit = trim($_POST['unit'] ?? 'lembar');
    $price = (float)($_POST['price'] ?? 0);

    if (empty($name) || $price < 0) {
        setFlash('error', 'Nama layanan dan tarif harga wajib diisi.');
    } else {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE products SET category_id = ?, code = ?, name = ?, unit = ?, price = ? WHERE id = ?");
            $stmt->execute([$categoryId, $code, $name, $unit, $price, $id]);
            setFlash('success', 'Layanan cetak berhasil diperbarui!');
        } else {
            $stmt = $pdo->prepare("INSERT INTO products (category_id, code, name, unit, price) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$categoryId, $code, $name, $unit, $price]);
            setFlash('success', 'Layanan cetak baru berhasil ditambahkan!');
        }
        header('Location: produk.php');
        exit;
    }
}

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $catName = trim($_POST['category_name'] ?? '');
    if (!empty($catName)) {
        $stmt = $pdo->prepare("INSERT INTO categories_product (name) VALUES (?)");
        $stmt->execute([$catName]);
        setFlash('success', 'Kategori produk baru berhasil ditambahkan!');
        header('Location: produk.php');
        exit;
    }
}

// Handle Delete Product
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$delId]);
    setFlash('success', 'Layanan cetak berhasil dihapus.');
    header('Location: produk.php');
    exit;
}

$pageTitle = 'Daftar Tarif Layanan Cetak';
$pageSubtitle = 'Kelola harga fotocopy, print dokumen, jilid, banner spanduk & stiker';
require_once __DIR__ . '/includes/header.php';

// Filter parameters
$catFilter = $_GET['cat'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories_product c ON p.category_id = c.id 
        WHERE 1=1";
$params = [];

if ($catFilter !== 'all') {
    $sql .= " AND p.category_id = ?";
    $params[] = $catFilter;
}

if (!empty($search)) {
    $sql .= " AND (p.name LIKE ? OR p.code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY c.id ASC, p.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>

<!-- Header Actions & Search -->
<div class="bg-white p-5 rounded-3xl border border-stone-200/90 shadow-sm mb-6 flex flex-col md:flex-row gap-4 items-center justify-between">
    <div class="flex items-center gap-2 w-full md:w-auto">
        <form method="GET" action="produk.php" class="relative flex-1 md:w-72">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari layanan, kode..." 
                   class="w-full pl-9 pr-3 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs outline-none focus:ring-1 focus:ring-amber-500 font-medium" />
            <i class="fa-solid fa-magnifying-glass text-stone-400 absolute left-3 top-3 text-xs"></i>
        </form>
    </div>

    <div class="flex items-center gap-2 w-full md:w-auto justify-end">
        <button onclick="openCategoryModal()" class="px-3.5 py-2 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold rounded-xl text-xs transition flex items-center gap-1.5">
            <i class="fa-solid fa-folder-plus"></i>
            <span>+ Kategori</span>
        </button>
        <button onclick="openModal()" class="px-4 py-2 bg-stone-900 hover:bg-stone-800 text-amber-300 font-bold rounded-xl text-xs shadow transition flex items-center gap-1.5">
            <i class="fa-solid fa-plus"></i>
            <span>Tambah Layanan</span>
        </button>
    </div>
</div>

<!-- Category Pills -->
<div class="flex items-center gap-1.5 overflow-x-auto pb-2 mb-4 scrollbar-none text-xs">
    <a href="produk.php" class="px-3.5 py-1.5 rounded-full font-bold transition whitespace-nowrap <?= $catFilter === 'all' ? 'bg-stone-900 text-amber-300 shadow-sm' : 'bg-white text-stone-600 border border-stone-200 hover:bg-stone-100' ?>">
        Semua (<?= count($products) ?>)
    </a>
    <?php foreach ($categories as $c): ?>
        <a href="produk.php?cat=<?= $c['id'] ?>" class="px-3.5 py-1.5 rounded-full font-semibold transition whitespace-nowrap <?= $catFilter == $c['id'] ? 'bg-stone-900 text-amber-300 shadow-sm font-bold' : 'bg-white text-stone-600 border border-stone-200 hover:bg-stone-100' ?>">
            <?= htmlspecialchars($c['name']) ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- Table of Products -->
<div class="bg-white rounded-3xl border border-stone-200/90 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
            <thead>
                <tr class="bg-stone-50 text-stone-600 border-b border-stone-200">
                    <th class="py-3.5 px-4 font-bold">Kode</th>
                    <th class="py-3.5 px-4 font-bold">Kategori</th>
                    <th class="py-3.5 px-4 font-bold">Nama Layanan / Produk Cetak</th>
                    <th class="py-3.5 px-4 font-bold text-center">Satuan Hitung</th>
                    <th class="py-3.5 px-4 font-bold text-right">Tarif Harga</th>
                    <th class="py-3.5 px-4 font-bold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="6" class="py-10 text-center text-stone-400">
                            <i class="fa-solid fa-box-open text-3xl mb-2 text-stone-300"></i>
                            <p>Tidak ada layanan cetak pada filter ini.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <tr class="hover:bg-stone-50/80 transition">
                            <td class="py-3 px-4 font-mono font-bold text-stone-800">
                                <?= htmlspecialchars($p['code'] ?: '-') ?>
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-stone-100 text-stone-700">
                                    <?= htmlspecialchars($p['category_name'] ?? 'Umum') ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 font-bold text-stone-900 text-xs">
                                <?= htmlspecialchars($p['name']) ?>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="bg-stone-50 border border-stone-200 px-2.5 py-0.5 rounded-lg text-stone-600 font-bold text-[10px]">
                                    <?= htmlspecialchars($p['unit']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <span class="font-black text-stone-900 text-xs">
                                    <?= formatRupiah($p['price']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right space-x-1 whitespace-nowrap">
                                <button onclick='editProduct(<?= json_encode($p) ?>)' class="p-1.5 bg-stone-100 hover:bg-stone-200 text-stone-700 rounded-lg text-xs" title="Edit">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <button onclick="confirmDelete('produk.php?delete=<?= $p['id'] ?>', 'Yakin ingin menghapus layanan ini?')" class="p-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg text-xs" title="Hapus">
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

<!-- Modal Add/Edit Product -->
<div id="productModal" class="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-stone-200">
        <div class="flex items-center justify-between pb-3 border-b border-stone-100">
            <h3 id="modalTitle" class="font-extrabold text-stone-800 text-sm">Tambah Layanan Cetak</h3>
            <button onclick="closeModal()" class="text-stone-400 hover:text-stone-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="produk.php" class="space-y-3 py-4 text-xs">
            <input type="hidden" name="save_product" value="1" />
            <input type="hidden" name="id" id="productId" value="0" />

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Kode Produk (Opsional)</label>
                    <input type="text" name="code" id="modalCode" placeholder="FC-04 / PRN-06"
                           class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none font-mono font-bold uppercase" />
                </div>
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Kategori Layanan</label>
                    <select name="category_id" id="modalCategory" class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none font-semibold">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block font-bold text-stone-700 mb-1">Nama Layanan Cetak *</label>
                <input type="text" name="name" id="modalName" required placeholder="Contoh: Print Kalkir A3+ Transparan"
                       class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none focus:ring-1 focus:ring-amber-500 font-semibold" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Satuan Hitung *</label>
                    <input type="text" name="unit" id="modalUnit" required placeholder="lembar / pcs / buku / meter / set"
                           class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none" />
                </div>
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Tarif Harga (Rp) *</label>
                    <input type="number" name="price" id="modalPrice" required min="0" placeholder="1000"
                           class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none font-black text-stone-900" />
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-stone-100">
                <button type="button" onclick="closeModal()" class="px-4 py-2 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold rounded-xl">Batal</button>
                <button type="submit" class="px-5 py-2 bg-stone-900 hover:bg-stone-800 text-amber-300 font-bold rounded-xl shadow-md">Simpan Layanan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Add Category -->
<div id="categoryModal" class="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-stone-200">
        <div class="flex items-center justify-between pb-3 border-b border-stone-100">
            <h3 class="font-extrabold text-stone-800 text-sm">Tambah Kategori Layanan</h3>
            <button onclick="closeCategoryModal()" class="text-stone-400 hover:text-stone-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="produk.php" class="space-y-3 py-4 text-xs">
            <input type="hidden" name="add_category" value="1" />
            <div>
                <label class="block font-bold text-stone-700 mb-1">Nama Kategori</label>
                <input type="text" name="category_name" required placeholder="Contoh: Cetak Souvenir & Merchandise"
                       class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none font-semibold" />
            </div>
            <div class="flex justify-end gap-2 pt-2 border-t border-stone-100">
                <button type="button" onclick="closeCategoryModal()" class="px-4 py-2 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold rounded-xl">Batal</button>
                <button type="submit" class="px-4 py-2 bg-stone-900 hover:bg-stone-800 text-amber-300 font-bold rounded-xl">Simpan Kategori</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('modalTitle').innerText = 'Tambah Layanan Cetak';
        document.getElementById('productId').value = '0';
        document.getElementById('modalCode').value = '';
        document.getElementById('modalName').value = '';
        document.getElementById('modalUnit').value = 'lembar';
        document.getElementById('modalPrice').value = '';
        document.getElementById('productModal').classList.remove('hidden');
    }

    function editProduct(p) {
        document.getElementById('modalTitle').innerText = 'Edit Layanan Cetak';
        document.getElementById('productId').value = p.id;
        document.getElementById('modalCode').value = p.code || '';
        document.getElementById('modalName').value = p.name;
        document.getElementById('modalCategory').value = p.category_id;
        document.getElementById('modalUnit').value = p.unit;
        document.getElementById('modalPrice').value = p.price;
        document.getElementById('productModal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('productModal').classList.add('hidden');
    }

    function openCategoryModal() {
        document.getElementById('categoryModal').classList.remove('hidden');
    }
    function closeCategoryModal() {
        document.getElementById('categoryModal').classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
