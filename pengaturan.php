<?php
// pengaturan.php
require_once __DIR__ . '/config/app.php';
requireAdmin();

$pdo = Database::getConnection();

// Handle Shop Settings POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_settings'])) {
    $keys = [
        'shop_name',
        'shop_tagline',
        'shop_address',
        'shop_phone',
        'shop_phone_2',
        'shop_email',
        'fine_per_day',
        'receipt_footer'
    ];

    foreach ($keys as $k) {
        if (isset($_POST[$k])) {
            updateSetting($k, trim($_POST[$k]));
        }
    }
    setFlash('success', 'Pengaturan profil toko berhasil disimpan!');
    header('Location: pengaturan.php');
    exit;
}

// Handle WhatsApp Fonnte Settings POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_fonnte'])) {
    $keys = ['fonnte_token', 'print_operator_wa', 'base_url'];
    foreach ($keys as $k) {
        if (isset($_POST[$k])) {
            updateSetting($k, trim($_POST[$k]));
        }
    }
    setFlash('success', 'Pengaturan WhatsApp Gateway (Fonnte) berhasil disimpan!');
    header('Location: pengaturan.php');
    exit;
}

// Handle Profit Sharing Settings POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_profit_share'])) {
    $owner = (float)($_POST['profit_share_owner'] ?? 40);
    $worker = (float)($_POST['profit_share_worker'] ?? 40);
    $reserve = (float)($_POST['profit_share_reserve'] ?? 20);

    $total = $owner + $worker + $reserve;
    if (abs($total - 100.0) > 0.01) {
        setFlash('error', "Total persentase pembagian hasil harus tepat 100%! Saat ini berjumlah: " . $total . "%. Silakan sesuaikan kembali.");
    } else {
        updateSetting('profit_share_owner', (string)$owner);
        updateSetting('profit_share_worker', (string)$worker);
        updateSetting('profit_share_reserve', (string)$reserve);
        setFlash('success', 'Skema persentase bagi hasil berhasil disimpan!');
    }
    header('Location: pengaturan.php');
    exit;
}

// Handle Test WhatsApp Message
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['test_fonnte'])) {
    $target = trim($_POST['test_phone'] ?? getSetting('print_operator_wa', ''));
    if (empty($target)) {
        setFlash('error', 'Nomor WhatsApp tujuan tes tidak boleh kosong.');
    } else {
        $testMsg = "Halo! Ini adalah *Pesan Uji Coba Integrasi Fonnte* dari Sistem Percetakan *Aquiris Print*.\n\n"
                 . "✅ Koneksi Fonnte WhatsApp API berhasil aktif.\n"
                 . "Pesanan cetak online dari landing page akan langsung masuk ke nomor WhatsApp ini!\n\n"
                 . "Waktu Tes: " . date('d F Y, H:i:s') . " WIB";

        $res = sendFonnteMessage($target, $testMsg);
        if ($res['status']) {
            setFlash('success', "Pesan uji coba WhatsApp berhasil dikirim ke {$target} via Fonnte!");
        } else {
            setFlash('error', "Gagal mengirim pesan WhatsApp: " . ($res['message'] ?? 'Periksa token Fonnte dan nomor tujuan.'));
        }
    }
    header('Location: pengaturan.php');
    exit;
}

// Handle Add / Edit User (Admin only)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_user'])) {
    if ($_SESSION['user_role'] !== 'admin') {
        setFlash('error', 'Hanya admin yang dapat mengelola akun pengguna.');
        header('Location: pengaturan.php');
        exit;
    }

    $userId = (int)($_POST['user_id'] ?? 0);
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'kasir';

    if (empty($name) || empty($username)) {
        setFlash('error', 'Nama dan username wajib diisi!');
    } else {
        if ($userId > 0) {
            // Update
            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, password = ?, role = ? WHERE id = ?");
                $stmt->execute([$name, $username, $hashed, $role, $userId]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, role = ? WHERE id = ?");
                $stmt->execute([$name, $username, $role, $userId]);
            }
            setFlash('success', 'Akun pengguna berhasil diperbarui!');
        } else {
            // Check username uniqueness
            $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $chk->execute([$username]);
            if ($chk->fetch()) {
                setFlash('error', 'Username sudah digunakan, silakan pilih username lain.');
            } else {
                if (empty($password)) {
                    setFlash('error', 'Password wajib diisi untuk pengguna baru.');
                } else {
                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $username, $hashed, $role]);
                    setFlash('success', 'Pengguna baru berhasil ditambahkan!');
                }
            }
        }
        header('Location: pengaturan.php');
        exit;
    }
}

