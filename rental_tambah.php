<?php
// rental_tambah.php
require_once __DIR__ . '/config/app.php';
requireAuth();

$pdo = Database::getConnection();

// Fetch available acrylic items
$acrylics = $pdo->query("SELECT * FROM acrylic_items WHERE status = 'available' ORDER BY name ASC")->fetchAll();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acrylicId = (int)$_POST['acrylic_id'];
    $customerName = trim($_POST['customer_name']);
    $customerPhone = trim($_POST['customer_phone']);
    $customerAddress = trim($_POST['customer_address'] ?? '');
    $eventName = trim($_POST['event_name'] ?? '');
    $eventDate = !empty($_POST['event_date']) ? $_POST['event_date'] : null;
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $customStickerFee = (float)($_POST['custom_sticker_fee'] ?? 0);
    $stickerText = trim($_POST['sticker_text'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? 'tunai';
    $paidAmount = (float)($_POST['paid_amount'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if (empty($acrylicId) || empty($customerName) || empty($customerPhone) || empty($startDate) || empty($endDate)) {
        setFlash('error', 'Semua data bertanda bintang (*) wajib diisi ya kak.');
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM acrylic_items WHERE id = ?");
            $stmt->execute([$acrylicId]);
            $item = $stmt->fetch();

            if (!$item) {
                throw new Exception("Papan akrilik tidak ditemukan.");
            }

            $startTs = strtotime($startDate);
            $endTs = strtotime($endDate);
            $diffDays = max(1, (int)ceil(($endTs - $startTs) / 86400));

            $rentalPricePerDay = (float)$item['rental_price_per_day'];
            $totalRentalPrice = $diffDays * $rentalPricePerDay;
            $depositAmount = (float)$item['deposit_amount'];
            $totalCost = $totalRentalPrice + $customStickerFee;

            $rentalCode = generateRentalCode($pdo);
            $userId = $_SESSION['user_id'] ?? null;
            $now = date('Y-m-d H:i:s');

            $custStmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ? LIMIT 1");
            $custStmt->execute([$customerPhone]);
            $customerId = $custStmt->fetchColumn();

            if (!$customerId) {
                $insCust = $pdo->prepare("INSERT INTO customers (name, phone, address) VALUES (?, ?, ?)");
                $insCust->execute([$customerName, $customerPhone, $customerAddress]);
                $customerId = $pdo->lastInsertId();
            }

            $fullNotes = $notes;
            if (!empty($stickerText)) {
                $fullNotes = ($fullNotes ? $fullNotes . "\n" : '') . "Desain Tulisan: " . $stickerText;
            }

            $insRental = $pdo->prepare("INSERT INTO acrylic_rentals 
                (rental_code, customer_id, customer_name, customer_phone, acrylic_id, event_name, event_date, start_date, end_date, duration_days, rental_price, custom_sticker_fee, deposit_amount, total_cost, paid_amount, status, payment_method, notes, user_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $insRental->execute([
                $rentalCode,
                $customerId,
                $customerName,
                $customerPhone,
                $acrylicId,
                $eventName,
                $eventDate,
                $startDate,
                $endDate,
                $diffDays,
                $totalRentalPrice,
                $customStickerFee,
                $depositAmount,
                $totalCost,
                $paidAmount,
                $status,
                $paymentMethod,
                $fullNotes,
                $userId,
                $now
            ]);

            $rentalId = $pdo->lastInsertId();

            if ($status === 'active' || $status === 'booked') {
                $pdo->prepare("UPDATE acrylic_items SET status = 'rented' WHERE id = ?")->execute([$acrylicId]);
            }

            $pdo->commit();

            setFlash('success', "Penyewaan akrilik {$rentalCode} berhasil disimpan!");
            header("Location: rental_detail.php?id={$rentalId}");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('error', "Gagal menyimpan: " . $e->getMessage());
        }
    }
}

$pageTitle = 'Catat Sewa Papan Akrilik';
$pageSubtitle = 'Input penyewaan welcome board, tripod berdiri, easel kayu & kustomisasi stiker';
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-3xl border border-stone-200/90 shadow-sm p-6 sm:p-8">
        <div class="flex items-center justify-between pb-5 border-b border-stone-200 mb-6">
            <div>
                <h3 class="text-lg font-black text-stone-900 serif-title">Formulir Sewa Papan Akrilik & Standing</h3>
                <p class="text-xs text-stone-500">Catat pesanan sewa welcome sign event pernikahan, lamaran, atau wisuda</p>
            </div>
            <a href="rental_akrilik.php" class="px-3.5 py-1.5 bg-stone-100 hover:bg-stone-200 text-stone-700 rounded-xl text-xs font-bold transition">
                <i class="fa-solid fa-arrow-left"></i> Kembali
            </a>
        </div>

        <form method="POST" action="rental_tambah.php" id="rentalForm" class="space-y-6 text-xs">
            <!-- 1. Pilih Papan Akrilik -->
            <div class="bg-stone-50/70 p-5 rounded-2xl border border-stone-200">
                <h4 class="font-extrabold text-stone-800 text-sm mb-1 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-stone-900 text-amber-300 text-xs flex items-center justify-center font-bold">1</span>
                    <span>Pilih Papan Akrilik & Standing Tersedia</span>
                </h4>
                <p class="text-[11px] text-stone-500 mb-4 ml-8">Pilih model akrilik yang cocok dengan konsep acara customer</p>

                <?php if (empty($acrylics)): ?>
                    <div class="p-4 bg-amber-50 border border-amber-200 text-amber-900 rounded-xl text-xs">
                        <i class="fa-solid fa-bell mr-1"></i>
                        Semua stok papan akrilik saat ini sedang disewa. 
                        <a href="akrilik_master.php" class="font-bold underline ml-1">Kelola master papan di sini &rarr;</a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" id="acrylicOptions">
                        <?php foreach ($acrylics as $acr): ?>
                            <label class="acrylic-radio-card p-4 bg-white border-2 border-stone-200 rounded-2xl cursor-pointer hover:border-amber-500 transition flex flex-col justify-between">
                                <div class="flex items-start gap-3">
                                    <input type="radio" name="acrylic_id" value="<?= $acr['id'] ?>" 
                                           data-price="<?= (float)$acr['rental_price_per_day'] ?>"
                                           data-deposit="<?= (float)$acr['deposit_amount'] ?>"
                                           class="mt-1 text-amber-600 focus:ring-amber-500" required onchange="calculateRentalTotals()" />
                                    <div>
                                        <div class="font-bold text-stone-900 text-xs"><?= htmlspecialchars($acr['name']) ?></div>
                                        <span class="text-[10px] text-stone-500 block mt-0.5"><?= htmlspecialchars($acr['stand_type'] ?: 'Standard') ?> • <?= htmlspecialchars($acr['size']) ?></span>
                                    </div>
                                </div>
                                <div class="mt-3 pt-2.5 border-t border-stone-100 flex items-center justify-between text-[11px]">
                                    <span class="font-black text-amber-800"><?= formatRupiah($acr['rental_price_per_day']) ?> <span class="text-[9px] text-stone-400 font-normal">/hari</span></span>
                                    <span class="text-stone-500 text-[10px] font-medium">Jaminan: <?= formatRupiah($acr['deposit_amount']) ?></span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 2. Data Pelanggan & Acara -->
            <div class="bg-stone-50/70 p-5 rounded-2xl border border-stone-200">
                <h4 class="font-extrabold text-stone-800 text-sm mb-1 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-stone-900 text-amber-300 text-xs flex items-center justify-center font-bold">2</span>
                    <span>Data Penyewa & Keterangan Acara</span>
                </h4>
                <p class="text-[11px] text-stone-500 mb-4 ml-8">Kontak untuk koordinasi dan pengembalian unit</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Nama Penyewa / Pengantin *</label>
                        <input type="text" name="customer_name" required placeholder="Contoh: Dian Puspitasari"
                               class="w-full px-3 py-2 bg-white border border-stone-300 rounded-xl outline-none focus:ring-1 focus:ring-amber-500 font-medium" />
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Nomor WhatsApp Aktif *</label>
                        <input type="text" name="customer_phone" required placeholder="Contoh: 081234567890"
                               class="w-full px-3 py-2 bg-white border border-stone-300 rounded-xl outline-none focus:ring-1 focus:ring-amber-500 font-medium" />
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Nama Acara / Event</label>
                        <input type="text" name="event_name" placeholder="Contoh: Wedding Dian & Rendy / Engagement Day"
                               class="w-full px-3 py-2 bg-white border border-stone-300 rounded-xl outline-none focus:ring-1 focus:ring-amber-500 font-medium" />
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Tanggal Hari H Acara</label>
                        <input type="date" name="event_date"
                               class="w-full px-3 py-2 bg-white border border-stone-300 rounded-xl outline-none focus:ring-1 focus:ring-amber-500 font-medium" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block font-bold text-stone-700 mb-1">Alamat Penyewa (Opsional)</label>
                        <input type="text" name="customer_address" placeholder="Alamat rumah / domisili pengantaran"
                               class="w-full px-3 py-2 bg-white border border-stone-300 rounded-xl outline-none focus:ring-1 focus:ring-amber-500 font-medium" />
                    </div>
                </div>
            </div>

            <!-- 3. Jadwal Sewa & Durasi -->
            <div class="bg-stone-50/70 p-5 rounded-2xl border border-stone-200">
                <h4 class="font-extrabold text-stone-800 text-sm mb-1 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-stone-900 text-amber-300 text-xs flex items-center justify-center font-bold">3</span>
                    <span>Jadwal Pengambilan & Pengembalian</span>
                </h4>
                <p class="text-[11px] text-stone-500 mb-4 ml-8">Sistem akan otomatis menghitung durasi hari sewa</p>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-center">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Tanggal Ambil (Mulai) *</label>
                        <input type="date" name="start_date" id="startDate" required value="<?= date('Y-m-d') ?>" onchange="calculateRentalTotals()"
                               class="w-full px-3 py-2 bg-white border border-stone-300 rounded-xl outline-none focus:ring-1 focus:ring-amber-500 font-semibold" />
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Tanggal Kembali *</label>
                        <input type="date" name="end_date" id="endDate" required value="<?= date('Y-m-d', strtotime('+1 day')) ?>" onchange="calculateRentalTotals()"
                               class="w-full px-3 py-2 bg-white border border-stone-300 rounded-xl outline-none focus:ring-1 focus:ring-amber-500 font-semibold" />
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Lama Sewa</label>
                        <div id="durationText" class="w-full px-3 py-2 bg-stone-200 border border-stone-300 rounded-xl font-bold text-stone-800">
                            1 Hari
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Layanan Tambahan (Cetak & Cutting Stiker Tulisan) -->
            <div class="bg-stone-50/70 p-5 rounded-2xl border border-stone-200">
                <div class="flex items-center justify-between mb-1">
                    <h4 class="font-extrabold text-stone-800 text-sm flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-stone-900 text-amber-300 text-xs flex items-center justify-center font-bold">4</span>
                        <span>Jasa Cetak Stiker Huruf Akrilik</span>
                    </h4>
                    <span class="px-2 py-0.5 bg-amber-100 text-amber-900 rounded-full text-[10px] font-bold">Pemasukan Percetakan</span>
                </div>
                <p class="text-[11px] text-stone-500 mb-3 ml-8">Tambahkan jasa cetak & cutting sticker tulisan ucapan (nama pasangan, quotes, dll.)</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Biaya Cetak Stiker (Rp)</label>
                        <input type="number" name="custom_sticker_fee" id="stickerFee" value="35000" min="0" oninput="calculateRentalTotals()"
                               class="w-full px-3 py-2 bg-white border border-stone-300 rounded-xl outline-none focus:ring-1 focus:ring-amber-500 font-bold text-stone-900" />
                    </div>
                    <div>
                        <label class="block font-bold text-stone-700 mb-1">Konsep / Teks Tulisan Akrilik</label>
                        <input type="text" name="sticker_text" placeholder="Contoh: Welcome to the Wedding of Dian & Rendy"
                               class="w-full px-3 py-2 bg-white border border-stone-300 rounded-xl outline-none focus:ring-1 focus:ring-amber-500" />
                    </div>
                </div>
            </div>

            <!-- 5. Rincian Pembayaran & Status -->
            <div class="bg-stone-900 text-white p-6 rounded-3xl shadow-sm">
                <h4 class="font-extrabold text-sm mb-4 flex items-center gap-2 text-amber-300">
                    <i class="fa-solid fa-calculator"></i>
                    <span>Rincian Biaya Sewa & Uang Jaminan (Deposit)</span>
                </h4>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-6 pb-6 border-b border-stone-800 text-xs">
                    <div>
                        <span class="text-stone-400 block mb-1">Sewa Papan & Stand:</span>
                        <div id="summaryRentalPrice" class="text-base font-bold text-white">Rp 0</div>
                        <span id="summaryRentalDays" class="text-[10px] text-stone-400">0 hari x Rp 0</span>
                    </div>
                    <div>
                        <span class="text-stone-400 block mb-1">Jasa Cetak Stiker:</span>
                        <div id="summaryStickerFee" class="text-base font-bold text-white">Rp 35.000</div>
                        <span class="text-[10px] text-stone-400">Cutting & pasang stiker</span>
                    </div>
                    <div>
                        <span class="text-stone-400 block mb-1">Uang Jaminan (Deposit):</span>
                        <div id="summaryDeposit" class="text-base font-bold text-amber-300">Rp 0</div>
                        <span class="text-[10px] text-stone-400 italic">*Dikembalikan saat papan kembali utuh</span>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <span class="text-xs text-stone-300 uppercase font-bold tracking-wider">Total Biaya Sewa (Omset Toko)</span>
                        <div id="summaryTotalCost" class="text-2xl font-black text-amber-300">Rp 0</div>
                    </div>
                    <div>
                        <span class="text-xs text-stone-300 uppercase font-bold tracking-wider">Total yang Dibayarkan Pelanggan</span>
                        <div id="summaryTotalToPay" class="text-xl font-black text-white">Rp 0 (Sewa + Deposit)</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4 border-t border-stone-800 text-xs text-stone-800">
                    <div>
                        <label class="block font-bold text-stone-300 mb-1">Status Awal</label>
                        <select name="status" class="w-full px-3 py-2 bg-white rounded-xl outline-none font-bold">
                            <option value="active">Sedang Disewa (Diambil Hari Ini)</option>
                            <option value="booked">Dipesan / Booking (Jadwal Mendatang)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-stone-300 mb-1">Metode Pembayaran</label>
                        <select name="payment_method" class="w-full px-3 py-2 bg-white rounded-xl outline-none font-bold">
                            <option value="tunai">Tunai / Cash</option>
                            <option value="transfer">Transfer Bank</option>
                            <option value="qris">QRIS / E-Wallet</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-stone-300 mb-1">Uang yang Diterima (Rp)</label>
                        <input type="number" name="paid_amount" id="paidAmount" value="0" min="0"
                               class="w-full px-3 py-2 bg-white rounded-xl outline-none font-black text-stone-900" />
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label class="block font-bold text-stone-700 mb-1">Catatan Tambahan (Opsional)</label>
                <textarea name="notes" rows="2" placeholder="Catatan kondisi papan, baut easel, bunga hiasan..."
                          class="w-full px-3 py-2 bg-stone-50 border border-stone-200 rounded-xl outline-none text-stone-600"></textarea>
            </div>

            <!-- Submit Button -->
            <div class="pt-4 flex items-center justify-end gap-3 border-t border-stone-200">
                <a href="rental_akrilik.php" class="px-5 py-2.5 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold rounded-xl transition">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2.5 bg-stone-900 hover:bg-stone-800 text-amber-300 font-bold rounded-2xl shadow-md transition flex items-center gap-2">
                    <i class="fa-solid fa-check"></i>
                    <span>Simpan Sewa Papan</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function calculateRentalTotals() {
        const selectedRadio = document.querySelector('input[name="acrylic_id"]:checked');
        if (!selectedRadio) return;

        const pricePerDay = parseFloat(selectedRadio.dataset.price) || 0;
        const depositAmount = parseFloat(selectedRadio.dataset.deposit) || 0;
        const stickerFee = parseFloat(document.getElementById('stickerFee').value) || 0;

        const startDateVal = document.getElementById('startDate').value;
        const endDateVal = document.getElementById('endDate').value;

        let diffDays = 1;
        if (startDateVal && endDateVal) {
            const start = new Date(startDateVal);
            const end = new Date(endDateVal);
            const diffTime = end - start;
            diffDays = Math.max(1, Math.ceil(diffTime / (1000 * 60 * 60 * 24)));
        }

        document.getElementById('durationText').innerText = diffDays + ' Hari';

        const totalRentalPrice = diffDays * pricePerDay;
        const totalCost = totalRentalPrice + stickerFee;
        const totalToPay = totalCost + depositAmount;

        document.getElementById('summaryRentalPrice').innerText = 'Rp ' + totalRentalPrice.toLocaleString('id-ID');
        document.getElementById('summaryRentalDays').innerText = diffDays + ' hari x Rp ' + pricePerDay.toLocaleString('id-ID');
        document.getElementById('summaryStickerFee').innerText = 'Rp ' + stickerFee.toLocaleString('id-ID');
        document.getElementById('summaryDeposit').innerText = 'Rp ' + depositAmount.toLocaleString('id-ID');
        document.getElementById('summaryTotalCost').innerText = 'Rp ' + totalCost.toLocaleString('id-ID');
        document.getElementById('summaryTotalToPay').innerText = 'Rp ' + totalToPay.toLocaleString('id-ID') + ' (Sewa + Deposit)';

        const paidInput = document.getElementById('paidAmount');
        if (parseFloat(paidInput.value) === 0 || paidInput.dataset.autoFilled === '1') {
            paidInput.value = totalToPay;
            paidInput.dataset.autoFilled = '1';
        }
    }

    window.addEventListener('DOMContentLoaded', () => {
        const firstRadio = document.querySelector('input[name="acrylic_id"]');
        if (firstRadio) {
            firstRadio.checked = true;
            calculateRentalTotals();
        }
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
