<?php

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

function sendResortReviewJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

if (!isLoggedIn() || getUserRole() !== 'customer') {
    sendResortReviewJson([
        'success' => false,
        'message' => 'A customer account is required to write a review.'
    ], 401);
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/env.php';

try {
    $pdo = normanCreateDatabaseConnection('web');
} catch (Throwable $e) {
    error_log('Resort review database connection failed: ' . $e->getMessage());
    sendResortReviewJson([
        'success' => false,
        'message' => 'The review service is temporarily unavailable.'
    ], 500);
}

if (empty($_SESSION['resort_review_csrf'])) {
    $_SESSION['resort_review_csrf'] = bin2hex(random_bytes(32));
}

$resortIdValue = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['resort_id'] ?? '')
    : ($_GET['resort_id'] ?? '');

if (!is_scalar($resortIdValue) || !ctype_digit((string) $resortIdValue) || (int) $resortIdValue < 1) {
    sendResortReviewJson([
        'success' => false,
        'message' => 'Select a valid resort.'
    ], 422);
}

$resortId = (int) $resortIdValue;
$userId = (int) getUserId();

try {
    $resortStatement = $pdo->prepare("
        SELECT id, resort_name
        FROM resorts
        WHERE id = :resort_id
          AND COALESCE(visible, 1) = 1
        LIMIT 1
    ");
    $resortStatement->execute([':resort_id' => $resortId]);
    $resort = $resortStatement->fetch(PDO::FETCH_ASSOC);

    if (!$resort) {
        sendResortReviewJson([
            'success' => false,
            'message' => 'That resort is not available for review.'
        ], 404);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $reviewStatement = $pdo->prepare("
            SELECT
                id,
                rating,
                review_title,
                review_text,
                photo_data IS NOT NULL AS has_photo,
                updated_at
            FROM resort_reviews
            WHERE resort_id = :resort_id
              AND user_id = :user_id
            LIMIT 1
        ");
        $reviewStatement->execute([
            ':resort_id' => $resortId,
            ':user_id' => $userId
        ]);

        sendResortReviewJson([
            'success' => true,
            'resort' => $resort,
            'review' => $reviewStatement->fetch(PDO::FETCH_ASSOC) ?: null,
            'csrf_token' => $_SESSION['resort_review_csrf']
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST');
        sendResortReviewJson([
            'success' => false,
            'message' => 'This endpoint accepts GET and POST requests only.'
        ], 405);
    }

    $csrfToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals((string) $_SESSION['resort_review_csrf'], $csrfToken)) {
        sendResortReviewJson([
            'success' => false,
            'message' => 'Your security token is invalid. Refresh the page and try again.'
        ], 403);
    }

    $ratingValue = $_POST['rating'] ?? '';
    $title = trim((string) ($_POST['review_title'] ?? ''));
    $reviewText = trim((string) ($_POST['review_text'] ?? ''));
    $removePhoto = (string) ($_POST['remove_photo'] ?? '') === '1';
    $photoUpload = $_FILES['review_photo'] ?? null;
    $photoData = null;
    $photoMimeType = null;
    $photoOriginalName = null;
    $hasNewPhoto = is_array($photoUpload)
        && (int) ($photoUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if (!is_scalar($ratingValue)
        || !ctype_digit((string) $ratingValue)
        || (int) $ratingValue < 1
        || (int) $ratingValue > 5) {
        sendResortReviewJson([
            'success' => false,
            'message' => 'Choose a rating from 1 to 5.'
        ], 422);
    }

    if ($title === '' || mb_strlen($title) > 150) {
        sendResortReviewJson([
            'success' => false,
            'message' => 'Enter a review title of 150 characters or fewer.'
        ], 422);
    }

    if ($reviewText === '' || mb_strlen($reviewText) > 4000) {
        sendResortReviewJson([
            'success' => false,
            'message' => 'Enter a review of 4,000 characters or fewer.'
        ], 422);
    }

    if ($hasNewPhoto) {
        $uploadError = (int) ($photoUpload['error'] ?? UPLOAD_ERR_NO_FILE);
        $uploadErrorMessages = [
            UPLOAD_ERR_INI_SIZE => 'The review photo exceeds the server upload limit.',
            UPLOAD_ERR_FORM_SIZE => 'The review photo is too large.',
            UPLOAD_ERR_PARTIAL => 'The review photo upload was incomplete.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server is missing an upload temp folder.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not receive the review photo.',
            UPLOAD_ERR_EXTENSION => 'A server extension stopped the photo upload.'
        ];

        if ($uploadError !== UPLOAD_ERR_OK) {
            sendResortReviewJson([
                'success' => false,
                'message' => $uploadErrorMessages[$uploadError] ?? 'The review photo could not be uploaded.'
            ], 422);
        }

        $photoSize = (int) ($photoUpload['size'] ?? 0);
        $temporaryPath = (string) ($photoUpload['tmp_name'] ?? '');

        if ($photoSize < 1 || $photoSize > 5 * 1024 * 1024 || !is_uploaded_file($temporaryPath)) {
            sendResortReviewJson([
                'success' => false,
                'message' => 'Upload a valid review photo no larger than 5 MB.'
            ], 422);
        }

        $allowedPhotoTypes = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif'
        ];
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMimeType = $fileInfo->file($temporaryPath);
        $imageDetails = @getimagesize($temporaryPath);

        if (!is_string($detectedMimeType)
            || !in_array($detectedMimeType, $allowedPhotoTypes, true)
            || $imageDetails === false
            || ($imageDetails['mime'] ?? '') !== $detectedMimeType) {
            sendResortReviewJson([
                'success' => false,
                'message' => 'Review photos must be valid JPEG, PNG, WebP, or GIF images.'
            ], 422);
        }

        $photoData = file_get_contents($temporaryPath);
        if ($photoData === false) {
            sendResortReviewJson([
                'success' => false,
                'message' => 'The review photo could not be read.'
            ], 500);
        }

        $photoMimeType = $detectedMimeType;
        $photoOriginalName = mb_substr(basename((string) ($photoUpload['name'] ?? 'review-photo')), 0, 255);
    }

    $baseInsert = "
        INSERT INTO resort_reviews (
            resort_id,
            user_id,
            rating,
            review_title,
            review_text,
            is_approved%s
        ) VALUES (
            :resort_id,
            :user_id,
            :rating,
            :review_title,
            :review_text,
            1%s
        )
        ON DUPLICATE KEY UPDATE
            rating = VALUES(rating),
            review_title = VALUES(review_title),
            review_text = VALUES(review_text),
            is_approved = 1,
            updated_at = CURRENT_TIMESTAMP%s
    ";

    if ($hasNewPhoto) {
        $saveSql = sprintf(
            $baseInsert,
            ', photo_data, photo_mime_type, photo_original_name',
            ', :photo_data, :photo_mime_type, :photo_original_name',
            ',
            photo_data = VALUES(photo_data),
            photo_mime_type = VALUES(photo_mime_type),
            photo_original_name = VALUES(photo_original_name)'
        );
    } elseif ($removePhoto) {
        $saveSql = sprintf(
            $baseInsert,
            '',
            '',
            ',
            photo_data = NULL,
            photo_mime_type = NULL,
            photo_original_name = NULL'
        );
    } else {
        $saveSql = sprintf($baseInsert, '', '', '');
    }

    $saveStatement = $pdo->prepare($saveSql);
    $saveStatement->bindValue(':resort_id', $resortId, PDO::PARAM_INT);
    $saveStatement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $saveStatement->bindValue(':rating', (int) $ratingValue, PDO::PARAM_INT);
    $saveStatement->bindValue(':review_title', $title, PDO::PARAM_STR);
    $saveStatement->bindValue(':review_text', $reviewText, PDO::PARAM_STR);

    if ($hasNewPhoto) {
        $saveStatement->bindValue(':photo_data', $photoData, PDO::PARAM_LOB);
        $saveStatement->bindValue(':photo_mime_type', $photoMimeType, PDO::PARAM_STR);
        $saveStatement->bindValue(':photo_original_name', $photoOriginalName, PDO::PARAM_STR);
    }

    $saveStatement->execute();

    sendResortReviewJson([
        'success' => true,
        'message' => 'Your review has been published.',
        'has_photo' => $hasNewPhoto || (!$removePhoto && !empty($_POST['existing_photo']))
    ]);
} catch (PDOException $e) {
    error_log('Resort review query failed: ' . $e->getMessage());
    sendResortReviewJson([
        'success' => false,
        'message' => 'Your review could not be saved right now.'
    ], 500);
} catch (Throwable $e) {
    error_log('Resort review request failed: ' . $e->getMessage());
    sendResortReviewJson([
        'success' => false,
        'message' => 'The review request could not be completed.'
    ], 500);
}
