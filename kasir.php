<?php
// kasir.php
require_once __DIR__ . '/config/app.php';
requireAuth();

$pageTitle = 'Kasir Cetak & Fotocopy';
$pageSubtitle = 'Input pesanan cetak dokumen, jilid, banner, dan transaksi kasir';
require_once __DIR__ . '/includes/header.php';

$pdo = Database::getConnection();

// Fetch categories
$categories = $pdo->query("SELECT * FROM categories_product ORDER BY id ASC")->fetchAll();

// Fetch products
$products = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories_product c ON p.category_id = c.id ORDER BY p.name ASC")->fetchAll();

// Fetch customers for selector
$customers = $pdo->query("SELECT * FROM customers ORDER BY name ASC")->fetchAll();
?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
    <!-- Left: Product Catalog & Selector (7 cols) -->
    <div class="lg:col-span-7 space-y-4">
        <!-- Search & Custom Item Bar -->
        <div class="bg-white p-4 rounded-2xl border border-stone-200/90 shadow-sm flex flex-col sm:flex-row gap-3 items-center justify-between">
            <div class="relative w-full sm:w-80">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-stone-400">
                    <i class="fa-solid fa-magnifying-glass text-xs"></i>
                </div>
                <input type="text" id="searchProduct" placeholder="Ketik nama layanan cetak..." 
                    class="w-full pl-9 pr-3 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:bg-white outline-none transition" />
            </div>

            <button type="button" onclick="openCustomItemModal()" class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-stone-100 hover:bg-stone-200 text-stone-800 rounded-xl text-xs font-bold transition">
                <i class="fa-solid fa-plus text-amber-700"></i>
                <span>+ Layanan Khusus / Custom</span>
            </button>
        </div>

        <!-- Category Filter Buttons -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none text-xs">
            <button type="button" onclick="filterCategory('all')" class="category-btn active px-3.5 py-2 rounded-xl font-bold transition bg-stone-900 text-amber-300 shadow-sm whitespace-nowrap" data-category="all">
                Semua Layanan
            </button>
            <?php foreach ($categories as $cat): ?>
                <button type="button" onclick="filterCategory(<?= $cat['id'] ?>)" class="category-btn px-3.5 py-2 rounded-xl font-semibold transition bg-white text-stone-600 border border-stone-200 hover:bg-stone-100 whitespace-nowrap" data-category="<?= $cat['id'] ?>">
                    <?= htmlspecialchars($cat['name']) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Product Cards Grid -->
        <div id="productGrid" class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-[580px] overflow-y-auto pr-1">
            <?php foreach ($products as $p): ?>
                <div class="product-item bg-white p-3.5 rounded-2xl border border-stone-200/90 hover:border-amber-400 hover:shadow-md transition cursor-pointer flex flex-col justify-between group"
                     data-id="<?= $p['id'] ?>"
                     data-name="<?= htmlspecialchars($p['name']) ?>"
                     data-price="<?= (float)$p['price'] ?>"
                     data-unit="<?= htmlspecialchars($p['unit']) ?>"
                     data-category="<?= $p['category_id'] ?>"
                     onclick="addToCart(<?= $p['id'] ?>, '<?= addslashes(htmlspecialchars($p['name'])) ?>', <?= (float)$p['price'] ?>, '<?= htmlspecialchars($p['unit']) ?>')">
                    
                    <div>
                        <div class="flex items-center justify-between text-[10px] text-stone-400 mb-1.5">
                            <span class="font-mono"><?= htmlspecialchars($p['code'] ?: 'CETAK') ?></span>
                            <span class="bg-stone-100 px-2 py-0.5 rounded-full text-stone-600 font-bold"><?= htmlspecialchars($p['unit']) ?></span>
                        </div>
                        <h4 class="font-bold text-xs text-stone-800 group-hover:text-amber-800 transition line-clamp-2 mb-2 leading-snug">
                            <?= htmlspecialchars($p['name']) ?>
                        </h4>
                    </div>

                    <div class="pt-2 border-t border-stone-100 flex items-center justify-between mt-auto">
                        <span class="font-black text-xs text-stone-900">
                            <?= formatRupiah($p['price']) ?>
                        </span>
                        <div class="w-7 h-7 rounded-xl bg-stone-100 text-stone-700 flex items-center justify-center text-xs group-hover:bg-stone-900 group-hover:text-amber-300 transition">
                            <i class="fa-solid fa-plus"></i>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Right: Kasir / Order Cart Panel (5 cols) styled like a warm paper receipt -->
    <div class="lg:col-span-5 bg-white rounded-3xl border border-stone-200/90 shadow-md p-5 sm:p-6 sticky top-0">
        <div class="flex items-center justify-between pb-3.5 border-b border-stone-200">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-900 flex items-center justify-center text-xs font-bold">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <h3 class="font-extrabold text-sm text-stone-800">Nota Pesanan</h3>
                    <p class="text-[10px] text-stone-400">Keranjang kasir saat ini</p>
                </div>
            </div>
            <button type="button" onclick="clearCart()" class="text-xs text-rose-600 hover:text-rose-700 font-bold">
                Kosongkan
            </button>
        </div>

        <!-- Customer Select / Input -->
        <div class="py-3 border-b border-stone-100 space-y-2">
            <div class="grid grid-cols-2 gap-2 text-xs">
                <div>
                    <label class="block text-[11px] font-bold text-stone-600 mb-1">Nama Pelanggan</label>
                    <input type="text" id="customerName" placeholder="Umum / Nama..." value="Pelanggan Umum"
                           class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-xl text-xs outline-none focus:ring-1 focus:ring-amber-500 font-medium" />
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-stone-600 mb-1">No. WhatsApp</label>
                    <input type="text" id="customerPhone" placeholder="08..."
                           class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-xl text-xs outline-none focus:ring-1 focus:ring-amber-500 font-medium" />
                </div>
            </div>
        </div>

        <!-- Cart Items List -->
        <div id="cartItemsList" class="my-3 max-h-56 overflow-y-auto divide-y divide-stone-100 space-y-2">
            <!-- Dynamic Cart Items Rendered Here -->
            <div id="emptyCartNotice" class="text-center py-10 text-stone-400 text-xs">
                <i class="fa-solid fa-basket-shopping text-3xl mb-2 text-stone-300"></i>
                <p>Belum ada item yang dipilih.<br>Klik layanan cetak di sebelah kiri.</p>
            </div>
        </div>

        <!-- Summary & Calculation -->
        <div class="pt-3 border-t border-stone-200 space-y-2.5 text-xs">
            <div class="flex justify-between items-center text-stone-600">
                <span class="font-medium">Subtotal</span>
                <span id="cartSubtotalText" class="font-bold text-stone-800">Rp 0</span>
            </div>
            <div class="flex justify-between items-center text-stone-600">
                <span class="font-medium">Potongan / Diskon</span>
                <div class="flex items-center gap-1.5 w-32">
                    <span class="text-stone-400 text-xs">Rp</span>
                    <input type="number" id="discountInput" value="0" min="0" oninput="renderCartSummary()"
                           class="w-full px-2 py-1 bg-stone-50 border border-stone-200 rounded-lg text-right text-xs outline-none focus:ring-1 focus:ring-amber-500 font-bold" />
                </div>
            </div>
            <div class="flex justify-between items-center pt-2 border-t border-stone-100 text-sm">
                <span class="font-black text-stone-800">Total Tagihan</span>
                <span id="cartGrandTotalText" class="font-black text-stone-900 text-base">Rp 0</span>
            </div>

            <!-- Payment Methods & Status -->
            <div class="pt-3 space-y-2.5 border-t border-stone-100">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[10px] font-bold text-stone-500 uppercase">Cara Bayar</label>
                        <select id="paymentMethod" class="w-full mt-1 px-2.5 py-1.5 bg-stone-50 border border-stone-200 rounded-xl text-xs outline-none font-semibold">
                            <option value="tunai">Tunai / Uang Pas</option>
                            <option value="transfer">Transfer Bank</option>
                            <option value="qris">QRIS / E-Wallet</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-stone-500 uppercase">Status Bayar</label>
                        <select id="paymentStatus" onchange="handlePaymentStatusChange()" class="w-full mt-1 px-2.5 py-1.5 bg-stone-50 border border-stone-200 rounded-xl text-xs outline-none font-semibold">
                            <option value="lunas">Lunas</option>
                            <option value="dp">Uang Muka (DP)</option>
                            <option value="belum_lunas">Belum Bayar</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 pt-1">
                    <div>
                        <label class="block text-[10px] font-bold text-stone-500 uppercase">Uang Diterima (Rp)</label>
                        <input type="number" id="paidAmountInput" value="0" min="0" oninput="renderCartSummary()"
                               class="w-full mt-1 px-2.5 py-1.5 bg-stone-50 border border-stone-200 rounded-xl text-xs outline-none font-bold text-stone-800" />
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-stone-500 uppercase">Kembalian / Sisa</label>
                        <div id="changeAmountText" class="w-full mt-1 px-2.5 py-1.5 bg-stone-100 border border-stone-200 rounded-xl text-xs font-bold text-stone-700">
                            Rp 0
                        </div>
                    </div>
                </div>

                <!-- Friendly Cash Presets -->
                <div class="flex items-center gap-1 pt-1 text-[11px] overflow-x-auto scrollbar-none">
                    <span class="text-stone-400 text-[10px] mr-1">Uang:</span>
                    <button type="button" onclick="setExactAmount()" class="px-2 py-0.5 bg-stone-100 hover:bg-stone-200 rounded-lg font-bold text-stone-700">Pas</button>
                    <button type="button" onclick="setCashPreset(10000)" class="px-2 py-0.5 bg-stone-100 hover:bg-stone-200 rounded-lg font-semibold text-stone-700">10rb</button>
                    <button type="button" onclick="setCashPreset(20000)" class="px-2 py-0.5 bg-stone-100 hover:bg-stone-200 rounded-lg font-semibold text-stone-700">20rb</button>
                    <button type="button" onclick="setCashPreset(50000)" class="px-2 py-0.5 bg-stone-100 hover:bg-stone-200 rounded-lg font-semibold text-stone-700">50rb</button>
                    <button type="button" onclick="setCashPreset(100000)" class="px-2 py-0.5 bg-stone-100 hover:bg-stone-200 rounded-lg font-semibold text-stone-700">100rb</button>
                </div>
            </div>

            <!-- Notes -->
            <div class="pt-1">
                <input type="text" id="orderNotes" placeholder="Catatan pesanan (misal: jilid mika biru, finishing doff)..."
                       class="w-full px-3 py-1.5 bg-stone-50 border border-stone-200 rounded-xl text-xs outline-none text-stone-600" />
            </div>

            <!-- Process Order Button -->
            <div class="pt-2">
                <button type="button" id="btnSubmitOrder" onclick="processOrderCheckout()" 
                        class="w-full py-3 bg-stone-900 hover:bg-stone-800 text-amber-300 font-bold rounded-2xl text-xs shadow-md shadow-stone-900/15 transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-check"></i>
                    <span>Simpan & Selesaikan Transaksi</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Custom Item -->
