<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/capstone.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = require_role_api('student');
$membership = student_membership($user['id']);

// A student who has not been added to a group yet
if ($membership === null) {
    json_response([
        'ok' => true,
        'user' => $user,
        'group' => null,
        'topic' => null,
        'previous_topics' => [],
        'milestones' => [],
        'limits' => ['max_size' => upload_settings()['max_size'], 'allowed_ext' => upload_settings()['allowed_ext']],
    ]);
}

$groupId = $membership['group_id'];
$adviser = find_user_basic($membership['adviser_id']);

$topic = null;
$previous = [];
foreach (group_topic_rows($groupId) as $row) {
    $view = topic_view($row, $membership['is_leader']);
    if ($row['status'] === 'Rejected') {
        $previous[] = $view;
    } elseif ($topic === null) {
        $topic = $view;
    }
}

if ($topic !== null) {
    $topic['comments'] = topic_comments_map([$topic['id']])[$topic['id']] ?? [];
    $topic['file'] = source_files_map('topic', [$topic['id']])[$topic['id']] ?? null;
}

$settings = upload_settings();

json_response([
    'ok' => true,
    'user' => $user,
    'group' => [
        'id' => $groupId,
        'name' => $membership['group_name'],
        'is_leader' => $membership['is_leader'],
        'adviser' => $adviser ? ['full_name' => $adviser['full_name'], 'email' => $adviser['email']] : null,
        'members' => group_members_list($groupId),
    ],
    'topic' => $topic,
    'previous_topics' => $previous,
    'milestones' => group_milestone_list($groupId),
    'limits' => ['max_size' => $settings['max_size'], 'allowed_ext' => $settings['allowed_ext']],
]);
