<?php
// index.php - Public Landing Page & Online Order Submission
require_once __DIR__ . '/config/app.php';

$pdo = Database::getConnection();
$shopName = getSetting('shop_name', 'Aquiris Print & Acrylic Studio');
$shopTagline = getSetting('shop_tagline', 'Solusi Percetakan, Fotocopy & Sewa Papan Akrilik Event');
$shopPhone = getSetting('shop_phone', '081298765432');
$shopAddress = getSetting('shop_address', 'Jl. Jenderal Sudirman No. 45 (Dekat Kampus & Perkantoran)');
$operatorPhone = getSetting('print_operator_wa', $shopPhone);
$baseUrl = getBaseUrl();

$orderSubmitted = false;
$submittedOrder = null;
$error = '';

// Handle Order Form Submission
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['submit_online_order'])) {
    $customerName = trim($_POST['customer_name'] ?? '');
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    $serviceType = trim($_POST['service_type'] ?? 'Print Dokumen');
    $paperType = trim($_POST['paper_type'] ?? 'A4 HVS 75/80gr');
    $colorMode = trim($_POST['color_mode'] ?? 'Hitam Putih (B/W)');
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));
    $finishing = trim($_POST['finishing'] ?? 'Tanpa Jilid');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($customerName) || empty($customerPhone)) {
        $error = 'Nama lengkap dan nomor WhatsApp wajib diisi.';
    } else {
        $orderCode = generateOnlineOrderCode($pdo);
        $savedFilePath = null;
        $originalFileName = null;
        $fileSize = 0;

        // Process File Upload if provided
        if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['document_file'];
            $fileSize = (int)$file['size'];
            $originalFileName = basename($file['name']);
            $fileExt = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));

            $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
            $maxSizeBytes = 50 * 1024 * 1024; // 50MB

            if ($fileSize > $maxSizeBytes) {
                $error = 'Ukuran file terlalu besar! Maksimal 50 MB.';
            } elseif (!in_array($fileExt, $allowedExtensions)) {
                $error = 'Format file tidak didukung! Format yang diperbolehkan: PDF, Word, Excel, PowerPoint, Gambar (JPG/PNG), atau ZIP/RAR.';
            } else {
                $uploadDir = __DIR__ . '/assets/uploads/orders/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                // Clean filename
                $cleanBase = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($originalFileName, PATHINFO_FILENAME));
                $newFileName = $orderCode . '-' . time() . '-' . substr($cleanBase, 0, 40) . '.' . $fileExt;
                $targetDest = $uploadDir . $newFileName;

                if (move_uploaded_file($file['tmp_name'], $targetDest)) {
                    $savedFilePath = 'assets/uploads/orders/' . $newFileName;
                } else {
                    $error = 'Gagal menyimpan file yang diunggah. Silakan coba kembali.';
                }
            }
        }

        if (empty($error)) {
            // Full download link for the laptop operator
            $fileDownloadUrl = $savedFilePath ? ($baseUrl . '/' . $savedFilePath) : '';

            // Construct WhatsApp Message for Print Operator Laptop
            $waMessage = "🖨️ *PESANAN CETAK ONLINE BARU!*\n"
                       . "*{$shopName}*\n"
                       . "────────────────────────────\n"
                       . "Kode Pesanan: *#{$orderCode}*\n"
                       . "Waktu Masuk: " . date('d M Y, H:i') . " WIB\n\n"
                       . "👤 *Data Pemesan:*\n"
                       . "• Nama: *{$customerName}*\n"
                       . "• WhatsApp: {$customerPhone} (https://wa.me/" . formatWaPhone($customerPhone) . ")\n\n"
                       . "📄 *Rincian Layanan:*\n"
                       . "• Layanan: *{$serviceType}*\n"
                       . "• Kertas: {$paperType}\n"
                       . "• Mode Cetak: {$colorMode}\n"
                       . "• Jumlah: {$quantity} Rangkap/Eksemplar\n"
                       . "• Finishing/Jilid: {$finishing}\n";

            if (!empty($notes)) {
                $waMessage .= "\n📝 *Catatan Pemesan:*\n\"{$notes}\"\n";
            }

            if (!empty($savedFilePath)) {
                $sizeMb = round($fileSize / 1024 / 1024, 2);
                $sizeFormatted = ($sizeMb >= 1) ? "{$sizeMb} MB" : round($fileSize / 1024) . " KB";
                $waMessage .= "\n📎 *File Dokumen:* Terlampir di Sistem ({$originalFileName} - {$sizeFormatted})\n";
            } else {
                $waMessage .= "\n⚠️ *Catatan File:* Pelanggan tidak melampirkan file (akan diserahkan manual / flashdisk).\n";
            }

            $waMessage .= "\n🔐 *Tindakan Petugas / Karyawan:*\n"
                       . "Silakan login ke sistem untuk download file & proses pesanan:\n"
                       . "👉 " . $baseUrl . "/login.php\n"
                       . "────────────────────────────\n"
                       . "_Sistem Notifikasi Pesanan Otomatis Aquiris Print_";

            // Send purely as WhatsApp notification via Fonnte (no direct open file URL)
            $fonnteResult = sendFonnteMessage($operatorPhone, $waMessage);
            $fonnteStatusText = $fonnteResult['status'] ? 'Terkirim ke WA Operator' : ('Gagal Fonnte: ' . ($fonnteResult['message'] ?? 'Error'));

            // Insert into online_orders table
            $stmt = $pdo->prepare("INSERT INTO online_orders (
                order_code, customer_name, customer_phone, service_type, paper_type, color_mode,
                quantity, finishing, notes, file_path, file_name, file_size, status, fonnte_status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'menunggu', ?, NOW())");

            $stmt->execute([
                $orderCode, $customerName, $customerPhone, $serviceType, $paperType, $colorMode,
                $quantity, $finishing, $notes, $savedFilePath, $originalFileName, $fileSize, $fonnteStatusText
            ]);

            // Also send automated confirmation WhatsApp to Customer
            $customerConfMsg = "🖨️ *PESANAN CETAK BERHASIL DITERIMA!*\n"
                             . "*{$shopName}*\n"
                             . "────────────────────────────\n"
                             . "Halo kak *{$customerName}*,\n"
                             . "Terima kasih telah memesan layanan cetak di *Aquiris Print*!\n\n"
                             . "📄 *Rincian Pesanan Anda:*\n"
                             . "• Kode Pesanan: *#{$orderCode}*\n"
                             . "• Layanan: {$serviceType}\n"
                             . "• Kertas: {$paperType}\n"
                             . "• Mode Cetak: {$colorMode}\n"
                             . "• Jumlah: {$quantity} Rangkap\n"
                             . "• Finishing: {$finishing}\n"
                             . "• Status: *⏳ MENUNGGU ANTRIAN CETAK*\n\n"
                             . "Kami akan otomatis mengabari Anda kembali via WhatsApp saat dokumen mulai dicetak dan saat sudah selesai siap diambil di studio kami.\n\n"
                             . "📍 *Lokasi Pengambilan:* {$shopAddress}\n"
                             . "────────────────────────────\n"
                             . "_Aquiris Print Popalia - Solusi Cetak Cepat & Berkualitas_";

            sendFonnteMessage($customerPhone, $customerConfMsg);

            $orderSubmitted = true;
            $submittedOrder = [
                'order_code' => $orderCode,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'service_type' => $serviceType,
                'paper_type' => $paperType,
                'color_mode' => $colorMode,
                'quantity' => $quantity,
                'finishing' => $finishing,
                'notes' => $notes,
                'file_name' => $originalFileName,
                'file_size' => $fileSize,
                'file_path' => $savedFilePath,
                'fonnte_sent' => $fonnteResult['status']
            ];
        }
    }
}

// Handle Order Tracking Search
$trackedOrder = null;
$trackingQuery = trim($_GET['track'] ?? '');
$trackingError = '';

if (!empty($trackingQuery)) {
    $cleanQuery = trim($trackingQuery, '# ');
    $cleanPhone = preg_replace('/[^0-9]/', '', $cleanQuery);
    $waPhone = !empty($cleanPhone) ? formatWaPhone($cleanPhone) : '';

    $stmtTrack = $pdo->prepare("SELECT * FROM online_orders WHERE order_code = ? OR order_code LIKE ? OR customer_phone = ? OR customer_phone = ? ORDER BY id DESC LIMIT 1");
    $stmtTrack->execute([$cleanQuery, '%' . $cleanQuery . '%', $cleanPhone, $waPhone]);
    $trackedOrder = $stmtTrack->fetch();

    if (!$trackedOrder) {
        $trackingError = "Pesanan dengan kode atau nomor WhatsApp \"{$cleanQuery}\" tidak ditemukan. Mohon pastikan nomor atau kodenya sudah benar.";
    }
}

