<?php
// includes/auth.php

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function currentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? 'User',
        'username' => $_SESSION['user_username'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'kasir'
    ];
}

function requireAuth(string $requiredRole = ''): void {
    if (!isLoggedIn()) {
        setFlash('warning', 'Silakan login terlebih dahulu untuk mengakses sistem.');
        header('Location: login.php');
        exit;
    }

    if ($requiredRole !== '' && $_SESSION['user_role'] !== $requiredRole && $_SESSION['user_role'] !== 'admin') {
        setFlash('error', 'Akses ditolak. Halaman tersebut hanya dapat diakses oleh Administrator/Owner.');
        header('Location: index.php');
        exit;
    }
}

function isAdmin(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function requireAdmin(): void {
    requireAuth('admin');
}

function attemptLogin(string $username, string $password): bool {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("SELECT id, name, username, password, role FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        return true;
    }
    return false;
}

function logout(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
