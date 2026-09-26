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
use core_tag_area;
use core_tag_tag;
use GuzzleHttp\Psr7\Utils;
use tool_mulib\muform\tagarea\course;
use tool_mulib\route\api\muform\tags;

/**
 * Tags suggestion endpoint tests.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\route\api\muform\tags
 * @covers \tool_mulib\muform\tagarea\base
 * @covers \tool_mulib\muform\tagarea\course
 */
final class tags_test extends route_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->add_class_routes_to_route_loader(tags::class);
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
            '/muform/tags',
            ['HTTP_ACCEPT' => 'application/json', 'Content-Type' => 'application/json'],
            Utils::streamFor(json_encode($body)),
        );
    }

    public function test_search(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['tags' => ['Physical']]);
        $collid = core_tag_area::get_collection('core', 'course');
        core_tag_tag::create_if_missing($collid, ['Physics', 'Chemistry', 'Philosophy'], true);

        $response = $this->post(['area' => course::class, 'args' => [$course->id], 'query' => 'PH', 'exclude' => ['philosophy']]);
        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $payload = json_decode((string)$response->getBody(), true);
        // Only standard tags are suggested.
        $this->assertSame(['list' => ['Physics'], 'overflow' => false, 'maxitems' => 50], $payload);

        $response = $this->post(['area' => course::class, 'args' => [$course->id], 'query' => '']);
        $payload = json_decode((string)$response->getBody(), true);
        $this->assertSame(['Chemistry', 'Philosophy', 'Physics'], $payload['list']);

        core_tag_area::update(core_tag_area::get_areas()['course']['core'], ['showstandard' => core_tag_tag::HIDE_STANDARD]);
        $response = $this->post(['area' => course::class, 'args' => [$course->id], 'query' => '']);
        $payload = json_decode((string)$response->getBody(), true);
        $this->assertSame([], $payload['list']);
    }

    public function test_bad_areas(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $bad = [
            'tool_mulib\\muform\\tagarea\\base',
            'tool_mulib\\muform\\tagarea\\missing',
            'tool_nope\\muform\\tagarea\\course',
            'tool_mulib\\muform\\autocompletemany\\site_users',
            '',
        ];
        foreach ($bad as $area) {
            $response = $this->post(['area' => $area, 'args' => [$course->id], 'query' => '']);
            $this->assertSame(400, $response->getStatusCode(), $area);
        }
        $response = $this->post(['area' => course::class, 'args' => [[1]], 'query' => '']);
        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_access(): void {
        $course = $this->getDataGenerator()->create_course();
        $body = ['area' => course::class, 'args' => [$course->id], 'query' => ''];

        $this->setUser(null);
        $this->assertNotSame(200, $this->post($body)->getStatusCode());

        $this->setGuestUser();
        $this->assertNotSame(200, $this->post($body)->getStatusCode());

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertNotSame(200, $this->post($body)->getStatusCode());

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $this->assertSame(200, $this->post($body)->getStatusCode());
    }
}
