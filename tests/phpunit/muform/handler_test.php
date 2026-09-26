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

namespace tool_mulib\phpunit\muform;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/muform_testcase.php');

use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use tool_mulib\muform\handler;
use tool_mulib\muform\handler\dialog;
use tool_mulib\muform\handler\page;
use tool_mulib\phpunit\muform\fixtures\simple_form;

/**
 * Form handler tests.
 *
 * Classic scripts stop after each answer, the tests use the router mode which returns the answers.
 *
 * @group       MuTMS
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mulib\muform\handler
 * @covers \tool_mulib\muform\handler\dialog
 * @covers \tool_mulib\muform\handler\page
 */
final class handler_test extends muform_testcase {
    #[\Override]
    protected function tearDown(): void {
        unset($_SERVER['HTTP_X_MUFORM_DIALOG']);
        parent::tearDown();
    }

    /**
     * New router response.
     *
     * @return ResponseInterface
     */
    private function response(): ResponseInterface {
        return \core\di::get(ResponseFactoryInterface::class)->createResponse(200);
    }

    /**
     * New router handler.
     *
     * @param bool $dialog
     * @return handler
     */
    private function handler(bool $dialog): handler {
        $request = new ServerRequest('GET', '/x');
        if ($dialog) {
            $request = $request->withHeader(dialog::HEADER, '1');
        }
        return handler::from_request($request, $this->response());
    }

    /**
     * Decode JSON response.
     *
     * @param ResponseInterface $response
     * @return array
     */
    private function decode(ResponseInterface $response): array {
        $this->assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        return json_decode((string)$response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_from_request(): void {
        $handler = handler::from_request();
        $this->assertInstanceOf(page::class, $handler);
        $this->assertFalse($handler->is_dialog());
        $_SERVER['HTTP_X_MUFORM_DIALOG'] = '1';
        $handler = handler::from_request();
        $this->assertInstanceOf(dialog::class, $handler);
        $this->assertTrue($handler->is_dialog());
        $_SERVER['HTTP_X_MUFORM_DIALOG'] = '0';
        $this->assertInstanceOf(page::class, handler::from_request());

        $this->assertInstanceOf(page::class, $this->handler(false));
        $this->assertInstanceOf(dialog::class, $this->handler(true));

        try {
            handler::from_request(new ServerRequest('GET', '/x'));
            $this->fail('Exception expected');
        } catch (\core\exception\coding_exception $ex) {
            $this->assertStringContainsString('Router response is required', $ex->getMessage());
        }
        try {
            handler::from_request(null, $this->response());
            $this->fail('Exception expected');
        } catch (\core\exception\coding_exception $ex) {
            $this->assertStringContainsString('Router request is required', $ex->getMessage());
        }
    }

    public function test_dialog_render(): void {
        global $PAGE;
        $PAGE->set_url('/admin/tool/mulib/tests/behat/fixtures/muform_wizard.php');
        $PAGE->set_context(\core\context\system::instance());
        $PAGE->set_heading('Page <i>heading</i>');
        $form = new simple_form($this->get_url(), ['name' => 'Jane']);
        $requires = $PAGE->requires;

        $response = $this->handler(true)->render(function (\core\output\core_renderer $output) use ($form, $PAGE) {
            echo 'stray';
            $PAGE->requires->js_call_amd('core/first', 'init');
            return $form->render($output);
        }, 'Edit <b>item</b>');

        $data = $this->decode($response);
        $this->assertSame('render', $data['status']);
        $this->assertSame('Edit &#60;b&#62;item&#60;/b&#62;', $data['title']);
        $this->assertStringStartsWith('stray<form ', $data['html']);
        $this->assertStringContainsString('value="Jane"', $data['html']);
        $this->assertStringContainsString('core/first', $data['javascript']);
        $this->assertStringContainsString('<script', $data['javascript']);
        $this->assertSame($requires, $PAGE->requires);
    }

    public function test_dialog_render_form(): void {
        global $PAGE;
        $PAGE->set_url('/admin/tool/mulib/tests/behat/fixtures/muform_wizard.php');
        $PAGE->set_context(\core\context\system::instance());
        $PAGE->set_heading('Page <i>heading</i>');
        $form = new simple_form($this->get_url(), ['name' => 'Jane']);

        $data = $this->decode($this->handler(true)->render($form));
        $this->assertSame('Page heading', $data['title']);
        $this->assertStringStartsWith('<form ', $data['html']);
    }

    public function test_dialog_submitted_and_cancelled(): void {
        $url = new \core\url('/admin/tool/mulib/tests/behat/fixtures/muform_wizard.php', ['id' => 3]);

        $data = $this->decode($this->handler(true)->submitted($url, ['id' => 3]));
        $this->assertSame(['status' => 'submitted', 'redirecturl' => $url->out(false), 'data' => ['id' => 3]], $data);

        $data = $this->decode($this->handler(true)->submitted($url));
        $this->assertSame(['status' => 'submitted', 'redirecturl' => $url->out(false), 'data' => []], $data);

        $data = $this->decode($this->handler(true)->cancelled($url));
        $this->assertSame(['status' => 'cancelled', 'redirecturl' => $url->out(false)], $data);
    }

    public function test_page_submitted_and_cancelled(): void {
        $url = new \core\url('/admin/tool/mulib/tests/behat/fixtures/muform_wizard.php', ['id' => 3]);

        $response = $this->handler(false)->submitted($url, ['id' => 3]);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($url->out(false), $response->getHeaderLine('Location'));

        $response = $this->handler(false)->cancelled($url);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($url->out(false), $response->getHeaderLine('Location'));
    }

    public function test_page_render(): void {
        global $PAGE;
        $PAGE->set_url('/admin/tool/mulib/tests/behat/fixtures/muform_wizard.php');
        $PAGE->set_context(\core\context\system::instance());
        $form = new simple_form($this->get_url(), ['name' => 'Jane']);

        $response = $this->handler(false)->render($form);
        $html = (string)$response->getBody();
        $this->assertStringContainsString('<html', $html);
        $this->assertStringContainsString('value="Jane"', $html);
    }
}
