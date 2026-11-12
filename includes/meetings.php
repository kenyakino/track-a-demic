<?php
declare(strict_types=1);

/**
 * Meeting requests and shared meeting logs.
 *
 * - A student group REQUESTS a meeting (date, time, purpose, optional link).
 *   The adviser accepts or rejects it. Advisers cannot propose meetings.
 * - Meeting LOGS (notes and action items) are shared: the group and the
 *   adviser can both write them and both can read them.
 */

require_once __DIR__ . '/capstone.php';

const REQUEST_PURPOSE_MIN = 5;
const REQUEST_PURPOSE_MAX = 255;
const REQUEST_NOTE_MAX = 500;
const DURATION_CHOICES = [15, 30, 45, 60, 90, 120, 180];

/** Combines a date (Y-m-d) and time (H:i) into "Y-m-d H:i:00", or null if invalid. */
function parse_date_time(string $date, string $time): ?string
{
    if (!is_valid_date($date) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
        return null;
    }
    return $date . ' ' . $time . ':00';
}

/** Checks a meeting request form. Returns [startsAt, duration, purpose, link, errors]. */
function validate_request_input(array $in): array
{
    $errors = [];
    $startsAt = parse_date_time(trim((string) ($in['date'] ?? '')), trim((string) ($in['time'] ?? '')));
    $duration = (int) ($in['duration'] ?? 60);
    $purpose = trim(preg_replace('/\s+/u', ' ', (string) ($in['purpose'] ?? '')) ?? '');
    $link = trim((string) ($in['link'] ?? ''));

    if ($startsAt === null) {
        $errors['date'] = 'Choose a valid date and time.';
    } elseif (strtotime($startsAt) <= time()) {
        $errors['date'] = 'Choose a date and time in the future.';
    }

    if (!in_array($duration, DURATION_CHOICES, true)) {
        $errors['duration'] = 'Choose how long the meeting should be.';
    }

    $length = text_length($purpose);
    if ($length < REQUEST_PURPOSE_MIN || $length > REQUEST_PURPOSE_MAX) {
        $errors['purpose'] = 'Say what the meeting is for (' . REQUEST_PURPOSE_MIN . ' to ' . REQUEST_PURPOSE_MAX . ' characters).';
    }

    if ($link !== '') {
        if (text_length($link) > 255 || !preg_match('#^https?://#i', $link) || !filter_var($link, FILTER_VALIDATE_URL)) {
            $errors['link'] = 'Enter a full link that starts with http:// or https://.';
        }
    }

    return [$startsAt, $duration, $purpose, $link !== '' ? $link : null, $errors];
}

/** Accepted meetings of an adviser that overlap the given time span. */
function overlapping_accepted(int $adviserId, string $startsAt, int $duration, int $ignoreId = 0): array
{
    $end = date('Y-m-d H:i:s', strtotime($startsAt) + $duration * 60);

    $stmt = db()->prepare(
        "SELECT r.id, r.starts_at, r.duration_minutes, g.group_name
           FROM meeting_requests r
           JOIN capstone_groups g ON g.id = r.group_id
          WHERE g.adviser_id = ? AND r.status = 'Accepted' AND r.id <> ?
            AND r.starts_at < ?
            AND DATE_ADD(r.starts_at, INTERVAL r.duration_minutes MINUTE) > ?"
    );
    $stmt->execute([$adviserId, $ignoreId, $end, $startsAt]);

    return array_map(static fn (array $r): array => [
        'group_name' => $r['group_name'],
        'when' => format_meeting_time($r['starts_at'], (int) $r['duration_minutes']),
    ], $stmt->fetchAll());
}

/** "Nov 20, 2026, 2:00 PM to 3:00 PM" */
function format_meeting_time(string $startsAt, int $duration): string
{
    $start = strtotime($startsAt);
    return date('M j, Y, g:i A', $start) . ' to ' . date('g:i A', $start + $duration * 60);
}

