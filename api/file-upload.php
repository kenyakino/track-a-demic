<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

start_secure_session();
require_post();
verify_csrf();

[$user, $membership] = require_student_group();
$settings = upload_settings();

$fail = static function (string $message, int $status = 422): void {
    json_response(['ok' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => ['file' => $message]], $status);
};

$limitText = round($settings['max_size'] / 1048576, 1) . ' MB';
$allowedText = strtoupper(implode(', ', $settings['allowed_ext']));

// When a request is bigger than the server allows, PHP drops the whole upload
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($_FILES === [] && $contentLength > ini_bytes((string) ini_get('post_max_size'))) {
    $fail('The file is too large. The limit is ' . $limitText . '.', 413);
}

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    $fail('Choose a file to upload.');
}

$file = $_FILES['file'];

switch ($file['error']) {
    case UPLOAD_ERR_OK:
        break;
    case UPLOAD_ERR_INI_SIZE:
    case UPLOAD_ERR_FORM_SIZE:
        $fail('The file is too large. The limit is ' . $limitText . '.', 413);
        break;
    case UPLOAD_ERR_NO_FILE:
        $fail('Choose a file to upload.');
        break;
    default:
        error_log('Upload error code ' . $file['error']);
        $fail('The upload failed. Try again.', 500);
}

if ((int) $file['size'] > $settings['max_size'] || (int) $file['size'] <= 0) {
    $fail((int) $file['size'] <= 0 ? 'The file is empty.' : 'The file is too large. The limit is ' . $limitText . '.', (int) $file['size'] <= 0 ? 422 : 413);
}

$originalName = clean_upload_name((string) $file['name']);
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (!in_array($extension, $settings['allowed_ext'], true) || !isset(UPLOAD_TYPES[$extension])) {
    $fail('Only ' . $allowedText . ' files are allowed.');
}

if (!is_uploaded_file($file['tmp_name'])) {
    json_response(['ok' => false, 'message' => 'Invalid upload.'], 400);
}

// Look inside the file: its content must match what the extension claims
if (detect_upload_type($file['tmp_name'], $extension) === null) {
    $fail('This file does not look like a real ' . strtoupper($extension) . ' file.');
}

// Optional: attach the file to one of the group's milestones
$milestoneId = (int) ($_POST['milestone_id'] ?? 0);
if ($milestoneId > 0) {
    $own = db()->prepare('SELECT id FROM group_milestones WHERE id = ? AND group_id = ? LIMIT 1');
    $own->execute([$milestoneId, $membership['group_id']]);
    if (!$own->fetch()) {
        $fail('Choose one of your group\'s milestones.');
    }
}

// Stored under a random name, so the original name can never reach the file system
$storedName = bin2hex(random_bytes(16)) . '.' . $extension;
if (!move_uploaded_file($file['tmp_name'], $settings['dir'] . DIRECTORY_SEPARATOR . $storedName)) {
    error_log('Could not move the uploaded file into ' . $settings['dir']);
    json_response(['ok' => false, 'message' => 'The server could not save the file. Try again.'], 500);
}

$insert = db()->prepare(
    'INSERT INTO files (group_id, milestone_id, file_name, file_path, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)'
);
$insert->execute([
    $membership['group_id'],
    $milestoneId > 0 ? $milestoneId : null,
    $originalName,
    'uploads/' . $storedName,
    (int) $file['size'],
    $user['id'],
]);

add_notification($membership['adviser_id'], $membership['group_name'] . ' uploaded ' . $originalName . '.');

json_response(['ok' => true, 'message' => 'File uploaded.'], 201);
