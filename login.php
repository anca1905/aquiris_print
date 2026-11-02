<?php
// login.php
require_once __DIR__ . '/config/app.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Silakan isi username dan kata sandi Anda.';
    } else {
        if (attemptLogin($username, $password)) {
            setFlash('success', 'Halo ' . htmlspecialchars($_SESSION['user_name']) . ', selamat bekerja!');
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Username atau kata sandi tidak cocok. Silakan coba lagi.';
        }
    }
}

$shopName = getSetting('shop_name', 'Aquiris Print');
$shopTagline = getSetting('shop_tagline', 'Solusi Percetakan & Sewa Papan Akrilik Event');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk Kasir • <?= htmlspecialchars($shopName) ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/png" href="assets/images/icon-mark.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #FAF6F0;
        }
        .serif-font {
            font-family: 'Playfair Display', Georgia, serif;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 selection:bg-amber-100 selection:text-amber-900">

    <div class="w-full max-w-md">
        <!-- Brand Header with Official Logo -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center p-4 bg-white rounded-3xl shadow-xl shadow-stone-300/40 border border-stone-200/90 mb-3.5 transform hover:scale-102 transition">
                <img src="assets/images/logo-light.jpg" alt="Aquiris Print" class="h-12 w-auto object-contain" />
            </div>
            <p class="text-xs text-stone-500 font-medium">Sistem Kasir Cetak, Fotocopy & Sewa Papan Akrilik</p>
        </div>

        <!-- Customer Landing Page Announcement Banner -->
        <div class="mb-5 bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200/90 rounded-2xl p-3.5 text-xs text-stone-800 shadow-sm flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-sm">
                    <i class="fa-solid fa-cloud-arrow-up text-sm"></i>
                </div>
                <div>
                    <span class="font-bold text-stone-900 block leading-tight">Mau Kirim File Cetak?</span>
                    <span class="text-[11px] text-stone-500">Kirim dari HP/Laptop Anda tanpa perlu login</span>
                </div>
            </div>
            <a href="index.php#order-form" class="px-3 py-1.5 bg-stone-900 hover:bg-stone-800 text-amber-300 rounded-xl font-bold text-[11px] shrink-0 transition flex items-center gap-1 shadow-sm">
                <span>Buka Form</span>
                <i class="fa-solid fa-arrow-right text-[9px]"></i>
            </a>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-3xl shadow-xl shadow-stone-400/10 border border-stone-200/80 p-8 sm:p-9 relative overflow-hidden">
            <div class="mb-6">
                <h2 class="text-lg font-extrabold text-stone-800">Masuk ke Kasir</h2>
                <p class="text-xs text-stone-500 mt-0.5">Silakan masuk menggunakan akun kasir atau admin Anda</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="mb-5 p-3.5 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl text-xs flex items-center gap-2.5">
                    <i class="fa-solid fa-circle-exclamation text-rose-500 text-sm"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="space-y-4 text-xs">
                <div>
                    <label class="block font-bold text-stone-700 mb-1.5 uppercase tracking-wider text-[11px]" for="username">
                        Username Kasir
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-stone-400">
                            <i class="fa-solid fa-user text-xs"></i>
                        </div>
                        <input type="text" name="username" id="username" required autofocus
                            placeholder="Ketik username Anda"
                            class="w-full pl-10 pr-4 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:bg-white outline-none transition font-medium" />
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block font-bold text-stone-700 uppercase tracking-wider text-[11px]" for="password">
                            Kata Sandi
                        </label>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-stone-400">
                            <i class="fa-solid fa-key text-xs"></i>
                        </div>
                        <input type="password" name="password" id="password" required
                            placeholder="••••••••"
                            class="w-full pl-10 pr-4 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs focus:ring-2 focus:ring-amber-500 focus:bg-white outline-none transition" />
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-3 px-4 bg-stone-900 hover:bg-stone-800 text-amber-300 hover:text-amber-200 font-bold rounded-xl text-xs shadow-md shadow-stone-900/15 transition transform active:scale-98 flex items-center justify-center gap-2">
                        <span>Masuk ke Sistem</span>
                        <i class="fa-solid fa-arrow-right text-[11px]"></i>
                    </button>
                </div>
            </form>
        </div>

        <div class="text-center text-xs text-stone-500 mt-6 flex items-center justify-center gap-2">
            <span>&copy; <?= date('Y') ?></span>
            <span class="font-bold text-stone-700"><?= htmlspecialchars($shopName) ?></span>
            <span>•</span>
            <span>Sistem Kasir & Rental</span>
        </div>
    </div>
</body>
</html>
