<?php

$error = '';
$submittedDuckId = strtoupper(trim($_GET['duck_id'] ?? ''));

if (!preg_match('/^NC-[0-9]{3}$/', $submittedDuckId)) {
    $submittedDuckId = '';
}

$success = isset($_GET['submitted']) && $_GET['submitted'] === '1' && $submittedDuckId !== ''
    ? 'Thank you! Your cruise duck update has been received.'
    : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $duckAction = trim($_POST['duck_action'] ?? '');
    $duckId = strtoupper(trim($_POST['duck_id'] ?? ''));
    $cruiseShip = trim($_POST['cruise_ship'] ?? '');
    $dateFound = trim($_POST['date_found'] ?? '');
    $generalLocation = trim($_POST['general_location'] ?? '');
    $duckMessage = trim($_POST['duck_message'] ?? '');
    $parsedDateFound = DateTime::createFromFormat('!Y-m-d', $dateFound);

    $validDuckActions = ['keeping', 'rehiding', 'gifted'];

    if (!in_array($duckAction, $validDuckActions, true)) {
        $error = 'Please select what you are doing with the duck.';
    } elseif (!preg_match('/^NC-[0-9]{3}$/', $duckId)) {
        $error = 'The Duck ID must use the format NC-001.';
    } elseif ($cruiseShip === '' || mb_strlen($cruiseShip) > 25) {
        $error = 'Please enter a valid cruise ship name.';
    } elseif (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFound) ||
        $parsedDateFound === false ||
        $parsedDateFound->format('Y-m-d') !== $dateFound
    ) {
        $error = 'Please enter the date the duck was found.';
    } elseif ($generalLocation === '' || mb_strlen($generalLocation) > 100) {
        $error = 'Please enter a valid general location.';
    } elseif (mb_strlen($duckMessage) > 1000) {
        $error = 'The optional message cannot exceed 1,000 characters.';
    }

    if (
        $error === '' &&
        isset($_FILES['duck_photo']) &&
        $_FILES['duck_photo']['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        if ($_FILES['duck_photo']['error'] !== UPLOAD_ERR_OK) {
            $error = 'The photo could not be uploaded.';
        } else {
            $allowedImageTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];

            $fileInfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $fileInfo->file($_FILES['duck_photo']['tmp_name']);

            if ($_FILES['duck_photo']['size'] > 5 * 1024 * 1024) {
                $error = 'The photo cannot be larger than 5 MB.';
            } elseif (!isset($allowedImageTypes[$mimeType])) {
                $error = 'The photo must be a JPG, PNG, or WebP image.';
            }
        }
    }

    if ($error === '') {
        $photoData = null;
        $photoMimeType = null;

        if (
            isset($_FILES['duck_photo']) &&
            $_FILES['duck_photo']['error'] === UPLOAD_ERR_OK
        ) {
            $photoData = file_get_contents($_FILES['duck_photo']['tmp_name']);
            $photoMimeType = $mimeType;

            if ($photoData === false) {
                $error = 'The photo could not be read.';
            }
        }

        if ($error === '') {
            try {
                require_once __DIR__ . '/config/env.php';
                $pdo = normanCreateDatabaseConnection('web');

                $stmt = $pdo->prepare(
                    'CALL sp_insert_cruise_duck(?, ?, ?, ?, ?, ?, ?, ?)'
                );

                $stmt->bindValue(1, $duckAction);
                $stmt->bindValue(2, $duckId);
                $stmt->bindValue(3, $cruiseShip);
                $stmt->bindValue(4, $dateFound);
                $stmt->bindValue(5, $generalLocation);
                $stmt->bindValue(
                    6,
                    $duckMessage === '' ? null : $duckMessage,
                    $duckMessage === '' ? PDO::PARAM_NULL : PDO::PARAM_STR
                );
                $stmt->bindValue(
                    7,
                    $photoData,
                    $photoData === null ? PDO::PARAM_NULL : PDO::PARAM_LOB
                );
                $stmt->bindValue(
                    8,
                    $photoMimeType,
                    $photoMimeType === null ? PDO::PARAM_NULL : PDO::PARAM_STR
                );
                $stmt->execute();
                $stmt->closeCursor();

                header(
                    'Location: /ducks.php?submitted=1&duck_id=' .
                    rawurlencode($duckId)
                );
                exit;
            } catch (Throwable $exception) {
                error_log('Cruise duck submission failed: ' . $exception->getMessage());
                $error = 'We could not save your duck update. Please try again.';
            }
        }
    }
}

include $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/navbar.php';
?>

