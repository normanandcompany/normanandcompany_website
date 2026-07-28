<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

include $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/navbar.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = $_POST['email_address'] ?? '';
    $password = $_POST['password'] ?? '';

    if (loginUser($pdo, $email, $password)) {

        redirectByRole();
        exit;

    } else {
        $error = "Invalid login credentials.";
    }
}
?>
<main id="content">
    <section class="content-section">

        <!-- Page Title Metadata -->
        <div id="page-title-meta"
            data-title="Norman and Company | User Registration Page"
            style="display: none;">
        </div>

        <!-- PAGE TITLE -->

        <h1>User Registration</h1>

        <p class="page-intro">
            Create your free Norman and Company account to unlock an even richer travel planning experience. 
            Registered members gain access to exclusive travel resources, premium destination guides, detailed 
            cruise and resort information, personalized planning tools, member-only articles, special offers, 
            and exciting future features as they're released. Your account also allows you to receive our travel 
            newsletter, and stay informed with the latest cruise news, destination updates, and exclusive 
            promotions. Registration is quick, free, and designed to help you travel smarter, plan with confidence, 
            and make every journey more rewarding.
        </p>

        <!-- LOGIN CARD -->

        <div class="card">

            <?php if (!empty($error)): ?>
                <p class="error-message">
                    <?= htmlspecialchars($error) ?>
                </p>
            <?php endif; ?>

            <form id="contactForm" method="POST" action="/api/registerCustomer.php">

                <!-- FIRST NAME -->
                <div class="form-group">

                    <label for="first_name">
                        First Name:
                    </label>

                    <input
                    type="text"
                    id="first-name"
                    name="first-name"
                    placeholder="First name"
                    size=30
                    required
                    >
                </div>

                <!-- LAST NAME -->
                <div class="form-group">
                    <label for="last_name">
                        Last Name:
                    </label>

                    <input
                    type="text"
                    id="last-name"
                    name="last-name"
                    placeholder="Last name"
                    size=30
                    required
                    >
                </div>

                <!-- BIRTH DATE -->
                <div class="form-group">
                    <label for="birthdate">
                        Birth Date:
                    </label>

                    <input
                        type="date"
                        id="birthdate"
                        name="birthdate"
                        max="<?= date('Y-m-d') ?>"
                        required
                    >
                </div>

                <!-- EMAIL -->
                <div class="form-group">
                    <label for="userName">
                        Email:
                    </label>

                    <input
                        type="email"
                        id="email_address"
                        name="email_address"
                        placeholder="Enter your email address."
                        size=50
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
                        size=25
                        required
                    >
                </div>

                <!-- PASSWORD RE-ENTRY -->

                <div class="form-group">
                    <label for="password">
                        Confirm Password:
                    </label>

                    <input
                        type="password"
                        id="password-confirm"
                        name="password-confirm"
                        placeholder="Confirm your password"
                        size=25
                        required
                    >
                </div>

                <!-- ADDRESS 1 -->

                <div class="form-group">
                    <label for="address_1">
                        Address:
                    </label>

                    <input
                    type="text"
                    id="address_1"
                    name="address_1"
                    placeholder="Address"
                    size=50
                    required
                    >
                </div>

                <!-- ADDRESS 2 -->

                <div class="form-group">
                    <label for="address_2">
                        Address 2:
                    </label>

                    <input
                    type="text"
                    id="address_2"
                    name="address_2"
                    placeholder="Address 2"
                    size=50
                    >
                </div>

                <!-- CITY -->

                <div class="form-group">
                    <label for="city">
                        City:
                    </label>

                    <input
                    type="text"
                    id="city"
                    name="city"
                    placeholder="City"
                    size=50
                    required
                    >
                </div>

                <!-- STATE/PROVINCE -->

                <div class="form-group">
                    <label for="state_prov">
                        State/Province: 
                    </label>

                <select id="state_prov" name="state_prov" required>
                    <option value="">Loading...</option>
                </select>
                </div>
                
                <!-- POSTAL CODE --> 

                <div class="form-group">
                    <label for="postal_code">
                        Postal Code:
                    </label>

                    <input
                    type="text"
                    id="postal_code"
                    name="postal_code"
                    placeholder="55512"
                    size=6
                    required
                    >
                </div>

                <!-- COUNTRY -->

                <div class="form-group">
                    <label for="country">
                        Country:
                    </label>

                    <input
                    type="text"
                    id="country"
                    name="country"
                    placeholder="Country"
                    size=50
                    required
                    >
                </div>

                <!-- PHONE -->

                <div class="form-group">
                    <label for="phone">
                        Phone:
                    </label>

                    <input
                    type="tel"
                    id="phone"
                    name="phone"
                    placeholder=""
                    size=10
                    required
                    >
                </div>

                <!-- SUBMIT BUTTON -->

                <div class="form-actions">
                    <button
                        type="submit"
                        class="btn-primary"
                    >
                        Submit
                    </button>

                </div>

            </form>

        </div>

    </section>
</main>
<?php
include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
?>

<script>
// Populate state/province dropdown on this page
document.addEventListener('DOMContentLoaded', function() {
    loadStateProvinces('state_prov');
});
</script>
