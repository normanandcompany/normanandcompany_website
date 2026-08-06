<?php

declare(strict_types=1);

require_once __DIR__ . '/download_helpers.php';
requireDownloadAdmin();
require_once __DIR__ . '/db.php';

function fetchDownloadRecord(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM downloads WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    return $record ?: null;
}

function downloadKeyExists(PDO $pdo, string $key, int $excludeId = 0): bool
{
    $sql = 'SELECT COUNT(*) FROM downloads WHERE download_key = :download_key';
    $params = [':download_key' => $key];

    if ($excludeId > 0) {
        $sql .= ' AND id <> :id';
        $params[':id'] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn() > 0;
}

function filenameExists(PDO $pdo, string $filename, int $excludeId = 0): bool
{
    $sql = 'SELECT COUNT(*) FROM downloads WHERE filename = :filename';
    $params = [':filename' => $filename];

    if ($excludeId > 0) {
        $sql .= ' AND id <> :id';
        $params[':id'] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn() > 0;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $categories = $pdo->query('SELECT id, description FROM download_category ORDER BY id ASC')
            ->fetchAll(PDO::FETCH_ASSOC);
        $rows = $pdo->query("SELECT d.id, d.download_category_id, dc.description AS category_description,
            d.title, d.description, d.filename, d.download_key, d.viewable,
            d.download_count, d.created_at, d.updated_at
            FROM downloads d
            LEFT JOIN download_category dc ON dc.id = d.download_category_id
            ORDER BY d.created_at DESC, d.id DESC")->fetchAll(PDO::FETCH_ASSOC);
        $storageDirectory = downloadStorageDirectory();

        foreach ($rows as &$row) {
            $row['link'] = '/customer/download.php?key=' . rawurlencode((string) $row['download_key']);
            $row['file_exists'] = is_file($storageDirectory . '/' . basename((string) $row['filename']));
        }
        unset($row);

        $available = 0;
        $totalDownloads = 0;

        foreach ($rows as $row) {
            if ((int) $row['viewable'] === 1 && $row['file_exists']) {
                $available++;
            }
            $totalDownloads += (int) $row['download_count'];
        }

        sendDownloadJson([
            'success' => true,
            'downloads' => $rows,
            'categories' => $categories,
            'metrics' => [
                'available' => $available,
                'total_downloads' => $totalDownloads,
                'records' => count($rows)
            ]
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendDownloadJson(['success' => false, 'message' => 'Unsupported request method.'], 405);
    }

    $action = trim((string) ($_POST['action'] ?? 'save'));

    if ($action === 'delete') {
        $id = downloadInt($_POST['id'] ?? '', 'Download ID');
        $record = fetchDownloadRecord($pdo, $id);

        if (!$record) {
            sendDownloadJson(['success' => false, 'message' => 'Download not found.'], 404);
        }

        $stmt = $pdo->prepare('UPDATE downloads SET viewable = 0 WHERE id = :id');
        $stmt->execute([':id' => $id]);

        sendDownloadJson(['success' => true, 'message' => 'Download hidden. The stored file was preserved.']);
    }

    if ($action !== 'save') {
        throw new InvalidArgumentException('Invalid download action.');
    }

    $id = trim((string) ($_POST['id'] ?? '')) === '' ? 0 : downloadInt($_POST['id'], 'Download ID');
    $record = $id > 0 ? fetchDownloadRecord($pdo, $id) : null;

    if ($id > 0 && !$record) {
        sendDownloadJson(['success' => false, 'message' => 'Download not found.'], 404);
    }

    $title = downloadRequiredText($_POST['title'] ?? '', 'Title', 180);
    $description = downloadRequiredText($_POST['description'] ?? '', 'Description', 10000);
    $downloadCategoryId = downloadInt($_POST['download_category_id'] ?? '', 'Download category');
    $viewable = isset($_POST['viewable']) ? 1 : 0;
    $downloadKey = strtolower(trim((string) ($_POST['download_key'] ?? '')));

    $category = $pdo->prepare('SELECT COUNT(*) FROM download_category WHERE id = :id');
    $category->execute([':id' => $downloadCategoryId]);
    if ((int) $category->fetchColumn() === 0) {
        throw new InvalidArgumentException('Select a valid download category.');
    }

    if ($downloadKey === '') {
        do {
            $downloadKey = bin2hex(random_bytes(16));
        } while (downloadKeyExists($pdo, $downloadKey));
    }

    if (!preg_match('/^[a-z0-9-]{8,64}$/', $downloadKey)) {
        throw new InvalidArgumentException('The link key must contain 8–64 lowercase letters, numbers, or hyphens.');
    }

    if (downloadKeyExists($pdo, $downloadKey, $id)) {
        throw new InvalidArgumentException('That unique link is already assigned to another download.');
    }

    $upload = $_FILES['download_file'] ?? null;
    $hasUpload = is_array($upload) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $requestedFilename = trim((string) ($_POST['filename'] ?? ''));
    $filename = $requestedFilename !== ''
        ? downloadSafeFilename($requestedFilename)
        : ($hasUpload ? downloadSafeFilename((string) $upload['name']) : (string) ($record['filename'] ?? ''));

    if ($filename === '') {
        throw new InvalidArgumentException('A download file is required.');
    }

    if (filenameExists($pdo, $filename, $id)) {
        throw new InvalidArgumentException('That filename is already in use.');
    }

    $storageDirectory = downloadStorageDirectory();

    if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0750, true) && !is_dir($storageDirectory)) {
        throw new RuntimeException('The download storage directory is unavailable.');
    }

    $oldFilename = (string) ($record['filename'] ?? '');
    $oldPath = $oldFilename !== '' ? $storageDirectory . '/' . basename($oldFilename) : '';
    $newPath = $storageDirectory . '/' . $filename;
    $stagedPath = '';
    $renamedExisting = false;
    $backupPath = '';
    $finalizedUpload = false;

    if ($hasUpload) {
        $uploadError = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($uploadError !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('The file upload did not complete successfully.');
        }

        if ((int) ($upload['size'] ?? 0) <= 0 || (int) $upload['size'] > 25 * 1024 * 1024) {
            throw new InvalidArgumentException('The uploaded file must be no larger than 25 MB.');
        }

        $uploadedExtension = strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));
        $requestedExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($uploadedExtension !== $requestedExtension) {
            throw new InvalidArgumentException('The filename extension must match the uploaded file.');
        }

        if (!is_uploaded_file((string) $upload['tmp_name'])) {
            throw new InvalidArgumentException('The uploaded file could not be verified.');
        }

        $stagedPath = $storageDirectory . '/.upload-' . bin2hex(random_bytes(12));

        if (!move_uploaded_file((string) $upload['tmp_name'], $stagedPath)) {
            throw new RuntimeException('The uploaded file could not be stored.');
        }
    } elseif ($id === 0) {
        throw new InvalidArgumentException('A download file is required.');
    } elseif ($filename !== $oldFilename) {
        if ($oldPath === '' || !is_file($oldPath)) {
            throw new InvalidArgumentException('Upload a replacement file before changing this missing filename.');
        }

        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== strtolower(pathinfo($oldFilename, PATHINFO_EXTENSION))) {
            throw new InvalidArgumentException('Upload a replacement file to change the file type.');
        }

        if (!rename($oldPath, $newPath)) {
            throw new RuntimeException('The existing file could not be renamed.');
        }
        $renamedExisting = true;
    }

    if ($viewable === 1 && !$hasUpload && !is_file($newPath)) {
        throw new InvalidArgumentException('A file must be uploaded before this download can be viewable.');
    }

    try {
        $pdo->beginTransaction();

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE downloads SET download_category_id = :download_category_id,
                title = :title, description = :description,
                filename = :filename, download_key = :download_key, viewable = :viewable
                WHERE id = :id");
            $stmt->execute([
                ':download_category_id' => $downloadCategoryId,
                ':title' => $title, ':description' => $description, ':filename' => $filename,
                ':download_key' => $downloadKey, ':viewable' => $viewable,
                ':id' => $id
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO downloads
                (download_category_id, title, description, filename, download_key, viewable)
                VALUES (:download_category_id, :title, :description, :filename, :download_key, :viewable)");
            $stmt->execute([
                ':download_category_id' => $downloadCategoryId,
                ':title' => $title, ':description' => $description, ':filename' => $filename,
                ':download_key' => $downloadKey, ':viewable' => $viewable
            ]);
            $id = (int) $pdo->lastInsertId();
        }

        if ($stagedPath !== '') {
            if (is_file($newPath)) {
                $backupPath = $storageDirectory . '/.backup-' . bin2hex(random_bytes(12));

                if (!rename($newPath, $backupPath)) {
                    throw new RuntimeException('The current file could not be prepared for replacement.');
                }
            }

            if (!rename($stagedPath, $newPath)) {
                if ($backupPath !== '' && is_file($backupPath)) {
                    rename($backupPath, $newPath);
                }
                throw new RuntimeException('The uploaded file could not be finalized.');
            }
            $finalizedUpload = true;
        }

        $pdo->commit();

        if ($backupPath !== '' && is_file($backupPath)) {
            unlink($backupPath);
        }

        if ($hasUpload && $oldPath !== '' && $oldPath !== $newPath && is_file($oldPath)) {
            unlink($oldPath);
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($stagedPath !== '' && is_file($stagedPath)) {
            unlink($stagedPath);
        }
        if ($finalizedUpload && is_file($newPath)) {
            unlink($newPath);
        }
        if ($backupPath !== '' && is_file($backupPath)) {
            rename($backupPath, $newPath);
        }
        if ($renamedExisting && is_file($newPath)) {
            rename($newPath, $oldPath);
        }
        throw $e;
    }

    sendDownloadJson([
        'success' => true,
        'id' => $id,
        'message' => $record ? 'Download updated.' : 'Download added.'
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $status = $e instanceof InvalidArgumentException ? 422 : 500;
    error_log('Download management failed: ' . $e->getMessage());
    sendDownloadJson(['success' => false, 'message' => $status === 500 ? 'The download could not be saved.' : $e->getMessage()], $status);
}
