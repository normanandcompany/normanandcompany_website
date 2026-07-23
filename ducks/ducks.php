<!-- ========================================= -->
<!-- CRUISE DUCK PAGE -->
<!-- File: /ducks/index.php -->
<!-- Copyright 2026 @ Norman & Company -->
<!-- Written by: Joe Leone -->
<!-- ========================================= -->

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

<section class="cruise-duck-section">

    <!-- Page Title Metadata -->
    <div id="page-title-meta"
        data-title="Norman and Company | Cruise Duck Tracker"
        style="display: none;">
    </div>

    <div class="cruise-duck-content">

        <h1>You Found a Norman and Company Cruise Duck!</h1>

        <p class="cruise-duck-intro">
            Tell us where you found the duck and whether you plan to keep it
            or re-hide it for another traveler to discover.
        </p>

        <form
            class="cruise-duck-form"
            action=""
            method="post"
            enctype="multipart/form-data">

            <!-- Duck action -->
            <fieldset class="cruise-duck-choice">

                <legend>What are you doing with the duck?</legend>

                <label class="cruise-duck-radio-option">
                    <input
                        type="radio"
                        name="duck_action"
                        value="keeping"
                        required>

                    <span>I’m keeping the duck</span>
                </label>

                <label class="cruise-duck-radio-option">
                    <input
                        type="radio"
                        name="duck_action"
                        value="rehiding"
                        required>

                    <span>I’m re-hiding the duck</span>
                </label>

            </fieldset>

            <!-- Duck ID -->
            <div class="cruise-duck-form-group">
                <label for="duck-id">
                    Enter the Duck ID
                </label>

                <input
                    type="text"
                    id="duck-id"
                    name="duck_id"
                    minlength="6"
                    maxlength="6"
                    pattern="[A-Za-z0-9]{6}"
                    autocomplete="off"
                    placeholder="Example: ND1234"
                    required>

                <small>
                    Enter the six-character ID printed on the duck.
                </small>
            </div>

            <!-- Cruise ship -->
            <div class="cruise-duck-form-group">
                <label for="cruise-ship">
                    Select the cruise ship
                </label>

                <input
                    type="text"
                    id="cruise-ship"
                    name="cruise_ship"
                    maxlength="25"
                    placeholder="Enter the ship name"
                    required>
            </div>

            <!-- Date found -->
            <div class="cruise-duck-form-group">
                <label for="date-found">
                    Enter the date found
                </label>

                <input
                    type="date"
                    id="date-found"
                    name="date_found"
                    required>
            </div>

            <!-- General location -->
            <div class="cruise-duck-form-group">
                <label for="general-location">
                    Enter a general location
                </label>

                <input
                    type="text"
                    id="general-location"
                    name="general_location"
                    maxlength="100"
                    placeholder="Example: Deck 8 near the elevators"
                    required>
            </div>

            <!-- Photo upload -->
            <div class="cruise-duck-form-group">
                <label for="duck-photo">
                    Upload a photo
                </label>

                <div class="cruise-duck-upload-row">
                    <label
                        class="cruise-duck-upload-button"
                        for="duck-photo">
                        Upload
                    </label>

                    <input
                        class="cruise-duck-file-input"
                        type="file"
                        id="duck-photo"
                        name="duck_photo"
                        accept="image/jpeg,image/png,image/webp">
                </div>

                <small>
                    Accepted image types: JPG, PNG, or WebP.
                </small>
            </div>

            <!-- Optional message -->
            <div class="cruise-duck-form-group">
                <label for="duck-message">
                    Add an optional message
                </label>

                <textarea
                    id="duck-message"
                    name="duck_message"
                    rows="7"
                    maxlength="1000"
                    placeholder="Tell us about finding the duck, your cruise, or where you plan to re-hide it."></textarea>
            </div>

            <!-- Form actions -->
            <div class="cruise-duck-form-actions">

                <button
                    class="cruise-duck-submit-button"
                    type="submit">
                    Submit Duck Update
                </button>

                <a
                    class="cruise-duck-history-button"
                    href="duckhistory.php">
                    View the Duck’s Travel History
                </a>

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