<div id="customItemModal" class="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-stone-200">
        <div class="flex items-center justify-between pb-3 border-b border-stone-100">
            <h3 class="font-extrabold text-stone-800 text-sm">Layanan Khusus / Custom</h3>
            <button onclick="closeCustomItemModal()" class="text-stone-400 hover:text-stone-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="space-y-3 py-4 text-xs">
            <div>
                <label class="block font-bold text-stone-700 mb-1">Nama Layanan / Cetakan</label>
                <input type="text" id="customName" placeholder="Contoh: Cetak Sertifikat Kertas Linen A4" class="w-full px-3 py-2 bg-stone-50 border border-stone-200 rounded-xl outline-none focus:ring-1 focus:ring-amber-500 font-semibold" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Jumlah</label>
                    <input type="number" id="customQty" value="1" min="0.1" step="any" class="w-full px-3 py-2 bg-stone-50 border border-stone-200 rounded-xl outline-none font-bold" />
                </div>
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Satuan</label>
                    <input type="text" id="customUnit" value="lembar" placeholder="lembar/pcs" class="w-full px-3 py-2 bg-stone-50 border border-stone-200 rounded-xl outline-none" />
                </div>
            </div>
            <div>
                <label class="block font-bold text-stone-700 mb-1">Harga Satuan (Rp)</label>
                <input type="number" id="customPrice" placeholder="5000" min="0" class="w-full px-3 py-2 bg-stone-50 border border-stone-200 rounded-xl outline-none font-bold text-stone-900" />
            </div>
        </div>
        <div class="flex justify-end gap-2 pt-2 border-t border-stone-100">
            <button type="button" onclick="closeCustomItemModal()" class="px-4 py-2 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold rounded-xl text-xs">Batal</button>
            <button type="button" onclick="addCustomItemToCart()" class="px-4 py-2 bg-stone-900 hover:bg-stone-800 text-amber-300 font-bold rounded-xl text-xs">Tambahkan</button>
        </div>
    </div>
