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

namespace tool_mulib\phpunit\muform\util;

use tool_mulib\muform\util\calendar;
use tool_mulib\local\lang_util;
use tool_mulib\tests\fake_langpack;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Calendar util tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mulib\muform\util\calendar::class)]
#[Group('MuTMS')]
final class calendar_test extends \advanced_testcase {
    public function test_get_format(): void {
        $this->resetAfterTest();
        $this->assertSame('Y-m-d H:i', calendar::get_format('en'));

        fake_langpack::install('cs');
        $this->assertSame('j. n. Y H:i', calendar::get_format('cs'));

        // Formats from langconfig are not compatible.
        lang_util::customise_string('cs', 'tool_mulib', 'muform_datetimeformat', '%d.%m.%Y %H:%M');
        $this->assertSame('Y-m-d H:i', calendar::get_format('cs'));
        $this->assertDebuggingCalled("Invalid muform_datetimeformat '%d.%m.%Y %H:%M' in language 'cs', PHP date() format expected");

        lang_util::customise_string('cs', 'tool_mulib', 'muform_datetimeformat', ' ');
        $this->assertSame('Y-m-d H:i', calendar::get_format('cs'));
    }

    public function test_parse(): void {
        $prague = new \DateTimeZone('Europe/Prague');
        $utc = new \DateTimeZone('UTC');

        $this->assertNull(calendar::parse('', $utc, 'en'));
        $this->assertNull(calendar::parse('   ', $utc, 'en'));
        $this->assertSame(1790450923, calendar::parse('1790450923', $utc, 'en'));
        $this->assertSame(-10, calendar::parse(' -10 ', $utc, 'en'));

        // English display format is ISO, year first only.
        $this->assertSame(1790443723, calendar::parse('2026-09-26 17:28:43', $utc, 'en'));
        $this->assertSame(1790443723, calendar::parse('2026-09-26 19:28:43', $prague, 'en'));
        $this->assertSame(1790443680, calendar::parse('2026-09-26 19:28', $prague, 'en'));
        $this->assertSame(1790443680, calendar::parse('2026-09-26T19:28', $prague, 'en'));
        $this->assertSame(1790443680, calendar::parse('2026-09-26T17:28:00Z', $prague, 'en'));
        $this->assertSame(1790443680, calendar::parse('2026-09-26T21:28:00+04:00', $prague, 'en'));
        $this->assertSame(1790380800, calendar::parse('2026-09-26', $utc, 'en'));
        $this->assertSame(1790380800, calendar::parse('2026-9-26', $utc, 'en'));
        $this->assertSame(1790380800, calendar::parse('2026/9/26', $utc, 'en'));

        // Time only means today.
        $today = (new \DateTime('now', $prague))->setTime(14, 30)->getTimestamp();
        $this->assertSame($today, calendar::parse('14:30', $prague, 'en'));
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
            $this->assertSame($nov4time, calendar::parse($text, $prague, 'cs'), $text);
        }
        $this->assertSame($nov4time + 15, calendar::parse('4.11.2026 14:30:15', $prague, 'cs'));

        // Date without time.
        foreach (['4. 11. 2026', '4.11.2026', '04.11.26', '4/11/2026', '2026-11-04'] as $text) {
            $this->assertSame($nov4, calendar::parse($text, $prague, 'cs'), $text);
        }