// Handle Delete User
if (isset($_GET['delete_user'])) {
    if ($_SESSION['user_role'] !== 'admin') {
        setFlash('error', 'Hanya admin yang dapat menghapus akun.');
    } else {
        $delId = (int)$_GET['delete_user'];
        if ($delId == $_SESSION['user_id']) {
            setFlash('error', 'Anda tidak dapat menghapus akun yang sedang Anda gunakan.');
        } else {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$delId]);
            setFlash('success', 'Pengguna berhasil dihapus.');
        }
    }
    header('Location: pengaturan.php');
    exit;
}

// Fetch all users
$users = $pdo->query("SELECT * FROM users ORDER BY role ASC, name ASC")->fetchAll();

$pageTitle = 'Pengaturan Toko & Pengguna';
$pageSubtitle = 'Konfigurasi profil usaha, nomor WA operator print & akun kasir';
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-8">
    <!-- 1. Shop Profile Settings -->
    <div class="bg-white rounded-3xl border border-stone-200/90 shadow-sm p-6 md:p-8">
        <div class="flex items-center gap-3.5 pb-4 border-b border-stone-100 mb-6">
            <div class="w-10 h-10 rounded-2xl bg-amber-100 text-amber-900 flex items-center justify-center text-lg font-bold">
                <i class="fa-solid fa-store"></i>
            </div>
            <div>
                <h3 class="font-extrabold text-stone-900 text-base">Profil Usaha Percetakan & Studio Akrilik</h3>
                <p class="text-xs text-stone-500">Informasi ini akan tertera pada struk kasir, nota standar & surat perjanjian sewa</p>
            </div>
        </div>

        <form method="POST" action="pengaturan.php" class="space-y-4 text-xs">
            <input type="hidden" name="save_settings" value="1" />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Nama Usaha / Toko *</label>
                    <input type="text" name="shop_name" value="<?= htmlspecialchars(getSetting('shop_name', 'Aquiris Print')) ?>" required
                           class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none focus:ring-1 focus:ring-amber-500 font-bold text-stone-900" />
                </div>
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Slogan / Keterangan Usaha</label>
                    <input type="text" name="shop_tagline" value="<?= htmlspecialchars(getSetting('shop_tagline', 'Solusi Percetakan & Sewa Akrilik')) ?>"
                           class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none font-medium" />
                </div>
                <div>
                    <label class="block font-bold text-stone-700 mb-1">No. WhatsApp Toko *</label>
                    <input type="text" name="shop_phone" value="<?= htmlspecialchars(getSetting('shop_phone', '081234567890')) ?>" required
                           class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none font-bold text-emerald-700" />
                </div>
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Email Toko</label>
                    <input type="email" name="shop_email" value="<?= htmlspecialchars(getSetting('shop_email', 'info@aquirisprint.com')) ?>"
                           class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none" />
                </div>
                <div class="sm:col-span-2">
                    <label class="block font-bold text-stone-700 mb-1">Alamat Lengkap Toko</label>
                    <input type="text" name="shop_address" value="<?= htmlspecialchars(getSetting('shop_address', 'Jl. Percetakan')) ?>"
                           class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none" />
                </div>
                <div>
                    <label class="block font-bold text-stone-700 mb-1">Tarif Denda Telat Sewa Akrilik (Rp/Hari) *</label>
                    <input type="number" name="fine_per_day" value="<?= htmlspecialchars(getSetting('fine_per_day', '25000')) ?>" required min="0"
                           class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none font-black text-rose-600" />
                </div>
                <div class="sm:col-span-2">
                    <label class="block font-bold text-stone-700 mb-1">Pesan Footer Struk Kasir</label>
                    <textarea name="receipt_footer" rows="2" class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none text-stone-700"><?= htmlspecialchars(getSetting('receipt_footer', 'Terima kasih atas kunjungan Anda!')) ?></textarea>
                </div>
            </div>

            <div class="pt-2 flex justify-end">
                <button type="submit" class="px-5 py-2.5 bg-stone-900 hover:bg-stone-800 text-amber-300 font-bold rounded-xl shadow transition">
                    Simpan Profil Toko
                </button>
            </div>
        </form>
    </div>

    <!-- 2. Skema Pembagian Hasil Usaha (Profit Sharing) -->
    <div class="bg-white rounded-3xl border border-stone-200/90 shadow-sm p-6 md:p-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-stone-100 mb-6 gap-3">
            <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-2xl bg-amber-100 text-amber-900 flex items-center justify-center text-lg font-bold">
                    <i class="fa-solid fa-scale-balanced"></i>
                </div>
                <div>
                    <h3 class="font-extrabold text-stone-900 text-base">Skema Pembagian Hasil Usaha (Profit Sharing)</h3>
                    <p class="text-xs text-stone-500">Rasio pembagian laba bersih untuk Pemilik Modal, Pekerja/Karyawan, dan Kas Cadangan Toko</p>
                </div>
            </div>
            <div>
                <a href="bagi_hasil.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-stone-100 hover:bg-stone-200 text-stone-800 rounded-xl text-xs font-bold transition">
                    <i class="fa-solid fa-list-check text-amber-700"></i>
                    <span>Riwayat Bagi Hasil &rarr;</span>
                </a>
            </div>
        </div>

        <div class="bg-amber-50/60 border border-amber-200/80 p-4 rounded-2xl text-xs text-stone-700 leading-relaxed mb-6">
            <div class="font-bold text-amber-950 flex items-center gap-2 mb-1">
                <i class="fa-solid fa-circle-info text-amber-700"></i>
                <span>Ketentuan Alokasi Bagi Hasil:</span>
            </div>
            <p>Dasar pembagian dihitung dari <strong>Laba Bersih</strong> (Total Omzet Cetak & Sewa dikurangi seluruh Beban Belanja/Operasional). Total ketiga porsi persentase harus berjumlah tepat <strong>100%</strong>.</p>
        </div>

        <?php 
            $curOwnerPct = (float)getSetting('profit_share_owner', '40');
            $curWorkerPct = (float)getSetting('profit_share_worker', '40');
            $curReservePct = (float)getSetting('profit_share_reserve', '20');
        ?>

        <form method="POST" action="pengaturan.php" id="formProfitShare" class="space-y-5 text-xs">
            <input type="hidden" name="save_profit_share" value="1" />

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Pemilik Modal -->
                <div class="p-4 rounded-2xl bg-stone-50 border border-stone-200/80 relative">
                    <div class="flex items-center gap-2 mb-2 font-bold text-stone-800 text-xs">
                        <i class="fa-solid fa-user-tie text-blue-600 text-sm"></i>
                        <span>1. Pemilik Modal (Owner)</span>
                    </div>
                    <p class="text-[11px] text-stone-500 mb-3">Hak bagian atas modal investasi usaha</p>
                    <div class="relative">
                        <input type="number" step="0.5" min="0" max="100" name="profit_share_owner" id="inputOwnerPct"
                               value="<?= htmlspecialchars((string)$curOwnerPct) ?>" required oninput="recalcProfitShare()"
                               class="w-full pl-3 pr-8 py-2.5 bg-white border border-stone-300 rounded-xl font-black text-base text-stone-900 outline-none focus:ring-1 focus:ring-amber-500" />
                        <span class="absolute right-3 top-2.5 font-bold text-stone-400">%</span>
                    </div>
                </div>

                <!-- Pekerja / Pengelola -->
                <div class="p-4 rounded-2xl bg-stone-50 border border-stone-200/80 relative">
                    <div class="flex items-center gap-2 mb-2 font-bold text-stone-800 text-xs">
                        <i class="fa-solid fa-users text-emerald-600 text-sm"></i>
                        <span>2. Pekerja (Karyawan/Operator)</span>
                    </div>
                    <p class="text-[11px] text-stone-500 mb-3">Hak bagi hasil untuk tim yang bekerja</p>
                    <div class="relative">
                        <input type="number" step="0.5" min="0" max="100" name="profit_share_worker" id="inputWorkerPct"
                               value="<?= htmlspecialchars((string)$curWorkerPct) ?>" required oninput="recalcProfitShare()"
                               class="w-full pl-3 pr-8 py-2.5 bg-white border border-stone-300 rounded-xl font-black text-base text-stone-900 outline-none focus:ring-1 focus:ring-amber-500" />
                        <span class="absolute right-3 top-2.5 font-bold text-stone-400">%</span>
                    </div>
                </div>

                <!-- Kas Usaha & Cadangan -->
                <div class="p-4 rounded-2xl bg-stone-50 border border-stone-200/80 relative">
                    <div class="flex items-center gap-2 mb-2 font-bold text-stone-800 text-xs">
                        <i class="fa-solid fa-vault text-amber-700 text-sm"></i>
                        <span>3. Kas Usaha (Dana Cadangan)</span>
                    </div>
                    <p class="text-[11px] text-stone-500 mb-3">Reinvestasi, servis alat & dana darurat toko</p>
                    <div class="relative">
                        <input type="number" step="0.5" min="0" max="100" name="profit_share_reserve" id="inputReservePct"
                               value="<?= htmlspecialchars((string)$curReservePct) ?>" required oninput="recalcProfitShare()"
                               class="w-full pl-3 pr-8 py-2.5 bg-white border border-stone-300 rounded-xl font-black text-base text-stone-900 outline-none focus:ring-1 focus:ring-amber-500" />
                        <span class="absolute right-3 top-2.5 font-bold text-stone-400">%</span>
                    </div>
                </div>
            </div>

            <!-- Status Total Persentase Realtime Indicator -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-xl border" id="totalPctContainer">
                <div class="flex items-center gap-2.5">
                    <div id="totalPctIcon" class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"></div>
                    <div>
                        <span class="font-extrabold text-xs" id="totalPctLabel">Total Persentase: 100%</span>
                        <p class="text-[11px]" id="totalPctDesc">Jumlah pembagian sudah pas dan seimbang.</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[11px] text-stone-400 font-semibold">Preset Cepat:</span>
                    <button type="button" onclick="applyPreset(40, 40, 20)" class="px-2 py-1 bg-stone-100 hover:bg-stone-200 text-stone-700 rounded-lg text-[10px] font-bold">40:40:20</button>
                    <button type="button" onclick="applyPreset(50, 30, 20)" class="px-2 py-1 bg-stone-100 hover:bg-stone-200 text-stone-700 rounded-lg text-[10px] font-bold">50:30:20</button>
                    <button type="button" onclick="applyPreset(33.34, 33.33, 33.33)" class="px-2 py-1 bg-stone-100 hover:bg-stone-200 text-stone-700 rounded-lg text-[10px] font-bold">Bagi Rata</button>
                </div>
            </div>

            <div class="pt-2 flex justify-end">
                <button type="submit" id="btnSaveProfitShare" class="px-5 py-2.5 bg-stone-900 hover:bg-stone-800 text-amber-300 font-bold rounded-xl shadow transition flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Simpan Skema Bagi Hasil</span>
                </button>
            </div>
        </form>
    </div>

    <!-- 3. WhatsApp Gateway (Fonnte) Configuration -->
    <div class="bg-white rounded-3xl border border-stone-200/90 shadow-sm p-6 md:p-8 relative overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-stone-100 mb-6 gap-3">
            <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-2xl bg-emerald-100 text-emerald-800 flex items-center justify-center text-lg font-bold">
                    <i class="fa-brands fa-whatsapp"></i>
                </div>
                <div>
                    <h3 class="font-extrabold text-stone-900 text-base">Integrasi WhatsApp Gateway (Fonnte)</h3>
                    <p class="text-xs text-stone-500">Notifikasi pesanan online & file cetak otomatis dikirim ke laptop operator print</p>
                </div>
            </div>

            <?php $currentToken = getSetting('fonnte_token', ''); ?>
            <div>
                <?php if (!empty($currentToken)): ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full text-[11px] font-bold">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Token Terpasang</span>
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-50 text-amber-800 border border-amber-200 rounded-full text-[11px] font-bold">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        <span>Token Belum Diisi</span>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Setup Form -->
        <form method="POST" action="pengaturan.php" class="space-y-4 text-xs">
            <input type="hidden" name="save_fonnte" value="1" />

            <div class="bg-amber-50/60 border border-amber-200/70 p-4 rounded-2xl text-xs text-stone-700 leading-relaxed mb-4">
                <div class="font-bold text-amber-900 flex items-center gap-2 mb-1">
                    <i class="fa-solid fa-circle-info text-amber-600"></i>
                    <span>Cara Kerja Notifikasi ke Laptop Operator Print:</span>
                </div>
                <p>1. Daftar akun & scan QR WhatsApp di <a href="https://fonnte.com" target="_blank" class="font-bold text-blue-600 underline">Fonnte.com</a> untuk mendapatkan Token API.</p>
                <p>2. Setiap ada pelanggan kirim file di Landing Page, Fonnte akan otomatis mengirimkan pesan WhatsApp ke <strong>nomor WhatsApp laptop operator</strong> yang sedang bertugas.</p>
                <p>3. Operator cukup membuka WhatsApp di laptopnya, membaca rincian pesanan, dan mengklik link file untuk langsung didownload & dicetak!</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block font-bold text-stone-700 mb-1">
                        Token API Fonnte *
                        <span class="font-normal text-stone-400 text-[11px] ml-1">(Dapat disalin dari dashboard Fonnte)</span>
                    </label>
                    <div class="relative">
                        <input type="password" id="fonnteTokenInput" name="fonnte_token" value="<?= htmlspecialchars($currentToken) ?>"
                               placeholder="Contoh: p192x78@zABC... (Token dari Fonnte)"
                               class="w-full pl-3 pr-10 py-2.5 bg-stone-50 border border-stone-300 rounded-xl outline-none focus:ring-1 focus:ring-emerald-500 font-mono text-stone-900" />
                        <button type="button" onclick="toggleTokenVisibility()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-stone-400 hover:text-stone-700">
                            <i id="tokenEyeIcon" class="fa-solid fa-eye text-xs"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-stone-700 mb-1">
                        Nomor WA Laptop Operator Print *
                        <span class="font-normal text-stone-400 text-[11px] ml-1">(Penerima Notifikasi)</span>
                    </label>
                    <input type="text" name="print_operator_wa" value="<?= htmlspecialchars(getSetting('print_operator_wa', '081234567890')) ?>" required
                           placeholder="Contoh: 081234567890"
                           class="w-full px-3 py-2.5 bg-stone-50 border border-stone-300 rounded-xl outline-none font-bold text-emerald-800" />
                    <span class="text-[10px] text-stone-500 mt-1 block">Nomor WA karyawan/teman yang membuka WhatsApp di laptop print.</span>
                </div>

                <div>
                    <label class="block font-bold text-stone-700 mb-1">
                        URL Sistem / Base URL
                        <span class="font-normal text-stone-400 text-[11px] ml-1">(Untuk Link Download File)</span>
                    </label>
                    <input type="text" name="base_url" value="<?= htmlspecialchars(getSetting('base_url', 'http://localhost/aquiris_print')) ?>" required
                           placeholder="http://localhost/aquiris_print"
                           class="w-full px-3 py-2.5 bg-stone-50 border border-stone-300 rounded-xl outline-none font-mono text-stone-800" />
                    <span class="text-[10px] text-stone-500 mt-1 block">Gunakan IP lokal (misal: http://192.168.1.5/aquiris_print) jika laptop operator berbeda PC.</span>
                </div>
            </div>

            <div class="pt-2 flex justify-end">
                <button type="submit" class="px-5 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white font-bold rounded-xl shadow transition flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Simpan Pengaturan Fonnte</span>
                </button>
            </div>
        </form>

        <!-- Test Message Box -->
        <div class="mt-6 pt-5 border-t border-stone-100 bg-stone-50/70 -mx-6 -mb-6 p-6 md:-mx-8 md:-mb-8 md:p-8 rounded-b-3xl">
            <h4 class="font-bold text-stone-800 text-xs mb-1 flex items-center gap-2">
                <i class="fa-solid fa-paper-plane text-emerald-600"></i>
                <span>Uji Coba Kirim Pesan WhatsApp</span>
            </h4>
            <p class="text-[11px] text-stone-500 mb-3">Kirimkan pesan tes untuk memastikan token Fonnte dan nomor WhatsApp laptop operator sudah terhubung dengan benar.</p>

            <form method="POST" action="pengaturan.php" class="flex flex-col sm:flex-row items-center gap-2.5 text-xs max-w-lg">
                <input type="hidden" name="test_fonnte" value="1" />
                <div class="w-full sm:flex-1">
                    <input type="text" name="test_phone" value="<?= htmlspecialchars(getSetting('print_operator_wa', '081234567890')) ?>" required
                           placeholder="Nomor WA Tujuan Tes (e.g. 081234567890)"
                           class="w-full px-3 py-2 bg-white border border-stone-300 rounded-xl outline-none font-medium" />
                </div>
                <button type="submit" class="w-full sm:w-auto px-4 py-2 bg-stone-900 hover:bg-stone-800 text-amber-300 font-bold rounded-xl shadow transition flex items-center justify-center gap-2 shrink-0">
                    <i class="fa-solid fa-paper-plane text-xs"></i>
                    <span>Kirim Pesan Tes</span>
                </button>
            </form>
        </div>
    </div>

    <!-- 3. User & Access Management -->
    <div class="bg-white rounded-3xl border border-stone-200/90 shadow-sm p-6 md:p-8">
        <div class="flex items-center justify-between pb-4 border-b border-stone-100 mb-6">
            <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-2xl bg-stone-100 text-stone-800 flex items-center justify-center text-lg font-bold">
                    <i class="fa-solid fa-users-gear"></i>
                </div>
                <div>
                    <h3 class="font-extrabold text-stone-900 text-base">Manajemen Akun Petugas & Kasir</h3>
                    <p class="text-xs text-stone-500">Kelola akun admin dan kasir yang bertugas</p>
                </div>
            </div>

            <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <button onclick="openUserModal()" class="px-3.5 py-1.5 bg-stone-900 hover:bg-stone-800 text-amber-300 rounded-xl text-xs font-bold transition flex items-center gap-1.5">
                    <i class="fa-solid fa-user-plus"></i>
                    <span>+ Pengguna Baru</span>
                </button>
            <?php endif; ?>
        </div>

        <div class="overflow-x-auto text-xs">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-stone-50 text-stone-600 border-b border-stone-200">
                        <th class="py-3 px-4 font-bold">Nama Petugas</th>
                        <th class="py-3 px-4 font-bold">Username</th>
                        <th class="py-3 px-4 font-bold">Hak Akses</th>
                        <th class="py-3 px-4 font-bold">Dibuat Pada</th>
                        <th class="py-3 px-4 font-bold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-stone-50/80 transition">
                            <td class="py-3 px-4 font-bold text-stone-900">
                                <?= htmlspecialchars($u['name']) ?>
                                <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                    <span class="ml-1 text-[10px] text-amber-800 font-bold">(Akun Anda)</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 font-mono text-stone-600"><?= htmlspecialchars($u['username']) ?></td>
                            <td class="py-3 px-4">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase <?= $u['role'] === 'admin' ? 'bg-stone-900 text-amber-300' : 'bg-stone-100 text-stone-800' ?>">
                                    <?= htmlspecialchars($u['role']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-stone-500"><?= formatDate($u['created_at']) ?></td>
                            <td class="py-3 px-4 text-right space-x-1 whitespace-nowrap">
                                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                                    <button onclick='editUser(<?= json_encode($u) ?>)' class="p-1.5 bg-stone-100 hover:bg-stone-200 text-stone-700 rounded-lg text-xs" title="Edit Pengguna">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <button onclick="confirmDelete('pengaturan.php?delete_user=<?= $u['id'] ?>', 'Yakin ingin menghapus akun ini?')" class="p-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg text-xs" title="Hapus Akun">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-stone-400 italic text-[11px]">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Add/Edit User -->
<div id="userModal" class="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-stone-200">
        <div class="flex items-center justify-between pb-3 border-b border-stone-100">
            <h3 id="userModalTitle" class="font-extrabold text-stone-800 text-sm">Tambah Pengguna Baru</h3>
            <button onclick="closeUserModal()" class="text-stone-400 hover:text-stone-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="pengaturan.php" class="space-y-3 py-4 text-xs">
            <input type="hidden" name="save_user" value="1" />
            <input type="hidden" name="user_id" id="userIdInput" value="0" />

            <div>
                <label class="block font-bold text-stone-700 mb-1">Nama Lengkap Petugas *</label>
                <input type="text" name="name" id="userNameInput" required placeholder="Contoh: Rina Kasir"
                       class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none focus:ring-1 focus:ring-amber-500 font-semibold" />
            </div>

            <div>
                <label class="block font-bold text-stone-700 mb-1">Username Login *</label>
                <input type="text" name="username" id="userUsernameInput" required placeholder="rina"
                       class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none font-mono" />
            </div>

            <div>
                <label class="block font-bold text-stone-700 mb-1">Kata Sandi</label>
                <input type="password" name="password" id="userPasswordInput" placeholder="Kosongkan jika tidak diubah"
                       class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none" />
                <span class="text-[10px] text-stone-400 mt-0.5 block" id="userPasswordHelp">Wajib diisi untuk akun baru.</span>
            </div>

            <div>
                <label class="block font-bold text-stone-700 mb-1">Hak Akses / Peran</label>
                <select name="role" id="userRoleInput" class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl outline-none font-bold">
                    <option value="kasir">Kasir (Order & Rental)</option>
                    <option value="admin">Administrator (Akses Penuh)</option>
                </select>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-stone-100">
                <button type="button" onclick="closeUserModal()" class="px-4 py-2 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold rounded-xl">Batal</button>
                <button type="submit" class="px-5 py-2 bg-stone-900 hover:bg-stone-800 text-amber-300 font-bold rounded-xl shadow-md">Simpan Pengguna</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openUserModal() {
        document.getElementById('userModalTitle').innerText = 'Tambah Pengguna Baru';
        document.getElementById('userIdInput').value = '0';
        document.getElementById('userNameInput').value = '';
        document.getElementById('userUsernameInput').value = '';
        document.getElementById('userPasswordInput').value = '';
        document.getElementById('userPasswordInput').required = true;
        document.getElementById('userPasswordHelp').innerText = 'Wajib diisi untuk akun baru.';
        document.getElementById('userRoleInput').value = 'kasir';
        document.getElementById('userModal').classList.remove('hidden');
    }

    function editUser(u) {
        document.getElementById('userModalTitle').innerText = 'Edit Pengguna';
        document.getElementById('userIdInput').value = u.id;
        document.getElementById('userNameInput').value = u.name;
        document.getElementById('userUsernameInput').value = u.username;
        document.getElementById('userPasswordInput').value = '';
        document.getElementById('userPasswordInput').required = false;
        document.getElementById('userPasswordHelp').innerText = 'Biarkan kosong jika password tidak diubah.';
        document.getElementById('userRoleInput').value = u.role;
        document.getElementById('userModal').classList.remove('hidden');
    }

    function closeUserModal() {
        document.getElementById('userModal').classList.add('hidden');
    }

    function toggleTokenVisibility() {
        const input = document.getElementById('fonnteTokenInput');
        const icon = document.getElementById('tokenEyeIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    function recalcProfitShare() {
        const o = parseFloat(document.getElementById('inputOwnerPct').value) || 0;
        const w = parseFloat(document.getElementById('inputWorkerPct').value) || 0;
        const r = parseFloat(document.getElementById('inputReservePct').value) || 0;
        const total = Math.round((o + w + r) * 100) / 100;

        const container = document.getElementById('totalPctContainer');
        const icon = document.getElementById('totalPctIcon');
        const label = document.getElementById('totalPctLabel');
        const desc = document.getElementById('totalPctDesc');
        const btn = document.getElementById('btnSaveProfitShare');

        label.innerText = `Total Persentase: ${total}%`;

        if (Math.abs(total - 100) < 0.01) {
            container.className = 'flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-xl border bg-emerald-50 border-emerald-300 text-emerald-900';
            icon.className = 'w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold bg-emerald-500 text-white';
            icon.innerHTML = '<i class="fa-solid fa-check"></i>';
            desc.innerText = 'Rasio pembagian pas 100%. Skema siap disimpan dan diterapkan!';
            btn.disabled = false;
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            container.className = 'flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-xl border bg-rose-50 border-rose-300 text-rose-900';
            icon.className = 'w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold bg-rose-500 text-white';
            icon.innerHTML = '<i class="fa-solid fa-exclamation"></i>';
            const diff = Math.round((100 - total) * 100) / 100;
            if (diff > 0) {
                desc.innerText = `Total kurang ${diff}%. Sesuaikan agar tepat 100%.`;
            } else {
                desc.innerText = `Total kelebihan ${Math.abs(diff)}%. Sesuaikan agar tepat 100%.`;
            }
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }

    function applyPreset(o, w, r) {
        document.getElementById('inputOwnerPct').value = o;
        document.getElementById('inputWorkerPct').value = w;
        document.getElementById('inputReservePct').value = r;
        recalcProfitShare();
    }

    // Run on load
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('inputOwnerPct')) {
            recalcProfitShare();
        }
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