function request_view(array $r): array
{
    $isPast = strtotime($r['starts_at']) + (int) $r['duration_minutes'] * 60 < time();

    return [
        'id' => (int) $r['id'],
        'group_id' => (int) $r['group_id'],
        'group_name' => $r['group_name'] ?? null,
        'requested_by' => $r['requester'] ?? null,
        'starts_at' => $r['starts_at'],
        'date' => substr($r['starts_at'], 0, 10),
        'time' => substr($r['starts_at'], 11, 5),
        'duration' => (int) $r['duration_minutes'],
        'when' => format_meeting_time($r['starts_at'], (int) $r['duration_minutes']),
        'purpose' => $r['purpose'],
        'link' => $r['link'],
        'status' => $r['status'],
        'decision_note' => $r['decision_note'],
        'suggested' => $r['suggested_at'] ? date('M j, Y, g:i A', strtotime($r['suggested_at'])) : null,
        'is_past' => $isPast,
        'created_at' => $r['created_at'],
    ];
}

const REQUEST_SELECT =
    'SELECT r.*, g.group_name, u.full_name AS requester
       FROM meeting_requests r
       JOIN capstone_groups g ON g.id = r.group_id
       LEFT JOIN users u ON u.id = r.requested_by';

/** The request if it exists and the signed-in user may act on it (their group, or their adviser group). */
function find_request_for(array $user, int $requestId): ?array
{
    $stmt = db()->prepare(REQUEST_SELECT . ' WHERE r.id = ? LIMIT 1');
    $stmt->execute([$requestId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    if ($user['role'] === 'adviser') {
        return find_adviser_group((int) $row['group_id'], $user['id']) === null ? null : $row;
    }

    $membership = student_membership($user['id']);
    return $membership !== null && $membership['group_id'] === (int) $row['group_id'] ? $row : null;
}

/** A meeting log as sent to the browser, with what the signed-in user may do with it. */
function log_view(array $row, array $user): array
{
    $isAdviserLog = ($row['author_role'] ?? null) === 'adviser';
    $confirmed = (bool) $row['confirmed_by_adviser'];

    if ($user['role'] === 'adviser') {
        $canEdit = (int) $row['author_id'] === $user['id'];
    } else {
        // The group can edit the logs written by students until the adviser confirms them
        $canEdit = !$isAdviserLog && !$confirmed;
    }

    return [
        'id' => (int) $row['id'],
        'group_id' => (int) $row['group_id'],
        'group_name' => $row['group_name'],
        'meeting_date' => $row['meeting_date'],
        'notes' => $row['notes'],
        'action_items' => $row['action_items'],
        'confirmed' => $confirmed,
        'author_name' => $row['author_name'] ?? 'A group member',
        'author_role' => $row['author_role'] ?? 'student',
        'author_id' => $row['author_id'] !== null ? (int) $row['author_id'] : null,
        'request_id' => $row['request_id'] !== null ? (int) $row['request_id'] : null,
        'request_label' => $row['request_starts_at'] ? date('M j, Y, g:i A', strtotime($row['request_starts_at'])) . ': ' . $row['request_purpose'] : null,
        'can_edit' => $canEdit,
        'can_confirm' => $user['role'] === 'adviser' && !$isAdviserLog && !$confirmed,
        'can_reopen' => $user['role'] === 'adviser' && !$isAdviserLog && $confirmed,
        'created_at' => $row['created_at'],
    ];
}

const LOG_SELECT =
    "SELECT m.*, g.group_name, u.full_name AS author_name, u.role AS author_role,
            r.starts_at AS request_starts_at, r.purpose AS request_purpose
       FROM meetings m
       JOIN capstone_groups g ON g.id = m.group_id
       LEFT JOIN users u ON u.id = m.author_id
       LEFT JOIN meeting_requests r ON r.id = m.request_id";
