<?php
declare(strict_types=1);

/**
 * Data helpers for groups, topics, and milestones.
 * Endpoints use these so the rules live in one place.
 */

require_once __DIR__ . '/auth.php';

/** A topic can only be edited or deleted while it is in one of these states. */
const TOPIC_EDITABLE_STATUSES = ['Proposed', 'For Revision'];

const TOPIC_TITLE_MIN = 5;
const TOPIC_TITLE_MAX = 200;
const TOPIC_DESC_MIN  = 20;
const TOPIC_DESC_MAX  = 2000;

/** The student's group (id, name, adviser, leader flag), or null if not in a group. */
function student_membership(int $userId): ?array
{
    $stmt = db()->prepare(
        'SELECT gm.group_id, gm.is_leader, g.group_name, g.adviser_id
           FROM group_members gm
           JOIN capstone_groups g ON g.id = gm.group_id
          WHERE gm.user_id = ?
          LIMIT 1'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    return [
        'group_id'   => (int) $row['group_id'],
        'group_name' => $row['group_name'],
        'adviser_id' => (int) $row['adviser_id'],
        'is_leader'  => (bool) $row['is_leader'],
    ];
}

/**
 * Use at the top of student-only endpoints that work on the student's group.
 * Returns [user, membership] or stops with a JSON error.
 */
function require_student_group(): array
{
    $user = require_role_api('student');
    $membership = student_membership($user['id']);

    if ($membership === null) {
        json_response(['ok' => false, 'message' => 'You are not in a group yet. Ask your adviser to add you.'], 403);
    }

    return [$user, $membership];
}

function find_user_basic(int $userId): ?array
{
    $stmt = db()->prepare('SELECT id, full_name, email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function group_members_list(int $groupId): array
{
    $stmt = db()->prepare(
        'SELECT u.id, u.full_name, u.email, gm.is_leader
           FROM group_members gm
           JOIN users u ON u.id = gm.user_id
          WHERE gm.group_id = ?
          ORDER BY gm.is_leader DESC, u.full_name'
    );
    $stmt->execute([$groupId]);

    return array_map(static fn (array $m): array => [
        'id'        => (int) $m['id'],
        'full_name' => $m['full_name'],
        'email'     => $m['email'],
        'is_leader' => (bool) $m['is_leader'],
    ], $stmt->fetchAll());
}

/** All topics of a group, newest first. */
function group_topic_rows(int $groupId): array
{
    $stmt = db()->prepare(
        'SELECT id, title, description, status, adviser_remarks, submitted_at, updated_at
           FROM topics
          WHERE group_id = ?
          ORDER BY id DESC'
    );
    $stmt->execute([$groupId]);
    return $stmt->fetchAll();
}

/** Shapes a topic row for the API and says what the viewer may do with it. */
function topic_view(array $topic, bool $isLeader): array
{
    $editable = in_array($topic['status'], TOPIC_EDITABLE_STATUSES, true);

    return [
        'id'              => (int) $topic['id'],
        'title'           => $topic['title'],
        'description'     => $topic['description'],
        'status'          => $topic['status'],
        'adviser_remarks' => $topic['adviser_remarks'],
        'submitted_at'    => $topic['submitted_at'],
        'updated_at'      => $topic['updated_at'],
        'can_edit'        => $editable,
        'can_delete'      => $editable && $isLeader,
    ];
}

/** Milestones of a group with due dates, in deadline order. */
function group_milestone_list(int $groupId): array
{
    $stmt = db()->prepare(
        'SELECT gm.id, t.name, t.due_date, gm.status
           FROM group_milestones gm
           JOIN milestone_templates t ON t.id = gm.template_id
          WHERE gm.group_id = ?
          ORDER BY t.due_date, gm.id'
    );
    $stmt->execute([$groupId]);
    $today = date('Y-m-d');

    return array_map(static fn (array $m): array => [
        'id'         => (int) $m['id'],
        'name'       => $m['name'],
        'due_date'   => $m['due_date'],
        'status'     => $m['status'],
        'is_overdue' => $m['status'] !== 'Completed' && $m['due_date'] < $today,
    ], $stmt->fetchAll());
}

/**
 * Checks a topic form. Returns [title, description, errors].
 * errors is a map of field name to message (empty when everything is fine).
 */
function validate_topic_input(array $input): array
{
    $title = trim((string) ($input['title'] ?? ''));
    $title = preg_replace('/\s+/u', ' ', $title) ?? $title;
    $description = trim((string) ($input['description'] ?? ''));

    $errors = [];

    $titleLength = text_length($title);
    if ($titleLength < TOPIC_TITLE_MIN || $titleLength > TOPIC_TITLE_MAX) {
        $errors['title'] = 'Use ' . TOPIC_TITLE_MIN . ' to ' . TOPIC_TITLE_MAX . ' characters for the title.';
    }

    $descLength = text_length($description);
    if ($descLength < TOPIC_DESC_MIN || $descLength > TOPIC_DESC_MAX) {
        $errors['description'] = 'Use ' . TOPIC_DESC_MIN . ' to ' . TOPIC_DESC_MAX . ' characters for the description.';
    }

    return [$title, $description, $errors];
}

/** Adds an in-system notification for a user. */
function add_notification(int $userId, string $message): void
{
    $stmt = db()->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
    $stmt->execute([$userId, mb_strimwidth_safe($message, 255)]);
}

/** Cuts text to a maximum length (works even without the mbstring extension). */
function mb_strimwidth_safe(string $text, int $max): string
{
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $max, '...');
    }
    return strlen($text) > $max ? substr($text, 0, $max - 3) . '...' : $text;
}

