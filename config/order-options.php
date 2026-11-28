<?php
declare(strict_types=1);

// Library only: shared order options for api/orders/*. Never a public endpoint.
if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

/** Single source of truth for campus pickup locations (stored in orders.delivery_location). */
function tidebites_pickup_locations(): array
{
    return ['Campus Main Gate', 'Student Center', 'Library Entrance'];
}

const TIDEBITES_TIMEZONE = 'Asia/Taipei';

/** Business hours as [first slot hour, last slot hour], by ISO weekday (1 = Monday ... 7 = Sunday). */
function tidebites_business_hours(int $isoWeekday): array
{
    if ($isoWeekday <= 5) return [11, 21]; // Monday - Friday 11:00 - 21:00
    if ($isoWeekday === 6) return [12, 21]; // Saturday 12:00 - 21:00
    return [12, 20];                        // Sunday 12:00 - 20:00
}

/**
 * Hourly pickup slots that may be booked right now (Asia/Taipei):
 * - before 10:00: any slot today;
 * - 10:00-19:59: today, no earlier than now + 1 hour rounded up to the next whole hour;
 * - 20:00-20:59: not taking orders;
 * - from 21:00: any slot tomorrow.
 * Slots run hourly from opening to closing time inclusive.
 */
function tidebites_pickup_schedule(?DateTimeImmutable $now = null): array
{
    $zone = new DateTimeZone(TIDEBITES_TIMEZONE);
    $now = ($now ?? new DateTimeImmutable('now', $zone))->setTimezone($zone);
    $hour = (int)$now->format('G');
    $tomorrowMessage = 'From 21:00 you can book a pickup time for tomorrow.';

    if ($hour === 20) {
        return ['accepting' => false, 'day' => null, 'date' => null, 'slots' => [],
            'message' => 'We are not taking orders between 20:00 and 21:00. ' . $tomorrowMessage];
    }
    $earliest = null;
    if ($hour >= 21) {
        $day = $now->modify('tomorrow');
        $dayLabel = 'tomorrow';
    } else {
        $day = $now->setTime(0, 0);
        $dayLabel = 'today';
        if ($hour >= 10) {
            $plusHour = $now->modify('+1 hour');
            $earliest = $plusHour->setTime((int)$plusHour->format('G'), 0);
            if ($earliest < $plusHour) $earliest = $earliest->modify('+1 hour');
        }
    }
    [$open, $close] = tidebites_business_hours((int)$day->format('N'));
    $slots = [];
    for ($h = $open; $h <= $close; $h++) {
        $slot = $day->setTime($h, 0);
        if ($earliest === null || $slot >= $earliest) $slots[] = $slot->format('H:i');
    }
    if (!$slots) {
        return ['accepting' => false, 'day' => null, 'date' => null, 'slots' => [],
            'message' => 'No pickup times are left today. ' . $tomorrowMessage];
    }
    return ['accepting' => true, 'day' => $dayLabel, 'date' => $day->format('Y-m-d'), 'slots' => $slots, 'message' => ''];
}

/**
 * Validate a requested "YYYY-MM-DD HH:MM" pickup time against the current schedule.
 * Returns the UTC DATETIME string for storage, or null when the slot cannot be booked now.
 */
function tidebites_pickup_time_utc(string $requested, ?DateTimeImmutable $now = null): ?string
{
    $schedule = tidebites_pickup_schedule($now);
    if (!$schedule['accepting'] || !preg_match('/\A(\d{4}-\d{2}-\d{2}) (\d{2}:00)\z/', $requested, $m)) return null;
    if ($m[1] !== $schedule['date'] || !in_array($m[2], $schedule['slots'], true)) return null;
    $local = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $requested, new DateTimeZone(TIDEBITES_TIMEZONE));
    if ($local === false) return null;
    return $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

/** Phone: digits with optional leading +, spaces, hyphens or parentheses; 8-15 digits in total. */
function tidebites_valid_phone(string $phone): bool
{
    if (!preg_match('/\A\+?[0-9 ()\-]+\z/', $phone)) return false;
    $digits = strlen(preg_replace('/\D/', '', $phone));
    return $digits >= 8 && $digits <= 15;
}
