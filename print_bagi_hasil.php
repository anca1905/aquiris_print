<?php
// print_bagi_hasil.php
require_once __DIR__ . '/config/app.php';
requireAdmin();

$pdo = Database::getConnection();
$distId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT d.*, u.name as staff_name 
    FROM profit_distributions d
    LEFT JOIN users u ON d.user_id = u.id
    WHERE d.id = ?");
$stmt->execute([$distId]);
$dist = $stmt->fetch();

if (!$dist) {
    die("Data pembagian hasil tidak ditemukan.");
}

$shopName = getSetting('shop_name', 'Aquiris Print');
$shopTagline = getSetting('shop_tagline', 'Solusi Percetakan & Sewa Akrilik');
$shopAddress = getSetting('shop_address', 'Jl. Percetakan');
$shopPhone = getSetting('shop_phone', '081234567890');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bukti Pembagian Hasil - <?= htmlspecialchars($dist['distribution_code']) ?></title>
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
        <a href="bagi_hasil.php" class="px-3.5 py-1.5 bg-stone-200 hover:bg-stone-300 text-stone-800 rounded-xl text-xs font-bold flex items-center gap-1.5 transition">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Riwayat
        </a>
        <div class="flex items-center gap-2">
            <a href="laporan.php?start_date=<?= $dist['period_start'] ?>&end_date=<?= $dist['period_end'] ?>" class="px-3.5 py-1.5 bg-stone-200 hover:bg-stone-300 text-stone-800 rounded-xl text-xs font-bold flex items-center gap-1.5 transition">
                <i class="fa-solid fa-chart-simple"></i> Lihat Laporan Periode
            </a>
            <button onclick="window.print()" class="px-4 py-1.5 bg-stone-900 hover:bg-stone-800 text-amber-300 rounded-xl text-xs font-bold shadow flex items-center gap-1.5 transition">
                <i class="fa-solid fa-print"></i> Cetak Bukti Bagi Hasil
            </button>
        </div>
    </div>

    <!-- Official Voucher Paper (A4 Formatted) -->
    <div class="bg-white p-8 max-w-2xl w-full shadow-md rounded-3xl border border-stone-200 text-xs text-stone-800 space-y-6">
        <!-- Letterhead Header -->
        <div class="flex justify-between items-center pb-5 border-b-2 border-stone-900">
            <div class="flex items-center gap-4">
                <img src="assets/images/logo-light.jpg" alt="Aquiris Print" class="h-12 w-auto object-contain" />
                <div class="border-l-2 border-stone-200 pl-3.5">
                    <h1 class="text-base font-black text-stone-900 uppercase tracking-wide"><?= htmlspecialchars($shopName) ?></h1>
                    <p class="text-[11px] text-stone-500 font-medium"><?= htmlspecialchars($shopTagline) ?></p>
                    <p class="text-[10px] text-stone-400 mt-0.5"><?= htmlspecialchars($shopAddress) ?> &bull; WA: <?= htmlspecialchars($shopPhone) ?></p>
                </div>
            </div>
            <div class="text-right">
                <span class="text-[10px] uppercase font-bold text-stone-400 block tracking-wider">No. Bukti Bagi Hasil</span>
                <span class="font-black text-stone-900 text-sm block font-mono"><?= htmlspecialchars($dist['distribution_code']) ?></span>
                <span class="text-[10px] text-stone-500">Cair: <?= formatDate($dist['distribution_date']) ?></span>
            </div>
        </div>

        <div class="text-center my-3">
            <h2 class="text-sm font-black uppercase tracking-wider text-stone-900 serif-title">BUKTI DISTRIBUSI & PENCAIRAN BAGI HASIL USAHA</h2>
            <p class="text-[11px] text-stone-500 mt-0.5">Sistem Pembagian Keuntungan Bersih (Mudharabah / Profit Sharing)</p>
        </div>

        <!-- Period and General Info Card -->
        <div class="bg-stone-50 p-4 rounded-2xl border border-stone-200 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
            <div>
                <span class="text-[10px] uppercase font-bold text-stone-400 block">Periode Buku</span>
                <span class="font-bold text-stone-900"><?= formatDate($dist['period_start']) ?> s/d <?= formatDate($dist['period_end']) ?></span>
            </div>
            <div>
                <span class="text-[10px] uppercase font-bold text-stone-400 block">Tanggal Eksekusi</span>
                <span class="font-bold text-stone-900"><?= formatDate($dist['distribution_date']) ?></span>
            </div>
            <div>
                <span class="text-[10px] uppercase font-bold text-stone-400 block">Metode Pencairan</span>
                <span class="font-bold uppercase text-stone-800"><?= htmlspecialchars($dist['payment_method']) ?></span>
            </div>
            <div>
                <span class="text-[10px] uppercase font-bold text-stone-400 block">Dicatat Oleh</span>
                <span class="font-bold text-stone-900"><?= htmlspecialchars($dist['staff_name'] ?? 'Admin') ?></span>
            </div>
        </div>

        <!-- Financial Breakdown (Omzet - Expenses = Net Profit) -->
        <div>
            <h3 class="text-xs font-bold text-stone-700 uppercase tracking-wider mb-2">1. Rekapitulasi Keuangan Periode Terkait</h3>
            <div class="border border-stone-200 rounded-xl overflow-hidden divide-y divide-stone-100 text-xs">
                <div class="flex justify-between items-center p-2.5 bg-stone-50/60">
                    <span class="text-stone-600">Total Pendapatan Kotor (Percetakan & Sewa Akrilik)</span>
                    <span class="font-bold text-stone-900"><?= formatRupiah($dist['gross_revenue']) ?></span>
                </div>
                <div class="flex justify-between items-center p-2.5 bg-stone-50/60">
                    <span class="text-stone-600">Total Beban & Biaya Operasional Usaha</span>
                    <span class="font-bold text-rose-600">- <?= formatRupiah($dist['total_expenses']) ?></span>
                </div>
                <div class="flex justify-between items-center p-2.5 bg-amber-50/80 font-black">
                    <span class="text-amber-950 uppercase">Total Keuntungan Bersih (Laba Bersih yang Dibagi)</span>
                    <span class="text-sm text-amber-900"><?= formatRupiah($dist['net_profit']) ?></span>
                </div>
            </div>
        </div>

        <!-- Profit Distribution Allocation Table -->
        <div>
            <h3 class="text-xs font-bold text-stone-700 uppercase tracking-wider mb-2">2. Rincian Alokasi Distribusi Bagi Hasil</h3>
            <div class="border border-stone-200 rounded-xl overflow-hidden text-xs">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-stone-100 text-stone-700 border-b border-stone-200 text-[11px]">
                            <th class="py-2.5 px-3 font-bold">Pos Penerima Hak</th>
                            <th class="py-2.5 px-3 font-bold text-center">Porsi (%)</th>
                            <th class="py-2.5 px-3 font-bold text-right">Nominal (IDR)</th>
                            <th class="py-2.5 px-3 font-bold">Keterangan / Fungsi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        <!-- Owner -->
                        <tr class="hover:bg-blue-50/30">
                            <td class="py-3 px-3 font-bold text-blue-950">
                                <i class="fa-solid fa-user-tie text-blue-600 mr-1.5"></i>
                                <span>Pemilik Modal (Owner)</span>
                            </td>
                            <td class="py-3 px-3 text-center font-bold text-blue-900"><?= (float)$dist['owner_percent'] ?>%</td>
                            <td class="py-3 px-3 text-right font-black text-blue-950"><?= formatRupiah($dist['owner_amount']) ?></td>
                            <td class="py-3 px-3 text-stone-500 text-[11px]">Dividen & return modal usaha percetakan</td>
                        </tr>
                        <!-- Worker -->
                        <tr class="hover:bg-emerald-50/30">
                            <td class="py-3 px-3 font-bold text-emerald-950">
                                <i class="fa-solid fa-users text-emerald-600 mr-1.5"></i>
                                <span>Pekerja / Tim Operator</span>
                            </td>
                            <td class="py-3 px-3 text-center font-bold text-emerald-900"><?= (float)$dist['worker_percent'] ?>%</td>
                            <td class="py-3 px-3 text-right font-black text-emerald-950"><?= formatRupiah($dist['worker_amount']) ?></td>
                            <td class="py-3 px-3 text-stone-500 text-[11px]">Insentif & bagi hasil tenaga pengelola</td>
                        </tr>
                        <!-- Reserve -->
                        <tr class="hover:bg-amber-50/30">
                            <td class="py-3 px-3 font-bold text-amber-950">
                                <i class="fa-solid fa-vault text-amber-700 mr-1.5"></i>
                                <span>Kas Usaha (Cadangan Toko)</span>
                            </td>
                            <td class="py-3 px-3 text-center font-bold text-amber-900"><?= (float)$dist['reserve_percent'] ?>%</td>
                            <td class="py-3 px-3 text-right font-black text-amber-950"><?= formatRupiah($dist['reserve_amount']) ?></td>
                            <td class="py-3 px-3 text-stone-500 text-[11px]">Dana cadangan tetap mengendap di kas usaha</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="bg-stone-100 font-black text-stone-900 border-t-2 border-stone-300">
                            <td class="py-2.5 px-3 uppercase text-[10px]">TOTAL ALOKASI</td>
                            <td class="py-2.5 px-3 text-center">100%</td>
                            <td class="py-2.5 px-3 text-right text-emerald-800"><?= formatRupiah($dist['net_profit']) ?></td>
                            <td class="py-2.5 px-3 text-[11px] text-stone-500 font-normal">Selesai Dibagikan</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <?php if (!empty($dist['notes'])): ?>
            <div class="p-3 bg-stone-50 rounded-xl border border-stone-200 text-xs">
                <span class="font-bold text-stone-700 block mb-0.5">Catatan Khusus:</span>
                <p class="text-stone-600 italic"><?= nl2br(htmlspecialchars($dist['notes'])) ?></p>
            </div>
        <?php endif; ?>

        <!-- Signatures: 3 Parties -->
        <div class="pt-8 mt-6 border-t border-stone-200">
            <div class="grid grid-cols-3 gap-4 text-center text-xs">
                <div>
                    <p class="text-stone-400 text-[11px] mb-14">Disiapkan & Dikeluarkan,</p>
                    <p class="font-bold text-stone-800 border-t border-stone-300 inline-block px-4 pt-1"><?= htmlspecialchars($dist['staff_name'] ?? 'Admin Kasir') ?></p>
                    <span class="text-[10px] text-stone-400 block">Pengelola Kasir / Pembukuan</span>
                </div>
                <div>
                    <p class="text-stone-400 text-[11px] mb-14">Diterima Oleh,</p>
                    <p class="font-bold text-stone-800 border-t border-stone-300 inline-block px-4 pt-1">( Tenaga Kerja )</p>
                    <span class="text-[10px] text-stone-400 block">Perwakilan Pekerja / Operator</span>
                </div>
                <div>
                    <p class="text-stone-400 text-[11px] mb-14">Disetujui Oleh,</p>
                    <p class="font-bold text-stone-800 border-t border-stone-300 inline-block px-4 pt-1">Pemilik Usaha</p>
                    <span class="text-[10px] text-stone-400 block">Pemilik Modal (Owner Toko)</span>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
