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
 * The page prints the current stage in #muform_state and the stored data in #wizard_data.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mulib\muform\wizard;

require(__DIR__ . '/../../../../../../config.php');

defined('BEHAT_SITE_RUNNING') || die();

require_admin();

require_once(__DIR__ . '/muform_wizard_details_form.php');
require_once(__DIR__ . '/muform_wizard_choice_form.php');
require_once(__DIR__ . '/muform_wizard_summary_form.php');

$pageurl = new \core\url('/admin/tool/mulib/tests/behat/fixtures/muform_wizard.php');
$PAGE->set_url($pageurl);
$PAGE->set_context(\core\context\system::instance());
$PAGE->set_pagelayout('report');
$PAGE->set_title('muform wizard');
$PAGE->set_heading('muform wizard');

$state = null;
if (optional_param('cancelled', 0, PARAM_BOOL)) {
    $state = 'Wizard cancelled';
} else if ($finished = optional_param('finished', '', PARAM_TEXT)) {
    $state = 'Wizard finished for ' . $finished;
}

if ($state === null) {
    $wizard = wizard::load('tool_mulib_fixture', optional_param(wizard::PARAM, 0, PARAM_INT));
    if (!$wizard) {
        $wizard = wizard::start('tool_mulib_fixture');
        redirect($wizard->get_url($pageurl));
    }
    $data = $wizard->get_data();

    $isvalid = function (string $stage) use ($data): bool {
        return match ($stage) {
            'details' => isset($data['fullname']) && is_string($data['fullname']) && trim($data['fullname']) !== '',
            'choice' => in_array($data['colour'] ?? null, ['red', 'green'], true),
            'summary' => false,
        };
    };
    $requested = optional_param(wizard::STAGE_PARAM, null, PARAM_ALPHA);
    $stages = wizard::resolve_stages(['details' => 'Details', 'choice' => 'Choice', 'summary' => 'Summary'], $isvalid, $requested);
    $stage = wizard::current_stage($stages);
    $formurl = $wizard->get_url($pageurl, $stage);
    $previous = ['choice' => 'details', 'summary' => 'choice'];

    if ($stage === 'details') {
        $form = new tool_mulib_muform_wizard_details_form($formurl, $data);
    } else if ($stage === 'choice') {
        $form = new tool_mulib_muform_wizard_choice_form($formurl, $data);
    } else {
        $files = [];
        if (!empty($data['attachments'])) {
            $probe = new tool_mulib_muform_wizard_details_form($formurl, $data);
            $files = array_map(fn($file) => $file->get_filename(), $probe->get_element('attachments')->get_files());
        }
        $summary = ['fullname' => $data['fullname'], 'colour' => $data['colour']];
        $summary['files'] = implode(', ', $files) ?: 'none';
        $form = new tool_mulib_muform_wizard_summary_form($formurl, [], $summary);
    }

    if ($form->is_cancelled()) {
        $wizard->delete();
        redirect(new \core\url($pageurl, ['cancelled' => 1]));
    }
    if ($form->is_reloaded() && isset($previous[$stage]) && $form->get_element('back')->get_value()) {
        redirect($wizard->get_url($pageurl, $previous[$stage]));
    }
    if ($submitted = $form->get_data()) {
        if ($stage === 'summary') {
            $wizard->delete();
            redirect(new \core\url($pageurl, ['finished' => $data['fullname']]));
        }
        // Store only what this stage validated, keep the rest.
        foreach ((array)$submitted as $name => $value) {
            $data[$name] = $value;
        }
        $wizard->set_data($data);
        redirect($wizard->get_url($pageurl));
    }
}

echo $OUTPUT->header();

if ($state !== null) {
    echo html_writer::div($state, 'alert alert-info', ['id' => 'muform_state']);
    echo html_writer::link($pageurl, 'Start again', ['id' => 'wizard_start']);
} else {
    $text = 'Stage ' . $stage . ($form->is_invalid() ? ' is invalid' : '');
    echo html_writer::div($text, 'alert alert-info', ['id' => 'muform_state']);
    echo html_writer::tag('pre', s(json_encode((object)$data, JSON_UNESCAPED_SLASHES)), ['id' => 'wizard_data']);
    echo $wizard->render($OUTPUT, $stages, $pageurl, $form);
}

echo $OUTPUT->footer();
