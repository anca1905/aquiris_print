<?php
// includes/header.php
if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../config/app.php';
}
requireAuth();
$currentUser = currentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
$shopName = getSetting('shop_name', 'Aquiris Print');

// Time-based human greeting
$hour = (int)date('H');
if ($hour >= 5 && $hour < 11) {
    $greeting = 'Selamat Pagi';
    $greetingIcon = 'fa-sun text-amber-500';
} elseif ($hour >= 11 && $hour < 15) {
    $greeting = 'Selamat Siang';
    $greetingIcon = 'fa-cloud-sun text-amber-500';
} elseif ($hour >= 15 && $hour < 18) {
    $greeting = 'Selamat Sore';
    $greetingIcon = 'fa-cloud-sun text-orange-500';
} else {
    $greeting = 'Selamat Malam';
    $greetingIcon = 'fa-moon text-indigo-400';
}
?>
<!DOCTYPE html>
<html lang="id" class="h-full overflow-hidden">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' • ' . $shopName : $shopName ?></title>
    
    <!-- Favicon with Official Aquiris Icon -->
    <link rel="icon" type="image/png" href="assets/images/icon-mark.png">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        studio: {
                            50: '#FBF9F5',
                            100: '#F5F0E6',
                            200: '#EBDDC7',
                            300: '#DFC59E',
                            400: '#CDA46F',
                            500: '#B88448',
                            600: '#9B6734',
                            700: '#7B4C27',
                            800: '#5F3921',
                            900: '#432717',
                        },
                        ambercraft: {
                            50: '#fffbeb',
                            100: '#fef3c7',
                            500: '#f59e0b',
                            600: '#d97706',
                            700: '#b45309',
                        },
                        warmdark: '#1C1917',
                        warmsurface: '#FAFAF8',
                        warmborder: '#E7E5E4'
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', '-apple-system', 'BlinkMacSystemFont', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <!-- Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        html, body {
            height: 100%;
            overflow: hidden;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #FAF8F5;
            color: #292524;
        }
        .serif-title {
            font-family: 'Playfair Display', Georgia, serif;
        }
        /* Custom friendly warm scrollbar */
        ::-webkit-scrollbar {
            width: 7px;
            height: 7px;
        }
        ::-webkit-scrollbar-track {
            background: #F5F0E6;
        }
        ::-webkit-scrollbar-thumb {
            background: #D7CABB;
            border-radius: 9999px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #BFAFA0;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body, html {
                height: auto !important;
                overflow: visible !important;
                background: white !important;
                color: black !important;
            }
        }
    </style>
