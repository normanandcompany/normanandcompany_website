<?php

$duckId = strtoupper(trim($_GET['duck_id'] ?? ''));
$error = '';
$historyEntries = [];
$hasSearched = $duckId !== '';

if ($hasSearched && !preg_match('/^NC-[0-9]{3}$/', $duckId)) {
    $error = 'The Duck ID must use the format NC-001.';
} elseif ($hasSearched) {
    try {
        require_once dirname(__DIR__) . '/config/env.php';
        $pdo = normanCreateDatabaseConnection('web');

        $stmt = $pdo->prepare('CALL sp_get_cruise_duck_history(?)');
        $stmt->execute([$duckId]);
        $historyEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
    } catch (Throwable $exception) {
        error_log('Cruise duck history lookup failed: ' . $exception->getMessage());
        $error = 'We could not load this duck’s history. Please try again.';
    }
}

include $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/navbar.php';
?>

<!-- ========================================= -->
<!-- CRUISE DUCK HISTORY PAGE -->
<!-- File: /ducks/duckhistory.php -->
<!-- Copyright 2026 @ Norman & Company -->
<!-- Written by: Joe Leone -->
<!-- ========================================= -->

<main id="content">

    <section class="cruise-duck-section">

        <div
            id="page-title-meta"
            data-title="Norman and Company | Cruise Duck Travel History"
            style="display: none;">
        </div>

        <div class="cruise-duck-content cruise-duck-history-content">

            <h1>Cruise Duck Travel History</h1>

            <p class="cruise-duck-intro">
                Enter the Duck ID in the format NC-001 to see where it has
                traveled and what previous finders chose to do with it.
            </p>

            <form class="cruise-duck-history-search" method="get">

                <div class="cruise-duck-form-group">
                    <label for="history-duck-id">Duck ID</label>

                    <input
                        type="text"
                        id="history-duck-id"
                        name="duck_id"
                        minlength="6"
                        maxlength="6"
                        pattern="NC-[0-9]{3}"
                        value="<?= htmlspecialchars($duckId, ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="off"
                        placeholder="Example: NC-001"
                        title="Enter NC- followed by three digits, such as NC-001"
                        required>
                </div>

                <button class="cruise-duck-submit-button" type="submit">
                    View Travel History
                </button>

            </form>

            <?php if ($error !== ''): ?>
                <div class="form-status error" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php elseif ($hasSearched && $historyEntries === []): ?>
                <div class="form-status cruise-duck-history-empty" role="status">
                    No travel history was found for Duck ID
                    <strong><?= htmlspecialchars($duckId, ENT_QUOTES, 'UTF-8') ?></strong>.
                </div>
            <?php elseif ($historyEntries !== []): ?>
                <div class="cruise-duck-history-results">

                    <h2>
                        Travel history for
                        <span><?= htmlspecialchars($duckId, ENT_QUOTES, 'UTF-8') ?></span>
                    </h2>

                    <ol class="cruise-duck-history-list">
                        <?php foreach ($historyEntries as $entry): ?>
                            <?php
                            $dateFound = new DateTimeImmutable($entry['date_found']);
                            $actionLabel = match ($entry['duck_action']) {
                                'keeping' => 'Kept the duck',
                                'rehiding' => 'Re-hid the duck',
                                'gifted' => 'Was gifted the duck',
                                default => 'Updated the duck'
                            };
                            ?>
                            <li class="cruise-duck-history-entry">
                                <article>
                                    <div class="cruise-duck-history-details">
                                        <p>
                                            <strong>Date:</strong>
                                            <time datetime="<?= htmlspecialchars(
                                                $entry['date_found'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"><?= $dateFound->format('n-j-Y') ?></time>
                                        </p>

                                        <p>
                                            <strong>Finder's Choice:</strong>
                                            <?= htmlspecialchars(
                                                $actionLabel,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </p>

                                        <p>
                                            <strong>Location:</strong>
                                            <?= htmlspecialchars(
                                                $entry['general_location'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </p>

                                        <p>
                                            <strong>Message:</strong>
                                            <?php if (!empty($entry['duck_message'])): ?>
                                                <?= nl2br(htmlspecialchars(
                                                    $entry['duck_message'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                )) ?>
                                            <?php else: ?>
                                                No message provided.
                                            <?php endif; ?>
                                        </p>
                                    </div>

                                    <?php if (
                                        !empty($entry['photo_data']) &&
                                        in_array(
                                            $entry['photo_mime_type'],
                                            ['image/jpeg', 'image/png', 'image/webp'],
                                            true
                                        )
                                    ): ?>
                                        <img
                                            class="cruise-duck-history-photo"
                                            src="data:<?= htmlspecialchars(
                                                $entry['photo_mime_type'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>;base64,<?= base64_encode($entry['photo_data']) ?>"
                                            alt="Photo submitted for Duck ID <?= htmlspecialchars(
                                                $duckId,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            loading="lazy">
                                    <?php endif; ?>
                                </article>
                            </li>
                        <?php endforeach; ?>
                    </ol>

                </div>
            <?php endif; ?>

            <form
                class="cruise-duck-history-back"
                action="/ducks.php"
                method="get">
                <button class="cruise-duck-submit-button" type="submit">
                    Report Another Duck
                </button>
            </form>

        </div>

    </section>

</main>

<?php
include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
?>