</div>

<script>
    let cart = [];

    function addToCart(productId, name, price, unit) {
        let existing = cart.find(item => item.productId === productId && item.isCustom === false);
        if (existing) {
            existing.qty += 1;
            existing.subtotal = existing.qty * existing.price;
        } else {
            cart.push({
                productId: productId,
                isCustom: false,
                name: name,
                price: parseFloat(price),
                qty: 1,
                unit: unit,
                subtotal: parseFloat(price),
                notes: ''
            });
        }
        renderCart();
    }

    function addCustomItemToCart() {
        const name = document.getElementById('customName').value.trim();
        const qty = parseFloat(document.getElementById('customQty').value) || 1;
        const unit = document.getElementById('customUnit').value.trim() || 'pcs';
        const price = parseFloat(document.getElementById('customPrice').value) || 0;

        if (!name) {
            showToast('Silakan tulis nama layanan / cetakan.', 'warning');
            return;
        }
        if (price <= 0) {
            showToast('Silakan isi tarif harga yang valid.', 'warning');
            return;
        }

        cart.push({
            productId: null,
            isCustom: true,
            name: name,
            price: price,
            qty: qty,
            unit: unit,
            subtotal: qty * price,
            notes: ''
        });

        closeCustomItemModal();
        renderCart();
        showToast('Item kustom ditambahkan ke nota', 'success');
    }

    function updateCartItemQty(index, newQty) {
        let qty = parseFloat(newQty);
        if (isNaN(qty) || qty <= 0) {
            removeCartItem(index);
            return;
        }
        cart[index].qty = qty;
        cart[index].subtotal = cart[index].qty * cart[index].price;
        renderCart();
    }

    function updateCartItemNotes(index, notes) {
        cart[index].notes = notes;
    }

    function removeCartItem(index) {
        cart.splice(index, 1);
        renderCart();
        showToast('Item dihapus dari nota', 'info');
    }

    function clearCart() {
        if (cart.length === 0) return;
        Swal.fire({
            title: 'Kosongkan Nota?',
            text: 'Semua item cetakan yang sudah dimasukkan ke nota akan dibersihkan.',
            icon: 'warning',
            iconColor: '#e11d48',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#78716c',
            confirmButtonText: '<i class="fa-solid fa-trash-can mr-1.5"></i> Ya, Kosongkan',
            cancelButtonText: 'Batal',
            reverseButtons: true,
            customClass: {
                popup: 'rounded-3xl border border-stone-200/90 shadow-2xl p-6 font-sans bg-white',
                title: 'text-base font-extrabold text-stone-900',
                htmlContainer: 'text-xs text-stone-500 font-medium leading-relaxed mt-2',
                confirmButton: 'rounded-xl text-xs font-bold px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white shadow-sm border-0',
                cancelButton: 'rounded-xl text-xs font-bold px-4 py-2.5 bg-stone-100 hover:bg-stone-200 text-stone-700 border-0'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                cart = [];
                renderCart();
                showToast('Nota kasir telah dikosongkan', 'info');
            }
        });
    }

    function renderCart() {
        const list = document.getElementById('cartItemsList');

        if (cart.length === 0) {
            list.innerHTML = `
                <div id="emptyCartNotice" class="text-center py-10 text-stone-400 text-xs">
                    <i class="fa-solid fa-basket-shopping text-3xl mb-2 text-stone-300"></i>
                    <p>Belum ada item yang dipilih.<br>Klik layanan cetak di sebelah kiri.</p>
                </div>
            `;
            renderCartSummary();
            return;
        }

        let html = '';
        cart.forEach((item, index) => {
            html += `
                <div class="py-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <span class="font-bold text-xs text-stone-800 truncate block">${item.name}</span>
                            <span class="text-[10px] text-stone-400">@ Rp ${item.price.toLocaleString('id-ID')} / ${item.unit}</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <button type="button" onclick="updateCartItemQty(${index}, ${item.qty - 1})" class="w-6 h-6 rounded-lg bg-stone-100 hover:bg-stone-200 text-stone-700 flex items-center justify-center text-xs font-bold">-</button>
                            <input type="number" value="${item.qty}" min="0.1" step="any" onchange="updateCartItemQty(${index}, this.value)" 
                                   class="w-11 text-center text-xs py-0.5 bg-stone-50 border border-stone-200 rounded-lg font-bold" />
                            <button type="button" onclick="updateCartItemQty(${index}, ${item.qty + 1})" class="w-6 h-6 rounded-lg bg-stone-100 hover:bg-stone-200 text-stone-700 flex items-center justify-center text-xs font-bold">+</button>
                        </div>
                        <div class="text-right min-w-[75px]">
                            <span class="font-black text-xs text-stone-900 block">Rp ${item.subtotal.toLocaleString('id-ID')}</span>
                            <button type="button" onclick="removeCartItem(${index})" class="text-[10px] text-rose-500 hover:underline">Hapus</button>
                        </div>
                    </div>
                    <div class="mt-1">
                        <input type="text" placeholder="Catatan finishing / ukuran..." value="${item.notes || ''}" onchange="updateCartItemNotes(${index}, this.value)" 
                               class="w-full text-[11px] px-2 py-0.5 bg-stone-50 border border-stone-150 rounded-lg text-stone-600 placeholder-stone-300" />
                    </div>
                </div>
            `;
        });

        list.innerHTML = html;
        renderCartSummary();
    }

    function renderCartSummary() {
        let subtotal = cart.reduce((sum, item) => sum + item.subtotal, 0);
        let discount = parseFloat(document.getElementById('discountInput').value) || 0;
        let grandTotal = Math.max(0, subtotal - discount);

        document.getElementById('cartSubtotalText').innerText = 'Rp ' + subtotal.toLocaleString('id-ID');
        document.getElementById('cartGrandTotalText').innerText = 'Rp ' + grandTotal.toLocaleString('id-ID');

        let paymentStatus = document.getElementById('paymentStatus').value;
        let paidInput = document.getElementById('paidAmountInput');

        if (paymentStatus === 'lunas' && (parseFloat(paidInput.value) === 0 || parseFloat(paidInput.dataset.autoFilled || 0) === 1)) {
            paidInput.value = grandTotal;
            paidInput.dataset.autoFilled = 1;
        }

        let paidAmount = parseFloat(paidInput.value) || 0;
        let change = paidAmount - grandTotal;
        let changeEl = document.getElementById('changeAmountText');

        if (change >= 0) {
            changeEl.innerHTML = `<span class="text-emerald-700 font-bold">Kembali: Rp ${change.toLocaleString('id-ID')}</span>`;
        } else {
            changeEl.innerHTML = `<span class="text-rose-600 font-bold">Kurang: Rp ${Math.abs(change).toLocaleString('id-ID')}</span>`;
        }
    }

    function handlePaymentStatusChange() {
        let status = document.getElementById('paymentStatus').value;
        let grandTotal = Math.max(0, (cart.reduce((sum, item) => sum + item.subtotal, 0)) - (parseFloat(document.getElementById('discountInput').value) || 0));
        let paidInput = document.getElementById('paidAmountInput');

        if (status === 'lunas') {
            paidInput.value = grandTotal;
        } else if (status === 'belum_lunas') {
            paidInput.value = 0;
        } else if (status === 'dp') {
            paidInput.value = Math.round(grandTotal / 2);
        }
        renderCartSummary();
    }

    function setExactAmount() {
        let subtotal = cart.reduce((sum, item) => sum + item.subtotal, 0);
        let discount = parseFloat(document.getElementById('discountInput').value) || 0;
        let grandTotal = Math.max(0, subtotal - discount);
        document.getElementById('paidAmountInput').value = grandTotal;
        renderCartSummary();
    }

    function setCashPreset(amount) {
        document.getElementById('paidAmountInput').value = amount;
        renderCartSummary();
    }

    // Filter category
    function filterCategory(catId) {
        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.classList.remove('bg-stone-900', 'text-amber-300', 'shadow-sm');
            btn.classList.add('bg-white', 'text-stone-600');
        });
        const activeBtn = document.querySelector(`.category-btn[data-category="${catId}"]`);
        if (activeBtn) {
            activeBtn.classList.remove('bg-white', 'text-stone-600');
            activeBtn.classList.add('bg-stone-900', 'text-amber-300', 'shadow-sm');
        }

        const items = document.querySelectorAll('.product-item');
        items.forEach(item => {
            if (catId === 'all' || item.dataset.category == catId) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }

    // Product search
    document.getElementById('searchProduct').addEventListener('input', function() {
        const query = this.value.toLowerCase();
        document.querySelectorAll('.product-item').forEach(item => {
            const name = item.dataset.name.toLowerCase();
            if (name.includes(query)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    });

    // Modal Custom Item
    function openCustomItemModal() {
        document.getElementById('customItemModal').classList.remove('hidden');
        document.getElementById('customName').focus();
    }
    function closeCustomItemModal() {
        document.getElementById('customItemModal').classList.add('hidden');
        document.getElementById('customName').value = '';
        document.getElementById('customPrice').value = '';
        document.getElementById('customQty').value = '1';
    }

    // Process Checkout
    async function processOrderCheckout() {
        if (cart.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Nota Masih Kosong',
                text: 'Silakan klik layanan cetak atau fotocopy terlebih dahulu.'
            });
            return;
        }

        const customerName = document.getElementById('customerName').value.trim() || 'Pelanggan Umum';
        const customerPhone = document.getElementById('customerPhone').value.trim();
        const discount = parseFloat(document.getElementById('discountInput').value) || 0;
        const paymentMethod = document.getElementById('paymentMethod').value;
        const paymentStatus = document.getElementById('paymentStatus').value;
        const paidAmount = parseFloat(document.getElementById('paidAmountInput').value) || 0;
        const notes = document.getElementById('orderNotes').value.trim();

        const btn = document.getElementById('btnSubmitOrder');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan nota...';

        try {
            const response = await fetch('ajax/save_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    customer_name: customerName,
                    customer_phone: customerPhone,
                    discount: discount,
                    payment_method: paymentMethod,
                    payment_status: paymentStatus,
                    paid_amount: paidAmount,
                    notes: notes,
                    items: cart
                })
            });

            const res = await response.json();
            if (res.success) {
                Swal.fire({
                    title: 'Transaksi Berhasil Disimpan! 🎉',
                    html: `
                        <div class="text-left text-xs space-y-2 mb-4 bg-stone-50 p-3.5 rounded-2xl border border-stone-200">
                            <p><strong>No. Invoice:</strong> <span class="font-bold text-amber-800">${res.invoice_number}</span></p>
                            <p><strong>Total Tagihan:</strong> Rp ${res.grand_total.toLocaleString('id-ID')}</p>
                            <p><strong>Status Bayar:</strong> <span class="uppercase font-bold text-emerald-700">${res.payment_status}</span></p>
                        </div>
                        <p class="text-xs text-stone-500 mb-2 font-medium">Cetak atau kirim nota ke pelanggan:</p>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <a href="print_nota.php?id=${res.order_id}&type=thermal" target="_blank" class="py-2.5 px-3 bg-stone-900 hover:bg-stone-800 text-amber-300 font-bold rounded-xl transition text-center flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-receipt"></i> Struk Thermal
                            </a>
                            <a href="print_nota.php?id=${res.order_id}&type=standard" target="_blank" class="py-2.5 px-3 bg-stone-100 hover:bg-stone-200 text-stone-800 font-bold rounded-xl transition text-center flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-file-invoice"></i> Nota Standar
                            </a>
                        </div>
                        ${customerPhone ? `
                            <div class="mt-2.5">
                                <a href="${res.wa_link}" target="_blank" class="w-full py-2.5 px-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl transition text-center flex items-center justify-center gap-1.5 text-xs">
                                    <i class="fa-brands fa-whatsapp text-sm"></i> Kirim Struk ke WhatsApp Pelanggan
                                </a>
                            </div>
                        ` : ''}
                    `,
                    icon: 'success',
                    showConfirmButton: true,
                    confirmButtonText: 'Selesai & Transaksi Baru',
                    confirmButtonColor: '#292524',
                    customClass: {
                        popup: 'rounded-3xl',
                        confirmButton: 'rounded-xl text-xs font-bold px-5 py-2.5'
                    }
                }).then(() => {
                    cart = [];
                    document.getElementById('discountInput').value = 0;
                    document.getElementById('paidAmountInput').value = 0;
                    document.getElementById('orderNotes').value = '';
                    renderCart();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Menyimpan',
                    text: res.message || 'Terjadi kesalahan sistem.'
                });
            }
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Error Koneksi',
                text: err.message
            });
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-check"></i><span>Simpan & Selesaikan Transaksi</span>';
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
