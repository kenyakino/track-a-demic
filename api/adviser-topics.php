<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$adviser = require_role_api('adviser');

$rows = adviser_topic_rows($adviser['id']);
$comments = topic_comments_map(array_map(static fn (array $r): int => (int) $r['id'], $rows));

$files = source_files_map('topic', array_map(static fn (array $r): int => (int) $r['id'], $rows));

$topics = array_map(static function (array $row) use ($comments, $files): array {
    $view = adviser_topic_view($row, $comments[(int) $row['id']] ?? []);
    $view['file'] = $files[(int) $row['id']] ?? null;
    return $view;
}, $rows);

json_response(['ok' => true, 'user' => $adviser, 'topics' => $topics]);
