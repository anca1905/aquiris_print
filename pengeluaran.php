<?php
// pengeluaran.php
require_once __DIR__ . '/config/app.php';
requireAdmin();

$pdo = Database::getConnection();

// Fetch Categories
$categories = $pdo->query("SELECT * FROM expense_categories ORDER BY name ASC")->fetchAll();

// Handle New Category Submission
if (isset($_POST['add_category'])) {
    $catName = trim($_POST['category_name']);
    if (!empty($catName)) {
        $stmt = $pdo->prepare("INSERT INTO expense_categories (name) VALUES (?)");
        $stmt->execute([$catName]);
        setFlash('success', 'Kategori pengeluaran baru berhasil ditambahkan!');
        header('Location: pengeluaran.php');
        exit;
    }
}

// Handle Add / Edit Expense Submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_expense'])) {
    $id = (int)($_POST['id'] ?? 0);
    $categoryId = (int)$_POST['category_id'];
    $title = trim($_POST['title']);
    $amount = (float)$_POST['amount'];
    $date = $_POST['date'] ?: date('Y-m-d');
    $paymentMethod = $_POST['payment_method'] ?? 'tunai';
    $notes = trim($_POST['notes'] ?? '');
    $userId = $_SESSION['user_id'] ?? null;

    if (empty($title) || $amount <= 0 || empty($categoryId)) {
        setFlash('error', 'Kategori, uraian pengeluaran, dan nominal wajib diisi!');
    } else {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE expenses SET category_id = ?, title = ?, amount = ?, date = ?, payment_method = ?, notes = ? WHERE id = ?");
            $stmt->execute([$categoryId, $title, $amount, $date, $paymentMethod, $notes, $id]);
            setFlash('success', 'Catatan pengeluaran berhasil diperbarui!');
        } else {
            $expCode = generateExpenseCode($pdo);
            $now = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("INSERT INTO expenses (expense_code, category_id, title, amount, date, payment_method, notes, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$expCode, $categoryId, $title, $amount, $date, $paymentMethod, $notes, $userId, $now]);
            setFlash('success', 'Pengeluaran berhasil dicatat!');
        }
        header('Location: pengeluaran.php');
        exit;
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM expenses WHERE id = ?")->execute([$delId]);
    setFlash('success', 'Catatan pengeluaran berhasil dihapus.');
    header('Location: pengeluaran.php');
    exit;
}

$pageTitle = 'Catatan Pengeluaran Toko';
$pageSubtitle = 'Pencatatan belanja kertas, tinta, listrik, gaji & biaya operasional';
require_once __DIR__ . '/includes/header.php';

// Filter parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$catFilter = $_GET['category'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT e.*, c.name as category_name, u.name as user_name 
        FROM expenses e 
        JOIN expense_categories c ON e.category_id = c.id 
        LEFT JOIN users u ON e.user_id = u.id 
        WHERE 1=1";
$params = [];

if (!empty($startDate) && !empty($endDate)) {
    $sql .= " AND e.date BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
}

if ($catFilter !== 'all') {
    $sql .= " AND e.category_id = ?";
    $params[] = $catFilter;
}

