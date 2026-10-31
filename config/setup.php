<?php
// config/setup.php
require_once __DIR__ . '/database.php';

function initDatabase() {
    $pdo = Database::getConnection();

    // 1. Users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `role` ENUM('admin', 'kasir') DEFAULT 'kasir',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 2. Categories product table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `categories_product` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 3. Products table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `products` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `category_id` INT NULL,
        `code` VARCHAR(50) NULL UNIQUE,
        `name` VARCHAR(150) NOT NULL,
        `unit` VARCHAR(30) NOT NULL DEFAULT 'lembar',
        `price` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`category_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 4. Customers table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `customers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `phone` VARCHAR(30) NULL,
        `address` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 5. Acrylic Items (Inventaris Papan Akrilik & Standing)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `acrylic_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `code` VARCHAR(50) NOT NULL UNIQUE,
        `name` VARCHAR(150) NOT NULL,
        `size` VARCHAR(100) NULL,
        `stand_type` VARCHAR(100) NULL,
        `rental_price_per_day` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `deposit_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `status` ENUM('available', 'rented', 'maintenance') DEFAULT 'available',
        `notes` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 6. Orders (Transaksi Cetak & Fotocopy)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `orders` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
        `customer_id` INT NULL,
        `customer_name` VARCHAR(100) NOT NULL DEFAULT 'Pelanggan Umum',
        `customer_phone` VARCHAR(30) NULL,
        `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `discount` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `grand_total` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `change_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `payment_status` ENUM('lunas', 'dp', 'belum_lunas') DEFAULT 'lunas',
        `payment_method` ENUM('tunai', 'transfer', 'qris') DEFAULT 'tunai',
        `order_status` ENUM('selesai', 'proses', 'dibatalkan') DEFAULT 'selesai',
        `notes` TEXT NULL,
        `user_id` INT NULL,
        `created_at` DATETIME NOT NULL,
        INDEX (`created_at`),
        INDEX (`payment_status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 7. Order Items
    $pdo->exec("CREATE TABLE IF NOT EXISTS `order_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `order_id` INT NOT NULL,
        `product_id` INT NULL,
        `item_name` VARCHAR(150) NOT NULL,
        `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1,
        `unit` VARCHAR(30) NOT NULL DEFAULT 'pcs',
        `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `notes` VARCHAR(255) NULL,
        INDEX (`order_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 8. Acrylic Rentals (Penyewaan Papan Akrilik)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `acrylic_rentals` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `rental_code` VARCHAR(50) NOT NULL UNIQUE,
        `customer_id` INT NULL,
        `customer_name` VARCHAR(100) NOT NULL,
        `customer_phone` VARCHAR(30) NOT NULL,
        `acrylic_id` INT NOT NULL,
        `event_name` VARCHAR(150) NULL,
        `event_date` DATE NULL,
        `start_date` DATE NOT NULL,
        `end_date` DATE NOT NULL,
        `duration_days` INT NOT NULL DEFAULT 1,
        `rental_price` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `custom_sticker_fee` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `deposit_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `total_cost` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `status` ENUM('booked', 'active', 'returned', 'late', 'cancelled') DEFAULT 'booked',
        `actual_return_date` DATE NULL,
        `late_fine` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `deposit_returned` TINYINT(1) NOT NULL DEFAULT 0,
        `payment_method` ENUM('tunai', 'transfer', 'qris') DEFAULT 'tunai',
        `notes` TEXT NULL,
        `user_id` INT NULL,
        `created_at` DATETIME NOT NULL,
        INDEX (`acrylic_id`),
        INDEX (`status`),
        INDEX (`start_date`),
        INDEX (`end_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 9. Expense Categories
    $pdo->exec("CREATE TABLE IF NOT EXISTS `expense_categories` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 10. Expenses (Pengeluaran Operasional)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `expenses` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `expense_code` VARCHAR(50) NOT NULL UNIQUE,
        `category_id` INT NOT NULL,
        `title` VARCHAR(200) NOT NULL,
        `amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `date` DATE NOT NULL,
        `payment_method` ENUM('tunai', 'transfer') DEFAULT 'tunai',
        `notes` TEXT NULL,
        `receipt_image` VARCHAR(255) NULL,
        `user_id` INT NULL,
        `created_at` DATETIME NOT NULL,
        INDEX (`category_id`),
        INDEX (`date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 11. Settings
    $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `setting_key` VARCHAR(50) PRIMARY KEY,
        `setting_value` TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 12. Online Orders (Pesanan Online dari Landing Page)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `online_orders` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `order_code` VARCHAR(50) NOT NULL UNIQUE,
        `customer_name` VARCHAR(100) NOT NULL,
        `customer_phone` VARCHAR(30) NOT NULL,
        `service_type` VARCHAR(100) NOT NULL,
        `paper_type` VARCHAR(100) NULL,
        `color_mode` VARCHAR(50) NULL,
        `quantity` INT NOT NULL DEFAULT 1,
        `finishing` VARCHAR(100) NULL,
        `notes` TEXT NULL,
        `file_path` VARCHAR(255) NULL,
        `file_name` VARCHAR(255) NULL,
        `file_size` INT NULL,
        `status` ENUM('menunggu', 'diproses', 'selesai', 'dibatalkan') DEFAULT 'menunggu',
        `fonnte_status` VARCHAR(255) NULL,
        `created_at` DATETIME NOT NULL,
        INDEX (`order_code`),
        INDEX (`status`),
        INDEX (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 13. Profit Distributions (Pembagian Hasil Usaha: Pemilik Modal, Pekerja & Kas Usaha)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `profit_distributions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `distribution_code` VARCHAR(50) NOT NULL UNIQUE,
        `period_start` DATE NOT NULL,
        `period_end` DATE NOT NULL,
        `gross_revenue` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `total_expenses` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `net_profit` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `owner_percent` DECIMAL(5,2) NOT NULL DEFAULT 40.00,
        `owner_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `worker_percent` DECIMAL(5,2) NOT NULL DEFAULT 40.00,
        `worker_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `reserve_percent` DECIMAL(5,2) NOT NULL DEFAULT 20.00,
        `reserve_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `distribution_date` DATE NOT NULL,
        `payment_method` ENUM('tunai', 'transfer') DEFAULT 'tunai',
        `notes` TEXT NULL,
        `user_id` INT NULL,
        `created_at` DATETIME NOT NULL,
        INDEX (`distribution_date`),
        INDEX (`period_start`),
        INDEX (`period_end`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Default settings for Fonnte & Print Operator & Profit Sharing if not set
    $defaultSettings = [
        'fonnte_token' => '',
        'print_operator_wa' => '081234567890',
        'base_url' => 'http://localhost/aquiris_print',
        'profit_share_owner' => '40',
        'profit_share_worker' => '40',
        'profit_share_reserve' => '20'
    ];
    $checkSet = $pdo->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`");
    foreach ($defaultSettings as $k => $v) {
        $checkSet->execute([$k, $v]);
    }

    // SEED INITIAL DATA IF USERS EMPTY
    $stmt = $pdo->query("SELECT COUNT(*) FROM `users`");
    if ($stmt->fetchColumn() == 0) {
        seedInitialData($pdo);
    }
}

function seedInitialData(PDO $pdo) {
    // 1. Users
    $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
    $kasirPass = password_hash('kasir123', PASSWORD_BCRYPT);

    $userStmt = $pdo->prepare("INSERT INTO `users` (`name`, `username`, `password`, `role`) VALUES (?, ?, ?, ?)");
    $userStmt->execute(['Administrator', 'admin', $adminPass, 'admin']);
    $userStmt->execute(['Kasir Front Office', 'kasir', $kasirPass, 'kasir']);

    // 2. Settings
    $settings = [
        'shop_name' => 'Aquiris Print',
        'shop_tagline' => 'Butuh Print Cepat dan Murah? Aquiris Print Siap Membantu !!!',
        'shop_address' => 'Rusunawa USN Popalia, Desa Popalia, Kec. Tanggetada, Kab. Kolaka, Sulawesi Tenggara',
        'shop_phone' => '081525876330',
        'shop_phone_2' => '082144811008',
        'print_operator_wa' => '081525876330',
        'partner_brand' => 'Skillance Digital Indonesia',
        'shop_email' => 'aquirisprint@gmail.com',
        'currency_symbol' => 'Rp',
        'fine_per_day' => '25000',
        'receipt_footer' => "Terima kasih telah mencetak di Aquiris Print!\nHubungi kami di 0815-2587-6330 untuk cetak online berikutnya."
    ];
    $setStmt = $pdo->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)");
    foreach ($settings as $k => $v) {
        $setStmt->execute([$k, $v]);
    }

    // 3. Product Categories
    $categories = [
        1 => 'Fotocopy',
        2 => 'Print Dokumen & Tugas',
        3 => 'Jilid Dokumen',
        4 => 'Spanduk & Banner',
        5 => 'Stiker & Label',
        6 => 'Kartu & Undangan',
        7 => 'Lain-lain',
        8 => 'Khusus FTI (USN Popalia)'
    ];
    $catStmt = $pdo->prepare("INSERT INTO `categories_product` (`id`, `name`) VALUES (?, ?)");
    foreach ($categories as $id => $name) {
        $catStmt->execute([$id, $name]);
    }

    // 4. Products (Sesuai Brosur Resmi Aquiris Print)
    $products = [
        [2, 'PRN-DOC', 'Print Dokumen Teks (HVS A4/Letter/F4)', 'lembar', 500],
        [2, 'PRN-IMG', 'Print Bergambar / Warna (HVS A4/Letter/F4)', 'lembar', 1000],
        [2, 'PRN-SKR', 'Print Skripsi (HVS A4/Letter/F4)', 'lembar', 500],
        [2, 'PRN-MKL', 'Print Makalah (HVS A4/Letter/F4)', 'lembar', 500],
        [2, 'PRN-CV',  'Print CV / Resume', 'lembar', 1000],
        [2, 'PRN-TGS', 'Print Tugas Sekolah / Kuliah', 'lembar', 500],
        [8, 'FTI-BMB', 'Print Lembar Bimbingan (Khusus FTI)', 'lembar', 500],
        [8, 'FTI-KPU', 'Print Kartu Peserta Ujian (Khusus FTI)', 'lembar', 1000],
        [8, 'FTI-KNG', 'Print Lembar Kuning (Khusus FTI)', 'lembar', 1000],
        [1, 'FC-01', 'Fotocopy HVS A4/F4 (1 Sisi)', 'lembar', 300],
        [1, 'FC-02', 'Fotocopy HVS A4/F4 (Bolak-Balik)', 'lembar', 500],
        [1, 'FC-03', 'Fotocopy KTP / Kartu Identitas', 'lembar', 1000],
        [3, 'JLD-01', 'Jilid Lakban + Mika Bening', 'buku', 4000],
        [3, 'JLD-02', 'Jilid Spiral Kawat A4 (Cover Mika)', 'buku', 12000],
        [3, 'JLD-03', 'Jilid Hardcover Skripsi Tinta Emas', 'buku', 35000],
        [4, 'BNR-01', 'Cetak Banner Flexy 280gr', 'meter', 20000],
        [4, 'BNR-02', 'Cetak Banner Flexy Korea 440gr (Tebal)', 'meter', 35000],
        [4, 'BNR-03', 'X-Banner 60x160cm (Lengkap Rangka)', 'set', 65000],
        [5, 'STK-01', 'Cetak + Cutting Stiker Vinyl Glossy A3+', 'lembar', 15000],
        [5, 'STK-02', 'Cetak + Cutting Stiker Chromo A3+', 'lembar', 12000],
        [5, 'STK-03', 'Cutting Huruf Stiker Akrilik Custom', 'desain', 35000],
        [6, 'KRT-01', 'Kartu Nama 1 Box (100 pcs Art Carton 260gr 2 Sisi)', 'box', 35000]
    ];
    $prodStmt = $pdo->prepare("INSERT INTO `products` (`category_id`, `code`, `name`, `unit`, `price`) VALUES (?, ?, ?, ?, ?)");
    foreach ($products as $p) {
        $prodStmt->execute($p);
    }

    // 5. Acrylic Items
    $acrylics = [
        ['AKR-01', 'Papan Akrilik Transparan A1 (60x80cm) + Easel Kayu Pinus', '60x80 cm (A1)', 'Easel Kayu Pinus Rustic', 75000, 50000, 'available', 'Sangat cocok untuk welcome sign wedding & engagement'],
        ['AKR-02', 'Papan Akrilik Bulat White Frosted D60cm + Stand Tripod Gold', 'Diameter 60 cm', 'Standing Tripod Besi Gold Elegan', 85000, 50000, 'available', 'Cocok untuk bridal shower, birthday party & wisuda'],
        ['AKR-03', 'Papan Akrilik Mirror Gold A1 + Easel Besi Hitam', '60x80 cm (A1)', 'Easel Besi Minimalis Hitam', 100000, 75000, 'available', 'Efek cermin emas mewah untuk grand opening atau luxury wedding'],
        ['AKR-04', 'Papan Akrilik Lengkung (Arch) Pastel A1 + Stand Kayu', '60x80 cm (Arch)', 'Stand Kayu Natural', 90000, 60000, 'available', 'Model kekinian bentuk kubah/lengkung'],
        ['AKR-05', 'Papan Akrilik Transparan A2 (42x60cm) + Standing Meja', '42x60 cm (A2)', 'Table Stand Kayu Minimalis', 50000, 30000, 'available', 'Ukuran medium untuk dekorasi meja resepsi atau sign seminar']
    ];
    $akrStmt = $pdo->prepare("INSERT INTO `acrylic_items` (`code`, `name`, `size`, `stand_type`, `rental_price_per_day`, `deposit_amount`, `status`, `notes`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($acrylics as $a) {
        $akrStmt->execute($a);
    }

    // 6. Expense Categories
    $expCategories = [
        'Bahan Baku Kertas & Mika',
        'Tinta, Toner & Cartridge',
        'Bahan Banner, Flexy & Stiker',
        'Perawatan / Servis Mesin',
        'Listrik, Internet & Air',
        'Gaji Karyawan & Konsumsi',
        'Perlengkapan Papan Akrilik & Standing',
        'Operasional Lainnya'
    ];
    $expCatStmt = $pdo->prepare("INSERT INTO `expense_categories` (`name`) VALUES (?)");
    foreach ($expCategories as $ec) {
        $expCatStmt->execute([$ec]);
    }
}
