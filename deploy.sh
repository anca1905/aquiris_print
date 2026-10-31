#!/bin/bash
# ==============================================================================
# Script Otomatis Deployment Aquiris Print & Acrylic Studio
# Kompatibel: Ubuntu 20.04 / 22.04 / 24.04 & Debian 11 / 12
# ==============================================================================

set -e

echo "=========================================================="
echo " 🚀 Memulai Instalasi & Setup Server Aquiris Print"
echo "=========================================================="

# 1. Update Repository Sistem
echo "📦 [1/6] Memperbarui paket sistem OS..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y && apt-get upgrade -y

# 2. Instalasi Web Server, PHP & Ekstensi, MariaDB, Git, Curl
echo "🌐 [2/6] Menginstal Nginx, PHP-FPM, MariaDB & modul pendukung..."
apt-get install -y nginx mariadb-server mariadb-client git curl unzip ufw

# Deteksi versi PHP default atau install PHP
apt-get install -y php-fpm php-mysql php-mbstring php-gd php-curl php-xml php-zip php-intl

# Deteksi socket PHP-FPM yang aktif
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
PHP_FPM_SOCK=$(find /run/php/ -name "php*fpm.sock" | head -n 1)

if [ -z "$PHP_FPM_SOCK" ]; then
    PHP_FPM_SOCK="/run/php/php${PHP_VERSION}-fpm.sock"
fi
echo "✅ Menggunakan PHP versi: ${PHP_VERSION} (Socket: ${PHP_FPM_SOCK})"

# 3. Konfigurasi Database MariaDB / MySQL
echo "🗄️ [3/6] Menyiapkan Database db_aquiris_print..."
systemctl start mariadb
systemctl enable mariadb

DB_NAME="db_aquiris_print"
DB_USER="aquiris_user"
DB_PASS="AquirisPrint#2026!"

mysql -u root <<EOF
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF

echo "✅ Database & User '${DB_USER}' berhasil dibuat."

# 4. Clone / Deploy Source Code dari GitHub
echo "📂 [4/6] Mengunduh source code dari GitHub..."
TARGET_DIR="/var/www/aquiris_print"

if [ -d "$TARGET_DIR/.git" ]; then
    echo "Direktori sudah ada, memperbarui dari GitHub..."
    cd "$TARGET_DIR"
    git fetch origin main
    git reset --hard origin/main
else
    rm -rf "$TARGET_DIR"
    git clone https://github.com/anca1905/aquiris_print.git "$TARGET_DIR"
    cd "$TARGET_DIR"
fi

# Impor database awal jika ada file SQL
if [ -f "$TARGET_DIR/db_aquiris_print.sql" ]; then
    echo "Mengimpor skema dan data transaksi awal ke database..."
    mysql -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" < "$TARGET_DIR/db_aquiris_print.sql"
    echo "✅ Database berhasil diimpor!"
fi

# Update config/database.php sesuai kredensial server
cat << 'EOF' > "$TARGET_DIR/config/database.php"
<?php
// config/database.php - Production Config

define('DB_HOST', 'localhost');
define('DB_USER', 'aquiris_user');
define('DB_PASS', 'AquirisPrint#2026!');
define('DB_NAME', 'db_aquiris_print');
define('DB_PORT', 3306);

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                die("Koneksi Database Gagal: " . $e->getMessage());
            }
        }
        return self::$instance;
    }
}
EOF

# 5. Konfigurasi Hak Akses Direktori
echo "🔒 [5/6] Mengatur permission folder dan upload..."
chown -R www-data:www-data "$TARGET_DIR"
find "$TARGET_DIR" -type d -exec chmod 755 {} \;
find "$TARGET_DIR" -type f -exec chmod 644 {} \;
mkdir -p "$TARGET_DIR/assets/uploads/orders"
chmod -R 775 "$TARGET_DIR/assets/uploads"

# 6. Konfigurasi VirtualHost Nginx
echo "⚙️ [6/6] Menyiapkan konfigurasi VirtualHost Nginx..."

SERVER_IP=$(curl -s https://api.ipify.org || hostname -I | awk '{print $1}')

cat << EOF > /etc/nginx/sites-available/aquiris_print
server {
    listen 80 default_server;
    listen [::]:80 default_server;

    server_name _;

    root /var/www/aquiris_print;
    index index.php landing.php index.html;

    client_max_body_size 50M;

    location / {
        try_files \$uri \$uri/ /index.php?\$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHP_FPM_SOCK};
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    location ~ /\.(git|sql|sh) {
        deny all;
        return 404;
    }
}
EOF

# Enable site Nginx
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/aquiris_print /etc/nginx/sites-enabled/

# Test Nginx & Restart Services
nginx -t
systemctl restart php*-fpm
systemctl restart nginx

# Firewall Setup
if command -v ufw >/dev/null 2>&1; then
    ufw allow 22/tcp || true
    ufw allow 80/tcp || true
    ufw allow 443/tcp || true
fi

echo "=========================================================="
echo " 🎉 INSTALASI & SETUP SELESAI DENGAN SUKSES!"
echo "=========================================================="
echo "Aplikasi Aquiris Print sekarang aktif dan dapat diakses di:"
echo "👉 http://${SERVER_IP}"
echo ""
echo "Informasi Login Bawaan:"
echo "👤 Admin: username 'admin' | password 'admin123'"
echo "👤 Kasir: username 'kasir' | password 'kasir123'"
echo "=========================================================="