/* ------------------------------------------------------------
 * Adviser side
 * ---------------------------------------------------------- */

/** What an adviser can do with a topic, by its current status. */
const TOPIC_TRANSITIONS = [
    'Proposed'    => ['approve' => 'Approved', 'revise' => 'For Revision', 'reject' => 'Rejected'],
    'Approved'    => ['start' => 'In Progress'],
    'In Progress' => ['defend' => 'Defended'],
];

/** These decisions must come with remarks so the group knows why. */
const REMARKS_REQUIRED_ACTIONS = ['revise', 'reject'];

const GROUP_NAME_MIN = 2;
const GROUP_NAME_MAX = 100;
const COMMENT_MAX    = 1000;
const REMARKS_MAX    = 2000;

/** The group, only if it belongs to this adviser. */
function find_adviser_group(int $groupId, int $adviserId): ?array
{
    $stmt = db()->prepare('SELECT id, group_name, adviser_id FROM capstone_groups WHERE id = ? AND adviser_id = ? LIMIT 1');
    $stmt->execute([$groupId, $adviserId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Cleans a group name. Returns [name, error or null]. */
function validate_group_name(string $raw): array
{
    $name = trim(preg_replace('/\s+/u', ' ', $raw) ?? $raw);
    $length = text_length($name);

    if ($length < GROUP_NAME_MIN || $length > GROUP_NAME_MAX) {
        return [$name, 'Use ' . GROUP_NAME_MIN . ' to ' . GROUP_NAME_MAX . ' characters for the group name.'];
    }

    return [$name, null];
}

/** True if the adviser already has another group with this name. */
function adviser_has_group_named(int $adviserId, string $name, int $exceptGroupId = 0): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM capstone_groups WHERE adviser_id = ? AND LOWER(group_name) = LOWER(?) AND id <> ?');
    $stmt->execute([$adviserId, $name, $exceptGroupId]);
    return (int) $stmt->fetchColumn() > 0;
}

/** Students who are not in any group yet. */
function available_students(): array
{
    $rows = db()->query(
        "SELECT u.id, u.full_name, u.email
           FROM users u
           LEFT JOIN group_members gm ON gm.user_id = u.id
          WHERE u.role = 'student' AND gm.id IS NULL
          ORDER BY u.full_name"
    )->fetchAll();

    return array_map(static fn (array $r): array => [
        'id' => (int) $r['id'], 'full_name' => $r['full_name'], 'email' => $r['email'],
    ], $rows);
}

/** Sends the same notification to every student in a group. */
function notify_group_members(int $groupId, string $message, ?int $exceptUserId = null): void
{
    foreach (group_members_list($groupId) as $member) {
        if ($member['id'] !== $exceptUserId) {
            add_notification($member['id'], $message);
        }
    }
}

/** Comments on topics, grouped by topic id. */
function topic_comments_map(array $topicIds): array
{
    if ($topicIds === []) {
        return [];
    }

    $marks = implode(',', array_fill(0, count($topicIds), '?'));
    $stmt = db()->prepare(
        "SELECT c.id, c.target_id, c.comment_text, c.created_at, u.full_name, u.role
           FROM comments c
           JOIN users u ON u.id = c.user_id
          WHERE c.target_type = 'topic' AND c.target_id IN ($marks)
          ORDER BY c.id"
    );
    $stmt->execute(array_values($topicIds));

    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[(int) $row['target_id']][] = comment_view($row);
    }
    return $map;
}

function comment_view(array $row): array
{
    return [
        'id'         => (int) $row['id'],
        'author'     => $row['full_name'],
        'role'       => $row['role'],
        'text'       => $row['comment_text'],
        'created_at' => $row['created_at'],
    ];
}

/** Every topic in the adviser's groups, ones needing a decision first. */
function adviser_topic_rows(int $adviserId): array
{
    $stmt = db()->prepare(
        "SELECT t.id, t.group_id, g.group_name, t.title, t.description, t.status,
                t.adviser_remarks, t.submitted_at, t.updated_at
           FROM topics t
           JOIN capstone_groups g ON g.id = t.group_id
          WHERE g.adviser_id = ?
          ORDER BY FIELD(t.status, 'Proposed', 'For Revision', 'Approved', 'In Progress', 'Defended', 'Rejected'),
                   t.updated_at DESC, t.id DESC"
    );
    $stmt->execute([$adviserId]);
    return $stmt->fetchAll();
}

function adviser_topic_view(array $row, array $comments): array
{
    return [
        'id'              => (int) $row['id'],
        'group_id'        => (int) $row['group_id'],
        'group_name'      => $row['group_name'],
        'title'           => $row['title'],
        'description'     => $row['description'],
        'status'          => $row['status'],
        'adviser_remarks' => $row['adviser_remarks'],
        'submitted_at'    => $row['submitted_at'],
        'updated_at'      => $row['updated_at'],
        'actions'         => array_keys(TOPIC_TRANSITIONS[$row['status']] ?? []),
        'comments'        => $comments,
    ];
}