<!-- ========================================= -->
<!-- CRUISE DUCK PAGE -->
<!-- File: /ducks.php -->
<!-- Copyright 2026 @ Norman & Company -->
<!-- Written by: Joe Leone -->
<!-- ========================================= -->

<main id="content">

    <section class="cruise-duck-section">

        <div
            id="page-title-meta"
            data-title="Norman and Company | Cruise Duck Tracker"
            style="display: none;">
        </div>

        <div class="cruise-duck-content">

            <h1>You Found a Norman and Company Cruise Duck!</h1>

            <p class="cruise-duck-intro">
                Tell us where you found the duck and whether you plan to keep it
                or re-hide it for another traveler to discover.
            </p>

            <?php if ($error !== ''): ?>
                <div class="form-status error" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="form-status success" role="status">
                    <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form
                class="cruise-duck-form"
                action=""
                method="post"
                enctype="multipart/form-data">

                <fieldset class="cruise-duck-choice">

                    <legend>What are you doing with the duck?</legend>

                    <label class="cruise-duck-radio-option">
                        <input
                            type="radio"
                            name="duck_action"
                            value="keeping"
                            <?= ($_POST['duck_action'] ?? '') === 'keeping'
                                ? 'checked'
                                : '' ?>
                            required>

                        <span>I’m keeping the duck</span>
                    </label>

                    <label class="cruise-duck-radio-option">
                        <input
                            type="radio"
                            name="duck_action"
                            value="rehiding"
                            <?= ($_POST['duck_action'] ?? '') === 'rehiding'
                                ? 'checked'
                                : '' ?>
                            required>

                        <span>I’m re-hiding the duck</span>
                    </label>

                    <label class="cruise-duck-radio-option">
                        <input
                            type="radio"
                            name="duck_action"
                            value="gifted"
                            <?= ($_POST['duck_action'] ?? '') === 'gifted'
                                ? 'checked'
                                : '' ?>
                            required>

                        <span>I was gifted the duck</span>
                    </label>

                </fieldset>

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
                        pattern="NC-[0-9]{3}"
                        value="<?= htmlspecialchars(
                            $_POST['duck_id'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        autocomplete="off"
                        placeholder="Example: NC-001"
                        title="Enter NC- followed by three digits, such as NC-001"
                        required>

                    <small>
                        Enter NC- followed by the three-digit number printed on the duck.
                    </small>
                </div>

                <div class="cruise-duck-form-group">
                    <label for="cruise-ship">
                        Select the cruise ship
                    </label>

                    <input
                        type="text"
                        id="cruise-ship"
                        name="cruise_ship"
                        maxlength="25"
                        value="<?= htmlspecialchars(
                            $_POST['cruise_ship'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        placeholder="Enter the ship name"
                        required>
                </div>

                <div class="cruise-duck-form-group">
                    <label for="date-found">
                        Enter the date found
                    </label>

                    <input
                        type="date"
                        id="date-found"
                        name="date_found"
                        value="<?= htmlspecialchars(
                            $_POST['date_found'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required>
                </div>

                <div class="cruise-duck-form-group">
                    <label for="general-location">
                        Enter a general location
                    </label>

                    <input
                        type="text"
                        id="general-location"
                        name="general_location"
                        maxlength="100"
                        value="<?= htmlspecialchars(
                            $_POST['general_location'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        placeholder="Example: Deck 8 near the elevators"
                        required>
                </div>

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
                        Accepted image types: JPG, PNG, or WebP (5 MB maximum).
                    </small>
                </div>

                <div class="cruise-duck-form-group">
                    <label for="duck-message">
                        Add an optional message
                    </label>

                    <textarea
                        id="duck-message"
                        name="duck_message"
                        rows="7"
                        maxlength="1000"
                        placeholder="Tell us about finding the duck, your cruise, or where you plan to re-hide it."><?= htmlspecialchars(
                            $_POST['duck_message'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?></textarea>
                </div>

                <section
                    class="cruise-duck-social-post"
                    aria-labelledby="social-media-post-title">
                    <h2 id="social-media-post-title">Social Media Post</h2>

                    <p>
                        Post about your duck on social media with the hashtags
                        <strong>#cruiseduck #normanandcompany</strong>
                    </p>
                </section>

                <div class="cruise-duck-form-actions">

                    <button
                        class="cruise-duck-submit-button"
                        type="submit">
                        Submit Duck Update
                    </button>

                    <a
                        class="cruise-duck-history-button"
                        href="/ducks/duckhistory.php<?= $submittedDuckId !== ''
                            ? '?duck_id=' . rawurlencode($submittedDuckId)
                            : '' ?>">
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
