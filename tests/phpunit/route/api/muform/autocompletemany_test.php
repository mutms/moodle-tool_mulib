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
use tool_mulib\route\api\muform\autocompletemany;

/**
 * Multiple values autocomplete endpoint tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\route\api\muform\autocompletemany
 */
final class autocompletemany_test extends route_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->add_class_routes_to_route_loader(autocompletemany::class);
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
            '/muform/autocompletemany',
            ['HTTP_ACCEPT' => 'application/json', 'Content-Type' => 'application/json'],
            Utils::streamFor(json_encode($body)),
        );
    }

    public function test_search(): void {
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $u1 = $generator->create_user(['firstname' => 'Anna', 'lastname' => 'Zeta']);
        $u2 = $generator->create_user(['firstname' => 'Bert', 'lastname' => 'Zeta']);
        $syscontext = system::instance();

        $response = $this->post([
            'source' => \tool_mulib\muform\autocompletemany\site_users::class,
            'args' => [$syscontext->id],
            'query' => 'Zeta',
            'exclude' => [(string)$u1->id],
        ]);
        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $payload = json_decode((string)$response->getBody(), true);
        $this->assertFalse($payload['overflow']);
        $this->assertSame(50, $payload['maxitems']);
        $this->assertCount(1, $payload['list']);
        $this->assertSame((string)$u2->id, $payload['list'][0]['value']);
        $this->assertStringContainsString('Bert Zeta', $payload['list'][0]['label']);
    }

    public function test_bad_sources(): void {
        $this->setAdminUser();
        $bad = [
            'tool_mulib\\muform\\autocomplete\\site_user',
            'tool_mulib\\muform\\autocompletemany\\base',
            'tool_mulib\\muform\\autocompletemany\\missing',
            'tool_nope\\muform\\autocompletemany\\site_users',
            'tool_mulib\\muform\\element\\text',
            '',
        ];
        foreach ($bad as $source) {
            $response = $this->post(['source' => $source, 'args' => [1], 'query' => '']);
            $this->assertSame(400, $response->getStatusCode(), $source);
        }
        $response = $this->post([
            'source' => \tool_mulib\muform\autocompletemany\site_users::class,
            'args' => [[1]],
            'query' => '',
        ]);
        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_access(): void {
        $syscontext = system::instance();
        $body = ['source' => \tool_mulib\muform\autocompletemany\site_users::class, 'args' => [$syscontext->id], 'query' => ''];

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
