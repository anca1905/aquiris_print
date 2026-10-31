<?php
// print_sewa.php
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
    die("Data sewa tidak ditemukan.");
}

$shopName = getSetting('shop_name', 'Aquiris Print');
$shopTagline = getSetting('shop_tagline', 'Percetakan & Rental Papan Akrilik Event');
$shopAddress = getSetting('shop_address', 'Jl. Percetakan');
$shopPhone = getSetting('shop_phone', '081234567890');
$finePerDay = getSetting('fine_per_day', '25000');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Perjanjian Sewa - <?= htmlspecialchars($rental['rental_code']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .serif-title { font-family: 'Playfair Display', Georgia, serif; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; }
        }
    </style>
</head>
<body class="bg-stone-100 min-h-screen py-8 flex flex-col items-center">

    <!-- Action Toolbar (No Print) -->
    <div class="no-print max-w-2xl w-full mb-4 flex items-center justify-between gap-2 px-4">
        <a href="rental_detail.php?id=<?= $rentalId ?>" class="px-3.5 py-1.5 bg-stone-200 hover:bg-stone-300 text-stone-800 rounded-xl text-xs font-bold flex items-center gap-1.5">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Rincian
        </a>
        <button onclick="window.print()" class="px-4 py-1.5 bg-stone-900 hover:bg-stone-800 text-amber-300 rounded-xl text-xs font-bold shadow flex items-center gap-1.5">
            <i class="fa-solid fa-print"></i> Cetak Surat Perjanjian
        </button>
    </div>

    <!-- Contract Paper (A4 Formatted) -->
    <div class="bg-white p-8 max-w-2xl w-full shadow-md rounded-3xl border border-stone-200 text-xs text-stone-800">
        <!-- Letterhead Header with Official Logo -->
        <div class="flex justify-between items-center pb-5 border-b-2 border-stone-900">
            <div class="flex items-center gap-4">
                <img src="assets/images/logo-light.jpg" alt="Aquiris Print" class="h-12 w-auto object-contain" />
                <div class="border-l-2 border-stone-200 pl-3.5">
                    <h1 class="text-base font-black text-stone-900 uppercase tracking-wide"><?= htmlspecialchars($shopName) ?></h1>
                    <p class="text-[11px] text-stone-500 font-medium"><?= htmlspecialchars($shopTagline) ?></p>
                    <p class="text-[10px] text-stone-400 mt-0.5"><?= htmlspecialchars($shopAddress) ?> • WhatsApp: <?= htmlspecialchars($shopPhone) ?></p>
                </div>
            </div>
            <div class="text-right">
                <span class="text-[10px] uppercase font-bold text-stone-400 block tracking-wider">No. Perjanjian Sewa</span>
                <span class="font-black text-stone-900 text-sm block font-mono"><?= htmlspecialchars($rental['rental_code']) ?></span>
                <span class="text-[10px] text-stone-500"><?= date('d F Y', strtotime($rental['created_at'])) ?></span>
            </div>
        </div>

        <div class="text-center my-5">
            <h2 class="text-sm font-black uppercase tracking-wider text-stone-900 serif-title">SURAT PERJANJIAN & TANDA TERIMA SEWA PAPAN AKRILIK</h2>
        </div>

        <!-- Parties Section -->
        <div class="space-y-3 mb-4">
            <p class="text-stone-700">Telah disepakati transaksi penyewaan perlengkapan event akrilik antara kedua pihak:</p>
            <div class="bg-stone-50 p-4 rounded-2xl border border-stone-200 grid grid-cols-2 gap-4">
                <div>
                    <span class="text-[10px] uppercase font-bold text-stone-400">Pihak Pertama (Penyedia):</span>
                    <p class="font-bold text-stone-900 text-xs mt-0.5"><?= htmlspecialchars($shopName) ?></p>
                    <p class="text-stone-500 text-[11px]">Petugas: <?= htmlspecialchars($rental['staff_name'] ?? 'Admin Kasir') ?></p>
                </div>
                <div>
                    <span class="text-[10px] uppercase font-bold text-stone-400">Pihak Kedua (Penyewa):</span>
                    <p class="font-bold text-stone-900 text-xs mt-0.5"><?= htmlspecialchars($rental['customer_name']) ?></p>
                    <p class="text-stone-500 text-[11px]">No. WhatsApp: <?= htmlspecialchars($rental['customer_phone']) ?></p>
                    <p class="text-stone-500 text-[11px]">Acara: <?= htmlspecialchars($rental['event_name'] ?: 'Acara Personal') ?></p>
                </div>
            </div>
        </div>

        <!-- Item & Schedule Table -->
        <table class="w-full my-4 text-left border-collapse border border-stone-200">
            <thead>
                <tr class="bg-stone-100 text-stone-700 text-[10px] font-bold uppercase">
                    <th class="p-2.5 border border-stone-200">Spesifikasi Barang</th>
                    <th class="p-2.5 border border-stone-200">Model Standing</th>
                    <th class="p-2.5 border border-stone-200 text-center">Tanggal Ambil</th>
                    <th class="p-2.5 border border-stone-200 text-center">Batas Pengembalian</th>
                    <th class="p-2.5 border border-stone-200 text-center">Lama Sewa</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="p-2.5 border border-stone-200 font-bold text-stone-900">
                        <?= htmlspecialchars($rental['acrylic_name']) ?>
                        <span class="block text-[10px] text-stone-500 font-normal">Ukuran: <?= htmlspecialchars($rental['size']) ?></span>
                    </td>
                    <td class="p-2.5 border border-stone-200 text-stone-700"><?= htmlspecialchars($rental['stand_type'] ?: 'Standard') ?></td>
                    <td class="p-2.5 border border-stone-200 text-center font-semibold"><?= formatDate($rental['start_date']) ?></td>
                    <td class="p-2.5 border border-stone-200 text-center font-semibold"><?= formatDate($rental['end_date']) ?></td>
                    <td class="p-2.5 border border-stone-200 text-center font-bold text-amber-900"><?= (int)$rental['duration_days'] ?> Hari</td>
                </tr>
            </tbody>
        </table>

        <!-- Financial Summary -->
        <div class="flex justify-end my-4">
            <div class="w-72 bg-stone-50 p-3.5 rounded-2xl border border-stone-200 space-y-1.5 text-xs">
                <div class="flex justify-between">
                    <span class="text-stone-600">Biaya Sewa Papan:</span>
                    <span class="font-bold text-stone-900"><?= formatRupiah($rental['rental_price']) ?></span>
                </div>
                <?php if ($rental['custom_sticker_fee'] > 0): ?>
                    <div class="flex justify-between">
                        <span class="text-stone-600">Jasa Cetak Stiker Tulisan:</span>
                        <span class="font-bold text-stone-900"><?= formatRupiah($rental['custom_sticker_fee']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="flex justify-between pt-1.5 border-t border-stone-200 font-black text-stone-900">
                    <span>Total Biaya Sewa:</span>
                    <span class="text-amber-800"><?= formatRupiah($rental['total_cost']) ?></span>
                </div>
                <div class="flex justify-between text-amber-900 font-bold pt-1 border-t border-dashed border-stone-300">
                    <span>Uang Jaminan (Deposit):</span>
                    <span><?= formatRupiah($rental['deposit_amount']) ?></span>
                </div>
                <div class="flex justify-between text-stone-600 pt-1 border-t border-stone-200 text-[11px]">
                    <span>Pembayaran:</span>
                    <span class="font-bold"><?= formatRupiah($rental['paid_amount']) ?> (<?= strtoupper($rental['payment_method']) ?>)</span>
                </div>
            </div>
        </div>

        <!-- Terms and Conditions -->
        <div class="my-4 p-3.5 bg-stone-50 rounded-2xl border border-stone-200 text-[11px] text-stone-600 space-y-1">
            <h5 class="font-bold text-stone-800 uppercase text-[10px] tracking-wider">Tata Tertib & Kesepakatan Sewa:</h5>
            <ol class="list-decimal pl-4 space-y-0.5">
                <li>Papan akrilik dan stand diterima penyewa dalam kondisi baik, bersih, dan siap pakai.</li>
                <li>Penyewa bertanggung jawab menjaga papan dari goresan benda tajam, benturan keras, atau patah.</li>
                <li>Keterlambatan pengembalian dikenakan denda sebesar <strong><?= formatRupiah($finePerDay) ?> per hari</strong>.</li>
                <li>Uang jaminan (deposit) akan diserahkan kembali secara utuh saat unit papan dan perlengkapannya dikembalikan dalam kondisi baik.</li>
                <li>Jika terjadi kerusakan permanen atau unit hilang, penyewa bersedia mengganti biaya pengadaan unit baru.</li>
            </ol>
        </div>

        <!-- Signatures -->
        <div class="grid grid-cols-2 gap-8 pt-6 mt-6 border-t border-stone-200 text-center text-xs">
            <div>
                <p class="text-stone-500 mb-14">Pihak Pertama (Penyedia),</p>
                <p class="font-bold text-stone-800 border-t border-stone-300 inline-block px-8 pt-1"><?= htmlspecialchars($shopName) ?></p>
            </div>
            <div>
                <p class="text-stone-500 mb-14">Pihak Kedua (Penyewa),</p>
                <p class="font-bold text-stone-800 border-t border-stone-300 inline-block px-8 pt-1"><?= htmlspecialchars($rental['customer_name']) ?></p>
            </div>
        </div>
    </div>

</body>
</html>
