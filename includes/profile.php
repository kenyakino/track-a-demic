<?php
declare(strict_types=1);

/**
 * Profile helpers: name rules, the 30-day lock, and profile pictures.
 */

require_once __DIR__ . '/auth.php';

const PROFILE_LOCK_DAYS = 30;
const AVATAR_MAX_BYTES = 2 * 1024 * 1024;

/** Trims a name and collapses repeated spaces. */
function clean_person_name(string $value): string
{
    return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
}

/** Returns an error message, or null when the name is fine. */
function validate_person_name(string $name, string $label): ?string
{
    if ($name === '' || text_length($name) > 50 || !preg_match("/^\p{L}[\p{L}\p{M}.'\- ]*$/u", $name)) {
        return "Enter your {$label} (letters only, up to 50 characters).";
    }
    return null;
}

/** Same rules as registration for passwords. */
function validate_new_password(string $password): ?string
{
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Use at least 8 characters with a letter and a number.';
    }
    return strlen($password) > 72 ? 'Use 72 characters or fewer.' : null;
}

/**
 * Whether the profile is locked. A user who saved a change cannot change
 * the profile again for PROFILE_LOCK_DAYS days.
 */
function profile_lock_status(?string $updatedAt): array
{
    if ($updatedAt === null || $updatedAt === '') {
        return ['locked' => false, 'updated_on' => null, 'unlocks_on' => null, 'days_left' => 0];
    }

    $updated = strtotime($updatedAt);
    $unlocks = strtotime('+' . PROFILE_LOCK_DAYS . ' days', $updated);
    $locked = $unlocks > time();

    return [
        'locked' => $locked,
        'updated_on' => date('M j, Y', $updated),
        'unlocks_on' => date('M j, Y', $unlocks),
        'days_left' => $locked ? (int) ceil(($unlocks - time()) / 86400) : 0,
    ];
}

function avatar_dir(): string
{
    $dir = rtrim(upload_dir_path(), '/\\') . DIRECTORY_SEPARATOR . 'avatars';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function upload_dir_path(): string
{
    return (string) (app_config()['upload']['dir'] ?? (__DIR__ . '/../uploads'));
}

/** Checks a picture upload. Returns [error|null, type|null] where type is jpg, png or webp. */
function check_avatar_upload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $tooBig = in_array($file['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
        return [$tooBig ? 'The picture is larger than 2 MB.' : 'The picture could not be uploaded. Try again.', null];
    }
    if ((int) $file['size'] > AVATAR_MAX_BYTES) {
        return ['The picture is larger than 2 MB.', null];
    }
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if ($info === false || !isset($types[$info[2]])) {
        return ['Use a JPG, PNG, or WebP picture.', null];
    }
    if ($info[0] > 6000 || $info[1] > 6000) {
        return ['The picture is too large in pixels (6000 px maximum).', null];
    }
    return [null, $types[$info[2]]];
}

/**
 * Saves the picture as a square JPEG (max 512 px). Re-encoding also removes
 * anything hidden in the file. Returns the stored file name.
 */
function store_avatar(string $tmpPath, string $type): string
{
    $name = bin2hex(random_bytes(12)) . '.jpg';
    $target = avatar_dir() . DIRECTORY_SEPARATOR . $name;

    $loader = ['jpg' => 'imagecreatefromjpeg', 'png' => 'imagecreatefrompng', 'webp' => 'imagecreatefromwebp'][$type];

    if (function_exists($loader) && function_exists('imagecreatetruecolor')) {
        $src = @$loader($tmpPath);
        if ($src !== false) {
            $w = imagesx($src);
            $h = imagesy($src);
            $side = min($w, $h);
            $size = min(512, $side);
            $dst = imagecreatetruecolor($size, $size);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $size, $size, $side, $side);
            imagejpeg($dst, $target, 88);
            imagedestroy($src);
            imagedestroy($dst);
            return $name;
        }
    }

    // GD is missing: keep the validated original
    $name = bin2hex(random_bytes(12)) . '.' . $type;
    move_uploaded_file($tmpPath, avatar_dir() . DIRECTORY_SEPARATOR . $name) || copy($tmpPath, avatar_dir() . DIRECTORY_SEPARATOR . $name);
    return $name;
}

function delete_avatar_file(?string $name): void
{
    if ($name) {
        $path = avatar_dir() . DIRECTORY_SEPARATOR . basename($name);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

/** A user's picture is visible to themselves, their adviser, and their group mates. */
function can_view_avatar(array $viewer, int $ownerId): bool
{
    if ($viewer['id'] === $ownerId) {
        return true;
    }
    if ($viewer['role'] === 'adviser') {
        $q = db()->prepare('SELECT 1 FROM group_members gm JOIN capstone_groups g ON g.id = gm.group_id WHERE gm.user_id = ? AND g.adviser_id = ? LIMIT 1');
        $q->execute([$ownerId, $viewer['id']]);
        return (bool) $q->fetchColumn();
    }
    $q = db()->prepare('SELECT 1 FROM group_members a JOIN group_members b ON a.group_id = b.group_id WHERE a.user_id = ? AND b.user_id = ? LIMIT 1');
    $q->execute([$viewer['id'], $ownerId]);
    if ($q->fetchColumn()) {
        return true;
    }
    // Students also see their own adviser's picture
    $q = db()->prepare('SELECT 1 FROM group_members gm JOIN capstone_groups g ON g.id = gm.group_id WHERE gm.user_id = ? AND g.adviser_id = ? LIMIT 1');
    $q->execute([$viewer['id'], $ownerId]);
    return (bool) $q->fetchColumn();
}