        // Date without year.
        $year = (int)(new \DateTime('now', $prague))->format('Y');
        $expected = (new \DateTime("$year-11-04 00:00", $prague))->getTimestamp();
        $this->assertSame($expected, calendar::parse('4. 11.', $prague, 'cs'));
        $this->assertSame($expected, calendar::parse('4.11.', $prague, 'cs'));
        $this->assertSame($expected + 14 * HOURSECS + 30 * MINSECS, calendar::parse('4. 11. 14:30', $prague, 'cs'));
    }

    public function test_parse_us(): void {
        $this->resetAfterTest();
        fake_langpack::install('en_us');
        $ny = new \DateTimeZone('America/New_York');
        $nov6 = (new \DateTime('2026-11-06 00:00', $ny))->getTimestamp();
        $nov6time = (new \DateTime('2026-11-06 14:30', $ny))->getTimestamp();

        // Plugin ships en_us override of the display format.
        $this->assertSame('n/j/Y g:i A', calendar::get_format('en_us'));
        $this->assertTrue(calendar::uses_12_hour_clock('en_us'));
        $this->assertFalse(calendar::uses_12_hour_clock('en'));
        $this->assertSame('11/6/2026 2:30 PM', calendar::describe($nov6time, $ny, 'en_us')['text']);
        $this->assertSame('11/6/2026 12:00 AM', calendar::describe($nov6, $ny, 'en_us')['text']);

        $texts = [
            '11/6/2026 2:30 PM', '11/6/26 2:30 pm', '11/6/2026 2:30PM', '11/6/2026 2:30 p.m.', '11/06/2026, 02:30 PM',
            '11/6/2026 14:30', '2026-11-06 14:30', '2026-11-06 2:30 PM',
        ];
        foreach ($texts as $text) {
            $this->assertSame($nov6time, calendar::parse($text, $ny, 'en_us'), $text);
        }
        $this->assertSame($nov6 + 14 * HOURSECS, calendar::parse('11/6/2026 2 PM', $ny, 'en_us'));
        $this->assertSame($nov6 + 15 * MINSECS, calendar::parse('11/6/2026 12:15 AM', $ny, 'en_us'));
        $this->assertSame($nov6 + 12 * HOURSECS, calendar::parse('11/6/2026 12:00 pm', $ny, 'en_us'));
        $this->assertSame($nov6, calendar::parse('11/6/2026', $ny, 'en_us'));
        $this->assertSame($nov6 + 23 * HOURSECS + 59 * MINSECS, calendar::parse('11/6/26', $ny, 'en_us', 23, 59));

        // Time only means today.
        $today = (new \DateTime('now', $ny))->setTime(14, 0)->getTimestamp();
        $this->assertSame($today, calendar::parse('2 pm', $ny, 'en_us'));
        $this->assertSame($today, calendar::parse('2:00PM', $ny, 'en_us'));

        foreach (['13/6/2026', '11/31/2026', '11/6/2026 13:00 PM', '11/6/2026 0:30 am', '11/6/2026 2', '11/6/2026 2 xm'] as $text) {
            try {
                calendar::parse($text, $ny, 'en_us');
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
        $this->assertSame($nov4 + 14 * HOURSECS + 30 * MINSECS, calendar::parse('11/4/2026 14:30', $prague, 'en'));
        $this->assertSame($nov4, calendar::parse('11.4.26', $prague, 'en'));
        $this->assertSame($nov4, calendar::parse('2026-11-04', $prague, 'en'));
        // Text matching the display format exactly.
        $this->assertSame(1790443680, calendar::parse('9/26/2026 7:28 PM', $prague, 'en'));
        $this->assertSame('9/26/2026 7:28 PM', calendar::describe(1790443680, $prague, 'en')['text']);

        lang_util::customise_string('en', 'tool_mulib', 'muform_datetimeformat', 'F j, Y H:i');
        $this->assertSame(1790443680, calendar::parse('September 26, 2026 19:28', $prague, 'en'));
        $this->assertSame($nov4, calendar::parse('11/4/2026', $prague, 'en'));
        // Years are never shorter than 4 digits in exact format matches.
        try {
            calendar::parse('September 26, 26 19:28', $prague, 'en');
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
            calendar::parse('4. 11. 2026', $prague, 'cs', 23, 59)
        );
        $this->assertSame(
            (new \DateTime('2026-11-04 15:00', $prague))->getTimestamp(),
            calendar::parse('2026-11-04', $prague, 'en', 15)
        );
        // Explicit time wins.
        $this->assertSame(
            (new \DateTime('2026-11-04 08:00', $prague))->getTimestamp(),
            calendar::parse('4. 11. 2026 8:00', $prague, 'cs', 23, 59)
        );
        // Daylight saving time starts on 29 March 2026 at 2:00 in Prague.
        $this->assertSame(
            (new \DateTime('2026-03-29 15:00', $prague))->getTimestamp(),
            calendar::parse('29. 3. 2026', $prague, 'cs', 15)
        );
        $this->assertSame(
            (new \DateTime('2026-03-29 00:00', $prague))->getTimestamp(),
            calendar::parse('29. 3. 2026', $prague, 'cs')
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
                calendar::parse($text, $utc, 'cs');
                $this->fail('Exception expected for: ' . $text);
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString($text, $e->getMessage());
            }
        }

        // ISO display format accepts year first only.
        foreach (['4.11.2026', '11/4/2026', '4. 11.'] as $text) {
            try {
                calendar::parse($text, $utc, 'en');
                $this->fail('Exception expected for: ' . $text);
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString($text, $e->getMessage());
            }
        }
    }

    public function test_describe(): void {
        $this->resetAfterTest();
        $prague = new \DateTimeZone('Europe/Prague');

        $this->assertSame(['text' => '', 'timestamp' => null, 'components' => null], calendar::describe(null, $prague, 'en'));

        $expected = [
            'text' => '2026-09-26 19:28',
            'timestamp' => 1790443723,
            'components' => ['year' => 2026, 'month' => 9, 'day' => 26, 'hour' => 19, 'minute' => 28, 'second' => 43],
        ];
        $this->assertSame($expected, calendar::describe(1790443723, $prague, 'en'));
        $this->assertSame('2026-09-26 17:28', calendar::describe(1790443723, new \DateTimeZone('UTC'), 'en')['text']);

        fake_langpack::install('cs');
        $this->assertSame('26. 9. 2026 19:28', calendar::describe(1790443723, $prague, 'cs')['text']);
    }

    public function test_get_timezone_name(): void {
        $this->assertSame('UTC', calendar::get_timezone_name('UTC'));
        $this->assertSame(get_string('europe/prague', 'core_timezones'), calendar::get_timezone_name('Europe/Prague'));
    }

    public function test_to_text(): void {
        $this->assertSame('', calendar::to_text([]));
        $this->assertSame(
            '1 year 2 months 1 week 3 days 1 hour 1 minute 5 seconds',
            calendar::to_text(['y' => 1, 'm' => 2, 'w' => 1, 'd' => 3, 'h' => 1, 'i' => 1, 's' => 5])
        );
        $this->assertSame('2 hours 30 minutes', calendar::to_text(['h' => '2', 'i' => '30', 'y' => 0]));
        $this->assertSame('weeks', calendar::get_unit_label('w'));
    }

    public function test_parse_interval(): void {
        $this->assertSame(
            ['y' => 1, 'm' => 2, 'w' => 1, 'd' => 3, 'h' => 1, 'i' => 2, 's' => 3],
            calendar::parse_interval('P1Y2M1W3DT1H2M3S')
        );
        $this->assertSame(['y' => 0, 'm' => 6, 'w' => 0, 'd' => 0, 'h' => 0, 'i' => 0, 's' => 0], calendar::parse_interval('P6M'));
        $this->assertSame(
            ['y' => 0, 'm' => 0, 'w' => 0, 'd' => 0, 'h' => 36, 'i' => 0, 's' => 0],
            calendar::parse_interval(' PT36H ')
        );
        $this->assertSame(['y' => 0, 'm' => 0, 'w' => 0, 'd' => 0, 'h' => 0, 'i' => 0, 's' => 0], calendar::parse_interval('P0D'));
        $this->assertSame(
            ['y' => 0, 'm' => 0, 'w' => 1, 'd' => 2, 'h' => 0, 'i' => 0, 's' => 0],
            calendar::parse_interval('P1W2D')
        );

        $this->assertNull(calendar::parse_interval(''));
        $this->assertNull(calendar::parse_interval('P'));
        $this->assertNull(calendar::parse_interval('PT'));
        $this->assertNull(calendar::parse_interval('P1DT'));
        $this->assertNull(calendar::parse_interval('P1M1Y'));
        $this->assertNull(calendar::parse_interval('P1H'));
        $this->assertNull(calendar::parse_interval('P-1D'));
        $this->assertNull(calendar::parse_interval('P1.5D'));
        $this->assertNull(calendar::parse_interval('1D'));
        $this->assertNull(calendar::parse_interval('p1d'));
    }

    public function test_build_interval(): void {
        $this->assertNull(calendar::build_interval([]));
        $this->assertNull(calendar::build_interval(['y' => 0, 'd' => '0']));
        $this->assertSame(
            'P1Y2M1W3DT1H2M3S',
            calendar::build_interval(['y' => 1, 'm' => 2, 'w' => 1, 'd' => 3, 'h' => 1, 'i' => 2, 's' => 3])
        );
        $this->assertSame('P6M', calendar::build_interval(['m' => 6]));
        $this->assertSame('PT36H', calendar::build_interval(['h' => '36']));
        $this->assertSame('P2M', calendar::build_interval(calendar::parse_interval('P0Y2M0DT0S')));
        // The result is always accepted by PHP.
        $interval = new \DateInterval(calendar::build_interval(['y' => 1, 'w' => 2, 's' => 5]));
        $this->assertSame(1, $interval->y);
        $this->assertSame(14, $interval->d);
        $this->assertSame(5, $interval->s);
    }
}