// Fetch some sample products for showcase
$featuredProducts = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories_product c ON p.category_id = c.id ORDER BY p.id ASC LIMIT 8")->fetchAll();
$acrylicShowcase = $pdo->query("SELECT * FROM acrylic_items WHERE status = 'available' ORDER BY id ASC LIMIT 4")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Primary SEO Meta Tags -->
    <title><?= htmlspecialchars($shopName) ?> • Percetakan, Fotocopy & Sewa Papan Akrilik Rusunawa USN Popalia Kolaka</title>
    <meta name="title" content="<?= htmlspecialchars($shopName) ?> • Percetakan, Fotocopy & Sewa Papan Akrilik Rusunawa USN Popalia Kolaka">
    <meta name="description" content="Pusat percetakan murah, cepat, dan berkualitas di Rusunawa USN Popalia, Tanggetada, Kolaka. Melayani print dokumen tugas, skripsi, fotocopy, jilid, dan sewa papan akrilik event online. Siap ambil langsung!">
    <meta name="keywords" content="aquiris print, percetakan usn popalia, print usn kolaka, fotocopy popalia, print skripsi tanggetada, sewa akrilik popalia, sewa papan akrilik kolaka, cetak tugas mahasiswa usn, percetakan kolaka">
    <meta name="author" content="Aquiris Print & Acrylic Studio">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <link rel="canonical" href="https://aquiris.skillance.cloud/">

    <!-- Geo Tags for Local SEO (Popalia, Tanggetada, Kolaka) -->
    <meta name="geo.region" content="ID-SG">
    <meta name="geo.placename" content="Popalia, Kec. Tanggetada, Kabupaten Kolaka, Sulawesi Tenggara">
    <meta name="geo.position" content="-4.2818;121.5422">
    <meta name="ICBM" content="-4.2818, 121.5422">

    <!-- Open Graph / Facebook / WhatsApp Preview Meta Tags -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://aquiris.skillance.cloud/">
    <meta property="og:site_name" content="Aquiris Print Popalia">
    <meta property="og:locale" content="id_ID">
    <meta property="og:title" content="Aquiris Print • Percetakan & Rental Akrilik Rusunawa USN Popalia">
    <meta property="og:description" content="Kirim file tugas, makalah & skripsi online tanpa antre! Tarif mulai Rp 500/lbr. Percetakan resmi di Rusunawa USN Popalia, Kec. Tanggetada, Kolaka.">
    <meta property="og:image" content="https://aquiris.skillance.cloud/assets/images/og-banner.jpg">
    <meta property="og:image:secure_url" content="https://aquiris.skillance.cloud/assets/images/og-banner.jpg">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Aquiris Print Popalia Banner Layanan">

    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="https://aquiris.skillance.cloud/">
    <meta name="twitter:title" content="Aquiris Print • Percetakan & Rental Akrilik Rusunawa USN Popalia">
    <meta name="twitter:description" content="Kirim file cetak online tugas & skripsi langsung diproses printer toko. Melayani mahasiswa FTI USN Popalia & umum.">
    <meta name="twitter:image" content="https://aquiris.skillance.cloud/assets/images/og-banner.jpg">

    <!-- PWA & Mobile Web App Manifest -->
    <meta name="theme-color" content="#d97706">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Aquiris Print">
    <link rel="manifest" href="site.webmanifest">

    <!-- Google Rich Results / Schema.org JSON-LD Structured Data for Local Business -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": ["LocalBusiness", "PrintShop"],
      "name": "Aquiris Print & Acrylic Studio",
      "alternateName": ["Aquiris Print Popalia", "Aquiris Print"],
      "url": "https://aquiris.skillance.cloud/",
      "logo": "https://aquiris.skillance.cloud/assets/images/icon-mark.png",
      "image": "https://aquiris.skillance.cloud/assets/images/og-banner.jpg",
      "description": "Pusat percetakan, print tugas mahasiswa FTI USN, cetak skripsi, fotocopy, dan persewaan papan akrilik event di Rusunawa USN Popalia, Tanggetada, Kolaka.",
      "telephone": "+6281525876330",
      "priceRange": "Rp 500 - Rp 150.000",
      "currenciesAccepted": "IDR",
      "paymentAccepted": "Cash, Transfer Bank, QRIS",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "Rusunawa Kampus FTI USN Popalia",
        "addressLocality": "Tanggetada",
        "addressRegion": "Sulawesi Tenggara",
        "postalCode": "93563",
        "addressCountry": "ID"
      },
      "geo": {
        "@type": "GeoCoordinates",
        "latitude": -4.2818,
        "longitude": 121.5422
      },
      "openingHoursSpecification": [
        {
          "@type": "OpeningHoursSpecification",
          "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"],
          "opens": "08:00",
          "closes": "21:00"
        }
      ],
      "hasOfferCatalog": {
        "@type": "OfferCatalog",
        "name": "Layanan Percetakan & Sewa Akrilik",
        "itemListElement": [
          {
            "@type": "Offer",
            "itemOffered": {
              "@type": "Service",
              "name": "Cetak Dokumen Teks Hitam Putih (B/W)"
            },
            "price": "500",
            "priceCurrency": "IDR"
          },
          {
            "@type": "Offer",
            "itemOffered": {
              "@type": "Service",
              "name": "Cetak Dokumen & Gambar Berwarna"
            },
            "price": "1000",
            "priceCurrency": "IDR"
          },
          {
            "@type": "Offer",
            "itemOffered": {
              "@type": "Service",
              "name": "Sewa Papan Akrilik Event & Wisuda"
            },
            "price": "25000",
            "priceCurrency": "IDR"
          }
        ]
      },
      "potentialAction": {
        "@type": "OrderAction",
        "target": {
          "@type": "EntryPoint",
          "urlTemplate": "https://aquiris.skillance.cloud/#order-form",
          "inLanguage": "id-ID",
          "actionPlatform": [
            "http://schema.org/DesktopWebPlatform",
            "http://schema.org/MobileWebPlatform"
          ]
        },
        "result": {
          "@type": "Order",
          "orderStatus": "http://schema.org/OrderProcessing"
        }
      }
    }
    </script>
    
    <!-- Favicon -->
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
                        }
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', '-apple-system', 'BlinkMacSystemFont', 'sans-serif'],
                        serif: ['"Playfair Display"', 'Georgia', 'serif'],
                    }
                }
            }
        }
    </script>

    <!-- Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #FAF8F5;
            color: #292524;
        }
        .serif-font {
            font-family: 'Playfair Display', Georgia, serif;
        }
        .dragover {
            border-color: #d97706 !important;
            background-color: #fef3c7 !important;
        }
    </style>
