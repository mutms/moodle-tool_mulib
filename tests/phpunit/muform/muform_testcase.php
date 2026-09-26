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

use tool_mulib\muform\form;

/**
 * Base class for muform tests.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class muform_testcase extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/admin/tool/mulib/tests/phpunit/muform/fixtures/simple_form.php');
        require_once($CFG->dirroot . '/admin/tool/mulib/tests/phpunit/muform/fixtures/dynamic_form.php');
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    #[\Override]
    protected function tearDown(): void {
        $_POST = [];
        unset($_SERVER['REQUEST_METHOD']);
        unset($_SERVER['HTTP_X_MUFORM_MODAL']);
        parent::tearDown();
    }

    /**
     * Simulate form submission.
     *
     * @param string $formclass
     * @param array $data
     * @param bool $addsesskey
     */
    protected function simulate_post(string $formclass, array $data, bool $addsesskey = true): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $data;
        $_POST['__formid'] = str_replace('\\', '-', $formclass);
        if ($addsesskey) {
            $_POST['__sesskey'] = sesskey();
        }
    }

    /**
     * Simulate GET request without any submitted data.
     */
    protected function simulate_get(): void {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
    }

    /**
     * Returns form target URL.
     *
     * @return \core\url
     */
    protected function get_url(): \core\url {
        return new \core\url('/admin/tool/mulib/tests/behat/fixtures/muform_element_text.php', ['id' => 10]);
    }

    /**
     * Returns element error messages from rendered form html.
     *
     * @param form $form
     * @param string $elname
     * @return string[]
     */
    protected function get_rendered_errors(form $form, string $elname): array {
        return $this->get_errors_from_html($this->render($form), 'id_error_' . $elname . $form->get_idsuffix());
    }

    /**
     * Returns form level error messages from rendered form html.
     *
     * @param form $form
     * @return string[]
     */
    protected function get_rendered_form_errors(form $form): array {
        $html = $this->render($form);
        if (!preg_match('~<div class="alert alert-danger" role="alert">(.*?)</div>\s*</div>~s', $html, $matches)) {
            return [];
        }
        preg_match_all('~<div>([^<]*)~', $matches[1], $errors);
        return $errors[1];
    }

    /**
     * Extract error messages from error container.
     *
     * @param string $html
     * @param string $id
     * @return string[]
     */
    private function get_errors_from_html(string $html, string $id): array {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><body>' . $html . '</body>');
        libxml_clear_errors();
        $container = $dom->getElementById($id);
        if (!$container) {
            return [];
        }
        $result = [];
        foreach ($container->childNodes as $node) {
            if ($node instanceof \DOMElement && $node->tagName === 'div') {
                $result[] = htmlspecialchars($node->textContent, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8', false);
            }
        }
        return $result;
    }

    /**
     * Render form.
     *
     * @param form $form
     * @param string $variant template variant
     * @return string
     */
    protected function render(form $form, string $variant = ''): string {
        global $PAGE;
        $PAGE->set_url('/admin/tool/mulib/tests/behat/fixtures/muform_element_text.php');
        $PAGE->set_context(\core\context\system::instance());
        return $form->render($PAGE->get_renderer('core'), $variant);
    }
}
