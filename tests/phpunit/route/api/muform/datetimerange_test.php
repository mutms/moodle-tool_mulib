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

namespace tool_mulib\phpunit\route\api\muform;

use core\tests\router\route_testcase;
use GuzzleHttp\Psr7\Utils;
use tool_mulib\route\api\muform\datetimerange;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Datetime range normalisation endpoint tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mulib\route\api\muform\datetimerange::class)]
#[Group('MuTMS')]
final class datetimerange_test extends route_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->add_class_routes_to_route_loader(datetimerange::class);
    }

    /**
     * Post JSON body to the endpoint.
     *
     * @param array $body
     * @param int $status expected status code
     * @return array decoded payload
     */
    private function post(array $body, int $status = 200): array {
        $response = $this->process_api_request(
            'POST',
            '/muform/datetimerange',
            ['HTTP_ACCEPT' => 'application/json', 'Content-Type' => 'application/json'],
            Utils::streamFor(json_encode($body)),
        );
        $this->assertSame($status, $response->getStatusCode(), (string)$response->getBody());
        return json_decode((string)$response->getBody(), true);
    }

    /**
     * Timestamp of a wall time in Prague.
     *
     * @param string $text
     * @return int
     */
    private static function at(string $text): int {
        return (new \DateTime($text, new \DateTimeZone('Europe/Prague')))->getTimestamp();
    }

    public function test_valid_text(): void {
        $payload = $this->post(['text' => '2026-11-06 23:30 - 01:30', 'timezone' => 'Europe/Prague', 'lang' => 'en']);
        $this->assertSame([
            'text' => "2026-11-06 23:30 \u{2013} 01:30 +1",
            'start' => self::at('2026-11-06 23:30'),
            'end' => self::at('2026-11-07 01:30'),
            'duration' => 7200,
            'dayoffset' => 1,
            'startcomponents' => ['year' => 2026, 'month' => 11, 'day' => 6, 'hour' => 23, 'minute' => 30, 'second' => 0],
            'endcomponents' => ['year' => 2026, 'month' => 11, 'day' => 7, 'hour' => 1, 'minute' => 30, 'second' => 0],
            'endtext' => '2026-11-07 01:30',
            'hint' => '2 hours, ends 2026-11-07 01:30',
            'valid' => true,
            'timezone' => 'Europe/Prague',
        ], $payload);

        $payload = $this->post(['text' => '', 'timezone' => 'UTC', 'lang' => 'en']);
        $this->assertTrue($payload['valid']);
        $this->assertSame('', $payload['text']);
        $this->assertNull($payload['start']);
        $this->assertNull($payload['end']);
    }

    public function test_defaults(): void {
        $body = ['text' => '2026-11-06', 'timezone' => 'Europe/Prague', 'lang' => 'en'];
        $this->assertFalse($this->post($body)['valid']);
        $payload = $this->post($body + ['defaulthour' => 9, 'defaultminute' => 15, 'defaultduration' => 3600]);
        $this->assertTrue($payload['valid']);
        $this->assertSame(self::at('2026-11-06 09:15'), $payload['start']);
        $this->assertSame(self::at('2026-11-06 10:15'), $payload['end']);
        $this->assertSame("2026-11-06 09:15 \u{2013} 10:15", $payload['text']);
        $this->assertSame('1 hour', $payload['hint']);
    }

    public function test_czech(): void {
        \tool_mulib\tests\fake_langpack::install('cs');
        $payload = $this->post(['text' => '6.11.26 11.30 - 13.00', 'timezone' => 'Europe/Prague', 'lang' => 'cs']);
        $this->assertTrue($payload['valid']);
        $this->assertSame("6. 11. 2026 11:30 \u{2013} 13:00", $payload['text']);
        $this->assertSame(self::at('2026-11-06 11:30'), $payload['start']);
        $this->assertSame('1 hodina 30 minut', $payload['hint']);
        $this->assertSame('en', current_language());
    }

    public function test_invalid_text(): void {
        $payload = $this->post(['text' => 'sometime soon', 'timezone' => 'Europe/Prague', 'lang' => 'en']);
        $this->assertFalse($payload['valid']);
        $this->assertSame('sometime soon', $payload['text']);
        $this->assertNull($payload['start']);
        $this->assertNull($payload['end']);
        $this->assertSame('Europe/Prague', $payload['timezone']);
    }

    public function test_invalid_parameters(): void {
        $body = ['text' => '2026-11-06 11:30', 'timezone' => 'UTC', 'lang' => 'en'];
        $this->post(['timezone' => 'Mars/Olympus'] + $body, 400);
        $this->post(['lang' => 'xx'] + $body, 400);
        foreach ([['defaulthour' => 24], ['defaultminute' => -1], ['defaultduration' => -10]] as $default) {
            $this->post($body + $default, 400);
        }
    }

    public function test_no_login_needed(): void {
        $this->setUser(null);
        $payload = $this->post(['text' => '2026-11-06 11:30 - 12:30', 'timezone' => 'UTC', 'lang' => 'en']);
        $this->assertTrue($payload['valid']);
    }
}
