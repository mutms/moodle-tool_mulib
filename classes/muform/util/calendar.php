<?php
// This file is part of MuTMS suite of plugins for Moodle™ LMS.
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <https://www.gnu.org/licenses/>.

// phpcs:disable moodle.Files.BoilerplateComment.CommentEndedTooSoon
namespace tool_mulib\muform\util;

/**
 * Calendar helpers shared by the datetime, duration and dateinterval elements,
 * the datetime Behat helper and the datetime normalisation API endpoint.
 *
 * Timestamps are the primary representation of dates, text is what users see and type.
 * Everything is explicit (timezone, language), there is no hidden dependency
 * on the current user, so the stateless endpoint can use it too.
 *
 * Interval parts are arrays with keys y, m, w, d, h, i, s (years, months, weeks,
 * days, hours, minutes, seconds) holding non-negative integers; missing keys mean zero.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class calendar {
    /** @var array unit keys in display order */
    public const array UNITS = ['y', 'm', 'w', 'd', 'h', 'i', 's'];

    /** @var array label lang string per unit */
    private const array LABELS = [
        'y' => 'muform_years', 'm' => 'muform_months', 'w' => 'muform_weeks', 'd' => 'muform_days',
        'h' => 'muform_hours', 'i' => 'muform_minutes', 's' => 'muform_seconds',
    ];

    /** @var array singular and plural lang strings per unit, core strings where they exist */
    private const array TEXTS = [
        'y' => ['numyear', 'core', 'numyears', 'core'],
        'm' => ['nummonth', 'core', 'nummonths', 'core'],
        'w' => ['numweek', 'core', 'numweeks', 'core'],
        'd' => ['numday', 'core', 'numdays', 'core'],
        'h' => ['muform_numhour', 'tool_mulib', 'numhours', 'core'],
        'i' => ['muform_numminute', 'tool_mulib', 'numminutes', 'core'],
        's' => ['muform_numsecond', 'tool_mulib', 'numseconds', 'core'],
    ];

    /** @var string ISO 8601 duration with the designators in fixed order, at least one part */
    private const string INTERVAL_REGEX = '/^P(?=\d|T\d)(?:(\d+)Y)?(?:(\d+)M)?(?:(\d+)W)?(?:(\d+)D)?'
        . '(?:T(?=\d)(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/D';

    /**
     * Parse ISO 8601 duration string such as P1Y2M or P1W2DT3H into parts.
     *
     * Only the basic designators Y, M, W, D, H, M and S in this order are accepted,
     * the same subset PHP DateInterval understands, so the result can be fed to it.
     *
     * @param string $iso
     * @return array|null parts indexed by UNITS, null when the string is not valid
     */
    public static function parse_interval(string $iso): ?array {
        if (!preg_match(self::INTERVAL_REGEX, trim($iso), $matches)) {
            return null;
        }
        $parts = [];
        foreach (self::UNITS as $index => $unit) {
            $parts[$unit] = (int)($matches[$index + 1] ?? 0);
        }
        return $parts;
    }

    /**
     * Build canonical ISO 8601 duration string from parts, zero parts are omitted.
     *
     * @param array $parts non-negative integers indexed by UNITS, missing keys mean zero
     * @return string|null null when all parts are zero
     */
    public static function build_interval(array $parts): ?string {
        $date = '';
        $time = '';
        foreach (['y' => 'Y', 'm' => 'M', 'w' => 'W', 'd' => 'D'] as $unit => $designator) {
            $number = (int)($parts[$unit] ?? 0);
            if ($number > 0) {
                $date .= $number . $designator;
            }
        }
        foreach (['h' => 'H', 'i' => 'M', 's' => 'S'] as $unit => $designator) {
            $number = (int)($parts[$unit] ?? 0);
            if ($number > 0) {
                $time .= $number . $designator;
            }
        }
        if ($date === '' && $time === '') {
            return null;
        }
        return 'P' . $date . ($time === '' ? '' : 'T' . $time);
    }

    /**
     * Lowercase label of a unit for inputs, such as "weeks".
     *
     * @param string $unit one of UNITS
     * @return string
     */
    public static function get_unit_label(string $unit): string {
        return get_string(self::LABELS[$unit], 'tool_mulib');
    }

    /**
     * Human readable text of interval parts, such as "1 week 2 days", zero parts are skipped.
     *
     * @param array $parts
     * @param bool $keepzeros include zero parts that are present in $parts, such as "0 minutes"
     * @return string empty string when nothing is printed
     */
    public static function to_text(array $parts, bool $keepzeros = false): string {
        $result = [];
        foreach (self::UNITS as $unit) {
            if (!array_key_exists($unit, $parts)) {
                continue;
            }
            $number = (int)$parts[$unit];
            if ($number === 0 && !$keepzeros) {
                continue;
            }
            [$singular, $scomponent, $plural, $pcomponent] = self::TEXTS[$unit];
            if ($number === 1) {
                $result[] = get_string($singular, $scomponent, $number);
            } else {
                $result[] = get_string($plural, $pcomponent, $number);
            }
        }
        return implode(' ', $result);
    }

    /**
     * Display format for given language, a PHP date() format from lang string.
     *
     * NOTE: strftime() style formats used in langconfig are not supported,
     * ISO format is used instead.
     *
     * @param string $lang language code
     * @return string
     */
    public static function get_format(string $lang): string {
        $format = get_string_manager()->get_string('muform_datetimeformat', 'tool_mulib', null, $lang);
        if (trim($format) === '') {
            return 'Y-m-d H:i';
        }
        if (str_contains($format, '%')) {
            debugging("Invalid muform_datetimeformat '$format' in language '$lang', PHP date() format expected", DEBUG_DEVELOPER);
            return 'Y-m-d H:i';
        }
        return $format;
    }

    /**
     * Does the display format of the language use 12 hour clock with AM and PM?
     *
     * @param string $lang
     * @return bool
     */
    public static function uses_12_hour_clock(string $lang): bool {
        return (bool)preg_match('/[gh]/', preg_replace('/\\\\./', '', self::get_format($lang)));
    }

    /**
     * Order of day, month and year in numeric dates typed by users, the same as in the display format.
     *
     * @param string $format display format
     * @return string dmy, mdy or ymd, formats without full date mean ymd
     */
    private static function get_date_order(string $format): string {
        // Escaped characters are literal text.
        $format = preg_replace('/\\\\./', '', $format);
        $positions = [];
        foreach (['d' => '/[dj]/', 'm' => '/[mnMF]/', 'y' => '/[Yy]/'] as $part => $regex) {
            if (!preg_match($regex, $format, $matches, PREG_OFFSET_CAPTURE)) {
                return 'ymd';
            }
            $positions[$part] = $matches[0][1];
        }
        asort($positions);
        $order = implode('', array_keys($positions));
        return in_array($order, ['dmy', 'mdy'], true) ? $order : 'ymd';
    }

    /**
     * Convert text to timestamp.
     *
     * Empty text means no value, integers are timestamps. Numeric dates such as
     * "4. 11. 2026 14:30", "4.11.26", "4. 11.", "11/4/2026" or "2026-11-04T14:30"
     * are parsed in the day and month order of the display format of the language, ISO dates
     * with year first are always accepted, dates without time get the default time
     * and dates without year get the current year. Otherwise the text must match
     * the display format exactly, PHP is never allowed to guess.
     *
     * @param string $text
     * @param \DateTimeZone $tz timezone of texts without explicit offset
     * @param string $lang language of the display format
     * @param int $defaulthour hour of dates without time
     * @param int $defaultminute minute of dates without time
     * @return int|null null for empty text
     * @throws \InvalidArgumentException when text cannot be parsed
     */
    public static function parse(
        string $text,
        \DateTimeZone $tz,
        string $lang,
        int $defaulthour = 0,
        int $defaultminute = 0
    ): ?int {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        if (preg_match('/^-?\d+$/D', $text)) {
            return (int)$text;
        }

        // Time with optional seconds and AM/PM, the hour alone is allowed only with AM/PM.
        $time = '(?<hour>\d{1,2})(?:[:.](?<minute>\d{2})(?:[:.](?<second>\d{2}))?)?\s*(?:(?<ampm>[AaPp])\.?\s*[Mm]\.?)?';

        // Time only means today, it needs a colon or AM/PM, texts with dots only such as "4.11.26" are dates.
        if (
            preg_match('/^' . $time . '$/D', $text, $matches)
            && (str_contains($text, ':') || ($matches['ampm'] ?? '') !== '')
        ) {
            $now = new \DateTime('now', $tz);
            [$hour, $minute, $second] = self::get_time_parts($text, $matches);
            return self::build_timestamp(
                $text,
                $tz,
                (int)$now->format('Y'),
                (int)$now->format('n'),
                (int)$now->format('j'),
                $hour,
                $minute,
                $second
            );
        }

        // Numeric dates with optional time and offset, the separator must be the same in the date.
        $format = self::get_format($lang);
        $order = self::get_date_order($format);
        $regex = '/^(?<first>\d{1,4})\s*(?<separator>[.\/-])\s*(?<second_>\d{1,2})'
            . '\s*(?:(?<closed>\k<separator>)\s*(?<third>\d{1,4})?)?'
            . '(?:(?:\s*,\s*|\s+|T)' . $time . ')?\s*(?<offset>Z|[+-]\d{2}:?\d{2})?$/D';
        if (preg_match($regex, $text, $matches)) {
            $first = $matches['first'];
            $second = $matches['second_'];
            $closed = ($matches['closed'] ?? '') !== '';
            $third = $matches['third'] ?? '';
            if (strlen($first) === 4) {
                // ISO style year first.
                if ($third === '') {
                    throw new \InvalidArgumentException('Cannot parse date and time text: ' . $text);
                }
                [$year, $month, $day] = [(int)$first, (int)$second, (int)$third];
            } else if (strlen($first) > 2 || $order === 'ymd') {
                throw new \InvalidArgumentException('Cannot parse date and time text: ' . $text);
            } else {
                if ($third === '') {
                    // Day and month without year need the closing dot, "4.11" might be a time.
                    if (!$closed || $matches['separator'] !== '.') {
                        throw new \InvalidArgumentException('Cannot parse date and time text: ' . $text);
                    }
                    $year = (int)(new \DateTime('now', $tz))->format('Y');
                } else if (strlen($third) === 2) {
                    $year = 2000 + (int)$third;
                } else if (strlen($third) === 4) {
                    $year = (int)$third;
                } else {
                    throw new \InvalidArgumentException('Cannot parse date and time text: ' . $text);
                }
                if ($order === 'mdy') {
                    [$month, $day] = [(int)$first, (int)$second];
                } else {
                    [$day, $month] = [(int)$first, (int)$second];
                }
            }
            if (($matches['hour'] ?? '') !== '') {
                [$hour, $minute, $seconds] = self::get_time_parts($text, $matches);
            } else {
                [$hour, $minute, $seconds] = [$defaulthour, $defaultminute, 0];
            }
            $offset = $matches['offset'] ?? '';
            if ($offset !== '') {
                $tz = new \DateTimeZone($offset === 'Z' ? 'UTC' : $offset);
            }
            return self::build_timestamp($text, $tz, $year, $month, $day, $hour, $minute, $seconds);
        }

        // Display formats with texts such as month names, fields missing in format are reset to zero.
        $date = \DateTime::createFromFormat('!' . $format, $text, $tz);
        if ($date && !self::has_warnings() && (int)$date->format('Y') >= 1000) {
            return $date->getTimestamp();
        }

        throw new \InvalidArgumentException('Cannot parse date and time text: ' . $text);
    }

    /**
     * Hour, minute and second from matched time, AM/PM converted to 24 hours.
     *
     * @param string $text original text for error messages
     * @param array $matches with keys hour, minute, second and ampm
     * @return int[] hour, minute and second
     */
    private static function get_time_parts(string $text, array $matches): array {
        $hour = (int)$matches['hour'];
        $minute = (int)($matches['minute'] ?? 0);
        $second = (int)($matches['second'] ?? 0);
        if (($matches['minute'] ?? '') === '' && ($matches['ampm'] ?? '') === '') {
            throw new \InvalidArgumentException('Cannot parse date and time text: ' . $text);
        }
        $ampm = strtolower($matches['ampm'] ?? '');
        if ($ampm !== '') {
            if ($hour < 1 || $hour > 12) {
                throw new \InvalidArgumentException('Cannot parse date and time text: ' . $text);
            }
            $hour = ($hour % 12) + ($ampm === 'p' ? 12 : 0);
        }
        return [$hour, $minute, $second];
    }

    /**
     * Validate date and time parts and convert them to timestamp.
     *
     * @param string $text original text for error messages
     * @param \DateTimeZone $tz
     * @param int $year
     * @param int $month
     * @param int $day
     * @param int $hour
     * @param int $minute
     * @param int $second
     * @return int
     */
    private static function build_timestamp(
        string $text,
        \DateTimeZone $tz,
        int $year,
        int $month,
        int $day,
        int $hour,
        int $minute,
        int $second
    ): int {
        if ($year < 1000 || !checkdate($month, $day, $year) || $hour > 23 || $minute > 59 || $second > 59) {
            throw new \InvalidArgumentException('Cannot parse date and time text: ' . $text);
        }
        $date = new \DateTime('now', $tz);
        $date->setDate($year, $month, $day);
        $date->setTime($hour, $minute, $second);
        return $date->getTimestamp();
    }

    /**
     * Describe a timestamp in text, timestamp and component form.
     *
     * @param int|null $timestamp
     * @param \DateTimeZone $tz
     * @param string $lang language of the display format
     * @return array with keys text, timestamp and components (year, month, day, hour, minute, second)
     */
    public static function describe(?int $timestamp, \DateTimeZone $tz, string $lang): array {
        if ($timestamp === null) {
            return ['text' => '', 'timestamp' => null, 'components' => null];
        }
        $date = (new \DateTime('@' . $timestamp))->setTimezone($tz);
        return [
            'text' => $date->format(self::get_format($lang)),
            'timestamp' => $timestamp,
            'components' => [
                'year' => (int)$date->format('Y'),
                'month' => (int)$date->format('n'),
                'day' => (int)$date->format('j'),
                'hour' => (int)$date->format('G'),
                'minute' => (int)$date->format('i'),
                'second' => (int)$date->format('s'),
            ],
        ];
    }

    /**
     * Localised timezone name for display.
     *
     * @param string $timezone IANA timezone name
     * @return string
     */
    public static function get_timezone_name(string $timezone): string {
        return \core_date::get_localised_timezone($timezone);
    }

    /**
     * Did the last date operation produce warnings or errors?
     *
     * @return bool
     */
    private static function has_warnings(): bool {
        $errors = \DateTime::getLastErrors();
        return $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);
    }
}
