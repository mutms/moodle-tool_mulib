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
// phpcs:disable moodle.Files.LineLength.TooLong
// phpcs:disable moodle.Commenting.DocblockDescription.Missing

namespace tool_mulib\phpunit\local;

use tool_mulib\local\date_util;
use tool_mulib\local\lang_util;
use tool_mulib\tests\fake_langpack;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Date helper tests.
 *
 * @package     tool_mulib
 * @copyright   2023 Open LMS
 * @copyright   2025 Petr Skoda
 * @author      Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mulib\local\date_util::class)]
#[Group('MuTMS')]
final class date_util_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_timestamp_forever(): void {
        $this->assertSame('9999999999', (string)date_util::TIMESTAMP_FOREVER);
        $this->assertSame(9999999999, date_util::TIMESTAMP_FOREVER);
    }

    public function test_format_event_date(): void {
        $result = date_util::format_event_date(strtotime('2022-08-15T11:00:00'), strtotime('2022-08-15T15:00:00'));
        $this->assertSame('15 August 2022&nbsp;&nbsp;&nbsp;11:00 AM&ndash;3:00 PM', $result);

        $result = date_util::format_event_date(strtotime('2022-08-15T11:00:00'), strtotime('2022-08-16T15:00:00'));
        $this->assertSame('15 August 2022&nbsp;&nbsp;&nbsp;11:00 AM&ndash;3:00 PM<sup> (+1 day)</sup>', $result);

        $result = date_util::format_event_date(strtotime('2022-08-15T11:00:00'), strtotime('2022-08-17T15:00:00'));
        $this->assertSame('15 August 2022&nbsp;&nbsp;&nbsp;11:00 AM&ndash;3:00 PM<sup> (+2 days)</sup>', $result);

        $result = date_util::format_event_date(strtotime('2022-08-15T11:00:00'), strtotime('2022-08-20T15:00:00'));
        $this->assertSame('15 August 2022&nbsp;&nbsp;&nbsp;11:00 AM&ndash;3:00 PM<sup> (+5 days)</sup>', $result);

        $result = date_util::format_event_date(strtotime('2022-08-15T11:00:00'), strtotime('2022-08-14T15:00:00'));
        $this->assertSame('15 August 2022&nbsp;&nbsp;&nbsp;11:00 AM', $result);

        $result = date_util::format_event_date(strtotime('2022-08-15T11:00:00'), 0);
        $this->assertSame('15 August 2022&nbsp;&nbsp;&nbsp;11:00 AM', $result);

        $result = date_util::format_event_date(strtotime('2022-08-15T11:00:00'), null);
        $this->assertSame('15 August 2022&nbsp;&nbsp;&nbsp;11:00 AM', $result);

        $result = date_util::format_event_date(strtotime('2022-08-15T11:00:00'), date_util::TIMESTAMP_FOREVER);
        $this->assertSame('15 August 2022&nbsp;&nbsp;&nbsp;11:00 AM', $result);

        $result = date_util::format_event_date(date_util::TIMESTAMP_FOREVER, null);
        $this->assertSame('21 November 2286&nbsp;&nbsp;&nbsp;1:46 AM', $result);

        $result = date_util::format_event_date(0, strtotime('2022-08-15T15:00:00'));
        $this->assertSame('', $result);

        $result = date_util::format_event_date(null, strtotime('2022-08-15T15:00:00'));
        $this->assertSame('', $result);

        // Plan text decoding.
        $result = date_util::format_event_date(strtotime('2022-08-15T11:00:00'), strtotime('2022-08-16T15:00:00'));
        $result = strip_tags($result);
        $result = \core_text::entities_to_utf8($result);
        $this->assertSame('15 August 2022   11:00 AM–3:00 PM (+1 day)', $result);
    }

    public function test_get_format(): void {
        $this->resetAfterTest();
        $this->assertSame('Y-m-d H:i', date_util::get_format('en'));

        fake_langpack::install('cs');
        $this->assertSame('j. n. Y H:i', date_util::get_format('cs'));

        // Formats from langconfig are not compatible.
        lang_util::customise_string('cs', 'tool_mulib', 'muform_datetimeformat', '%d.%m.%Y %H:%M');
        $this->assertSame('Y-m-d H:i', date_util::get_format('cs'));
        $this->assertDebuggingCalled("Invalid muform_datetimeformat '%d.%m.%Y %H:%M' in language 'cs', PHP date() format expected");

        lang_util::customise_string('cs', 'tool_mulib', 'muform_datetimeformat', ' ');
        $this->assertSame('Y-m-d H:i', date_util::get_format('cs'));
    }

    public function test_parse(): void {
        $prague = new \DateTimeZone('Europe/Prague');
        $utc = new \DateTimeZone('UTC');

        $this->assertNull(date_util::parse('', $utc, 'en'));
        $this->assertNull(date_util::parse('   ', $utc, 'en'));
        $this->assertSame(1790450923, date_util::parse('1790450923', $utc, 'en'));
        $this->assertSame(-10, date_util::parse(' -10 ', $utc, 'en'));

        // English display format is ISO, year first only.
        $this->assertSame(1790443723, date_util::parse('2026-09-26 17:28:43', $utc, 'en'));
        $this->assertSame(1790443723, date_util::parse('2026-09-26 19:28:43', $prague, 'en'));
        $this->assertSame(1790443680, date_util::parse('2026-09-26 19:28', $prague, 'en'));
        $this->assertSame(1790443680, date_util::parse('2026-09-26T19:28', $prague, 'en'));
        $this->assertSame(1790443680, date_util::parse('2026-09-26T17:28:00Z', $prague, 'en'));
        $this->assertSame(1790443680, date_util::parse('2026-09-26T21:28:00+04:00', $prague, 'en'));
        $this->assertSame(1790380800, date_util::parse('2026-09-26', $utc, 'en'));
        $this->assertSame(1790380800, date_util::parse('2026-9-26', $utc, 'en'));
        $this->assertSame(1790380800, date_util::parse('2026/9/26', $utc, 'en'));

        // Time only means today.
        $today = (new \DateTime('now', $prague))->setTime(14, 30)->getTimestamp();
        $this->assertSame($today, date_util::parse('14:30', $prague, 'en'));
    }

    public function test_parse_czech(): void {
        $this->resetAfterTest();
        fake_langpack::install('cs');
        $prague = new \DateTimeZone('Europe/Prague');
        $nov4 = (new \DateTime('2026-11-04 00:00', $prague))->getTimestamp();
        $nov4time = (new \DateTime('2026-11-04 14:30', $prague))->getTimestamp();

        // Spaces, leading zeros, separators and 2-digit years do not matter.
        $texts = [
            '4. 11. 2026 14:30', '4.11.2026 14:30', '04.11.2026 14:30', '4. 11. 2026, 14:30', '4.11.2026 14.30',
            '4.11.26 14:30', '4/11/2026 14:30', '4-11-2026 14:30', '2026-11-04 14:30', ' 4 . 11 . 2026   14:30 ',
        ];
        foreach ($texts as $text) {
            $this->assertSame($nov4time, date_util::parse($text, $prague, 'cs'), $text);
        }
        $this->assertSame($nov4time + 15, date_util::parse('4.11.2026 14:30:15', $prague, 'cs'));

        // Date without time.
        foreach (['4. 11. 2026', '4.11.2026', '04.11.26', '4/11/2026', '2026-11-04'] as $text) {
            $this->assertSame($nov4, date_util::parse($text, $prague, 'cs'), $text);
        }

        // Date without year.
        $year = (int)(new \DateTime('now', $prague))->format('Y');
        $expected = (new \DateTime("$year-11-04 00:00", $prague))->getTimestamp();
        $this->assertSame($expected, date_util::parse('4. 11.', $prague, 'cs'));
        $this->assertSame($expected, date_util::parse('4.11.', $prague, 'cs'));
        $this->assertSame($expected + 14 * HOURSECS + 30 * MINSECS, date_util::parse('4. 11. 14:30', $prague, 'cs'));
    }

    public function test_parse_us(): void {
        $this->resetAfterTest();
        fake_langpack::install('en_us');
        $ny = new \DateTimeZone('America/New_York');
        $nov6 = (new \DateTime('2026-11-06 00:00', $ny))->getTimestamp();
        $nov6time = (new \DateTime('2026-11-06 14:30', $ny))->getTimestamp();

        // Plugin ships en_us override of the display format.
        $this->assertSame('n/j/Y g:i A', date_util::get_format('en_us'));
        $this->assertTrue(date_util::uses_12_hour_clock('en_us'));
        $this->assertFalse(date_util::uses_12_hour_clock('en'));
        $this->assertSame('11/6/2026 2:30 PM', date_util::describe($nov6time, $ny, 'en_us')['text']);
        $this->assertSame('11/6/2026 12:00 AM', date_util::describe($nov6, $ny, 'en_us')['text']);

        $texts = [
            '11/6/2026 2:30 PM', '11/6/26 2:30 pm', '11/6/2026 2:30PM', '11/6/2026 2:30 p.m.', '11/06/2026, 02:30 PM',
            '11/6/2026 14:30', '2026-11-06 14:30', '2026-11-06 2:30 PM',
        ];
        foreach ($texts as $text) {
            $this->assertSame($nov6time, date_util::parse($text, $ny, 'en_us'), $text);
        }
        $this->assertSame($nov6 + 14 * HOURSECS, date_util::parse('11/6/2026 2 PM', $ny, 'en_us'));
        $this->assertSame($nov6 + 15 * MINSECS, date_util::parse('11/6/2026 12:15 AM', $ny, 'en_us'));
        $this->assertSame($nov6 + 12 * HOURSECS, date_util::parse('11/6/2026 12:00 pm', $ny, 'en_us'));
        $this->assertSame($nov6, date_util::parse('11/6/2026', $ny, 'en_us'));
        $this->assertSame($nov6 + 23 * HOURSECS + 59 * MINSECS, date_util::parse('11/6/26', $ny, 'en_us', 23, 59));

        // Time only means today.
        $today = (new \DateTime('now', $ny))->setTime(14, 0)->getTimestamp();
        $this->assertSame($today, date_util::parse('2 pm', $ny, 'en_us'));
        $this->assertSame($today, date_util::parse('2:00PM', $ny, 'en_us'));

        foreach (['13/6/2026', '11/31/2026', '11/6/2026 13:00 PM', '11/6/2026 0:30 am', '11/6/2026 2', '11/6/2026 2 xm'] as $text) {
            try {
                date_util::parse($text, $ny, 'en_us');
                $this->fail('Exception expected for: ' . $text);
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString($text, $e->getMessage());
            }
        }
    }

    public function test_parse_custom_format(): void {
        $this->resetAfterTest();
        $prague = new \DateTimeZone('Europe/Prague');
        $nov4 = (new \DateTime('2026-11-04 00:00', $prague))->getTimestamp();

        // Day and month order follows the display format.
        lang_util::customise_string('en', 'tool_mulib', 'muform_datetimeformat', 'n/j/Y g:i A');
        $this->assertSame($nov4 + 14 * HOURSECS + 30 * MINSECS, date_util::parse('11/4/2026 14:30', $prague, 'en'));
        $this->assertSame($nov4, date_util::parse('11.4.26', $prague, 'en'));
        $this->assertSame($nov4, date_util::parse('2026-11-04', $prague, 'en'));
        // Text matching the display format exactly.
        $this->assertSame(1790443680, date_util::parse('9/26/2026 7:28 PM', $prague, 'en'));
        $this->assertSame('9/26/2026 7:28 PM', date_util::describe(1790443680, $prague, 'en')['text']);

        lang_util::customise_string('en', 'tool_mulib', 'muform_datetimeformat', 'F j, Y H:i');
        $this->assertSame(1790443680, date_util::parse('September 26, 2026 19:28', $prague, 'en'));
        $this->assertSame($nov4, date_util::parse('11/4/2026', $prague, 'en'));
        // Years are never shorter than 4 digits in exact format matches.
        try {
            date_util::parse('September 26, 26 19:28', $prague, 'en');
            $this->fail('Exception expected');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('September 26, 26 19:28', $e->getMessage());
        }
    }

    public function test_parse_default_time(): void {
        $this->resetAfterTest();
        fake_langpack::install('cs');
        $prague = new \DateTimeZone('Europe/Prague');

        $this->assertSame(
            (new \DateTime('2026-11-04 23:59', $prague))->getTimestamp(),
            date_util::parse('4. 11. 2026', $prague, 'cs', 23, 59)
        );
        $this->assertSame(
            (new \DateTime('2026-11-04 15:00', $prague))->getTimestamp(),
            date_util::parse('2026-11-04', $prague, 'en', 15)
        );
        // Explicit time wins.
        $this->assertSame(
            (new \DateTime('2026-11-04 08:00', $prague))->getTimestamp(),
            date_util::parse('4. 11. 2026 8:00', $prague, 'cs', 23, 59)
        );
        // Daylight saving time starts on 29 March 2026 at 2:00 in Prague.
        $this->assertSame(
            (new \DateTime('2026-03-29 15:00', $prague))->getTimestamp(),
            date_util::parse('29. 3. 2026', $prague, 'cs', 15)
        );
        $this->assertSame(
            (new \DateTime('2026-03-29 00:00', $prague))->getTimestamp(),
            date_util::parse('29. 3. 2026', $prague, 'cs')
        );
    }

    public function test_parse_invalid(): void {
        $this->resetAfterTest();
        fake_langpack::install('cs');
        $utc = new \DateTimeZone('UTC');
        $invalid = [
            'abc', '2026-02-30 10:00:00', '2026-02-30', '2026-13-01', '12:99', '24:00',
            // PHP would guess these, users mean something else or nothing.
            'tomorrow 10:00', 'now', '26 September 2026 19:28', 'next monday',
            // Day and month without the closing dot look like time.
            '4.11', '4/11', '14.30',
            // Invalid dates are never rolled over.
            '31. 2. 2026', '30.2.2026', '4.13.2026', '32.1.2026', '4.11.2026 25:00', '4.11.2026 14:60',
            // Odd years and mixed separators.
            '4.11.202', '4.11.0026', '4.11/2026', '1.2.3.4', '4. 11. 2026 14', '4.11.2026.', '2026-11-04.',
        ];
        foreach ($invalid as $text) {
            try {
                date_util::parse($text, $utc, 'cs');
                $this->fail('Exception expected for: ' . $text);
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString($text, $e->getMessage());
            }
        }

        // ISO display format accepts year first only.
        foreach (['4.11.2026', '11/4/2026', '4. 11.'] as $text) {
            try {
                date_util::parse($text, $utc, 'en');
                $this->fail('Exception expected for: ' . $text);
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString($text, $e->getMessage());
            }
        }
    }

    public function test_describe(): void {
        $this->resetAfterTest();
        $prague = new \DateTimeZone('Europe/Prague');

        $this->assertSame(['text' => '', 'timestamp' => null, 'components' => null], date_util::describe(null, $prague, 'en'));

        $expected = [
            'text' => '2026-09-26 19:28',
            'timestamp' => 1790443723,
            'components' => ['year' => 2026, 'month' => 9, 'day' => 26, 'hour' => 19, 'minute' => 28, 'second' => 43],
        ];
        $this->assertSame($expected, date_util::describe(1790443723, $prague, 'en'));
        $this->assertSame('2026-09-26 17:28', date_util::describe(1790443723, new \DateTimeZone('UTC'), 'en')['text']);

        fake_langpack::install('cs');
        $this->assertSame('26. 9. 2026 19:28', date_util::describe(1790443723, $prague, 'cs')['text']);
    }

    /**
     * Timestamp of a wall time in a timezone.
     *
     * @param string $text Y-m-d H:i
     * @param \DateTimeZone $tz
     * @return int
     */
    private static function at(string $text, \DateTimeZone $tz): int {
        return (new \DateTime($text, $tz))->getTimestamp();
    }

    public function test_get_time_format(): void {
        $this->resetAfterTest();
        $this->assertSame('H:i', date_util::get_time_format('en'));
        fake_langpack::install('en_us');
        $this->assertSame('g:i A', date_util::get_time_format('en_us'));
        fake_langpack::install('cs');
        $this->assertSame('H:i', date_util::get_time_format('cs'));
        lang_util::customise_string('cs', 'tool_mulib', 'muform_datetimeformat', 'j. \h\o\d. n. Y');
        $this->assertSame('H:i', date_util::get_time_format('cs'));
    }

    public function test_get_date_format(): void {
        $this->resetAfterTest();
        $this->assertSame('Y-m-d', date_util::get_date_format('en'));
        fake_langpack::install('en_us');
        $this->assertSame('n/j/Y', date_util::get_date_format('en_us'));
        lang_util::customise_string('en', 'tool_mulib', 'muform_datetimeformat', 'F j, Y, H:i');
        $this->assertSame('F j, Y', date_util::get_date_format('en'));
        lang_util::customise_string('en', 'tool_mulib', 'muform_datetimeformat', 'j. \h\o\d. n. Y');
        $this->assertSame('j. \h\o\d. n. Y', date_util::get_date_format('en'));
    }

    public function test_get_picker_settings(): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/Prague');
        $this->setAdminUser();
        $settings = date_util::get_picker_settings();
        $this->assertSame('Europe/Prague', $settings['timezone']);
        $this->assertSame('en', $settings['lang']);
        $this->assertSame('Y-m-d', $settings['dateformat']);
        $this->assertSame(5, $settings['step']);
        $this->assertFalse($settings['hour12']);
    }

    public function test_parse_range(): void {
        $prague = new \DateTimeZone('Europe/Prague');
        $start = self::at('2026-11-06 11:30', $prague);
        $range = ['start' => $start, 'end' => self::at('2026-11-06 12:30', $prague)];

        $this->assertNull(date_util::parse_range('', $prague, 'en'));
        $this->assertNull(date_util::parse_range('  ', $prague, 'en', 0, 0, 3600));

        $this->assertSame($range, date_util::parse_range("2026-11-06 11:30 \u{2013} 12:30", $prague, 'en'));
        $this->assertSame($range, date_util::parse_range("2026-11-06 11:30\u{2013}12:30", $prague, 'en'));
        $this->assertSame($range, date_util::parse_range("2026-11-06 11:30 \u{2014} 12:30", $prague, 'en'));
        $this->assertSame($range, date_util::parse_range('2026-11-06 11:30 - 12:30', $prague, 'en'));
        $this->assertSame($range, date_util::parse_range('2026-11-06T11:30 - 2026-11-06 12:30', $prague, 'en'));
        $this->assertSame($range, date_util::parse_range('2026 - 11 - 06 11:30 - 12:30', $prague, 'en'));
        $this->assertSame($range, date_util::parse_range('2026-11-06 11:30 - 12:30 +0', $prague, 'en'));

        // Start only gets the default duration.
        $this->assertSame($range, date_util::parse_range('2026-11-06 11:30', $prague, 'en', 0, 0, 3600));
        $this->assertSame(
            ['start' => self::at('2026-11-06 09:00', $prague), 'end' => self::at('2026-11-06 10:30', $prague)],
            date_util::parse_range('2026-11-06', $prague, 'en', 9, 0, 5400)
        );

        // End time before start means the next day, +N is optional.
        $night = ['start' => self::at('2026-11-06 23:30', $prague), 'end' => self::at('2026-11-07 01:30', $prague)];
        $this->assertSame($night, date_util::parse_range('2026-11-06 23:30 - 01:30', $prague, 'en'));
        $this->assertSame($night, date_util::parse_range('2026-11-06 23:30 - 01:30 +1', $prague, 'en'));
        $this->assertSame($night, date_util::parse_range("2026-11-06 23:30 \u{2013} 01:30+1", $prague, 'en'));
        $this->assertSame($night, date_util::parse_range('2026-11-06 23:30 - 01:30 + 1', $prague, 'en'));
        $this->assertSame($night, date_util::parse_range('2026-11-06 23:30 - 2026-11-07 01:30', $prague, 'en'));

        // Same time means a whole day.
        $this->assertSame(
            ['start' => $start, 'end' => self::at('2026-11-07 11:30', $prague)],
            date_util::parse_range('2026-11-06 11:30 - 11:30', $prague, 'en')
        );

        $this->assertSame(
            ['start' => $start, 'end' => self::at('2026-11-07 11:30', $prague)],
            date_util::parse_range('2026-11-06 11:30 - 2026-11-07 11:30', $prague, 'en')
        );
        $this->assertSame(
            ['start' => $start, 'end' => self::at('2026-11-07 11:00', $prague)],
            date_util::parse_range('2026-11-06 11:30 - 11:00 +1', $prague, 'en')
        );

        // Timezone of the user decides the day.
        $utc = new \DateTimeZone('UTC');
        $this->assertSame(
            ['start' => self::at('2026-11-06 23:30', $utc), 'end' => self::at('2026-11-07 00:30', $utc)],
            date_util::parse_range('2026-11-06 23:30 - 00:30', $utc, 'en')
        );
    }

    public function test_parse_range_dst(): void {
        $prague = new \DateTimeZone('Europe/Prague');

        // Clocks go back from 03:00 to 02:00 on 25 October 2026.
        $range = date_util::parse_range('2026-10-25 01:30 - 03:30', $prague, 'en');
        $this->assertSame(3 * HOURSECS, $range['end'] - $range['start']);

        // Clocks go forward from 02:00 to 03:00 on 29 March 2026.
        $range = date_util::parse_range('2026-03-29 01:30 - 03:30', $prague, 'en');
        $this->assertSame(HOURSECS, $range['end'] - $range['start']);

        // The limit is the same wall clock time on the next day, 25 or 23 hours across DST changes.
        $range = date_util::parse_range('2026-10-24 12:00 - 12:00', $prague, 'en');
        $this->assertSame(25 * HOURSECS, $range['end'] - $range['start']);
        $range = date_util::parse_range('2026-03-28 12:00 - 12:00', $prague, 'en');
        $this->assertSame(23 * HOURSECS, $range['end'] - $range['start']);
        try {
            date_util::parse_range('2026-03-28 12:00', $prague, 'en', 0, 0, DAYSECS);
            $this->fail('Exception expected');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringStartsWith('Cannot parse date and time range text: ', $e->getMessage());
        }
    }

    public function test_parse_range_czech_and_us(): void {
        $this->resetAfterTest();
        fake_langpack::install('cs');
        $prague = new \DateTimeZone('Europe/Prague');
        $night = ['start' => self::at('2026-11-06 23:30', $prague), 'end' => self::at('2026-11-07 01:30', $prague)];
        $this->assertSame($night, date_util::parse_range("6. 11. 2026 23:30 \u{2013} 01:30 +1", $prague, 'cs'));
        $this->assertSame($night, date_util::parse_range('6.11.26 23.30 - 1.30', $prague, 'cs'));
        $this->assertSame($night, date_util::parse_range('6. 11. 2026 23:30 - 7. 11. 2026 1:30', $prague, 'cs'));

        fake_langpack::install('en_us');
        $ny = new \DateTimeZone('America/New_York');
        $night = ['start' => self::at('2026-11-06 23:30', $ny), 'end' => self::at('2026-11-07 01:30', $ny)];
        $this->assertSame($night, date_util::parse_range("11/6/2026 11:30 PM \u{2013} 1:30 AM +1", $ny, 'en_us'));
        $this->assertSame($night, date_util::parse_range('11/6/2026 11:30 pm - 1:30am', $ny, 'en_us'));
        $this->assertSame($night, date_util::parse_range('11/6/2026 11:30 PM - 1:30 AM+1', $ny, 'en_us'));
    }

    public function test_parse_range_invalid(): void {
        $prague = new \DateTimeZone('Europe/Prague');
        $invalid = [
            '2026-11-06 11:30',
            '2026-11-06 11:30 -',
            "\u{2013} 12:30",
            '1793917800 - 1793925000',
            '2026-11-06 11:30 - 1793925000',
            '2026-11-06 11:30 - 12',
            '2026-11-06 11:30 - 10:30 +0',
            '2026-11-06 11:30 - 2026-11-06 10:30',
            '2026-11-06 11:30 - 2026-11-06 11:30',
            '2026-11-06 11:30 - 25:00',
            '2026-11-06 11:30 - 12:30 +x',
            '2026-11-06 11:30 - 12:30 - 13:30',
            '2026-11-06 11:30 - 12:30 +1',
            '2026-11-06 11:30 - 02:30 +2',
            '2026-11-06 23:30 - 01:30 +2',
            '2026-11-06 11:30 - 2026-11-08 02:30',
            '2026-11-06 11:30 - 2026-11-07 11:31',
            'tomorrow - 12:30',
            '2026-11-06 11:30-12:30',
        ];
        foreach ($invalid as $text) {
            try {
                date_util::parse_range($text, $prague, 'en');
                $this->fail('Exception expected for: ' . $text);
            } catch (\InvalidArgumentException $e) {
                $this->assertStringStartsWith('Cannot parse date and time range text: ', $e->getMessage());
            }
        }
    }

    public function test_describe_range(): void {
        $this->resetAfterTest();
        $prague = new \DateTimeZone('Europe/Prague');

        $empty = date_util::describe_range(null, $prague, 'en');
        $this->assertSame('', $empty['text']);
        $this->assertNull($empty['start']);
        $this->assertNull($empty['end']);
        $this->assertSame('', $empty['hint']);

        $start = self::at('2026-11-06 11:30', $prague);
        $end = self::at('2026-11-06 13:00', $prague);
        $info = date_util::describe_range(['start' => $start, 'end' => $end], $prague, 'en');
        $this->assertSame("2026-11-06 11:30 \u{2013} 13:00", $info['text']);
        $this->assertSame($start, $info['start']);
        $this->assertSame($end, $info['end']);
        $this->assertSame(5400, $info['duration']);
        $this->assertSame(0, $info['dayoffset']);
        $this->assertSame('1 hour 30 minutes', $info['hint']);
        $this->assertSame(13, $info['endcomponents']['hour']);
        $this->assertSame(['start' => $start, 'end' => $end], date_util::parse_range($info['text'], $prague, 'en'));

        $end = self::at('2026-11-07 01:30', $prague);
        $info = date_util::describe_range(['start' => $start, 'end' => $end], $prague, 'en');
        $this->assertSame("2026-11-06 11:30 \u{2013} 01:30 +1", $info['text']);
        $this->assertSame(1, $info['dayoffset']);
        $this->assertSame('14 hours, ends 2026-11-07 01:30', $info['hint']);
        $this->assertSame(['start' => $start, 'end' => $end], date_util::parse_range($info['text'], $prague, 'en'));

        // Viewers in other timezones see a different day offset.
        $tokyo = new \DateTimeZone('Asia/Tokyo');
        $start = self::at('2026-11-06 14:00', $prague);
        $end = self::at('2026-11-06 17:00', $prague);
        $info = date_util::describe_range(['start' => $start, 'end' => $end], $tokyo, 'en');
        $this->assertSame("2026-11-06 22:00 \u{2013} 01:00 +1", $info['text']);
        $this->assertSame(['start' => $start, 'end' => $end], date_util::parse_range($info['text'], $tokyo, 'en'));
        $this->assertSame(0, date_util::describe_range(['start' => $start, 'end' => $end], $prague, 'en')['dayoffset']);

        // The longest range.
        $end = self::at('2026-11-07 14:00', $prague);
        $info = date_util::describe_range(['start' => $start, 'end' => $end], $prague, 'en');
        $this->assertSame("2026-11-06 14:00 \u{2013} 14:00 +1", $info['text']);
        $this->assertSame('1 day, ends 2026-11-07 14:00', $info['hint']);
        $this->assertSame(['start' => $start, 'end' => $end], date_util::parse_range($info['text'], $prague, 'en'));

        fake_langpack::install('en_us');
        $ny = new \DateTimeZone('America/New_York');
        $start = self::at('2026-11-06 23:30', $ny);
        $end = self::at('2026-11-07 01:30', $ny);
        $info = date_util::describe_range(['start' => $start, 'end' => $end], $ny, 'en_us');
        $this->assertSame("11/6/2026 11:30 PM \u{2013} 1:30 AM +1", $info['text']);
        $this->assertSame(['start' => $start, 'end' => $end], date_util::parse_range($info['text'], $ny, 'en_us'));
    }

    public function test_format_range_html(): void {
        $prague = new \DateTimeZone('Europe/Prague');
        $this->assertSame('', date_util::format_range_html(null, $prague, 'en'));

        $start = self::at('2026-11-06 11:30', $prague);
        $range = ['start' => $start, 'end' => self::at('2026-11-06 12:30', $prague)];
        $this->assertSame(
            "<span class=\"muform-datetimerange-text text-nowrap\">2026-11-06 11:30 \u{2013} 12:30</span>",
            date_util::format_range_html($range, $prague, 'en')
        );

        $range = ['start' => self::at('2026-11-06 23:30', $prague), 'end' => self::at('2026-11-07 01:30', $prague)];
        $html = date_util::format_range_html($range, $prague, 'en');
        $this->assertSame(
            "<span class=\"muform-datetimerange-text text-nowrap\">2026-11-06 23:30 \u{2013} 01:30"
                . "<sup title=\"2026-11-07 01:30\">+1</sup></span>",
            $html
        );
        // Copied text can be pasted back.
        $this->assertSame($range, date_util::parse_range(strip_tags($html), $prague, 'en'));
    }

    public function test_is_valid_range(): void {
        $prague = new \DateTimeZone('Europe/Prague');
        $valid = fn(string $start, string $end): bool
            => date_util::is_valid_range(self::at($start, $prague), self::at($end, $prague), $prague);
        $this->assertTrue($valid('2026-11-06 11:30', '2026-11-06 11:31'));
        $this->assertTrue($valid('2026-11-06 11:30', '2026-11-07 11:30'));
        $this->assertFalse($valid('2026-11-06 11:30', '2026-11-06 11:30'));
        $this->assertFalse($valid('2026-11-06 11:30', '2026-11-06 11:00'));
        $this->assertFalse($valid('2026-11-06 11:30', '2026-11-07 11:31'));
        // Wall clock time decides across DST changes.
        $this->assertTrue($valid('2026-10-24 12:00', '2026-10-25 12:00'));
        $this->assertFalse($valid('2026-03-28 12:00', '2026-03-29 12:01'));
        $this->assertFalse(date_util::is_valid_range(
            self::at('2026-03-28 12:00', $prague),
            self::at('2026-03-28 12:00', $prague) + DAYSECS,
            $prague
        ));
    }

    public function test_get_range_limit(): void {
        $prague = new \DateTimeZone('Europe/Prague');
        $this->assertSame(
            self::at('2026-11-07 11:30', $prague),
            date_util::get_range_limit(self::at('2026-11-06 11:30', $prague), $prague)
        );
        $start = self::at('2026-10-24 12:00', $prague);
        $this->assertSame($start + 25 * HOURSECS, date_util::get_range_limit($start, $prague));
    }

    public function test_get_duration_text(): void {
        $this->assertSame('0 minutes', date_util::get_duration_text(0));
        $this->assertSame('30 minutes', date_util::get_duration_text(1800));
        $this->assertSame('1 hour', date_util::get_duration_text(3600));
        $this->assertSame('1 day 2 hours 5 seconds', date_util::get_duration_text(DAYSECS + 2 * HOURSECS + 5));
    }

    public function test_get_day_offset(): void {
        $prague = new \DateTimeZone('Europe/Prague');
        $offset = fn(string $start, string $end): int
            => date_util::get_day_offset(self::at($start, $prague), self::at($end, $prague), $prague);
        $this->assertSame(0, $offset('2026-11-06 00:00', '2026-11-06 23:59'));
        $this->assertSame(1, $offset('2026-11-06 23:59', '2026-11-07 00:00'));
        // DST change day is shorter or longer.
        $this->assertSame(2, $offset('2026-10-24 12:00', '2026-10-26 00:30'));
        $this->assertSame(1, $offset('2026-03-29 00:00', '2026-03-30 00:00'));
    }

    public function test_get_timezone_name(): void {
        $this->assertSame('UTC', date_util::get_timezone_name('UTC'));
        $this->assertSame(get_string('europe/prague', 'core_timezones'), date_util::get_timezone_name('Europe/Prague'));
    }

    public function test_to_text(): void {
        $this->assertSame('', date_util::to_text([]));
        $this->assertSame(
            '1 year 2 months 1 week 3 days 1 hour 1 minute 5 seconds',
            date_util::to_text(['y' => 1, 'm' => 2, 'w' => 1, 'd' => 3, 'h' => 1, 'i' => 1, 's' => 5])
        );
        $this->assertSame('2 hours 30 minutes', date_util::to_text(['h' => '2', 'i' => '30', 'y' => 0]));
        $this->assertSame('weeks', date_util::get_unit_label('w'));
    }

    public function test_parse_interval(): void {
        $this->assertSame(
            ['y' => 1, 'm' => 2, 'w' => 1, 'd' => 3, 'h' => 1, 'i' => 2, 's' => 3],
            date_util::parse_interval('P1Y2M1W3DT1H2M3S')
        );
        $this->assertSame(['y' => 0, 'm' => 6, 'w' => 0, 'd' => 0, 'h' => 0, 'i' => 0, 's' => 0], date_util::parse_interval('P6M'));
        $this->assertSame(
            ['y' => 0, 'm' => 0, 'w' => 0, 'd' => 0, 'h' => 36, 'i' => 0, 's' => 0],
            date_util::parse_interval(' PT36H ')
        );
        $this->assertSame(['y' => 0, 'm' => 0, 'w' => 0, 'd' => 0, 'h' => 0, 'i' => 0, 's' => 0], date_util::parse_interval('P0D'));
        $this->assertSame(
            ['y' => 0, 'm' => 0, 'w' => 1, 'd' => 2, 'h' => 0, 'i' => 0, 's' => 0],
            date_util::parse_interval('P1W2D')
        );

        $this->assertNull(date_util::parse_interval(''));
        $this->assertNull(date_util::parse_interval('P'));
        $this->assertNull(date_util::parse_interval('PT'));
        $this->assertNull(date_util::parse_interval('P1DT'));
        $this->assertNull(date_util::parse_interval('P1M1Y'));
        $this->assertNull(date_util::parse_interval('P1H'));
        $this->assertNull(date_util::parse_interval('P-1D'));
        $this->assertNull(date_util::parse_interval('P1.5D'));
        $this->assertNull(date_util::parse_interval('1D'));
        $this->assertNull(date_util::parse_interval('p1d'));
    }

    public function test_build_interval(): void {
        $this->assertNull(date_util::build_interval([]));
        $this->assertNull(date_util::build_interval(['y' => 0, 'd' => '0']));
        $this->assertSame(
            'P1Y2M1W3DT1H2M3S',
            date_util::build_interval(['y' => 1, 'm' => 2, 'w' => 1, 'd' => 3, 'h' => 1, 'i' => 2, 's' => 3])
        );
        $this->assertSame('P6M', date_util::build_interval(['m' => 6]));
        $this->assertSame('PT36H', date_util::build_interval(['h' => '36']));
        $this->assertSame('P2M', date_util::build_interval(date_util::parse_interval('P0Y2M0DT0S')));
        // The result is always accepted by PHP.
        $interval = new \DateInterval(date_util::build_interval(['y' => 1, 'w' => 2, 's' => 5]));
        $this->assertSame(1, $interval->y);
        $this->assertSame(14, $interval->d);
        $this->assertSame(5, $interval->s);
    }
}
