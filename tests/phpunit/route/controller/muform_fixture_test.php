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

namespace tool_mulib\phpunit\route\controller;

use core\router\route_loader_interface;
use core\router\util;
use core\tests\router\route_testcase;
use tool_mulib\muform\handler\dialog;
use tool_mulib\route\controller\muform_fixture;

/**
 * Muform in a router controller.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\route\controller\muform_fixture
 * @covers \tool_mulib\muform\handler
 * @covers \tool_mulib\muform\handler\dialog
 */
final class muform_fixture_test extends route_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    #[\Override]
    protected function tearDown(): void {
        $_POST = [];
        unset($_SERVER['REQUEST_METHOD']);
        parent::tearDown();
    }

    /**
     * Simulate posted form data.
     *
     * @param array $data
     */
    private function post(array $data): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $data + ['__formid' => 'tool_mulib_muform_elements_form', '__sesskey' => sesskey()];
    }

    public function test_index(): void {
        $response = $this->process_request('GET', 'tool_mulib/muform/fixture', route_loader_interface::ROUTE_GROUP_PAGE);
        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $html = (string)$response->getBody();
        $this->assertStringContainsString('Nothing yet', $html);
        $editurl = util::get_path_for_callable([muform_fixture::class, 'edit'])->out(false);
        $this->assertStringEndsWith('/tool_mulib/muform/fixture/edit', $editurl);
        $this->assertStringContainsString('data-muform-dialog-url="' . $editurl . '"', $html);
    }

    public function test_index_saved(): void {
        $path = 'tool_mulib/muform/fixture?saved=Jane';
        $response = $this->process_request('GET', $path, route_loader_interface::ROUTE_GROUP_PAGE);
        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertStringContainsString('Saved Jane', (string)$response->getBody());
    }

    public function test_edit_page(): void {
        $path = 'tool_mulib/muform/fixture/edit';
        $response = $this->process_request('GET', $path, route_loader_interface::ROUTE_GROUP_PAGE);
        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $html = (string)$response->getBody();
        $editurl = util::get_path_for_callable([muform_fixture::class, 'edit'])->out(false);
        $this->assertStringContainsString('action="' . $editurl . '"', $html);
        $this->assertStringContainsString('value="Jane Doe"', $html);
    }

    public function test_edit_page_post(): void {
        $path = 'tool_mulib/muform/fixture/edit';
        $this->post(['fullname' => 'Posted Jane', 'agree' => '1', 'country' => 'cz', 'starts' => '1790443723', 'submit' => '1']);
        $response = $this->process_request('POST', $path, route_loader_interface::ROUTE_GROUP_PAGE);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringEndsWith('/tool_mulib/muform/fixture?saved=Posted%20Jane', $response->getHeaderLine('Location'));
        $this->assertCount(1, \core\notification::fetch());

        $this->post(['fullname' => 'x', 'cancel' => '1']);
        $response = $this->process_request('POST', $path, route_loader_interface::ROUTE_GROUP_PAGE);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringEndsWith('/muform/fixture?cancelled=1', $response->getHeaderLine('Location'));

        // Rendering starts page output, so it is the last request of the test.
        $this->post(['fullname' => 'invalid', 'submit' => '1']);
        $response = $this->process_request('POST', $path, route_loader_interface::ROUTE_GROUP_PAGE);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('This name is not allowed', (string)$response->getBody());
    }

    public function test_edit_dialog(): void {
        $headers = ['HTTP_ACCEPT' => 'application/json', dialog::HEADER => '1'];
        $path = 'tool_mulib/muform/fixture/edit';

        $this->post(['fullname' => 'Dialog Jane', 'agree' => '1', 'country' => 'cz', 'starts' => '1790443723', 'submit' => '1']);
        $response = $this->process_request('POST', $path, route_loader_interface::ROUTE_GROUP_PAGE, $headers);
        $this->assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
        $payload = json_decode((string)$response->getBody(), true);
        $this->assertSame('submitted', $payload['status']);
        $this->assertSame(['fullname' => 'Dialog Jane'], $payload['data']);
        $this->assertStringEndsWith('/muform/fixture?saved=Dialog%20Jane', $payload['redirecturl']);
        $this->assertCount(1, \core\notification::fetch());

        $this->post(['fullname' => 'x', 'cancel' => '1']);
        $response = $this->process_request('POST', $path, route_loader_interface::ROUTE_GROUP_PAGE, $headers);
        $payload = json_decode((string)$response->getBody(), true);
        $this->assertSame('cancelled', $payload['status']);

        // Rendering starts page output, so it is the last request of the test.
        $_POST = [];
        $response = $this->process_request('GET', $path, route_loader_interface::ROUTE_GROUP_PAGE, $headers);
        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string)$response->getBody(), true);
        $this->assertSame('render', $payload['status']);
        $this->assertSame('Edit fixture', $payload['title']);
        $this->assertStringContainsString('value="Jane Doe"', $payload['html']);
        $this->assertStringContainsString('tool_mulib/muform/form', $payload['javascript'] . $payload['html']);
    }

    public function test_edit_dialog_invalid(): void {
        $headers = ['HTTP_ACCEPT' => 'application/json', dialog::HEADER => '1'];
        $this->post(['fullname' => 'invalid', 'submit' => '1']);
        $path = 'tool_mulib/muform/fixture/edit';
        $response = $this->process_request('POST', $path, route_loader_interface::ROUTE_GROUP_PAGE, $headers);
        $payload = json_decode((string)$response->getBody(), true);
        $this->assertSame('render', $payload['status']);
        $this->assertStringContainsString('This name is not allowed', $payload['html']);
    }

    public function test_access(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $response = $this->process_request('GET', 'tool_mulib/muform/fixture', route_loader_interface::ROUTE_GROUP_PAGE);
        $this->assertNotSame(200, $response->getStatusCode());
    }
}
