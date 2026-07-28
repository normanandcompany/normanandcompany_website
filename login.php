<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';


$error = "";
$returnTo = normalizeLoginReturnTo($_POST['return_to'] ?? $_GET['return_to'] ?? '');

function normalizeLoginReturnTo(?string $returnTo): string
{
    $returnTo = trim((string) $returnTo);

    if ($returnTo === '' || !str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
        return '';
    }

    if (preg_match('/[\r\n]/', $returnTo)) {
        return '';
    }

    return $returnTo;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = $_POST['email_address'] ?? '';
    $password = $_POST['password'] ?? '';

    if (loginUser($pdo, $email, $password)) {

        if ($returnTo !== '' && getUserRole() === 'customer') {
            header("Location: {$returnTo}");
            exit;
        }

        redirectByRole();
        exit;

    } else {
        $error = "Invalid login credentials.";
    }
}

include $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/navbar.php';

?>
<main id="content">
    <section class="content-section">

        <!-- Page Title Metadata -->
        <div id="page-title-meta"
            data-title="Norman and Company | Login Page"
            style="display: none;">
        </div>

        <!-- PAGE TITLE -->

        <h1>Login Page</h1>

        <p class="page-intro">
            Login and access your Norman and Company profile.
        </p>

        <!-- LOGIN CARD -->

        <div class="card">

            <?php if (!empty($error)): ?>
                <p class="error-message">
                    <?= htmlspecialchars($error) ?>
                </p>
            <?php endif; ?>

            <form id="contactForm" method="POST" action="/login.php">
                <?php if ($returnTo !== ''): ?>
                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">
                <?php endif; ?>

                <!-- LOGIN NAME -->

                <div class="form-group">

                    <label for="userName">
                        Username:
                    </label>

                    <input
                        type="email"
                        id="email_address"
                        name="email_address"
                        placeholder="Enter your username"
                        required
                    >

                </div>

                <!-- PASSWORD -->

                <div class="form-group">

                    <label for="password">
                        Password:
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                    >

                </div>

                <!-- REMEMBER ME CHECKBOX -->

                <div class="form-group">

                    <label>
                        <input
                            type="checkbox"
                            id="rememberMe"
                            name="rememberMe"
                        >
                        Remember Me
                    </label>

                </div>

                <!-- LOGIN BUTTON -->

                <div class="form-actions">

                    <button
                        type="submit"
                        class="btn-primary"
                    >
                        Login
                    </button>

                </div>
                <div>
                    <a href="/resetpassword.php">Reset Password</a>
                </div>
                <div>
                    <a href="/customerregistration.php">Register New User</a>
                </div>

            </form>

        </div>

    </section>
</main>
<?php
include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
?>