if (!empty($search)) {
    $sql .= " AND (e.expense_code LIKE ? OR e.title LIKE ? OR e.notes LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY e.date DESC, e.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

$totalExpenses = array_sum(array_column($expenses, 'amount'));
?>

<!-- Filter & Search Toolbar -->
<div class="bg-white p-5 rounded-3xl border border-stone-200/90 shadow-sm mb-6">
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <form method="GET" action="pengeluaran.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 flex-1 text-xs">
            <div>
                <label class="block font-bold text-stone-600 mb-1">Mulai Tanggal</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="w-full px-3 py-2 bg-stone-50 border border-stone-200 rounded-xl outline-none font-medium" />
            </div>
            <div>
                <label class="block font-bold text-stone-600 mb-1">Sampai Tanggal</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="w-full px-3 py-2 bg-stone-50 border border-stone-200 rounded-xl outline-none font-medium" />
            </div>
            <div>
                <label class="block font-bold text-stone-600 mb-1">Kategori Biaya</label>
                <select name="category" class="w-full px-3 py-2 bg-stone-50 border border-stone-200 rounded-xl outline-none font-semibold">
                    <option value="all">Semua Kategori</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $catFilter == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-2 items-end">
                <div class="flex-1">
                    <label class="block font-bold text-stone-600 mb-1">Cari Keterangan</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Beli kertas, token..." class="w-full px-3 py-2 bg-stone-50 border border-stone-200 rounded-xl outline-none" />
                </div>
                <button type="submit" class="py-2 px-3 bg-stone-900 hover:bg-stone-800 text-amber-300 font-bold rounded-xl transition" title="Filter">
                    <i class="fa-solid fa-filter"></i>
                </button>
            </div>
        </form>

        <div class="flex items-center gap-2 self-start lg:self-end">
            <button onclick="openCategoryModal()" class="px-3.5 py-2 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold rounded-xl text-xs transition flex items-center gap-1.5 whitespace-nowrap">
                <i class="fa-solid fa-tags"></i>
                <span>+ Kategori</span>
            </button>
            <button onclick="openModal()" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl text-xs shadow-md shadow-rose-600/15 transition flex items-center gap-1.5 whitespace-nowrap">
                <i class="fa-solid fa-plus"></i>
                <span>Catat Belanja / Beban</span>
            </button>
        </div>
    </div>
</div>

<!-- Total Summary Header Card -->
<div class="p-5 bg-white border border-stone-200/90 rounded-3xl shadow-sm mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div class="flex items-center gap-3.5">
        <div class="w-11 h-11 rounded-2xl bg-rose-100 text-rose-700 flex items-center justify-center text-lg font-bold">
            <i class="fa-solid fa-wallet"></i>
        </div>
        <div>
            <span class="text-xs text-stone-500 font-medium">Total Beban Belanja Periode Ini:</span>
            <div class="text-2xl font-black text-rose-600 tracking-tight"><?= formatRupiah($totalExpenses) ?></div>
        </div>
    </div>
    <div class="text-xs text-stone-500">
        Periode: <strong><?= formatDate($startDate) ?></strong> s/d <strong><?= formatDate($endDate) ?></strong> (<?= count($expenses) ?> transaksi)
    </div>
</div>

<!-- Table of Expenses -->
<div class="bg-white rounded-3xl border border-stone-200/90 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
            <thead>
                <tr class="bg-stone-50 text-stone-600 border-b border-stone-200">
                    <th class="py-3.5 px-4 font-bold">Kode / Tanggal</th>
                    <th class="py-3.5 px-4 font-bold">Kategori</th>
                    <th class="py-3.5 px-4 font-bold">Uraian Pengeluaran</th>
                    <th class="py-3.5 px-4 font-bold">Metode Bayar</th>
                    <th class="py-3.5 px-4 font-bold text-right">Nominal Beban</th>
                    <th class="py-3.5 px-4 font-bold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                <?php if (empty($expenses)): ?>
                    <tr>
                        <td colspan="6" class="py-10 text-center text-stone-400">
                            <i class="fa-solid fa-receipt text-3xl mb-2 text-stone-300"></i>
                            <p>Tidak ada catatan pengeluaran pada rentang waktu ini.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($expenses as $e): ?>
                        <tr class="hover:bg-stone-50/80 transition">
                            <td class="py-3.5 px-4">
                                <span class="font-bold text-stone-800 block font-mono"><?= htmlspecialchars($e['expense_code']) ?></span>
                                <span class="text-[10px] text-stone-500"><?= formatDate($e['date']) ?></span>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2.5 py-1 bg-stone-100 text-stone-700 font-bold rounded-lg text-[11px] inline-block">
                                    <?= htmlspecialchars($e['category_name']) ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="font-bold text-stone-900 block text-xs"><?= htmlspecialchars($e['title']) ?></span>
                                <?php if (!empty($e['notes'])): ?>
                                    <span class="text-[11px] text-stone-400 block"><?= htmlspecialchars($e['notes']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 uppercase font-bold text-[10px] text-stone-500">
                                <?= htmlspecialchars($e['payment_method']) ?>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <span class="font-extrabold text-rose-600 text-xs block">- <?= formatRupiah($e['amount']) ?></span>
                            </td>
                            <td class="py-3.5 px-4 text-right space-x-1 whitespace-nowrap">
                                <button onclick='editExpense(<?= json_encode($e) ?>)' class="p-1.5 bg-stone-100 hover:bg-stone-200 text-stone-700 rounded-lg text-xs" title="Edit">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <button onclick="confirmDelete('pengeluaran.php?delete=<?= $e['id'] ?>', 'Yakin ingin menghapus catatan pengeluaran ini?')" class="p-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg text-xs" title="Hapus">
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

<!-- Modal Add/Edit Expense -->
<div id="expenseModal" class="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-stone-200">
        <div class="flex items-center justify-between pb-3 border-b border-stone-100">
            <h3 id="modalTitle" class="font-extrabold text-stone-800 text-sm">Catat Pengeluaran Toko</h3>
            <button onclick="closeModal()" class="text-stone-400 hover:text-stone-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="pengeluaran.php" class="space-y-3 py-4 text-xs">
            <input type="hidden" name="save_expense" value="1" />
            <input type="hidden" name="id" id="expenseId" value="0" />

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Tanggal *</label>
                    <input type="date" name="date" id="modalDate" value="<?= date('Y-m-d') ?>" required
                           class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none font-bold" />
                </div>
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Kategori Beban *</label>
                    <select name="category_id" id="modalCategory" required class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none font-semibold">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block font-bold text-stone-700 mb-1">Deskripsi Belanja / Beban *</label>
                <input type="text" name="title" id="modalTitleInput" required placeholder="Contoh: Beli Kertas HVS SiDU A4 75gr (5 Rim)"
                       class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none focus:ring-1 focus:ring-rose-500 font-semibold" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Nominal Biaya (Rp) *</label>
                    <input type="number" name="amount" id="modalAmount" required min="1" placeholder="250000"
                           class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none font-black text-rose-600" />
                </div>
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Metode Bayar</label>
                    <select name="payment_method" id="modalMethod" class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none font-semibold">
                        <option value="tunai">Tunai / Uang Laci Kas</option>
                        <option value="transfer">Transfer Bank</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block font-bold text-stone-700 mb-1">Catatan Tambahan / Toko Penjual</label>
                <textarea name="notes" id="modalNotes" rows="2" placeholder="Toko kertas sinar terang, bon no..."
                          class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-stone-100">
                <button type="button" onclick="closeModal()" class="px-4 py-2 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold rounded-xl">Batal</button>
                <button type="submit" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl shadow-md">Simpan Pengeluaran</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Add Category -->
<div id="categoryModal" class="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-stone-200">
        <div class="flex items-center justify-between pb-3 border-b border-stone-100">
            <h3 class="font-extrabold text-stone-800 text-sm">Tambah Kategori Beban</h3>
            <button onclick="closeCategoryModal()" class="text-stone-400 hover:text-stone-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="pengeluaran.php" class="space-y-3 py-4 text-xs">
            <input type="hidden" name="add_category" value="1" />
            <div>
                <label class="block font-bold text-stone-700 mb-1">Nama Kategori</label>
                <input type="text" name="category_name" required placeholder="Contoh: Pemeliharaan Komputer Desain"
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
        document.getElementById('modalTitle').innerText = 'Catat Pengeluaran Toko';
        document.getElementById('expenseId').value = '0';
        document.getElementById('modalTitleInput').value = '';
        document.getElementById('modalAmount').value = '';
        document.getElementById('modalDate').value = '<?= date('Y-m-d') ?>';
        document.getElementById('modalNotes').value = '';
        document.getElementById('expenseModal').classList.remove('hidden');
    }

    function editExpense(exp) {
        document.getElementById('modalTitle').innerText = 'Edit Pengeluaran';
        document.getElementById('expenseId').value = exp.id;
        document.getElementById('modalTitleInput').value = exp.title;
        document.getElementById('modalAmount').value = exp.amount;
        document.getElementById('modalCategory').value = exp.category_id;
        document.getElementById('modalMethod').value = exp.payment_method;
        document.getElementById('modalDate').value = exp.date;
        document.getElementById('modalNotes').value = exp.notes || '';
        document.getElementById('expenseModal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('expenseModal').classList.add('hidden');
    }

    function openCategoryModal() {
        document.getElementById('categoryModal').classList.remove('hidden');
    }
    function closeCategoryModal() {
        document.getElementById('categoryModal').classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