</head>
<body class="selection:bg-amber-100 selection:text-amber-900 antialiased">

    <!-- Top Announcement Bar -->
    <div class="bg-stone-900 text-stone-200 text-xs py-2 px-4 border-b border-stone-800">
        <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2 text-center sm:text-left">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span class="font-medium text-stone-300">
                    <strong class="text-white">Aquiris Print</strong> • Rusunawa USN Popalia, Kec. Tanggetada, Kolaka
                </span>
                <span class="hidden lg:inline text-stone-500">|</span>
                <span class="hidden lg:inline text-[11px] text-amber-300/90 font-medium">Mitra Skillance Digital Indonesia</span>
            </div>
            <div class="flex items-center gap-3 sm:gap-4 text-[11px] text-stone-300">
                <span><i class="fa-brands fa-whatsapp text-emerald-400 mr-1"></i> 0815-2587-6330</span>
                <span><i class="fa-brands fa-whatsapp text-emerald-400 mr-1"></i> 0821-4481-1008</span>
                <?php if (isLoggedIn()): ?>
                    <a href="dashboard.php" class="text-amber-300 hover:text-white font-bold ml-1 transition flex items-center gap-1">
                        <i class="fa-solid fa-gauge-high"></i> Ke Dashboard &rarr;
                    </a>
                <?php else: ?>
                    <a href="login.php" class="text-amber-300 hover:text-white font-bold ml-1 transition flex items-center gap-1">
                        <i class="fa-solid fa-lock"></i> Masuk Kasir &rarr;
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Navigation Bar -->
    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-stone-200/80 shadow-xs">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 h-20 flex items-center justify-between">
            <!-- Brand Logo -->
            <a href="index.php" class="flex items-center gap-3 group">
                <div class="w-12 h-12 rounded-2xl bg-white border border-stone-200 flex items-center justify-center p-1.5 shadow-sm group-hover:scale-105 transition-transform shrink-0">
                    <img src="assets/images/icon-mark.png" alt="Aquiris Print" class="w-full h-full object-contain" />
                </div>
                <div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-stone-900 font-extrabold text-lg tracking-tight leading-none">AQUIRIS</span>
                        <span class="px-1.5 py-0.5 bg-blue-600 text-white font-black text-[10px] rounded-md tracking-wider uppercase">PRINT</span>
                    </div>
                    <span class="text-xs text-stone-500 font-medium block mt-0.5">Percetakan & Sewa Akrilik</span>
                </div>
            </a>

            <!-- Nav Links (Desktop) -->
            <nav class="hidden md:flex items-center gap-7 text-xs font-bold text-stone-600">
                <a href="#order-form" class="hover:text-amber-700 transition">Kirim File Cetak</a>
                <a href="#cek-status" class="hover:text-amber-700 transition flex items-center gap-1.5"><i class="fa-solid fa-magnifying-glass text-[10px] text-amber-600"></i> Cek Status</a>
                <a href="#layanan" class="hover:text-amber-700 transition">Tarif Layanan</a>
                <a href="#akrilik" class="hover:text-amber-700 transition">Rental Akrilik</a>
                <a href="#cara-kerja" class="hover:text-amber-700 transition">Cara Pesan</a>
                <a href="#lokasi" class="hover:text-amber-700 transition">Lokasi Studio</a>
            </nav>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2.5">
                <a href="#order-form" class="px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold shadow-md shadow-amber-600/20 transition flex items-center gap-2 transform active:scale-95">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <span>Kirim File Sekarang</span>
                </a>
                <?php if (isLoggedIn()): ?>
                    <a href="dashboard.php" class="px-3.5 py-2.5 bg-amber-100 hover:bg-amber-200 text-amber-900 rounded-xl text-xs font-bold transition flex items-center gap-1.5" title="Buka Dashboard Internal">
                        <i class="fa-solid fa-gauge-high text-amber-700"></i>
                        <span class="hidden sm:inline">Dashboard</span>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="px-3.5 py-2.5 bg-stone-100 hover:bg-stone-200 text-stone-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5" title="Login untuk Karyawan/Kasir">
                        <i class="fa-solid fa-user-lock text-stone-500"></i>
                        <span class="hidden sm:inline">Kasir</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="relative overflow-hidden pt-10 pb-16 md:pt-14 md:pb-20 bg-gradient-to-b from-[#FAF8F5] via-[#F5F0E6]/60 to-[#FAF8F5]">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <!-- Hero Top Badge -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 bg-red-100/80 border border-red-200 text-red-900 rounded-full text-xs font-bold shadow-xs">
                    <span class="w-2 h-2 rounded-full bg-red-600 animate-pulse"></span>
                    <span>Butuh Print Cepat dan Murah? Rusunawa USN Popalia</span>
                </div>
            </div>

            <!-- Big Poster Headline -->
            <div class="text-center max-w-3xl mx-auto mb-10">
                <p class="text-xs sm:text-sm font-extrabold uppercase tracking-widest text-stone-500 mb-1">Pusat Layanan Mahasiswa & Umum</p>
                <h1 class="text-3xl sm:text-5xl md:text-6xl font-black text-stone-900 tracking-tight leading-none mb-3">
                    AQUIRIS <span class="text-red-600">PRINT</span>
                </h1>
                <p class="serif-font text-lg sm:text-2xl font-bold text-stone-800 italic mb-4">
                    "Siap Membantu Kebutuhan Cetak Anda !!!"
                </p>
                <p class="text-xs sm:text-sm text-stone-600 max-w-xl mx-auto leading-relaxed">
                    Melayani cetak tugas, skripsi, makalah, CV, dan berkas khusus <strong>Mahasiswa FTI USN Popalia</strong>. Kirim file Anda dari kamar/rumah, langsung diproses ke printer toko!
                </p>

                <!-- Official Price Highlights (Exactly as on Poster) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-8 max-w-xl mx-auto">
                    <!-- Price Card 1: Document -->
                    <div class="p-4 rounded-3xl bg-white border-2 border-stone-200/90 shadow-sm flex items-center justify-between hover:border-amber-400 transition">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-2xl bg-stone-100 text-stone-800 flex items-center justify-center text-xl font-bold">
                                <i class="fa-solid fa-file-lines"></i>
                            </div>
                            <div class="text-left">
                                <span class="font-bold text-stone-900 text-sm block">Document Teks</span>
                                <span class="text-[11px] text-stone-500">HVS A4 / Letter / F4</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-stone-400 block font-semibold">Tarif</span>
                            <span class="text-xl font-black text-stone-900">Rp 500 <span class="text-[11px] font-normal text-stone-500">/lbr</span></span>
                        </div>
                    </div>

                    <!-- Price Card 2: Bergambar -->
                    <div class="p-4 rounded-3xl bg-white border-2 border-red-200 shadow-sm flex items-center justify-between hover:border-red-400 transition">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-2xl bg-red-100 text-red-700 flex items-center justify-center text-xl font-bold">
                                <i class="fa-solid fa-image"></i>
                            </div>
                            <div class="text-left">
                                <span class="font-bold text-stone-900 text-sm block">Bergambar / Warna</span>
                                <span class="text-[11px] text-stone-500">HVS A4 / Letter / F4</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-stone-400 block font-semibold">Tarif</span>
                            <span class="text-xl font-black text-red-600">Rp 1.000 <span class="text-[11px] font-normal text-stone-500">/lbr</span></span>
                        </div>
                    </div>
                </div>

                <!-- CTA Jump Buttons -->
                <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                    <a href="#order-form" class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-2xl text-xs sm:text-sm font-extrabold shadow-lg shadow-red-600/25 transition transform active:scale-95 flex items-center gap-2">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <span>Kirim File Cetak Sekarang</span>
                    </a>
                    <a href="#brosur-layanan" class="px-5 py-3 bg-white hover:bg-stone-50 text-stone-800 border border-stone-200 rounded-2xl text-xs font-bold transition flex items-center gap-2 shadow-xs">
                        <i class="fa-solid fa-rectangle-list text-amber-600"></i>
                        <span>Lihat Rincian Layanan & Brosur</span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Poster Service Overview Section (Sesuai Brosur Resmi) -->
    <section id="brosur-layanan" class="py-12 bg-white border-y border-stone-200 scroll-mt-20">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="text-center max-w-xl mx-auto mb-10">
                <span class="text-[11px] font-black uppercase tracking-wider text-red-700 bg-red-50 border border-red-200 px-3 py-1 rounded-full">
                    Daftar Layanan Resmi Saat Ini
                </span>
                <h2 class="serif-font text-2xl sm:text-3xl font-bold text-stone-900 mt-2 mb-1">
                    Layanan & Tarif Aquiris Print
                </h2>
                <p class="text-xs text-stone-500">
                    Sesuai brosur resmi Aquiris Print bersama Skillance Digital Indonesia
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-stretch">
                <!-- Column 1: Melayani Umum -->
                <div class="bg-gradient-to-br from-[#FAF8F5] to-amber-50/40 rounded-3xl border border-stone-200/90 p-6 flex flex-col justify-between shadow-xs">
                    <div>
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-2xl bg-amber-500 text-white flex items-center justify-center font-bold text-lg">
                                <i class="fa-solid fa-print"></i>
                            </div>
                            <div>
                                <h3 class="font-black text-stone-900 text-base">Melayani :</h3>
                                <p class="text-[11px] text-stone-500">Kebutuhan Mahasiswa & Umum</p>
                            </div>
                        </div>

                        <ul class="space-y-2.5 text-xs">
                            <li class="flex items-center justify-between p-2.5 bg-white rounded-xl border border-stone-100 shadow-2xs font-semibold text-stone-800">
                                <div class="flex items-center gap-2.5">
                                    <i class="fa-solid fa-circle-check text-emerald-600 text-xs"></i>
                                    <span>Print Dokumen</span>
                                </div>
                                <span class="font-bold text-stone-900 font-mono text-[11px]">Rp 500/lbr</span>
                            </li>
                            <li class="flex items-center justify-between p-2.5 bg-white rounded-xl border border-stone-100 shadow-2xs font-semibold text-stone-800">
                                <div class="flex items-center gap-2.5">
                                    <i class="fa-solid fa-circle-check text-emerald-600 text-xs"></i>
                                    <span>Print Skripsi</span>
                                </div>
                                <span class="font-bold text-stone-900 font-mono text-[11px]">Rp 500 - 1.000</span>
                            </li>
                            <li class="flex items-center justify-between p-2.5 bg-white rounded-xl border border-stone-100 shadow-2xs font-semibold text-stone-800">
                                <div class="flex items-center gap-2.5">
                                    <i class="fa-solid fa-circle-check text-emerald-600 text-xs"></i>
                                    <span>Print Makalah</span>
                                </div>
                                <span class="font-bold text-stone-900 font-mono text-[11px]">Rp 500/lbr</span>
                            </li>
                            <li class="flex items-center justify-between p-2.5 bg-white rounded-xl border border-stone-100 shadow-2xs font-semibold text-stone-800">
                                <div class="flex items-center gap-2.5">
                                    <i class="fa-solid fa-circle-check text-red-600 text-xs"></i>
                                    <span>Print Gambar / Warna</span>
                                </div>
                                <span class="font-bold text-red-600 font-mono text-[11px]">Rp 1.000/lbr</span>
                            </li>
                            <li class="flex items-center justify-between p-2.5 bg-white rounded-xl border border-stone-100 shadow-2xs font-semibold text-stone-800">
                                <div class="flex items-center gap-2.5">
                                    <i class="fa-solid fa-circle-check text-emerald-600 text-xs"></i>
                                    <span>Print CV / Portofolio</span>
                                </div>
                                <span class="font-bold text-stone-900 font-mono text-[11px]">Rp 1.000/lbr</span>
                            </li>
                            <li class="flex items-center justify-between p-2.5 bg-white rounded-xl border border-stone-100 shadow-2xs font-semibold text-stone-800">
                                <div class="flex items-center gap-2.5">
                                    <i class="fa-solid fa-circle-check text-emerald-600 text-xs"></i>
                                    <span>Print Tugas Sekolah</span>
                                </div>
                                <span class="font-bold text-stone-900 font-mono text-[11px]">Rp 500/lbr</span>
                            </li>
                        </ul>
                    </div>
                    <div class="mt-4 pt-3 border-t border-stone-200/80 text-[11px] text-stone-500">
                        <i class="fa-solid fa-layer-group text-amber-600 mr-1"></i> Pilihan Kertas: <strong>HVS A4, Letter, F4</strong>
                    </div>
                </div>

                <!-- Column 2: Khusus FTI (Fakultas Teknologi Informasi USN) -->
                <div class="bg-gradient-to-br from-red-50/70 via-white to-amber-50/40 rounded-3xl border-2 border-red-200 p-6 flex flex-col justify-between shadow-xs relative overflow-hidden">
                    <span class="absolute -top-3 -right-8 bg-red-600 text-white text-[9px] font-black uppercase tracking-wider py-1 px-8 rotate-45 shadow-xs">
                        Spesial FTI
                    </span>
                    <div>
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-2xl bg-red-600 text-white flex items-center justify-center font-bold text-lg shadow-sm">
                                <i class="fa-solid fa-graduation-cap"></i>
                            </div>
                            <div>
                                <h3 class="font-black text-red-900 text-base">Khusus FTI :</h3>
                                <p class="text-[11px] text-red-700/80 font-medium">Fakultas Teknologi Informasi (USN)</p>
                            </div>
                        </div>

                        <ul class="space-y-3 text-xs">
                            <li class="p-3 bg-white rounded-2xl border border-red-100 shadow-2xs">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-bold text-stone-900 flex items-center gap-2">
                                        <i class="fa-solid fa-certificate text-red-600 text-xs"></i>
                                        <span>Print Lembar Bimbingan</span>
                                    </span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] bg-red-100 text-red-800 font-black">
                                        Rp 500
                                    </span>
                                </div>
                                <p class="text-[11px] text-stone-500">Format resmi bimbingan tugas akhir & skripsi FTI.</p>
                            </li>

                            <li class="p-3 bg-white rounded-2xl border border-red-100 shadow-2xs">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-bold text-stone-900 flex items-center gap-2">
                                        <i class="fa-solid fa-id-card text-red-600 text-xs"></i>
                                        <span>Print Kartu Peserta Ujian</span>
                                    </span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] bg-red-100 text-red-800 font-black">
                                        Rp 1.000
                                    </span>
                                </div>
                                <p class="text-[11px] text-stone-500">Cetak tajam & rapi kartu ujian semester UTS / UAS.</p>
                            </li>

                            <li class="p-3 bg-white rounded-2xl border border-amber-200 bg-amber-50/50 shadow-2xs">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-bold text-stone-900 flex items-center gap-2">
                                        <i class="fa-solid fa-file text-amber-500 text-xs"></i>
                                        <span>Print Lembar Kuning</span>
                                    </span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] bg-amber-200 text-amber-900 font-black">
                                        Rp 1.000
                                    </span>
                                </div>
                                <p class="text-[11px] text-stone-600">Kertas kuning khusus berkas administrasi FTI USN.</p>
                            </li>
                        </ul>
                    </div>

                    <div class="mt-4 pt-3 border-t border-red-100 text-[11px] text-red-800 font-semibold flex items-center gap-1.5">
                        <i class="fa-solid fa-location-dot"></i>
                        <span>Dekat Rusunawa USN Popalia</span>
                    </div>
                </div>

                <!-- Column 3: Brosur Resmi & Kontak Pelayanan -->
                <div class="bg-gradient-to-br from-stone-900 to-stone-850 text-white rounded-3xl p-6 flex flex-col justify-between shadow-md">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-[10px] font-mono tracking-widest uppercase text-amber-400 bg-stone-800 px-2.5 py-1 rounded-md">
                                Brosur Resmi
                            </span>
                            <span class="text-[10px] text-stone-400 font-semibold">Skillance Digital</span>
                        </div>

                        <!-- Poster Thumbnail Card -->
                        <div class="rounded-2xl overflow-hidden border border-stone-700 bg-stone-800 relative group cursor-pointer mb-4" onclick="openPosterModal()">
                            <img src="assets/images/poster-layanan.png" alt="Poster Brosur Aquiris Print" class="w-full h-44 object-cover object-top group-hover:scale-105 transition duration-300" />
                            <div class="absolute inset-0 bg-stone-950/40 group-hover:bg-stone-950/20 transition flex items-center justify-center">
                                <span class="px-3 py-1.5 bg-stone-900/90 text-white font-bold text-xs rounded-xl border border-stone-600 backdrop-blur-xs flex items-center gap-1.5">
                                    <i class="fa-solid fa-magnifying-glass-plus text-amber-400"></i>
                                    <span>Klik untuk Perbesar</span>
                                </span>
                            </div>
                        </div>

                        <div class="space-y-1.5 text-xs text-stone-300">
                            <p class="flex items-center gap-2">
                                <i class="fa-solid fa-location-dot text-amber-400 text-xs"></i>
                                <span class="text-[11px]">Rusunawa USN Popalia, Tanggetada, Kolaka</span>
                            </p>
                            <p class="flex items-center gap-2">
                                <i class="fa-brands fa-whatsapp text-emerald-400 text-xs"></i>
                                <span class="text-[11px] font-mono">0815-2587-6330 / 0821-4481-1008</span>
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-stone-800">
                        <a href="#order-form" class="w-full py-2.5 bg-amber-400 hover:bg-amber-300 text-stone-950 font-bold rounded-xl text-xs transition flex items-center justify-center gap-2">
                            <span>Kirim File via Website</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Order Tracking Section -->
    <section id="cek-status" class="py-10 bg-gradient-to-b from-[#FAF8F5] via-[#F4EFE6]/60 to-[#FAF8F5] border-t border-stone-200 scroll-mt-20">
        <div class="max-w-4xl mx-auto px-4 sm:px-6">
            <div class="bg-white rounded-3xl border-2 border-stone-200/90 shadow-sm p-6 sm:p-8">
                <div class="max-w-xl mx-auto text-center mb-6">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-extrabold uppercase tracking-wider bg-amber-100 text-amber-900 border border-amber-200 mb-2">
                        <i class="fa-solid fa-magnifying-glass text-amber-600"></i>
                        <span>Lacak Pesanan Mandiri</span>
                    </span>
                    <h2 class="serif-font text-2xl font-bold text-stone-900">
                        Cek Status & Progres Pesanan Cetak
                    </h2>
                    <p class="text-xs text-stone-600 mt-1.5 leading-relaxed">
                        Masukkan <strong>Kode Pesanan</strong> (contoh: <em>ORD-20261102-0001</em>) atau <strong>Nomor WhatsApp</strong> Anda untuk melihat status cetak terkini secara real-time.
                    </p>
                </div>

                <!-- Tracking Form -->
                <form method="GET" action="index.php#cek-status" class="max-w-lg mx-auto flex flex-col sm:flex-row gap-2.5 mb-6">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-stone-400 text-xs">
                            <i class="fa-solid fa-hashtag"></i>
                        </div>
                        <input type="text" name="track" value="<?= htmlspecialchars($trackingQuery) ?>" required
                               placeholder="Contoh: ORD-20261102-0001 atau 08123456..."
                               class="w-full pl-9 pr-4 py-2.5 bg-stone-50 border border-stone-300 rounded-xl outline-none focus:bg-white focus:ring-2 focus:ring-amber-500 font-semibold text-xs text-stone-900" />
                    </div>
                    <button type="submit" class="px-5 py-2.5 bg-stone-900 hover:bg-stone-800 text-amber-300 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 shrink-0 shadow-sm active:scale-95">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <span>Cek Pesanan</span>
                    </button>
                </form>

                <!-- Tracking Results -->
                <?php if (!empty($trackingError)): ?>
                    <div class="max-w-lg mx-auto p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs text-center flex items-center justify-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation text-rose-600"></i>
                        <span><?= htmlspecialchars($trackingError) ?></span>
                    </div>
                <?php elseif ($trackedOrder): ?>
                    <?php
                        $st = $trackedOrder['status'];
                        $stColors = [
                            'menunggu' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-800', 'border' => 'border-amber-200', 'icon' => 'fa-clock-rotate-left', 'label' => 'Menunggu Antrean Cetak'],
                            'diproses' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-800', 'border' => 'border-blue-200', 'icon' => 'fa-gear fa-spin', 'label' => 'Sedang Diproses / Dicetak'],
                            'selesai' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-800', 'border' => 'border-emerald-200', 'icon' => 'fa-circle-check', 'label' => 'Selesai & Siap Diambil'],
                            'dibatalkan' => ['bg' => 'bg-rose-50', 'text' => 'text-rose-800', 'border' => 'border-rose-200', 'icon' => 'fa-circle-xmark', 'label' => 'Dibatalkan']
                        ];
                        $currSt = $stColors[$st] ?? $stColors['menunggu'];

                        $waDirectMsg = "Halo Kasir Aquiris Print, saya ingin menanyakan pesanan cetak saya dengan kode *#{$trackedOrder['order_code']}*.";
                        $waDirectLink = buildWaLink($shopPhone, $waDirectMsg);
                    ?>
                    <div class="max-w-xl mx-auto bg-[#FAF8F5] rounded-2xl border-2 border-stone-200 p-5 text-xs shadow-sm space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-stone-200">
                            <div>
                                <span class="text-[10px] uppercase font-bold text-stone-400 block">Kode Pesanan</span>
                                <span class="font-mono font-black text-sm text-stone-900">#<?= htmlspecialchars($trackedOrder['order_code']) ?></span>
                            </div>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full font-black text-xs border <?= $currSt['bg'] ?> <?= $currSt['text'] ?> <?= $currSt['border'] ?>">
                                <i class="fa-solid <?= $currSt['icon'] ?>"></i>
                                <span><?= $currSt['label'] ?></span>
                            </span>
                        </div>

                        <!-- Progress Steps Tracker -->
                        <div class="grid grid-cols-3 gap-2 text-center text-[11px] py-1">
                            <div class="p-2.5 rounded-xl border <?= in_array($st, ['menunggu', 'diproses', 'selesai']) ? 'bg-amber-100/70 border-amber-300 text-amber-900 font-bold' : 'bg-white border-stone-200 text-stone-400' ?>">
                                <i class="fa-solid fa-inbox block text-sm mb-1 text-amber-600"></i>
                                <span>1. Diterima</span>
                            </div>
                            <div class="p-2.5 rounded-xl border <?= in_array($st, ['diproses', 'selesai']) ? 'bg-blue-100/70 border-blue-300 text-blue-900 font-bold' : 'bg-white border-stone-200 text-stone-400' ?>">
                                <i class="fa-solid fa-print block text-sm mb-1 text-blue-600"></i>
                                <span>2. Dicetak</span>
                            </div>
                            <div class="p-2.5 rounded-xl border <?= $st === 'selesai' ? 'bg-emerald-100/70 border-emerald-300 text-emerald-900 font-bold' : 'bg-white border-stone-200 text-stone-400' ?>">
                                <i class="fa-solid fa-circle-check block text-sm mb-1 text-emerald-600"></i>
                                <span>3. Siap Ambil</span>
                            </div>
                        </div>

                        <!-- Details -->
                        <div class="space-y-1.5 bg-white p-3.5 rounded-xl border border-stone-200 text-stone-600">
                            <div class="flex justify-between">
                                <span class="text-stone-400">Nama Pemesan:</span>
                                <span class="font-bold text-stone-800"><?= htmlspecialchars($trackedOrder['customer_name']) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-stone-400">Layanan & Kertas:</span>
                                <span class="font-bold text-stone-800"><?= htmlspecialchars($trackedOrder['service_type']) ?> (<?= htmlspecialchars($trackedOrder['paper_type']) ?>)</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-stone-400">Jumlah & Finishing:</span>
                                <span class="font-bold text-stone-800"><?= htmlspecialchars($trackedOrder['quantity']) ?> Rangkap • <?= htmlspecialchars($trackedOrder['finishing']) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-stone-400">Waktu Masuk:</span>
                                <span class="font-semibold text-stone-700"><?= formatDate($trackedOrder['created_at'], true) ?></span>
                            </div>
                        </div>

                        <!-- Dynamic Status Note & Action -->
                        <div class="p-3 rounded-xl <?= $currSt['bg'] ?> border <?= $currSt['border'] ?> text-[11px] <?= $currSt['text'] ?> flex items-start gap-2">
                            <i class="fa-solid fa-circle-info text-sm mt-0.5 shrink-0"></i>
                            <div>
                                <?php if ($st === 'menunggu'): ?>
                                    <strong>Pesanan Dalam Antrean:</strong> File Anda sudah kami terima dan menunggu giliran cetak. Kami akan otomatis mengabari via WhatsApp saat mulai diproses.
                                <?php elseif ($st === 'diproses'): ?>
                                    <strong>Sedang Dikerjakan:</strong> Tim operator kami saat ini sedang mencetak berkas Anda di printer toko. Mohon ditunggu sebentar ya!
                                <?php elseif ($st === 'selesai'): ?>
                                    <strong>Dokumen Siap Diambil:</strong> Cetakan Anda sudah selesai rapi! Silakan datang ke Studio Aquiris Print di <strong>Rusunawa USN Popalia</strong> dan sebutkan kode #<?= htmlspecialchars($trackedOrder['order_code']) ?> kepada kasir.
                                <?php elseif ($st === 'dibatalkan'): ?>
                                    <strong>Pesanan Dibatalkan:</strong> Hubungi kasir jika ada kendala berkas atau ingin mengirim pesanan baru.
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3 pt-2">
                            <a href="<?= $waDirectLink ?>" target="_blank" class="flex-1 py-2 px-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-center transition flex items-center justify-center gap-1.5 shadow-sm">
                                <i class="fa-brands fa-whatsapp text-sm"></i>
                                <span>Hubungi Kasir Toko</span>
                            </a>
                            <a href="index.php#order-form" class="py-2 px-3 bg-stone-200 hover:bg-stone-300 text-stone-800 font-bold rounded-xl text-center transition">
                                Pesan Lagi
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Main Order & File Upload Form Section -->
    <section id="order-form" class="py-10 bg-white border-y border-stone-200 scroll-mt-24">
        <div class="max-w-4xl mx-auto px-4 sm:px-6">

            <!-- Success Card if just submitted -->
            <?php if ($orderSubmitted && $submittedOrder): ?>
                <?php
                    $waDirectOperatorMsg = "Halo Operator Aquiris Print, saya sudah mengirim pesanan online di website!\n\n"
                                         . "Kode Pesanan: *#{$submittedOrder['order_code']}*\n"
                                         . "Nama: *{$submittedOrder['customer_name']}*\n"
                                         . "Layanan: *{$submittedOrder['service_type']}*\n"
                                         . "File: {$submittedOrder['file_name']}\n\n"
                                         . "Mohon dicek ya mas/mbak, terima kasih!";
                    $waDirectFallbackLink = buildWaLink($operatorPhone, $waDirectOperatorMsg);
                ?>
                <div class="mb-10 p-6 md:p-8 bg-gradient-to-br from-emerald-50 via-white to-amber-50 rounded-3xl border-2 border-emerald-300 shadow-xl relative overflow-hidden">
                    <div class="text-center max-w-lg mx-auto">
                        <div class="w-16 h-16 rounded-full bg-emerald-500 text-white flex items-center justify-center text-3xl mx-auto mb-4 shadow-lg shadow-emerald-500/20 animate-bounce">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <span class="text-xs font-black uppercase tracking-wider text-emerald-700 bg-emerald-100 px-3 py-1 rounded-full">
                            Pesanan Berhasil Dikirim!
                        </span>
                        <h3 class="serif-font text-2xl font-bold text-stone-900 mt-2 mb-1">
                            File Anda Sudah Diteruskan ke Operator Cetak
                        </h3>
                        <p class="text-xs text-stone-600 mb-6">
                            Terima kasih kak <strong><?= htmlspecialchars($submittedOrder['customer_name']) ?></strong>! Rincian pesanan dan link file cetak telah dikirimkan ke WhatsApp di laptop operator toko kami.
                        </p>

                        <!-- Order Summary Card -->
                        <div class="bg-white rounded-2xl border border-stone-200 p-4 text-left text-xs mb-6 shadow-xs space-y-2">
                            <div class="flex items-center justify-between pb-2 border-b border-stone-100">
                                <span class="text-stone-400 font-semibold">Kode Pesanan:</span>
                                <span class="font-mono font-black text-sm text-stone-900 bg-stone-100 px-2.5 py-0.5 rounded-lg">#<?= htmlspecialchars($submittedOrder['order_code']) ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-stone-500">Layanan & Kertas:</span>
                                <span class="font-bold text-stone-800"><?= htmlspecialchars($submittedOrder['service_type']) ?> • <?= htmlspecialchars($submittedOrder['paper_type']) ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-stone-500">Jumlah & Finishing:</span>
                                <span class="font-bold text-stone-800"><?= htmlspecialchars($submittedOrder['quantity']) ?> Rangkap • <?= htmlspecialchars($submittedOrder['finishing']) ?></span>
                            </div>
                            <?php if (!empty($submittedOrder['file_name'])): ?>
                                <div class="flex items-center justify-between pt-2 border-t border-stone-100">
                                    <span class="text-stone-500">File Terlampir:</span>
                                    <span class="font-bold text-emerald-700 truncate max-w-[200px]"><?= htmlspecialchars($submittedOrder['file_name']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- 1-Click WhatsApp Fallback Button -->
                        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                            <a href="<?= $waDirectFallbackLink ?>" target="_blank" class="w-full sm:w-auto px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-lg shadow-emerald-600/20 transition flex items-center justify-center gap-2">
                                <i class="fa-brands fa-whatsapp text-base"></i>
                                <span>Konfirmasi / Chat Operator via WhatsApp</span>
                            </a>
                            <a href="index.php#order-form" class="w-full sm:w-auto px-5 py-3 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold rounded-xl text-xs transition">
                                Kirim Pesanan Lain
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Form Card -->
            <div class="bg-[#FAF8F5] rounded-3xl border border-stone-200/90 shadow-md p-6 sm:p-10 relative">
                <div class="pb-6 border-b border-stone-200/80 mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-amber-500 text-white flex items-center justify-center text-lg font-bold shadow-sm">
                            <i class="fa-solid fa-print"></i>
                        </div>
                        <div>
                            <h2 class="serif-font text-xl sm:text-2xl font-bold text-stone-900">Form Pemesanan & Kirim File Cetak</h2>
                            <p class="text-xs text-stone-500 mt-0.5">Isi rincian cetakan & unggah file Anda, pesanan akan langsung diteruskan ke WhatsApp operator toko</p>
                        </div>
                    </div>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 text-xs flex items-center gap-3">
                        <i class="fa-solid fa-triangle-exclamation text-rose-500 text-base"></i>
                        <span class="font-semibold"><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="index.php#order-form" enctype="multipart/form-data" id="orderSubmitForm" class="space-y-6 text-xs">
                    <input type="hidden" name="submit_online_order" value="1" />

                    <!-- Section 1: Data Pemesan -->
                    <div>
                        <span class="text-[11px] font-extrabold text-amber-800 uppercase tracking-wider block mb-3 flex items-center gap-1.5">
                            <i class="fa-solid fa-user text-amber-600"></i>
                            <span>1. Data Diri Pemesan</span>
                        </span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-stone-700 mb-1.5" for="customer_name">Nama Lengkap Anda *</label>
                                <input type="text" name="customer_name" id="customer_name" required placeholder="Contoh: Budi Santoso / Maya Putri"
                                       class="w-full px-4 py-2.5 bg-white border border-stone-300 rounded-xl outline-none focus:ring-2 focus:ring-amber-500 font-semibold text-stone-900 text-xs" />
                            </div>
                            <div>
                                <label class="block font-bold text-stone-700 mb-1.5" for="customer_phone">Nomor WhatsApp Aktif *</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-emerald-600 font-bold">
                                        <i class="fa-brands fa-whatsapp"></i>
                                    </div>
                                    <input type="tel" name="customer_phone" id="customer_phone" required placeholder="Contoh: 081234567890"
                                           class="w-full pl-9 pr-4 py-2.5 bg-white border border-stone-300 rounded-xl outline-none focus:ring-2 focus:ring-emerald-500 font-bold text-emerald-800 text-xs" />
                                </div>
                                <div class="mt-2 p-2.5 rounded-xl bg-emerald-50 border border-emerald-200/80 text-[11px] text-emerald-900 flex items-start gap-2 shadow-xs">
                                    <i class="fa-brands fa-whatsapp text-emerald-600 text-sm mt-0.5 shrink-0"></i>
                                    <div class="leading-tight">
                                        <strong class="font-bold">Notifikasi Status Otomatis:</strong>
                                        <span class="text-stone-600 block mt-0.5">Sistem akan otomatis mengirim pesan WA saat pesanan Anda <em>mulai dicetak</em> & saat <em>selesai siap diambil</em> di Rusunawa USN Popalia.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Pilihan Layanan & Spesifikasi -->
                    <div class="pt-2 border-t border-stone-200">
                        <span class="text-[11px] font-extrabold text-amber-800 uppercase tracking-wider block mb-3 flex items-center gap-1.5">
                            <i class="fa-solid fa-sliders text-amber-600"></i>
                            <span>2. Pilihan Layanan & Rincian Cetak</span>
                        </span>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-stone-700 mb-1.5" for="service_type">Kategori Layanan Cetak *</label>
                                <select name="service_type" id="service_type" class="w-full px-3 py-2.5 bg-white border border-stone-300 rounded-xl outline-none font-bold text-stone-800 text-xs">
                                    <optgroup label="── Melayani Umum (Rp 500 - 1.000) ──">
                                        <option value="Print Dokumen Teks (Rp 500 / lbr)">Print Dokumen Teks (Rp 500 / lbr)</option>
                                        <option value="Print Skripsi (Teks Rp 500 / Bergambar Rp 1.000)">Print Skripsi (Teks Rp 500 / Bergambar Rp 1.000)</option>
                                        <option value="Print Makalah (Rp 500 / lbr)">Print Makalah (Rp 500 / lbr)</option>
                                        <option value="Print Gambar / Full Warna (Rp 1.000 / lbr)">Print Gambar / Full Warna (Rp 1.000 / lbr)</option>
                                        <option value="Print CV / Resume (Rp 1.000 / lbr)">Print CV / Resume (Rp 1.000 / lbr)</option>
                                        <option value="Print Tugas Sekolah / Kuliah (Rp 500 / lbr)">Print Tugas Sekolah / Kuliah (Rp 500 / lbr)</option>
                                    </optgroup>
                                    <optgroup label="── Khusus Mahasiswa FTI (USN Popalia) ──">
                                        <option value="[Khusus FTI] Print Lembar Bimbingan (Rp 500)">[Khusus FTI] Print Lembar Bimbingan (Rp 500)</option>
                                        <option value="[Khusus FTI] Print Kartu Peserta Ujian (Rp 1.000)">[Khusus FTI] Print Kartu Peserta Ujian (Rp 1.000)</option>
                                        <option value="[Khusus FTI] Print Lembar Kuning (Rp 1.000)">[Khusus FTI] Print Lembar Kuning (Rp 1.000)</option>
                                    </optgroup>
                                    <optgroup label="── Layanan Lainnya ──">
                                        <option value="Fotocopy HVS (1 Sisi / Bolak-Balik)">Fotocopy HVS (1 Sisi / Bolak-Balik)</option>
                                        <option value="Jilid Dokumen (Spiral / Lakban / Hardcover)">Jilid Dokumen (Spiral / Lakban / Hardcover)</option>
                                        <option value="Sewa Papan Akrilik Event & Wisuda">Sewa Papan Akrilik Event & Wisuda</option>
                                        <option value="Cetak Custom / Lainnya">Cetak Custom / Lainnya</option>
                                    </optgroup>
                                </select>
                            </div>

                            <div>
                                <label class="block font-bold text-stone-700 mb-1.5" for="paper_type">Pilihan Kertas *</label>
                                <select name="paper_type" id="paper_type" class="w-full px-3 py-2.5 bg-white border border-stone-300 rounded-xl outline-none font-bold text-stone-800 text-xs">
                                    <option value="HVS A4 (Standar Skripsi / Tugas)">HVS A4 (Standar Skripsi / Tugas)</option>
                                    <option value="HVS Letter">HVS Letter</option>
                                    <option value="HVS F4 / Folio">HVS F4 / Folio</option>
                                    <option value="Kertas Kuning Khusus (FTI)">Kertas Kuning Khusus (FTI)</option>
                                    <option value="Kertas Foto Glossy A4">Kertas Foto Glossy A4</option>
                                    <option value="Bahan Khusus / Bawa Sendiri">Bahan Khusus / Bawa Sendiri</option>
                                </select>
                            </div>

                            <div>
                                <label class="block font-bold text-stone-700 mb-1.5">Mode Cetak & Tarif</label>
                                <div class="grid grid-cols-3 gap-2">
                                    <label class="flex flex-col items-center justify-center p-2 bg-white border border-stone-200 rounded-xl cursor-pointer hover:bg-stone-50 font-bold text-[11px] text-stone-700 text-center has-[:checked]:border-stone-900 has-[:checked]:bg-stone-900 has-[:checked]:text-amber-300 transition">
                                        <input type="radio" name="color_mode" value="Document (Teks Rp 500/lbr)" checked class="hidden" />
                                        <span>Document (Teks)</span>
                                        <span class="text-[10px] font-mono opacity-80">Rp 500/lbr</span>
                                    </label>
                                    <label class="flex flex-col items-center justify-center p-2 bg-white border border-stone-200 rounded-xl cursor-pointer hover:bg-stone-50 font-bold text-[11px] text-stone-700 text-center has-[:checked]:border-red-600 has-[:checked]:bg-red-600 has-[:checked]:text-white transition">
                                        <input type="radio" name="color_mode" value="Bergambar / Warna (Rp 1.000/lbr)" class="hidden" />
                                        <span>Bergambar</span>
                                        <span class="text-[10px] font-mono opacity-80">Rp 1.000/lbr</span>
                                    </label>
                                    <label class="flex flex-col items-center justify-center p-2 bg-white border border-stone-200 rounded-xl cursor-pointer hover:bg-stone-50 font-bold text-[11px] text-stone-700 text-center has-[:checked]:border-amber-600 has-[:checked]:bg-amber-600 has-[:checked]:text-white transition">
                                        <input type="radio" name="color_mode" value="Campuran (Sebagian Teks & Gambar)" class="hidden" />
                                        <span>Campuran</span>
                                        <span class="text-[10px] font-mono opacity-80">Teks + Gambar</span>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label class="block font-bold text-stone-700 mb-1.5" for="quantity">Jumlah Rangkap / Eksemplar</label>
                                <div class="flex items-center gap-2">
                                    <input type="number" name="quantity" id="quantity" value="1" min="1" max="1000" required
                                           class="w-32 px-3 py-2 bg-white border border-stone-300 rounded-xl outline-none font-bold text-stone-900 text-xs" />
                                    <span class="text-stone-500 text-[11px]">Set / Rangkap / Buku</span>
                                </div>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block font-bold text-stone-700 mb-1.5" for="finishing">Pilihan Jilid & Finishing</label>
                                <select name="finishing" id="finishing" class="w-full px-3 py-2.5 bg-white border border-stone-300 rounded-xl outline-none font-medium text-stone-800 text-xs">
                                    <option value="Tanpa Jilid (Staples di Pojok)">Tanpa Jilid (Staples di Pojok)</option>
                                    <option value="Jilid Lakban Hitam + Mika Bening Depan & Buffalo Belakang">Jilid Lakban Hitam + Mika Bening Depan & Buffalo Belakang</option>
                                    <option value="Jilid Spiral Kawat A4 (Cover Mika Bening)">Jilid Spiral Kawat A4 (Cover Mika Bening)</option>
                                    <option value="Jilid Hardcover Skripsi / Tesis Tinta Emas">Jilid Hardcover Skripsi / Tesis Tinta Emas</option>
                                    <option value="Laminasi Panas Glossy / Doff">Laminasi Panas Glossy / Doff</option>
                                    <option value="Potong / Cutting Custom">Potong / Cutting Custom</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Catatan Pemesan -->
                    <div class="pt-2 border-t border-stone-200">
                        <span class="text-[11px] font-extrabold text-amber-800 uppercase tracking-wider block mb-2 flex items-center gap-1.5">
                            <i class="fa-solid fa-comment-dots text-amber-600"></i>
                            <span>3. Catatan Khusus untuk Operator Toko</span>
                        </span>
                        <textarea name="notes" id="notes" rows="3"
                                  placeholder="Contoh: Tolong print bolak-balik halaman 1-20, halaman 21-25 full warna satu sisi. Cover mika bening. Mau diambil jam 16.00 WIB."
                                  class="w-full px-4 py-2.5 bg-white border border-stone-300 rounded-xl outline-none focus:ring-2 focus:ring-amber-500 text-stone-800 text-xs leading-relaxed"></textarea>
                    </div>

                    <!-- Section 4: File Upload -->
                    <div class="pt-2 border-t border-stone-200">
                        <span class="text-[11px] font-extrabold text-amber-800 uppercase tracking-wider block mb-2 flex items-center gap-1.5">
                            <i class="fa-solid fa-cloud-arrow-up text-amber-600"></i>
                            <span>4. Unggah File Dokumen Cetakan</span>
                        </span>

                        <div id="dropZone" class="border-2 border-dashed border-stone-300 hover:border-amber-500 rounded-2xl p-6 sm:p-8 text-center bg-white transition cursor-pointer relative">
                            <input type="file" name="document_file" id="fileInput" class="hidden" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip,.rar" onchange="handleFileSelect(this)" />
                            
                            <div id="uploadPrompt" class="space-y-2">
                                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl mx-auto shadow-xs">
                                    <i class="fa-solid fa-file-arrow-up"></i>
                                </div>
                                <p class="font-bold text-stone-800 text-xs sm:text-sm">
                                    Klik di sini untuk memilih file, atau drag & drop ke kotak ini
                                </p>
                                <p class="text-[11px] text-stone-400">
                                    Mendukung PDF, Word (DOCX), Excel, PPT, JPG/PNG, atau arsip ZIP/RAR (Maksimal 50 MB)
                                </p>
                            </div>

                            <!-- Selected File Preview -->
                            <div id="filePreview" class="hidden mt-3 p-3 bg-stone-50 border border-stone-200 rounded-xl flex items-center justify-between gap-3 text-left">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-9 h-9 rounded-lg bg-amber-500 text-white flex items-center justify-center font-bold text-xs shrink-0">
                                        <i class="fa-solid fa-file"></i>
                                    </div>
                                    <div class="truncate">
                                        <p id="fileNameDisplay" class="font-bold text-stone-900 text-xs truncate"></p>
                                        <span id="fileSizeDisplay" class="text-[10px] text-stone-500"></span>
                                    </div>
                                </div>
                                <button type="button" onclick="clearFileSelection()" class="text-stone-400 hover:text-rose-600 p-1.5 rounded-lg shrink-0">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-4 border-t border-stone-200 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="text-[11px] text-stone-500 flex items-center gap-2">
                            <i class="fa-solid fa-shield-halved text-emerald-600 text-xs"></i>
                            <span>File Anda aman & hanya diproses oleh operator resmi Aquiris Print</span>
                        </div>

                        <button type="submit" id="submitBtn" class="w-full sm:w-auto px-8 py-3.5 bg-stone-900 hover:bg-stone-800 text-amber-300 hover:text-amber-200 font-extrabold rounded-2xl text-xs sm:text-sm shadow-xl shadow-stone-900/20 transition transform active:scale-98 flex items-center justify-center gap-2.5">
                            <i class="fa-solid fa-paper-plane text-xs"></i>
                            <span>Kirim Pesanan ke Operator Cetak</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- Services & Pricing Catalog Preview Section -->
    <section id="layanan" class="py-16 bg-[#FAF8F5] scroll-mt-20">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="text-center max-w-xl mx-auto mb-12">
                <span class="text-xs font-black uppercase tracking-wider text-amber-700 bg-amber-100/80 px-3 py-1 rounded-full">
                    Katalog & Tarif Terjangkau
                </span>
                <h2 class="serif-font text-2xl sm:text-3xl font-bold text-stone-900 mt-2 mb-2">
                    Layanan Percetakan & Fotocopy
                </h2>
                <p class="text-xs sm:text-sm text-stone-600">
                    Hasil cetak tajam menggunakan mesin berkualitas tinggi dengan harga ramah kantong pelajar & bisnis.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <!-- Card 1 -->
                <div class="bg-white p-5 rounded-3xl border border-stone-200/90 shadow-xs hover:shadow-md transition">
                    <div class="w-10 h-10 rounded-2xl bg-amber-100 text-amber-900 flex items-center justify-center text-lg font-bold mb-3">
                        <i class="fa-solid fa-file-lines"></i>
                    </div>
                    <h3 class="font-extrabold text-stone-900 text-sm mb-1">Print Dokumen HVS</h3>
                    <p class="text-xs text-stone-500 mb-3">Mulai dari Rp 500 / lbr untuk hitam putih & Rp 1.000 untuk warna. Kertas 75/80gr tajam anti blobor.</p>
                    <span class="text-[11px] font-bold text-amber-700">A4, F4 Folio, A3</span>
                </div>

                <!-- Card 2 -->
                <div class="bg-white p-5 rounded-3xl border border-stone-200/90 shadow-xs hover:shadow-md transition">
                    <div class="w-10 h-10 rounded-2xl bg-blue-100 text-blue-900 flex items-center justify-center text-lg font-bold mb-3">
                        <i class="fa-solid fa-copy"></i>
                    </div>
                    <h3 class="font-extrabold text-stone-900 text-sm mb-1">Fotocopy Cepat</h3>
                    <p class="text-xs text-stone-500 mb-3">Rp 300 / lbr untuk 1 sisi, Rp 500 / lbr bolak-balik. Cocok untuk penggandaan soal, materi, dan buku.</p>
                    <span class="text-[11px] font-bold text-blue-700">Kecepatan Tinggi</span>
                </div>

                <!-- Card 3 -->
                <div class="bg-white p-5 rounded-3xl border border-stone-200/90 shadow-xs hover:shadow-md transition">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-100 text-emerald-900 flex items-center justify-center text-lg font-bold mb-3">
                        <i class="fa-solid fa-book-bookmark"></i>
                    </div>
                    <h3 class="font-extrabold text-stone-900 text-sm mb-1">Jilid & Finishing</h3>
                    <p class="text-xs text-stone-500 mb-3">Jilid lakban mika, spiral kawat elegan, hingga hardcover skripsi tinta emas standar universitas.</p>
                    <span class="text-[11px] font-bold text-emerald-700">Rapi & Kuat</span>
                </div>

                <!-- Card 4 -->
                <div class="bg-white p-5 rounded-3xl border border-stone-200/90 shadow-xs hover:shadow-md transition">
                    <div class="w-10 h-10 rounded-2xl bg-rose-100 text-rose-900 flex items-center justify-center text-lg font-bold mb-3">
                        <i class="fa-solid fa-panorama"></i>
                    </div>
                    <h3 class="font-extrabold text-stone-900 text-sm mb-1">Banner & Stiker</h3>
                    <p class="text-xs text-stone-500 mb-3">Cetak spanduk Flexy 280/440gr, X-Banner promosi, dan stiker kemasan A3+ kiss cut siap tempel.</p>
                    <span class="text-[11px] font-bold text-rose-700">Warna Awet Outdoor</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Acrylic Rental Section -->
    <section id="akrilik" class="py-16 bg-white border-t border-stone-200 scroll-mt-20">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-10">
                <div>
                    <span class="text-xs font-black uppercase tracking-wider text-amber-700 bg-amber-100/80 px-3 py-1 rounded-full">
                        Event & Wedding Studio
                    </span>
                    <h2 class="serif-font text-2xl sm:text-3xl font-bold text-stone-900 mt-2 mb-1">
                        Penyewaan Papan Akrilik Welcome Sign
                    </h2>
                    <p class="text-xs sm:text-sm text-stone-600">
                        Sempurnakan momen pernikahan, engagement, wisuda, atau grand opening dengan papan akrilik mewah lengkap dengan easel tripod.
                    </p>
                </div>
                <a href="#order-form" class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-700 hover:text-amber-800 shrink-0">
                    <span>Pesan Sewa di Form Atas</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>

            <!-- Acrylic Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if (!empty($acrylicShowcase)): ?>
                    <?php foreach ($acrylicShowcase as $akr): ?>
                        <div class="bg-[#FAF8F5] rounded-3xl border border-stone-200/90 p-5 shadow-xs flex flex-col justify-between hover:shadow-md transition">
                            <div>
                                <div class="w-full h-40 rounded-2xl bg-gradient-to-br from-stone-200 to-amber-100/60 flex items-center justify-center text-amber-700 text-3xl mb-4 relative overflow-hidden">
                                    <i class="fa-solid fa-shapes"></i>
                                    <span class="absolute top-3 right-3 px-2 py-0.5 bg-white/90 text-stone-800 text-[10px] font-extrabold rounded-md shadow-xs">
                                        <?= htmlspecialchars($akr['size'] ?? 'A1') ?>
                                    </span>
                                </div>
                                <h3 class="font-extrabold text-stone-900 text-sm leading-snug mb-1">
                                    <?= htmlspecialchars($akr['name']) ?>
                                </h3>
                                <p class="text-[11px] text-stone-500 mb-2">Standing: <?= htmlspecialchars($akr['stand_type'] ?? 'Easel Stand') ?></p>
                                <p class="text-xs text-stone-600 italic">"<?= htmlspecialchars($akr['notes'] ?? 'Sangat cantik untuk acara spesial') ?>"</p>
                            </div>

                            <div class="mt-4 pt-3 border-t border-stone-200 flex items-center justify-between">
                                <div>
                                    <span class="text-[10px] text-stone-400 block font-semibold">Tarif Sewa:</span>
                                    <span class="font-black text-amber-800 text-sm"><?= formatRupiah($akr['rental_price_per_day']) ?> <span class="text-[10px] font-normal text-stone-500">/hari</span></span>
                                </div>
                                <a href="#order-form" class="px-3 py-1.5 bg-stone-900 hover:bg-stone-800 text-amber-300 font-bold rounded-xl text-xs transition">
                                    Sewa Sekarang
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-3 text-center py-6 text-stone-400 text-xs">
                        Katalog papan akrilik sedang diperbarui.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- How it Works (Alur Pemesanan) -->
    <section id="cara-kerja" class="py-16 bg-[#FAF8F5] border-t border-stone-200 scroll-mt-20">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="text-center max-w-xl mx-auto mb-12">
                <span class="text-xs font-black uppercase tracking-wider text-amber-700 bg-amber-100/80 px-3 py-1 rounded-full">
                    Sangat Praktis & Cepat
                </span>
                <h2 class="serif-font text-2xl sm:text-3xl font-bold text-stone-900 mt-2 mb-2">
                    Bagaimana Cara Kerjanya?
                </h2>
                <p class="text-xs sm:text-sm text-stone-600">
                    Cukup 4 langkah mudah untuk menyelesaikan kebutuhan cetak Anda tanpa antre.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white p-6 rounded-3xl border border-stone-200 text-center relative shadow-xs">
                    <div class="w-12 h-12 rounded-2xl bg-amber-500 text-white flex items-center justify-center text-lg font-bold mx-auto mb-4 shadow-sm">
                        1
                    </div>
                    <h3 class="font-extrabold text-stone-900 text-sm mb-1">Unggah Dokumen</h3>
                    <p class="text-xs text-stone-500">Pilih layanan, spesifikasi cetak, dan upload file tugas/skripsi di form online.</p>
                </div>

                <div class="bg-white p-6 rounded-3xl border border-stone-200 text-center relative shadow-xs">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-600 text-white flex items-center justify-center text-lg font-bold mx-auto mb-4 shadow-sm">
                        2
                    </div>
                    <h3 class="font-extrabold text-stone-900 text-sm mb-1">Notifikasi ke WA Toko</h3>
                    <p class="text-xs text-stone-500">File & rincian pesanan langsung masuk otomatis ke WhatsApp laptop operator print.</p>
                </div>

                <div class="bg-white p-6 rounded-3xl border border-stone-200 text-center relative shadow-xs">
                    <div class="w-12 h-12 rounded-2xl bg-blue-600 text-white flex items-center justify-center text-lg font-bold mx-auto mb-4 shadow-sm">
                        3
                    </div>
                    <h3 class="font-extrabold text-stone-900 text-sm mb-1">Proses Cetak & Jilid</h3>
                    <p class="text-xs text-stone-500">Operator kami langsung membuka file dan mencetak sesuai rincian Anda.</p>
                </div>

                <div class="bg-white p-6 rounded-3xl border border-stone-200 text-center relative shadow-xs">
                    <div class="w-12 h-12 rounded-2xl bg-stone-900 text-amber-300 flex items-center justify-center text-lg font-bold mx-auto mb-4 shadow-sm">
                        4
                    </div>
                    <h3 class="font-extrabold text-stone-900 text-sm mb-1">Ambil Jadi di Toko</h3>
                    <p class="text-xs text-stone-500">Cukup sebutkan kode pesanan Anda saat mengambil dan lakukan pembayaran di kasir.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Location & Contact Section -->
    <section id="lokasi" class="py-16 bg-white border-t border-stone-200 scroll-mt-20">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="bg-[#FAF8F5] rounded-3xl border border-stone-200/90 p-8 sm:p-12 shadow-sm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 bg-amber-100 text-amber-900 rounded-full text-xs font-bold mb-3">
                            <i class="fa-solid fa-location-dot text-amber-700"></i>
                            <span>Kunjungi Toko Kami</span>
                        </div>
                        <h2 class="serif-font text-2xl sm:text-3xl font-bold text-stone-900 mb-3">
                            <?= htmlspecialchars($shopName) ?>
                        </h2>
                        <p class="text-xs sm:text-sm text-stone-600 leading-relaxed mb-6">
                            <?= htmlspecialchars($shopAddress) ?>
                        </p>

                        <div class="space-y-3 text-xs">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold shrink-0">
                                    <i class="fa-solid fa-clock"></i>
                                </div>
                                <div>
                                    <span class="font-bold text-stone-800 block">Jam Operasional:</span>
                                    <span class="text-stone-500">Senin - Sabtu: 08.00 - 21.00 WIB | Minggu: 09.00 - 17.00 WIB</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold shrink-0">
                                    <i class="fa-brands fa-whatsapp"></i>
                                </div>
                                <div>
                                    <span class="font-bold text-stone-800 block">WhatsApp Operator Cetak:</span>
                                    <div class="flex flex-wrap items-center gap-2 mt-0.5">
                                        <a href="<?= buildWaLink('081525876330', 'Halo Aquiris Print, saya ingin bertanya tentang cetak dokumen.') ?>" target="_blank" class="text-emerald-700 font-bold hover:underline">0815-2587-6330</a>
                                        <span class="text-stone-300">•</span>
                                        <a href="<?= buildWaLink('082144811008', 'Halo Aquiris Print, saya ingin bertanya tentang cetak dokumen.') ?>" target="_blank" class="text-emerald-700 font-bold hover:underline">0821-4481-1008</a>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-xl bg-blue-100 text-blue-800 flex items-center justify-center font-bold shrink-0">
                                    <i class="fa-solid fa-code"></i>
                                </div>
                                <div>
                                    <span class="font-bold text-stone-800 block">Digital & IT Partner:</span>
                                    <span class="text-stone-600 font-medium">Skillance Digital Indonesia</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 flex flex-wrap items-center gap-3">
                            <a href="<?= buildWaLink('081525876330', 'Halo Aquiris Print, saya ingin mengirim file cetakan.') ?>" target="_blank" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-2 shadow-sm">
                                <i class="fa-brands fa-whatsapp text-sm"></i>
                                <span>Hubungi WA 0815-2587-6330</span>
                            </a>
                            <a href="#order-form" class="px-5 py-2.5 bg-stone-900 hover:bg-stone-800 text-amber-300 rounded-xl text-xs font-bold transition">
                                Kirim File Cetak &rarr;
                            </a>
                        </div>
                    </div>

                    <!-- Poster Graphic Card on Location Section -->
                    <div class="rounded-3xl bg-stone-900 text-white border border-stone-800 p-5 sm:p-6 shadow-xl flex flex-col items-center justify-center text-center relative overflow-hidden group cursor-pointer" onclick="openPosterModal()">
                        <div class="w-full max-w-xs rounded-2xl overflow-hidden border border-stone-700 mb-3 relative">
                            <img src="assets/images/poster-layanan.png" alt="Aquiris Print Poster" class="w-full h-auto object-contain transform group-hover:scale-102 transition duration-300" />
                            <div class="absolute inset-0 bg-stone-900/30 group-hover:bg-stone-900/10 transition flex items-center justify-center">
                                <span class="px-3 py-1.5 bg-black/70 backdrop-blur-xs text-white rounded-xl text-xs font-bold flex items-center gap-1.5">
                                    <i class="fa-solid fa-expand text-amber-400"></i>
                                    <span>Klik untuk Lihat Brosur Asli</span>
                                </span>
                            </div>
                        </div>
                        <h4 class="font-bold text-white text-xs">AQUIRIS PRINT • Rusunawa USN Popalia</h4>
                        <p class="text-[11px] text-stone-400 max-w-xs mt-0.5">Kec. Tanggetada, Kab. Kolaka, Sulawesi Tenggara</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Public Footer -->
    <footer class="bg-stone-900 text-stone-400 py-10 border-t border-stone-800 text-xs">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-center sm:text-left">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-white flex items-center justify-center p-1 shrink-0">
                    <img src="assets/images/icon-mark.png" alt="Aquiris" class="w-full h-full object-contain" />
                </div>
                <div>
                    <span class="font-bold text-white"><?= htmlspecialchars($shopName) ?></span>
                    <p class="text-[11px] text-stone-500">Percetakan, Fotocopy Digital & Sewa Akrilik Event</p>
                </div>
            </div>

            <div class="text-[11px] text-stone-500 flex items-center gap-4">
                <span>&copy; <?= date('Y') ?> All Rights Reserved.</span>
                <span>•</span>
                <a href="login.php" class="text-amber-400 hover:text-white font-semibold transition">Portal Masuk Kasir</a>
            </div>
        </div>
    </footer>

    <!-- Poster Lightbox Modal -->
    <div id="posterModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-md hidden flex items-center justify-center p-4" onclick="closePosterModal(event)">
        <div class="relative max-w-lg w-full bg-stone-900 rounded-3xl overflow-hidden shadow-2xl border border-stone-700" onclick="event.stopPropagation()">
            <div class="p-4 bg-stone-900 border-b border-stone-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-red-600"></span>
                    <h3 class="text-xs font-bold text-white">Brosur Resmi • Aquiris Print</h3>
                </div>
                <button type="button" onclick="closePosterModal()" class="w-8 h-8 rounded-full bg-stone-800 hover:bg-stone-700 text-stone-300 flex items-center justify-center text-xs transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="p-2 max-h-[80vh] overflow-y-auto flex items-center justify-center bg-stone-950">
                <img src="assets/images/poster-layanan.png" alt="Brosur Lengkap Aquiris Print" class="w-full h-auto rounded-xl object-contain" />
            </div>
            <div class="p-3 bg-stone-900 border-t border-stone-800 flex items-center justify-between text-xs text-stone-400">
                <span>Rusunawa USN Popalia, Tanggetada, Kolaka</span>
                <a href="#order-form" onclick="closePosterModal()" class="px-3.5 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold transition">
                    Order Sekarang
                </a>
            </div>
        </div>
    </div>

    <!-- Interactive Drag & Drop JS -->
    <script>
        function openPosterModal() {
            document.getElementById('posterModal').classList.remove('hidden');
        }

        function closePosterModal() {
            document.getElementById('posterModal').classList.add('hidden');
        }

        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const uploadPrompt = document.getElementById('uploadPrompt');
        const filePreview = document.getElementById('filePreview');
        const fileNameDisplay = document.getElementById('fileNameDisplay');
        const fileSizeDisplay = document.getElementById('fileSizeDisplay');
        const submitForm = document.getElementById('orderSubmitForm');
        const submitBtn = document.getElementById('submitBtn');

        dropZone.addEventListener('click', (e) => {
            if (e.target.closest('button')) return;
            fileInput.click();
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
            }, false);
        });

        dropZone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                updateFileDisplay(files[0]);
            }
        });

        function handleFileSelect(input) {
            if (input.files.length > 0) {
                updateFileDisplay(input.files[0]);
            }
        }

        function updateFileDisplay(file) {
            uploadPrompt.classList.add('hidden');
            filePreview.classList.remove('hidden');
            fileNameDisplay.textContent = file.name;
            const sizeKb = (file.size / 1024).toFixed(1);
            if (sizeKb > 1024) {
                fileSizeDisplay.textContent = (sizeKb / 1024).toFixed(2) + ' MB';
            } else {
                fileSizeDisplay.textContent = sizeKb + ' KB';
            }
        }

        function clearFileSelection() {
            fileInput.value = '';
            uploadPrompt.classList.remove('hidden');
            filePreview.classList.add('hidden');
        }

        // Handle button submit spinner
        submitForm.addEventListener('submit', function() {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs"></i> <span>Mengunggah file & mengirim ke operator...</span>';
            submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
        });
    </script>
</body>
</html>
