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
use tool_mulib\route\api\muform\cohortpicker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Cohorts picker endpoint tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_mulib\route\api\muform\cohortpicker::class)]
#[Group('MuTMS')]
final class cohortpicker_test extends route_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->add_class_routes_to_route_loader(cohortpicker::class);
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
            '/muform/cohortpicker/search',
            ['HTTP_ACCEPT' => 'application/json', 'Content-Type' => 'application/json'],
            Utils::streamFor(json_encode($body)),
        );
    }

    public function test_search(): void {
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $category = $generator->create_category(['name' => 'Faculty']);
        $catcontext = \core\context\coursecat::instance($category->id);
        $c1 = $generator->create_cohort(['name' => 'Zeta one', 'idnumber' => 'Z1']);
        $c2 = $generator->create_cohort(['name' => 'Zeta two', 'contextid' => $catcontext->id]);
        $syscontext = system::instance();

        $response = $this->post([
            'source' => \tool_mulib\muform\cohortpicker\context_cohorts::class,
            'args' => [$syscontext->id],
            'query' => 'Zeta',
            'exclude' => [$c1->id],
        ]);
        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $payload = json_decode((string)$response->getBody(), true);
        $this->assertSame(1, $payload['total']);
        $this->assertFalse($payload['overflow']);
        $this->assertSame(['System', 'Faculty'], array_column($payload['nodes'], 'name'));
        $this->assertSame((int)$c2->id, $payload['cohorts'][0]['id']);
        $this->assertSame('Zeta two', $payload['cohorts'][0]['name']);
        $this->assertSame((int)$catcontext->id, $payload['cohorts'][0]['nodeid']);

        $response = $this->post([
            'source' => \tool_mulib\muform\cohortpicker\context_cohorts::class,
            'args' => [$syscontext->id],
            'query' => 'z1',
        ]);
        $payload = json_decode((string)$response->getBody(), true);
        $this->assertSame([(int)$c1->id], array_column($payload['cohorts'], 'id'));
    }

    public function test_bad_sources(): void {
        $this->setAdminUser();
        $bad = [
            'tool_mulib\\muform\\userpicker\\site_users',
            'tool_mulib\\muform\\cohortpicker\\base',
            'tool_mulib\\muform\\cohortpicker\\missing',
            'tool_nope\\muform\\cohortpicker\\context_cohorts',
            '',
        ];
        foreach ($bad as $source) {
            $response = $this->post(['source' => $source, 'args' => [1], 'query' => '']);
            $this->assertSame(400, $response->getStatusCode(), $source);
        }
    }

    public function test_access(): void {
        $syscontext = system::instance();
        $body = ['source' => \tool_mulib\muform\cohortpicker\context_cohorts::class, 'args' => [$syscontext->id], 'query' => ''];

        $this->setUser(null);
        $this->assertNotSame(200, $this->post($body)->getStatusCode());
        $this->setGuestUser();
        $this->assertNotSame(200, $this->post($body)->getStatusCode());

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame(200, $this->post($body)->getStatusCode());
    }
}
