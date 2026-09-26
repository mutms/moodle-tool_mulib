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

/**
 * Calendar util tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\util\calendar
 */
final class calendar_test extends \advanced_testcase {
    public function test_get_format(): void {
        $this->assertSame('Y-m-d H:i', calendar::get_format('en'));
    }

    public function test_parse(): void {
        $prague = new \DateTimeZone('Europe/Prague');
        $utc = new \DateTimeZone('UTC');
        $format = 'Y-m-d H:i:s';

        $this->assertNull(calendar::parse('', $utc, $format));
        $this->assertNull(calendar::parse('   ', $utc, $format));
        $this->assertSame(1790450923, calendar::parse('1790450923', $utc, $format));
        $this->assertSame(-10, calendar::parse(' -10 ', $utc, $format));

        // Display format round trip in both timezones.
        $this->assertSame(1790443723, calendar::parse('2026-09-26 17:28:43', $utc, $format));
        $this->assertSame(1790443723, calendar::parse('2026-09-26 19:28:43', $prague, $format));

        // Localised formats, fields not in format are zero.
        $this->assertSame(1790443680, calendar::parse('26. 9. 2026 19:28', $prague, 'j. n. Y H:i'));
        $this->assertSame(1790443680, calendar::parse('9/26/2026 7:28 PM', $prague, 'n/j/Y g:i A'));

        // Anything PHP understands.
        $this->assertSame(1790443680, calendar::parse('2026-09-26 19:28', $prague, $format));
        $this->assertSame(1790443680, calendar::parse('2026-09-26T19:28', $prague, $format));
        $this->assertSame(1790443680, calendar::parse('2026-09-26T17:28:00Z', $prague, $format));
        $this->assertSame(1790443680, calendar::parse('26 September 2026 19:28', $prague, $format));
        $this->assertSame(1790380800, calendar::parse('2026-09-26', $utc, $format));
        $tomorrow = (new \DateTime('now', $utc))->modify('+1 day')->setTime(10, 0)->getTimestamp();
        $this->assertSame($tomorrow, calendar::parse('tomorrow 10:00', $utc, $format));
    }

    public function test_parse_invalid(): void {
        $utc = new \DateTimeZone('UTC');
        $format = 'Y-m-d H:i:s';
        foreach (['abc', '2026-02-30 10:00:00', '2026-02-30', '2026-13-01', '12:99'] as $text) {
            try {
                calendar::parse($text, $utc, $format);
                $this->fail('Exception expected for: ' . $text);
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString($text, $e->getMessage());
            }
        }
    }

    public function test_describe(): void {
        $prague = new \DateTimeZone('Europe/Prague');
        $format = 'Y-m-d H:i:s';

        $this->assertSame(['text' => '', 'timestamp' => null, 'components' => null], calendar::describe(null, $prague, $format));

        $expected = [
            'text' => '2026-09-26 19:28:43',
            'timestamp' => 1790443723,
            'components' => ['year' => 2026, 'month' => 9, 'day' => 26, 'hour' => 19, 'minute' => 28, 'second' => 43],
        ];
        $this->assertSame($expected, calendar::describe(1790443723, $prague, $format));
        $this->assertSame('26. 9. 2026 19:28', calendar::describe(1790443723, $prague, 'j. n. Y H:i')['text']);
        $this->assertSame('2026-09-26 17:28:43', calendar::describe(1790443723, new \DateTimeZone('UTC'), $format)['text']);
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
