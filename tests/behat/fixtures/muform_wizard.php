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

/**
 * Behat fixture page for the muform wizard helper, a three stage form.
 *
 * Stage forms live in the muform_wizard_*_form.php files, the page decides the stage from the stored data:
 * details are valid when a name was stored, choice when a colour was stored,
 * the summary stage is the last one and finishes the wizard. Query parameters:
 * wizard=<id> and stage=<name> as the helper defines them, cancelled=1 and
 * finished=<name> are set by the redirects.
 *
 * With locked=1 the details stage is locked once it is completed, with unavailable=1
 * the wizard is not available and a stored wizard is deleted.
 *
 * The page prints the current stage in #muform_state and the stored data in #wizard_data.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mulib\muform\form;
use tool_mulib\muform\wizard_page;

require(__DIR__ . '/../../../../../../config.php');

defined('BEHAT_SITE_RUNNING') || die();

require_admin();

require_once(__DIR__ . '/muform_wizard_details_form.php');
require_once(__DIR__ . '/muform_wizard_choice_form.php');
require_once(__DIR__ . '/muform_wizard_summary_form.php');

/**
 * The fixture wizard.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tool_mulib_muform_wizard_fixture_page extends wizard_page {
    /** @var string fixture page path */
    private const string PATH = '/admin/tool/mulib/tests/behat/fixtures/muform_wizard.php';

    /**
     * Constructor.
     *
     * @param bool $locked lock the details stage
     * @param bool $unavailable the wizard cannot be used
     */
    public function __construct(
        /** @var bool lock the details stage */
        private bool $locked,
        /** @var bool the wizard cannot be used */
        private bool $unavailable
    ) {
    }

    #[\Override]
    protected function get_name(): string {
        return 'tool_mulib_fixture' . ($this->locked ? '_locked' : '');
    }

    #[\Override]
    protected function get_stages(): array {
        return ['details' => 'Details', 'choice' => 'Choice', 'summary' => 'Summary'];
    }

    #[\Override]
    protected function get_page_url(): \core\url {
        $params = $this->locked ? ['locked' => 1] : [];
        return new \core\url(self::PATH, $params);
    }

    #[\Override]
    protected function get_unavailable_reason(): ?string {
        return $this->unavailable ? 'Fixture wizard is not available' : null;
    }

    #[\Override]
    protected function get_locked_stages(): array {
        return $this->locked ? ['details'] : [];
    }

    #[\Override]
    protected function get_return_url(): \core\url {
        return new \core\url(self::PATH, ['cancelled' => 1]);
    }

    #[\Override]
    protected function setup_page(): void {
        global $PAGE;
        $PAGE->set_url($this->get_page_url());
        $PAGE->set_context(\core\context\system::instance());
        $PAGE->set_pagelayout('report');
        $PAGE->set_title('muform wizard');
        $PAGE->set_heading('muform wizard');
    }

    #[\Override]
    protected function is_stage_valid(string $stage): bool {
        $data = $this->state;
        return match ($stage) {
            'details' => isset($data['fullname']) && is_string($data['fullname']) && trim($data['fullname']) !== '',
            'choice' => in_array($data['colour'] ?? null, ['red', 'green'], true),
            'summary' => false,
        };
    }

    #[\Override]
    protected function create_form(string $stage, \core\url $formurl): form {
        $data = $this->state;
        if ($stage === 'details') {
            return new tool_mulib_muform_wizard_details_form($formurl, $data, ['locked' => $this->is_stage_locked($stage)]);
        }
        if ($stage === 'choice') {
            return new tool_mulib_muform_wizard_choice_form($formurl, $data);
        }
        $files = [];
        if (!empty($data['attachments'])) {
            $probe = new tool_mulib_muform_wizard_details_form($formurl, $data);
            $files = array_map(fn($file) => $file->get_filename(), $probe->get_element('attachments')->get_files());
        }
        $summary = ['fullname' => $data['fullname'], 'colour' => $data['colour']];
        $summary['files'] = implode(', ', $files) ?: 'none';
        return new tool_mulib_muform_wizard_summary_form($formurl, [], $summary);
    }

    #[\Override]
    protected function submit_stage(string $stage, stdClass $data, form $form): ?\core\url {
        if ($stage === 'summary') {
            $this->wizard->delete();
            return new \core\url(self::PATH, ['finished' => $this->state['fullname']]);
        }
        // Store only what this stage validated, keep the rest.
        foreach ((array)$data as $name => $value) {
            $this->state[$name] = $value;
        }
        return null;
    }

    #[\Override]
    protected function render_page(form $form): void {
        global $OUTPUT;
        echo $OUTPUT->header();
        $text = 'Stage ' . $this->stage . ($form->is_invalid() ? ' is invalid' : '');
        echo html_writer::div($text, 'alert alert-info', ['id' => 'muform_state']);
        echo html_writer::tag('pre', s(json_encode((object)$this->state, JSON_UNESCAPED_SLASHES)), ['id' => 'wizard_data']);
        echo $this->wizard->render($OUTPUT, $this->get_page_url(), $form);
        echo $OUTPUT->footer();
    }
}

$state = null;
if (optional_param('cancelled', 0, PARAM_BOOL)) {
    $state = 'Wizard cancelled';
} else if ($finished = optional_param('finished', '', PARAM_TEXT)) {
    $state = 'Wizard finished for ' . $finished;
}

$page = new tool_mulib_muform_wizard_fixture_page(
    optional_param('locked', 0, PARAM_BOOL),
    optional_param('unavailable', 0, PARAM_BOOL)
);
if ($state === null) {
    $page->execute();
    die;
}

$pageurl = new \core\url('/admin/tool/mulib/tests/behat/fixtures/muform_wizard.php');
$PAGE->set_url($pageurl);
$PAGE->set_context(\core\context\system::instance());
$PAGE->set_pagelayout('report');
$PAGE->set_title('muform wizard');
$PAGE->set_heading('muform wizard');

echo $OUTPUT->header();
echo html_writer::div($state, 'alert alert-info', ['id' => 'muform_state']);
echo html_writer::link($pageurl, 'Start again', ['id' => 'wizard_start']);
echo $OUTPUT->footer();