</head>
<body class="bg-[#FAF8F5] text-stone-800 antialiased h-screen overflow-hidden flex selection:bg-amber-100 selection:text-amber-900">

    <!-- Mobile Sidebar Backdrop -->
    <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-stone-900/40 backdrop-blur-sm z-40 hidden md:hidden transition-opacity"></div>

    <!-- Fixed Pinned Studio Sidebar (Never Scrolls Away) -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-white text-stone-700 flex flex-col transition-transform duration-300 transform -translate-x-full md:translate-x-0 md:static md:z-auto h-screen flex-shrink-0 border-r border-stone-200 shadow-sm">
        <!-- Brand Header with Official Aquiris Logo -->
        <div class="flex-shrink-0 h-20 px-4 flex items-center justify-between border-b border-stone-100 bg-[#FAF8F5]/80">
            <a href="dashboard.php" class="flex items-center gap-2.5 group">
                <div class="w-11 h-11 rounded-2xl bg-white border border-stone-200/90 flex items-center justify-center p-1.5 shadow-xs group-hover:scale-105 transition-transform flex-shrink-0">
                    <img src="assets/images/icon-mark.png" alt="Aquiris Logo" class="w-full h-full object-contain" />
                </div>
                <div class="truncate">
                    <div class="flex items-center gap-1.5">
                        <span class="text-stone-900 font-black text-base tracking-tight leading-none font-sans">AQUIRIS</span>
                        <span class="px-1.5 py-0.5 bg-blue-600 text-white font-black text-[9px] rounded-md tracking-wider uppercase">PRINT</span>
                    </div>
                    <span class="text-[10px] text-stone-500 font-medium block mt-1 truncate">Percetakan & Sewa Akrilik</span>
                </div>
            </a>
            <button onclick="toggleSidebar()" class="md:hidden text-stone-400 hover:text-stone-800 p-1.5 rounded-lg flex-shrink-0">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Navigation Links (Scrolls independently inside sidebar if needed) -->
        <div class="flex-1 overflow-y-auto px-3.5 py-4 space-y-6">
            <!-- Big Friendly POS Button -->
            <div>
                <a href="kasir.php" class="flex items-center justify-center gap-2.5 w-full py-3 px-4 bg-stone-900 hover:bg-stone-800 text-amber-300 hover:text-amber-200 rounded-xl font-bold shadow-md shadow-stone-900/10 transition-all duration-150 transform active:scale-95 text-xs tracking-wide">
                    <i class="fa-solid fa-cash-register text-sm"></i>
                    <span>Buka Kasir Cetak</span>
                </a>
            </div>

            <!-- Menu Kasir & Order -->
            <div>
                <div class="text-[11px] font-bold text-stone-400 uppercase tracking-wider px-3 mb-1.5 flex items-center gap-1.5">
                    <span>Kasir & Percetakan</span>
                </div>
                <ul class="space-y-1">
                    <li>
                        <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition <?= $currentPage === 'dashboard.php' ? 'bg-stone-100 text-stone-900 font-bold border-l-4 border-amber-600' : 'text-stone-600 hover:bg-stone-50 hover:text-stone-900' ?>">
                            <i class="fa-solid fa-house-chimney w-5 text-center <?= $currentPage === 'dashboard.php' ? 'text-amber-600' : 'text-stone-400' ?>"></i>
                            <span>Beranda / Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="kasir.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition <?= $currentPage === 'kasir.php' ? 'bg-stone-100 text-stone-900 font-bold border-l-4 border-amber-600' : 'text-stone-600 hover:bg-stone-50 hover:text-stone-900' ?>">
                            <i class="fa-solid fa-receipt w-5 text-center <?= $currentPage === 'kasir.php' ? 'text-amber-600' : 'text-stone-400' ?>"></i>
                            <span>Kasir POS (Print & FC)</span>
                        </a>
                    </li>
                    <li>
                        <a href="transaksi_cetak.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition <?= $currentPage === 'transaksi_cetak.php' ? 'bg-stone-100 text-stone-900 font-bold border-l-4 border-amber-600' : 'text-stone-600 hover:bg-stone-50 hover:text-stone-900' ?>">
                            <i class="fa-solid fa-clock-rotate-left w-5 text-center <?= $currentPage === 'transaksi_cetak.php' ? 'text-amber-600' : 'text-stone-400' ?>"></i>
                            <span>Riwayat Transaksi Cetak</span>
                        </a>
                    </li>
                    <li>
                        <?php $pendingOrdersCount = countPendingOnlineOrders(); ?>
                        <a href="pesanan_online.php" class="flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold transition <?= $currentPage === 'pesanan_online.php' ? 'bg-amber-50 text-amber-900 font-bold border-l-4 border-amber-600' : 'text-stone-600 hover:bg-stone-50 hover:text-stone-900' ?>">
                            <div class="flex items-center gap-3">
                                <i class="fa-solid fa-cloud-arrow-down w-5 text-center <?= $currentPage === 'pesanan_online.php' ? 'text-amber-600' : 'text-stone-400' ?>"></i>
                                <span>Pesanan Cetak Online</span>
                            </div>
                            <?php if ($pendingOrdersCount > 0): ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] bg-amber-500 text-white font-extrabold animate-pulse">
                                    <?= $pendingOrdersCount ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Menu Rental Papan Akrilik -->
            <div>
                <div class="text-[11px] font-bold text-stone-400 uppercase tracking-wider px-3 mb-1.5 flex items-center justify-between">
                    <span>Rental Papan Akrilik</span>
                    <span class="bg-amber-100 text-amber-800 text-[10px] px-1.5 py-0.5 rounded-full font-bold">Event & Wedding</span>
                </div>
                <ul class="space-y-1">
                    <li>
                        <a href="rental_akrilik.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition <?= $currentPage === 'rental_akrilik.php' ? 'bg-amber-50 text-amber-900 font-bold border-l-4 border-amber-600' : 'text-stone-600 hover:bg-stone-50 hover:text-stone-900' ?>">
                            <i class="fa-solid fa-calendar-check w-5 text-center <?= $currentPage === 'rental_akrilik.php' ? 'text-amber-600' : 'text-stone-400' ?>"></i>
                            <span>Daftar Sewa & Jadwal</span>
                        </a>
                    </li>
                    <li>
                        <a href="rental_tambah.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition <?= $currentPage === 'rental_tambah.php' ? 'bg-amber-50 text-amber-900 font-bold border-l-4 border-amber-600' : 'text-stone-600 hover:bg-stone-50 hover:text-stone-900' ?>">
                            <i class="fa-solid fa-circle-plus w-5 text-center <?= $currentPage === 'rental_tambah.php' ? 'text-amber-600' : 'text-stone-400' ?>"></i>
                            <span>Catat Sewa Baru</span>
                        </a>
                    </li>
                    <li>
                        <a href="akrilik_master.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition <?= $currentPage === 'akrilik_master.php' ? 'bg-amber-50 text-amber-900 font-bold border-l-4 border-amber-600' : 'text-stone-600 hover:bg-stone-50 hover:text-stone-900' ?>">
                            <i class="fa-solid fa-shapes w-5 text-center <?= $currentPage === 'akrilik_master.php' ? 'text-amber-600' : 'text-stone-400' ?>"></i>
                            <span>Katalog Papan & Easel</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Keuangan & Arus Kas (Khusus Administrator / Owner) -->
            <?php if (isAdmin()): ?>
            <div>
                <div class="text-[11px] font-bold text-stone-400 uppercase tracking-wider px-3 mb-1.5 flex items-center justify-between">
                    <span>Buku Keuangan & Kas</span>
                    <span class="text-[9px] bg-amber-100 text-amber-800 font-extrabold px-1.5 py-0.5 rounded">ADMIN</span>
                </div>
                <ul class="space-y-1">
                    <li>
                        <a href="pengeluaran.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition <?= $currentPage === 'pengeluaran.php' ? 'bg-stone-100 text-stone-900 font-bold border-l-4 border-rose-500' : 'text-stone-600 hover:bg-stone-50 hover:text-stone-900' ?>">
                            <i class="fa-solid fa-arrow-down-wide-short w-5 text-center <?= $currentPage === 'pengeluaran.php' ? 'text-rose-500' : 'text-stone-400' ?>"></i>
                            <span>Pengeluaran Toko</span>
                        </a>
                    </li>
                    <li>
                        <a href="arus_kas.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition <?= $currentPage === 'arus_kas.php' ? 'bg-stone-100 text-stone-900 font-bold border-l-4 border-emerald-600' : 'text-stone-600 hover:bg-stone-50 hover:text-stone-900' ?>">
                            <i class="fa-solid fa-book-bookmark w-5 text-center <?= $currentPage === 'arus_kas.php' ? 'text-emerald-600' : 'text-stone-400' ?>"></i>
                            <span>Buku Kas & Mutasi</span>
                        </a>
                    </li>
                    <li>
                        <a href="laporan.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition <?= $currentPage === 'laporan.php' ? 'bg-stone-100 text-stone-900 font-bold border-l-4 border-stone-800' : 'text-stone-600 hover:bg-stone-50 hover:text-stone-900' ?>">
                            <i class="fa-solid fa-chart-simple w-5 text-center <?= $currentPage === 'laporan.php' ? 'text-stone-700' : 'text-stone-400' ?>"></i>
                            <span>Laporan Laba Rugi</span>
                        </a>
                    </li>
                    <li>
                        <a href="bagi_hasil.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition <?= $currentPage === 'bagi_hasil.php' ? 'bg-stone-100 text-stone-900 font-bold border-l-4 border-amber-600' : 'text-stone-600 hover:bg-stone-50 hover:text-stone-900' ?>">
                            <i class="fa-solid fa-hand-holding-dollar w-5 text-center <?= $currentPage === 'bagi_hasil.php' ? 'text-amber-600' : 'text-stone-400' ?>"></i>
                            <span>Bagi Hasil & Kas Usaha</span>
                        </a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Master Data & Toko -->
            <div>
                <div class="text-[11px] font-bold text-stone-400 uppercase tracking-wider px-3 mb-1.5 flex items-center justify-between">
                    <span><?= isAdmin() ? 'Master & Pengaturan' : 'Data & Kontak' ?></span>
                    <?php if (!isAdmin()): ?>
                        <span class="text-[9px] bg-stone-100 text-stone-500 font-bold px-1.5 py-0.5 rounded">KASIR</span>
                    <?php endif; ?>
                </div>
                <ul class="space-y-1">
                    <?php if (isAdmin()): ?>
                    <li>
                        <a href="produk.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition <?= $currentPage === 'produk.php' ? 'bg-stone-100 text-stone-900 font-bold border-l-4 border-amber-600' : 'text-stone-600 hover:bg-stone-50 hover:text-stone-900' ?>">
                            <i class="fa-solid fa-tags w-5 text-center <?= $currentPage === 'produk.php' ? 'text-amber-600' : 'text-stone-400' ?>"></i>
                            <span>Daftar Tarif Layanan</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="pelanggan.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition <?= $currentPage === 'pelanggan.php' ? 'bg-stone-100 text-stone-900 font-bold border-l-4 border-amber-600' : 'text-stone-600 hover:bg-stone-50 hover:text-stone-900' ?>">
                            <i class="fa-solid fa-address-book w-5 text-center <?= $currentPage === 'pelanggan.php' ? 'text-amber-600' : 'text-stone-400' ?>"></i>
                            <span>Kontak Pelanggan</span>
                        </a>
                    </li>
                    <?php if (isAdmin()): ?>
                    <li>
                        <a href="pengaturan.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold transition <?= $currentPage === 'pengaturan.php' ? 'bg-stone-100 text-stone-900 font-bold border-l-4 border-amber-600' : 'text-stone-600 hover:bg-stone-50 hover:text-stone-900' ?>">
                            <i class="fa-solid fa-gear w-5 text-center <?= $currentPage === 'pengaturan.php' ? 'text-amber-600' : 'text-stone-400' ?>"></i>
                            <span>Pengaturan Toko & Akun</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Human User Profile Footer (Fixed at bottom of sidebar) -->
        <div class="flex-shrink-0 p-3.5 border-t border-stone-200 bg-[#FAF8F5] flex items-center justify-between">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-full <?= isAdmin() ? 'bg-stone-900 text-amber-300' : 'bg-amber-600 text-white' ?> flex items-center justify-center font-bold text-xs shadow-inner flex-shrink-0">
                    <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="truncate">
                    <p class="text-xs font-bold text-stone-800 truncate"><?= htmlspecialchars($currentUser['name']) ?></p>
                    <span class="text-[10px] text-stone-500 font-medium block">
                        <?= isAdmin() ? '👑 Administrator (Owner)' : '💼 Kasir • Operator Shift' ?>
                    </span>
                </div>
            </div>
            <button type="button" onclick="confirmLogout()" title="Keluar / Logout" class="text-stone-400 hover:text-rose-600 p-2 rounded-lg hover:bg-stone-100 transition flex-shrink-0 cursor-pointer">
                <i class="fa-solid fa-arrow-right-from-bracket text-xs"></i>
            </button>
        </div>
    </aside>

    <!-- Main Workspace Column (Full Height, Locked Topbar + Scrollable Main Content) -->
    <div class="flex-1 flex flex-col h-screen min-w-0 overflow-hidden">
        <!-- Pinned Topbar (Never Scrolls Away) -->
        <header class="flex-shrink-0 h-20 bg-white border-b border-stone-200 px-4 md:px-8 flex items-center justify-between z-30 shadow-xs">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden text-stone-600 hover:text-stone-900 p-2 rounded-xl hover:bg-stone-100">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold text-stone-500">
                        <i class="fa-solid <?= $greetingIcon ?>"></i>
                        <span><?= $greeting ?>, <strong><?= htmlspecialchars(explode(' ', $currentUser['name'])[0]) ?></strong>!</span>
                        <span class="hidden sm:inline text-stone-300">•</span>
                        <span class="hidden sm:inline text-stone-400"><?= date('d F Y') ?></span>
                    </div>
                    <h2 class="text-base sm:text-lg font-extrabold text-stone-900 leading-tight tracking-tight mt-0.5">
                        <?= $pageTitle ?? 'Beranda Toko' ?>
                    </h2>
                </div>
            </div>

            <!-- Topbar Quick Warm Actions -->
            <div class="flex items-center gap-2">
                <a href="index.php" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-2 bg-stone-100 hover:bg-stone-200 text-stone-700 rounded-xl text-xs font-semibold transition" title="Buka Landing Page Pemesanan Pelanggan">
                    <i class="fa-solid fa-arrow-up-right-from-square text-amber-600"></i>
                    <span class="hidden lg:inline">Landing Page</span>
                </a>
                <a href="kasir.php" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-stone-900 hover:bg-stone-800 text-amber-300 rounded-xl text-xs font-bold shadow-sm transition">
                    <i class="fa-solid fa-print"></i>
                    <span class="hidden sm:inline">Kasir Cetak</span>
                </a>
                <a href="rental_tambah.php" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold shadow-sm transition">
                    <i class="fa-solid fa-shapes"></i>
                    <span class="hidden sm:inline">Sewa Akrilik</span>
                </a>
                <?php if (isAdmin()): ?>
                <a href="pengeluaran.php" class="inline-flex items-center gap-1.5 px-3 py-2 bg-stone-100 hover:bg-stone-200 text-stone-700 rounded-xl text-xs font-semibold transition" title="Catat Pengeluaran Toko">
                    <i class="fa-solid fa-wallet text-rose-500"></i>
                    <span class="hidden md:inline">Beban Kas</span>
                </a>
                <?php endif; ?>
            </div>
        </header>

        <!-- Scrollable Main Content Area (Only This Area Scrolls) -->
        <main class="flex-1 overflow-y-auto min-h-0 flex flex-col">
            <div class="p-4 md:p-8 flex-1">
                <!-- Warm Flash Notification -->
                <?php $flash = getFlash(); ?>
                <?php if ($flash): ?>
                    <div class="mb-6 p-4 rounded-2xl flex items-center gap-3 shadow-sm border <?= $flash['type'] === 'success' ? 'bg-emerald-50/80 border-emerald-200 text-emerald-900' : ($flash['type'] === 'error' ? 'bg-rose-50/80 border-rose-200 text-rose-900' : ($flash['type'] === 'warning' ? 'bg-amber-50/80 border-amber-200 text-amber-900' : 'bg-stone-100 border-stone-200 text-stone-900')) ?>">
                        <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 <?= $flash['type'] === 'success' ? 'bg-emerald-200 text-emerald-800' : ($flash['type'] === 'error' ? 'bg-rose-200 text-rose-800' : ($flash['type'] === 'warning' ? 'bg-amber-200 text-amber-800' : 'bg-stone-200 text-stone-800')) ?>">
                            <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-check' : ($flash['type'] === 'error' ? 'fa-xmark' : ($flash['type'] === 'warning' ? 'fa-bell' : 'fa-info')) ?> text-sm font-bold"></i>
                        </div>
                        <span class="text-xs font-semibold"><?= htmlspecialchars($flash['message']) ?></span>
                    </div>
                <?php endif; ?>
