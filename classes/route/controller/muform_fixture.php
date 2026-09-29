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

namespace tool_mulib\route\controller;

use core\param;
use core\router\require_login;
use core\router\route;
use core\router\schema\parameters\query_parameter;
use core\router\util;
use core\url;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use tool_mulib\muform\handler;
use tool_mulib\muform\handler\dialog;
use tool_mulib\output\muform\dialog\button as dialog_button;
use tool_mulib\output\muform\dialog\link as dialog_link;

/**
 * Router pages exercising muform in a controller, as a full page and in dialogs.
 *
 * This is the reference for handling muform with PSR-7 responses and the fixture
 * for the Behat dialog scenarios, it is available only in Behat and PHPUnit.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class muform_fixture {
    use \core\router\route_controller;

    /**
     * Page with dialog triggers and a link to the full page form.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    #[route(
        path: '/muform/fixture',
        method: ['GET'],
        queryparams: [
            new query_parameter(name: 'saved', type: param::TEXT, default: ''),
            // Core accepts only the strings true and false for BOOL query parameters, so this is an INT.
            new query_parameter(name: 'cancelled', type: param::INT, default: 0),
        ],
        requirelogin: new require_login(requirelogin: true, autologinguest: false),
    )]
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        global $OUTPUT;

        $this->setup_page($request, $response, 'muform router fixture');
        $editurl = util::get_path_for_callable([self::class, 'edit']);

        $reloadbutton = new dialog_button($editurl, 'Edit in dialog', true);
        $reloadbutton->set_submitted_action(dialog::ACTION_RELOAD);

        $staybutton = new dialog_button($editurl, 'Edit and stay');
        $staybutton->set_submitted_action(dialog::ACTION_NOTHING);
        $staybutton->add_class('fixture-stay');

        $link = new dialog_link($editurl, 'Edit as link', 'i/edit');
        $link->set_form_size('lg');

        $dateurl = util::get_path_for_callable([self::class, 'date']);
        $datebutton = new dialog_button($dateurl, 'Pick date');
        $datebutton->set_form_size('sm');

        $response->getBody()->write((string)$OUTPUT->header());
        $response->getBody()->write($OUTPUT->heading('muform router fixture'));

        $params = $request->getQueryParams();
        if (!empty($params['saved'])) {
            $state = \html_writer::div('Saved ' . s($params['saved']), 'alert alert-success', ['id' => 'fixture_state']);
        } else if (!empty($params['cancelled'])) {
            $state = \html_writer::div('Cancelled', 'alert alert-info', ['id' => 'fixture_state']);
        } else {
            $state = \html_writer::div('Nothing yet', 'alert alert-info', ['id' => 'fixture_state']);
        }
        $response->getBody()->write($state);

        $response->getBody()->write(\html_writer::div(
            $OUTPUT->render($reloadbutton) . ' ' . $OUTPUT->render($staybutton) . ' ' . $OUTPUT->render($link)
                . ' ' . $OUTPUT->render($datebutton),
            'd-flex gap-2 align-items-center mb-3'
        ));
        $response->getBody()->write(\html_writer::div(\html_writer::link($editurl, 'Edit full page'), 'mb-3'));
        $response->getBody()->write(\html_writer::tag('script', '
document.querySelector(".fixture-stay").addEventListener("muform:dialog-submitted", (event) => {
    const state = document.getElementById("fixture_state");
    state.textContent = "Dialog saved " + event.detail.data.fullname;
});
', ['type' => 'module']));
        $response->getBody()->write((string)$OUTPUT->footer());
        return $response;
    }

    /**
     * Form handler, serves the full page and the dialog from the same URL.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    #[route(
        path: '/muform/fixture/edit',
        method: ['GET', 'POST'],
        requirelogin: new require_login(requirelogin: true, autologinguest: false),
    )]
    public function edit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        global $CFG;

        $this->setup_page($request, $response, 'Edit fixture');
        require_once($CFG->dirroot . '/admin/tool/mulib/tests/behat/fixtures/muform_elements_form.php');

        $indexurl = util::get_path_for_callable([self::class, 'index']);
        $editurl = util::get_path_for_callable([self::class, 'edit']);
        $current = ['id' => 42, 'token' => 'abc', 'fullname' => 'Jane Doe', 'created' => 'yesterday', 'roles' => ['teacher']];
        $form = new \tool_mulib_muform_elements_form($editurl, $current);
        $handler = handler::from_request($request, $response);

        if ($form->is_cancelled()) {
            return $handler->cancelled(new url($indexurl, ['cancelled' => 1]));
        }
        if ($data = $form->get_data()) {
            \core\notification::success('Saved ' . s($data->fullname));
            return $handler->submitted(new url($indexurl, ['saved' => $data->fullname]), ['fullname' => $data->fullname]);
        }
        return $handler->render($form);
    }

    /**
     * Small form with a single date, the calendar must not be clipped by a small dialog.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    #[route(
        path: '/muform/fixture/date',
        method: ['GET', 'POST'],
        requirelogin: new require_login(requirelogin: true, autologinguest: false),
    )]
    public function date(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        global $CFG;

        $this->setup_page($request, $response, 'Date fixture');
        require_once($CFG->dirroot . '/admin/tool/mulib/tests/behat/fixtures/muform_date_form.php');

        $indexurl = util::get_path_for_callable([self::class, 'index']);
        $dateurl = util::get_path_for_callable([self::class, 'date']);
        $form = new \tool_mulib_muform_date_form($dateurl, []);
        $handler = handler::from_request($request, $response);

        if ($form->is_cancelled()) {
            return $handler->cancelled(new url($indexurl, ['cancelled' => 1]));
        }
        if ($data = $form->get_data()) {
            return $handler->submitted(new url($indexurl, ['saved' => (string)$data->due]));
        }
        return $handler->render($form);
    }

    /**
     * Common page setup and access check.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param string $title
     */
    private function setup_page(ServerRequestInterface $request, ResponseInterface $response, string $title): void {
        global $PAGE;

        $behat = defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING;
        $phpunit = defined('PHPUNIT_TEST') && PHPUNIT_TEST;
        if (!$behat && !$phpunit) {
            util::throw_page_not_found($request, $response);
        }
        $context = \core\context\system::instance();
        require_capability('moodle/site:config', $context);

        $PAGE->set_context($context);
        $PAGE->set_url(new url($request->getUri()->getPath()));
        $PAGE->set_pagelayout('standard');
        $PAGE->set_title($title);
        $PAGE->set_heading($title);
    }
}
