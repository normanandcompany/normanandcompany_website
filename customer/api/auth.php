<?php
session_start();
ob_start();

/*
|--------------------------------------------------------------------------
| AUTH SYSTEM (PDO + ROLE TABLE SUPPORTED)
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| SESSION HELPERS
|--------------------------------------------------------------------------
*/

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function getUserId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function getUserRole(): ?string
{
    return $_SESSION['role_name'] ?? null;
}

function getUserEmail(): ?string
{
    return $_SESSION['email_address'] ?? null;
}

/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
*/

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header("Location: /pages/login.php");
        exit();
    }
}

/**
 * Require a specific role name (admin, customer, etc.)
 */
function requireRole(string $role): void
{
    requireLogin();

    if (getUserRole() !== $role) {
        header("Location: /");
        exit();
    }
}

/**
 * Require one of multiple roles
 */
function requireAnyRole(array $roles): void
{
    requireLogin();

    if (!in_array(getUserRole(), $roles, true)) {
        header("Location: /");
        exit();
    }
}

/*
|--------------------------------------------------------------------------
| LOGIN
|--------------------------------------------------------------------------
*/

function loginUser(PDO $pdo, string $email, string $password): bool
{
    $sql = "
        SELECT
            u.id,
            u.email_address,
            u.password_hash,
            u.user_role_id,
            r.role_name,
            u.is_active
        FROM users u
        LEFT JOIN user_roles r ON r.id = u.user_role_id
        WHERE u.email_address = :email
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':email' => $email
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // User not found
    if (!$user) {
        return false;
    }

    // Account disabled check
    if ((int)$user['is_active'] !== 1) {
        return false;
    }

    // Password check
    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }

    // Secure session handling
    session_regenerate_id(true);

    // Store session data
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['email_address'] = $user['email_address'];
    $_SESSION['user_role_id'] = (int)$user['user_role_id'];
    $_SESSION['role_name'] = $user['role_name'];

    // Update last login timestamp
    $log = $pdo->prepare("
        INSERT INTO user_login_logs (user_id)
        VALUES (:id)
    ");

    $log->execute([
        ':id' => $user['id']
    ]);

    return true;
}

/*
|--------------------------------------------------------------------------
| LOGOUT
|--------------------------------------------------------------------------
*/

function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/*
|--------------------------------------------------------------------------
| ROLE REDIRECT
|--------------------------------------------------------------------------
*/

function redirectByRole(): void
{
    $role = getUserRole();

    if (!$role) {
        header("Location: /pages/login.php");
        exit();
    }

    if ($role === 'admin') {
        header("Location: /admin/");
        exit();
    }

    if ($role === 'customer') {
        header("Location: /customer/");
        exit();
    }

    header("Location: /");
    exit();
}?>
