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
use tool_mulib\route\api\muform\datetime;

/**
 * Datetime normalisation endpoint tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\route\api\muform\datetime
 */
final class datetime_test extends route_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->add_class_routes_to_route_loader(datetime::class);
    }

    /**
     * Post JSON body to the endpoint.
     *
     * @param array $body
     * @return array decoded payload
     */
    private function post(array $body): array {
        $response = $this->process_api_request(
            'POST',
            '/muform/datetime',
            ['HTTP_ACCEPT' => 'application/json', 'Content-Type' => 'application/json'],
            Utils::streamFor(json_encode($body)),
        );
        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        return json_decode((string)$response->getBody(), true);
    }

    public function test_valid_text(): void {
        $payload = $this->post(['text' => '2026-09-26 19:28', 'timezone' => 'Europe/Prague', 'lang' => 'en']);
        $this->assertSame([
            'text' => '2026-09-26 19:28',
            'timestamp' => 1790443680,
            'components' => ['year' => 2026, 'month' => 9, 'day' => 26, 'hour' => 19, 'minute' => 28, 'second' => 0],
            'valid' => true,
            'timezone' => 'Europe/Prague',
        ], $payload);

        $payload = $this->post(['text' => '1790450880', 'timezone' => 'UTC', 'lang' => 'en']);
        $this->assertSame('2026-09-26 19:28', $payload['text']);
        $this->assertSame(1790450880, $payload['timestamp']);

        $payload = $this->post(['text' => '', 'timezone' => 'UTC', 'lang' => 'en']);
        $this->assertTrue($payload['valid']);
        $this->assertSame('', $payload['text']);
        $this->assertNull($payload['timestamp']);
        $this->assertNull($payload['components']);
    }

    public function test_custom_format(): void {
        $body = ['text' => '26. 9. 2026 19:28', 'timezone' => 'Europe/Prague', 'lang' => 'en', 'format' => 'j. n. Y H:i'];
        $payload = $this->post($body);
        $this->assertTrue($payload['valid']);
        $this->assertSame('26. 9. 2026 19:28', $payload['text']);
        $this->assertSame(1790443680, $payload['timestamp']);
    }

    public function test_invalid_text(): void {
        $payload = $this->post(['text' => 'sometime soon', 'timezone' => 'Europe/Prague', 'lang' => 'en']);
        $this->assertSame([
            'valid' => false,
            'text' => 'sometime soon',
            'timestamp' => null,
            'components' => null,
            'timezone' => 'Europe/Prague',
        ], $payload);
    }

    public function test_invalid_parameters(): void {
        $response = $this->process_api_request(
            'POST',
            '/muform/datetime',
            ['HTTP_ACCEPT' => 'application/json', 'Content-Type' => 'application/json'],
            Utils::streamFor(json_encode(['text' => 'now', 'timezone' => 'Mars/Olympus', 'lang' => 'en'])),
        );
        $this->assertSame(400, $response->getStatusCode());

        $response = $this->process_api_request(
            'POST',
            '/muform/datetime',
            ['HTTP_ACCEPT' => 'application/json', 'Content-Type' => 'application/json'],
            Utils::streamFor(json_encode(['text' => 'now', 'timezone' => 'UTC', 'lang' => 'xx'])),
        );
        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_no_login_needed(): void {
        $this->setUser(null);
        $payload = $this->post(['text' => '2026-09-26 19:28:43', 'timezone' => 'UTC', 'lang' => 'en']);
        $this->assertTrue($payload['valid']);
        $this->assertSame(1790450923, $payload['timestamp']);
    }
}
