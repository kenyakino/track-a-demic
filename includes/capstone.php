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
    $stmt = db()->prepare('SELECT id, full_name, title, email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    $row['display_name'] = display_name($row);
    return $row;
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

/* ------------------------------------------------------------
 * Similar-topic check
 * A topic is turned into a set of words. Two topics are compared with
 * the Jaccard score: shared words / all different words used by either one.
 * 0 means nothing in common, 1 means the same words.
 * ---------------------------------------------------------- */

const SIMILARITY_THRESHOLD = 0.30;

/** The distinct meaningful words of a text (lowercase, no common words). */
function topic_words(string $text): array
{
    static $skip = null;
    $skip ??= array_flip(explode(' ', 'the and for with from that this into using use used based study system systems web app application applications proposed develop development design implementation analysis project our their its are was were has have been of in on to a an by at as or is it be via'));

    $text = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
    preg_match_all('/[\p{L}\p{N}]{3,}/u', $text, $m);

    $words = [];
    foreach ($m[0] as $word) {
        // Treat simple plurals the same ("students" = "student")
        if (strlen($word) > 4 && substr($word, -1) === 's') {
            $word = substr($word, 0, -1);
        }
        if (!isset($skip[$word])) {
            $words[$word] = true;
        }
    }
    return array_keys($words);
}

/** Word-overlap (Jaccard) score between two texts, from 0 to 1. */
function topic_similarity(string $a, string $b): float
{
    $wa = topic_words($a);
    $wb = topic_words($b);
    if ($wa === [] || $wb === []) {
        return 0.0;
    }
    $shared = count(array_intersect($wa, $wb));
    $all = count(array_unique(array_merge($wa, $wb)));
    return $all > 0 ? $shared / $all : 0.0;
}

/**
 * The most similar topic of another group (rejected topics are ignored),
 * or null when nothing reaches the threshold.
 */
function find_similar_topic(string $title, string $description, int $groupId): ?array
{
    $stmt = db()->prepare(
        "SELECT t.id, t.title, t.description, g.group_name
           FROM topics t JOIN capstone_groups g ON g.id = t.group_id
          WHERE t.group_id <> ? AND t.status <> 'Rejected'"
    );
    $stmt->execute([$groupId]);

    $mine = $title . ' ' . $title . ' ' . $description;   // the title counts double
    $best = null;
    foreach ($stmt->fetchAll() as $row) {
        $score = topic_similarity($mine, $row['title'] . ' ' . $row['title'] . ' ' . $row['description']);
        if ($score >= SIMILARITY_THRESHOLD && ($best === null || $score > $best['score'])) {
            $best = ['id' => (int) $row['id'], 'title' => $row['title'], 'group_name' => $row['group_name'], 'score' => round($score, 2)];
        }
    }
    return $best;
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

/** "5 minutes ago" style text for a timestamp. */
function time_ago(string $timestamp): string
{
    $diff = max(0, time() - (int) strtotime($timestamp));
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' h ago';
    if ($diff < 604800) return floor($diff / 86400) . ' d ago';
    return date('M j, Y', strtotime($timestamp));
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

/** Comments on one kind of record (topic, milestone, file), grouped by record id. */
function target_comments_map(string $type, array $ids): array
{
    if ($ids === []) {
        return [];
    }

    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        "SELECT c.id, c.target_id, c.comment_text, c.created_at, u.full_name, u.title, u.role
           FROM comments c
           JOIN users u ON u.id = c.user_id
          WHERE c.target_type = ? AND c.target_id IN ($marks)
          ORDER BY c.id"
    );
    $stmt->execute(array_merge([$type], array_values($ids)));

    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[(int) $row['target_id']][] = comment_view($row);
    }
    return $map;
}

/** Comments on topics, grouped by topic id. */
function topic_comments_map(array $topicIds): array
{
    return target_comments_map('topic', $topicIds);
}

function comment_view(array $row): array
{
    return [
        'id'         => (int) $row['id'],
        'author'     => display_name($row),
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
    // Advisers see which topic looks similar (title and group only)
    $similar = in_array($row['status'], ['Proposed', 'For Revision'], true)
        ? find_similar_topic($row['title'], $row['description'], (int) $row['group_id'])
        : null;

    return [
        'similar'         => $similar === null ? null : ['title' => $similar['title'], 'group_name' => $similar['group_name'], 'percent' => (int) round($similar['score'] * 100)],
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

/* ------------------------------------------------------------
 * Milestones
 * ---------------------------------------------------------- */

const MILESTONE_NAME_MIN = 2;
const MILESTONE_NAME_MAX = 150;
const MILESTONE_TERM_MAX = 50;
const NOTE_MAX = 1000;

/** What a group can do with a milestone, by its current status. */
const MILESTONE_STUDENT_TRANSITIONS = [
    'Not Started'  => ['start' => 'In Progress'],
    'In Progress'  => ['submit' => 'Submitted'],
    'Needs Rework' => ['submit' => 'Submitted'],
    'Submitted'    => ['withdraw' => 'In Progress'],
];

/** What the adviser can do with a milestone, by its current status. */
const MILESTONE_ADVISER_TRANSITIONS = [
    'Submitted' => ['complete' => 'Completed', 'rework' => 'Needs Rework'],
    'Completed' => ['reopen' => 'In Progress'],
];

/** True for a real calendar date written as YYYY-MM-DD. */
function is_valid_date(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

/** Checks a milestone form. Returns [name, due_date, term, errors]. */
function validate_milestone_input(array $input): array
{
    $name = trim(preg_replace('/\s+/u', ' ', (string) ($input['name'] ?? '')) ?? '');
    $due  = trim((string) ($input['due_date'] ?? ''));
    $term = trim((string) ($input['term'] ?? ''));

    $errors = [];

    $nameLength = text_length($name);
    if ($nameLength < MILESTONE_NAME_MIN || $nameLength > MILESTONE_NAME_MAX) {
        $errors['name'] = 'Use ' . MILESTONE_NAME_MIN . ' to ' . MILESTONE_NAME_MAX . ' characters for the name.';
    }

    if (!is_valid_date($due)) {
        $errors['due_date'] = 'Choose a valid due date.';
    }

    if (text_length($term) > MILESTONE_TERM_MAX) {
        $errors['term'] = 'Use ' . MILESTONE_TERM_MAX . ' characters or fewer for the term.';
    }

    return [$name, $due, $term, $errors];
}

/** Ids of the adviser's groups. */
function adviser_group_ids(int $adviserId): array
{
    $stmt = db()->prepare('SELECT id FROM capstone_groups WHERE adviser_id = ?');
    $stmt->execute([$adviserId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** The milestone, only if it belongs to this adviser. */
function find_adviser_milestone(int $milestoneId, int $adviserId): ?array
{
    $stmt = db()->prepare('SELECT id, adviser_id, name, due_date, term FROM milestone_templates WHERE id = ? AND adviser_id = ? LIMIT 1');
    $stmt->execute([$milestoneId, $adviserId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Gives a new group the adviser's upcoming milestones. */
function attach_upcoming_milestones(int $groupId, int $adviserId): void
{
    $stmt = db()->prepare('SELECT id FROM milestone_templates WHERE adviser_id = ? AND due_date >= ?');
    $stmt->execute([$adviserId, date('Y-m-d')]);

    $insert = db()->prepare('INSERT INTO group_milestones (group_id, template_id) VALUES (?, ?)');
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $templateId) {
        $insert->execute([$groupId, (int) $templateId]);
    }
}

/** Shapes one group's progress on a milestone for the API. */
function milestone_state_view(array $row, array $transitions, array $comments): array
{
    return [
        'id'           => (int) $row['id'],
        'name'         => $row['name'],
        'due_date'     => $row['due_date'],
        'term'         => $row['term'],
        'status'       => $row['status'],
        'completed_at' => $row['completed_at'],
        'is_overdue'   => $row['status'] !== 'Completed' && $row['due_date'] < date('Y-m-d'),
        'actions'      => array_keys($transitions[$row['status']] ?? []),
        'comments'     => $comments,
    ];
}

/* ------------------------------------------------------------
 * Meetings
 * ---------------------------------------------------------- */

const MEETING_NOTES_MIN   = 10;
const MEETING_NOTES_MAX   = 2000;
const MEETING_ACTIONS_MAX = 1000;

/** Checks a meeting form. Returns [date, notes, action_items, errors]. */
function validate_meeting_input(array $input): array
{
    $date    = trim((string) ($input['meeting_date'] ?? ''));
    $notes   = trim((string) ($input['notes'] ?? ''));
    $actions = trim((string) ($input['action_items'] ?? ''));

    $errors = [];

    if (!is_valid_date($date)) {
        $errors['meeting_date'] = 'Choose a valid date.';
    } elseif ($date > date('Y-m-d')) {
        $errors['meeting_date'] = 'The meeting date cannot be in the future.';
    }

    $notesLength = text_length($notes);
    if ($notesLength < MEETING_NOTES_MIN || $notesLength > MEETING_NOTES_MAX) {
        $errors['notes'] = 'Use ' . MEETING_NOTES_MIN . ' to ' . MEETING_NOTES_MAX . ' characters for the notes.';
    }

    if (text_length($actions) > MEETING_ACTIONS_MAX) {
        $errors['action_items'] = 'Use ' . MEETING_ACTIONS_MAX . ' characters or fewer.';
    }

    return [$date, $notes, $actions, $errors];
}

function meeting_view(array $row): array
{
    $confirmed = (bool) $row['confirmed_by_adviser'];

    return [
        'id'           => (int) $row['id'],
        'meeting_date' => $row['meeting_date'],
        'notes'        => $row['notes'],
        'action_items' => $row['action_items'],
        'confirmed'    => $confirmed,
        'can_edit'     => !$confirmed,
        'created_at'   => $row['created_at'],
    ];
}

/* ------------------------------------------------------------
 * References
 * ---------------------------------------------------------- */

const REFERENCE_TITLE_MIN  = 3;
const REFERENCE_TITLE_MAX  = 255;
const REFERENCE_AUTHOR_MIN = 2;
const REFERENCE_AUTHOR_MAX = 150;
const REFERENCE_LINK_MAX   = 500;

/** Checks a reference form. Returns [title, author, year or null, link or null, errors]. */
function validate_reference_input(array $input): array
{
    $title  = trim(preg_replace('/\s+/u', ' ', (string) ($input['title'] ?? '')) ?? '');
    $author = trim(preg_replace('/\s+/u', ' ', (string) ($input['author'] ?? '')) ?? '');
    $yearText = trim((string) ($input['pub_year'] ?? ''));
    $link   = trim((string) ($input['link'] ?? ''));

    $errors = [];

    $titleLength = text_length($title);
    if ($titleLength < REFERENCE_TITLE_MIN || $titleLength > REFERENCE_TITLE_MAX) {
        $errors['title'] = 'Use ' . REFERENCE_TITLE_MIN . ' to ' . REFERENCE_TITLE_MAX . ' characters for the title.';
    }

    $authorLength = text_length($author);
    if ($authorLength < REFERENCE_AUTHOR_MIN || $authorLength > REFERENCE_AUTHOR_MAX) {
        $errors['author'] = 'Use ' . REFERENCE_AUTHOR_MIN . ' to ' . REFERENCE_AUTHOR_MAX . ' characters for the author.';
    }

    $year = null;
    if ($yearText !== '') {
        $maxYear = (int) date('Y') + 1;
        if (!ctype_digit($yearText) || (int) $yearText < 1900 || (int) $yearText > $maxYear) {
            $errors['pub_year'] = 'Enter a year between 1900 and ' . $maxYear . ', or leave it empty.';
        } else {
            $year = (int) $yearText;
        }
    }

    if ($link === '') {
        $link = null;
    } else {
        $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));
        if (text_length($link) > REFERENCE_LINK_MAX || !filter_var($link, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
            $errors['link'] = 'Enter a full web address that starts with http:// or https://, or leave it empty.';
        }
    }

    return [$title, $author, $year, $link, $errors];
}

function reference_view(array $row): array
{
    return [
        'id'         => (int) $row['id'],
        'title'      => $row['title'],
        'author'     => $row['author'],
        'pub_year'   => $row['pub_year'] !== null ? (int) $row['pub_year'] : null,
        'link'       => $row['link'],
        'created_at' => $row['created_at'],
    ];
}

function group_reference_rows(int $groupId): array
{
    $stmt = db()->prepare('SELECT id, title, author, pub_year, link, created_at FROM references_list WHERE group_id = ? ORDER BY id DESC');
    $stmt->execute([$groupId]);
    return array_map('reference_view', $stmt->fetchAll());
}

/* ------------------------------------------------------------
 * Files
 * ---------------------------------------------------------- */

/** Upload limits and folder from config.php, with safe defaults. */
function upload_settings(): array
{
    $config = app_config()['upload'] ?? [];
    $dir = (string) ($config['dir'] ?? (__DIR__ . '/../uploads'));

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return [
        'max_size'    => (int) ($config['max_size'] ?? 5 * 1024 * 1024),
        'allowed_ext' => array_map('strtolower', (array) ($config['allowed_ext'] ?? ['pdf', 'docx'])),
        'dir'         => realpath($dir) ?: $dir,
    ];
}

/** Turns a php.ini size such as "8M" or "512K" into bytes. */
function ini_bytes(string $value): int
{
    $value = trim($value);
    $number = (int) $value;

    switch (strtolower(substr($value, -1))) {
        case 'g': return $number * 1024 * 1024 * 1024;
        case 'm': return $number * 1024 * 1024;
        case 'k': return $number * 1024;
        default:  return $number;
    }
}

/** File types the system knows how to check, by extension. */
const UPLOAD_TYPES = [
    'pdf'  => 'application/pdf',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];

/** Keeps the readable part of an uploaded file's name and drops anything risky. */
function clean_upload_name(string $name): string
{
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[\x00-\x1F\x7F"<>|?*:]+/u', '', $name) ?? '';
    $name = trim($name, " .\t\n\r");

    if (text_length($name) > 150) {
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $name = (function_exists('mb_substr') ? mb_substr($name, 0, 140) : substr($name, 0, 140)) . ($ext !== '' ? '.' . $ext : '');
    }

    return $name !== '' ? $name : 'file';
}

/**
 * Looks inside the uploaded file to confirm it really is what its extension
 * says. Returns the content type, or null if the content does not match.
 */
function detect_upload_type(string $path, string $extension): ?string
{
    $head = (string) file_get_contents($path, false, null, 0, 8);

    if ($extension === 'pdf') {
        return str_starts_with($head, '%PDF-') ? UPLOAD_TYPES['pdf'] : null;
    }

    if ($extension === 'docx') {
        if (substr($head, 0, 4) !== "PK\x03\x04") {
            return null;
        }
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($path) !== true) {
                return null;
            }
            $isWord = $zip->locateName('word/document.xml') !== false;
            $zip->close();
            if (!$isWord) {
                return null;
            }
        } else {
            // Without the zip extension, look for the Word document entry in the file's own index
            $content = (string) file_get_contents($path);
            if (strpos($content, 'word/document.xml') === false || strpos($content, '[Content_Types].xml') === false) {
                return null;
            }
        }
        return UPLOAD_TYPES['docx'];
    }

    return null;
}

/** Files of a group with who uploaded them and where each one came from. */
function group_file_rows(int $groupId): array
{
    $stmt = db()->prepare(
        'SELECT f.id, f.file_name, f.file_size, f.uploaded_at, f.uploaded_by, u.full_name AS uploader,
                f.source_type, f.milestone_id, t.name AS milestone_name, tp.title AS topic_title
           FROM files f
           JOIN users u ON u.id = f.uploaded_by
           LEFT JOIN group_milestones gm ON gm.id = f.milestone_id
           LEFT JOIN milestone_templates t ON t.id = gm.template_id
           LEFT JOIN topics tp ON tp.id = f.topic_id
          WHERE f.group_id = ?
          ORDER BY f.id DESC'
    );
    $stmt->execute([$groupId]);
    return $stmt->fetchAll();
}

/** Where a file came from, in words. */
function file_source_label(array $row): string
{
    switch ($row['source_type']) {
        case 'topic':
            return 'Topic proposal' . ($row['topic_title'] ? ': ' . $row['topic_title'] : '');
        case 'milestone':
            return 'Milestone' . ($row['milestone_name'] ? ': ' . $row['milestone_name'] : '');
        default:
            return 'Direct upload';
    }
}

/**
 * Shapes a file for the API. Only files uploaded on the Files page ("direct")
 * can be deleted by hand; the others are replaced when the group resubmits.
 */
function file_view(array $row, int $viewerId, bool $viewerIsLeader): array
{
    $isDirect = $row['source_type'] === 'direct';

    return [
        'id'          => (int) $row['id'],
        'name'        => $row['file_name'],
        'size'        => (int) $row['file_size'],
        'uploaded_at' => $row['uploaded_at'],
        'uploader'    => $row['uploader'],
        'source'      => ['type' => $row['source_type'], 'label' => file_source_label($row)],
        'can_delete'  => $isDirect && ($viewerIsLeader || (int) $row['uploaded_by'] === $viewerId),
    ];
}

/* ------------------------------------------------------------
 * Receiving uploads (used by every endpoint that takes a file)
 * ---------------------------------------------------------- */

/**
 * Checks one uploaded file from $_FILES.
 * Returns ['ok' => true, 'skipped' => false, 'name', 'ext', 'size', 'tmp'] for a good file,
 * ['ok' => true, 'skipped' => true] when no file was sent and none is required,
 * or ['ok' => false, 'message', 'status'] with the reason.
 */
function check_uploaded_file(?array $file, bool $required = true): array
{
    $settings = upload_settings();
    $limitText = round($settings['max_size'] / 1048576, 1) . ' MB';
    $allowedText = strtoupper(implode(' or ', $settings['allowed_ext']));
    $fail = static fn (string $message, int $status = 422): array => ['ok' => false, 'message' => $message, 'status' => $status];

    // When a request is bigger than the server allows, PHP drops the whole upload
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($_FILES === [] && $contentLength > ini_bytes((string) ini_get('post_max_size'))) {
        return $fail('The file is too large. The limit is ' . $limitText . '.', 413);
    }

    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $required ? $fail('Choose a file to upload.') : ['ok' => true, 'skipped' => true];
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return $fail('The file is too large. The limit is ' . $limitText . '.', 413);
        default:
            error_log('Upload error code ' . $file['error']);
            return $fail('The upload failed. Try again.', 500);
    }

    $size = (int) $file['size'];
    if ($size <= 0) {
        return $fail('The file is empty.');
    }
    if ($size > $settings['max_size']) {
        return $fail('The file is too large. The limit is ' . $limitText . '.', 413);
    }

    $name = clean_upload_name((string) $file['name']);
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if (!in_array($extension, $settings['allowed_ext'], true) || !isset(UPLOAD_TYPES[$extension])) {
        return $fail('Only ' . $allowedText . ' files are allowed.');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return $fail('Invalid upload.', 400);
    }

    // Look inside the file: its content must match what the extension claims
    if (detect_upload_type($file['tmp_name'], $extension) === null) {
        return $fail('This file does not look like a real ' . strtoupper($extension) . ' file.');
    }

    return ['ok' => true, 'skipped' => false, 'name' => $name, 'ext' => $extension, 'size' => $size, 'tmp' => $file['tmp_name']];
}

/** Moves a checked upload into uploads/ under a random name. Returns that name, or null. */
function store_checked_upload(array $checked): ?string
{
    $settings = upload_settings();
    $stored = bin2hex(random_bytes(16)) . '.' . $checked['ext'];

    if (!move_uploaded_file($checked['tmp'], $settings['dir'] . DIRECTORY_SEPARATOR . $stored)) {
        error_log('Could not move the uploaded file into ' . $settings['dir']);
        return null;
    }

    return $stored;
}

/** Removes stored files from the disk (the paths as saved in the files table). */
function unlink_stored_files(array $paths): void
{
    $dir = upload_settings()['dir'];

    foreach ($paths as $path) {
        $full = $dir . DIRECTORY_SEPARATOR . basename((string) $path);
        if (is_file($full)) {
            @unlink($full);
        }
    }
}

/** Saves a files row. Returns its id. */
function record_file(int $groupId, int $uploaderId, string $name, string $storedName, int $size, string $sourceType, ?int $topicId = null, ?int $milestoneId = null): int
{
    $insert = db()->prepare(
        'INSERT INTO files (group_id, source_type, topic_id, milestone_id, file_name, file_path, file_size, uploaded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([$groupId, $sourceType, $topicId, $milestoneId, $name, 'uploads/' . $storedName, $size, $uploaderId]);

    return (int) db()->lastInsertId();
}

/** The files attached to one topic ('topic') or one milestone submission ('milestone'). */
function source_file_rows(string $sourceType, int $ownerId): array
{
    $column = $sourceType === 'topic' ? 'topic_id' : 'milestone_id';
    $stmt = db()->prepare("SELECT id, file_path FROM files WHERE source_type = ? AND $column = ?");
    $stmt->execute([$sourceType, $ownerId]);
    return $stmt->fetchAll();
}

/** Deletes the given files rows (call unlink_stored_files for the disk copies). */
function delete_file_rows(array $rows): void
{
    $delete = db()->prepare('DELETE FROM files WHERE id = ?');
    foreach ($rows as $row) {
        $delete->execute([(int) $row['id']]);
    }
}

/** The newest file of each topic or milestone submission: owner id => [id, name, size]. */
function source_files_map(string $sourceType, array $ownerIds): array
{
    if ($ownerIds === []) {
        return [];
    }

    $column = $sourceType === 'topic' ? 'topic_id' : 'milestone_id';
    $marks = implode(',', array_fill(0, count($ownerIds), '?'));
    $stmt = db()->prepare("SELECT id, file_name, file_size, $column AS owner_id FROM files WHERE source_type = ? AND $column IN ($marks) ORDER BY id");
    $stmt->execute(array_merge([$sourceType], array_values($ownerIds)));

    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[(int) $row['owner_id']] = ['id' => (int) $row['id'], 'name' => $row['file_name'], 'size' => (int) $row['file_size']];
    }
    return $map;
}
