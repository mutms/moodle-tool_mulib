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

use core\context\system;
use core\tests\router\route_testcase;
use GuzzleHttp\Psr7\Utils;
use tool_mulib\route\api\muform\userpicker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Users picker endpoint tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mulib\route\api\muform\userpicker::class)]
#[Group('MuTMS')]
final class userpicker_test extends route_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->add_class_routes_to_route_loader(userpicker::class);
    }

    /**
     * Post JSON body to the endpoint.
     *
     * @param array $body
     * @return \Psr\Http\Message\ResponseInterface
     */
    private function post(array $body): \Psr\Http\Message\ResponseInterface {
        return $this->process_api_request(
            'POST',
            '/muform/userpicker/search',
            ['HTTP_ACCEPT' => 'application/json', 'Content-Type' => 'application/json'],
            Utils::streamFor(json_encode($body)),
        );
    }

    public function test_search(): void {
        $this->setAdminUser();
        set_config('showuseridentity', 'email');
        $generator = $this->getDataGenerator();
        $u1 = $generator->create_user(['firstname' => 'Anna', 'lastname' => 'Zeta', 'email' => 'anna@example.com']);
        $u2 = $generator->create_user(['firstname' => 'Bert', 'lastname' => 'Zeta', 'email' => 'bert@example.com']);
        $syscontext = system::instance();

        $response = $this->post([
            'source' => \tool_mulib\muform\userpicker\site_users::class,
            'args' => [$syscontext->id],
            'query' => 'Zeta',
            'exclude' => [$u1->id],
            'page' => 0,
        ]);
        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $payload = json_decode((string)$response->getBody(), true);
        $this->assertSame([['name' => 'email', 'label' => 'Email address']], $payload['columns']);
        $this->assertSame(1, $payload['total']);
        $this->assertSame(0, $payload['page']);
        $this->assertSame(50, $payload['perpage']);
        $this->assertSame((int)$u2->id, $payload['rows'][0]['id']);
        $this->assertSame('Bert Zeta', $payload['rows'][0]['fullname']);
        $this->assertSame(['email' => 'bert@example.com'], $payload['rows'][0]['fields']);
        $this->assertStringContainsString('Bert Zeta', $payload['rows'][0]['label']);
        $this->assertSame('active', $payload['filters'][0]['key']);
        $this->assertSame('1', $payload['filters'][0]['value']);
    }

    public function test_search_filters(): void {
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $u1 = $generator->create_user(['firstname' => 'Anna', 'lastname' => 'Zeta']);
        $u2 = $generator->create_user(['firstname' => 'Bert', 'lastname' => 'Zeta', 'suspended' => 1]);
        $body = [
            'source' => \tool_mulib\muform\userpicker\site_users::class,
            'args' => [system::instance()->id],
            'query' => 'Zeta',
        ];

        $payload = json_decode((string)$this->post($body)->getBody(), true);
        $this->assertSame([(int)$u1->id], array_column($payload['rows'], 'id'));

        $response = $this->post($body + ['filters' => ['active' => '0']]);
        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $payload = json_decode((string)$response->getBody(), true);
        $this->assertSame([(int)$u2->id], array_column($payload['rows'], 'id'));
        $this->assertSame('0', $payload['filters'][0]['value']);

        // All filters removed.
        $response = $this->post($body + ['filters' => new \stdClass()]);
        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $payload = json_decode((string)$response->getBody(), true);
        $this->assertSame([(int)$u1->id, (int)$u2->id], array_column($payload['rows'], 'id'));
        $this->assertNull($payload['filters'][0]['value']);
    }

    public function test_bad_sources(): void {
        $this->setAdminUser();
        $bad = [
            'tool_mulib\\muform\\autocomplete\\site_users',
            'tool_mulib\\muform\\userpicker\\base',
            'tool_mulib\\muform\\userpicker\\missing',
            'tool_nope\\muform\\userpicker\\site_users',
            'tool_mulib\\muform\\element\\text',
            '',
        ];
        foreach ($bad as $source) {
            $response = $this->post(['source' => $source, 'args' => [1], 'query' => '']);
            $this->assertSame(400, $response->getStatusCode(), $source);
        }
        $response = $this->post(['source' => \tool_mulib\muform\userpicker\site_users::class, 'args' => [[1]], 'query' => '']);
        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_access(): void {
        $syscontext = system::instance();
        $body = ['source' => \tool_mulib\muform\userpicker\site_users::class, 'args' => [$syscontext->id], 'query' => ''];

        $this->setUser(null);
        $this->assertNotSame(200, $this->post($body)->getStatusCode());
        $this->setGuestUser();
        $this->assertNotSame(200, $this->post($body)->getStatusCode());

        $this->setUser($this->getDataGenerator()->create_user());
        $response = $this->post($body);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertStringContainsString('View user full information', (string)$response->getBody());
    }
}
