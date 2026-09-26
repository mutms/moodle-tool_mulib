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
 * Shared helpers of muform element fixture pages.
 *
 * Every element type has its own self-contained fixture page
 * tests/behat/fixtures/muform_element_<type>.php with the form class inside,
 * this file only takes care of the page setup and the output so that the pages
 * stay short. Include it from a copied fixture page when testing custom elements
 * in other plugins.
 *
 * Pages check defined('BEHAT_SITE_RUNNING') right after config.php, before any include,
 * and call require_admin() after the includes; test pages must never run on a real site.
 *
 * Common query parameters of the fixture pages, all optional:
 * - required=1  the tested element is required
 * - frozen=1    the tested element is frozen
 * - prefill=1   current data contains the tested element value
 * - default=1   the tested element has a default value
 * - cancelled=1 set by the cancel redirect
 *
 * The page prints the form state in #muform_state and after a successful
 * submission one "#submitted_<name>" list item per form data key, arrays joined
 * with commas, null printed as "null" and line breaks as a literal backslash n.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mulib\muform\form;
use tool_mulib\muform\util\file_area;

defined('BEHAT_SITE_RUNNING') || die();

/**
 * Read the common fixture flags from the request.
 *
 * @return array{required: bool, frozen: bool, prefill: bool, default: bool}
 */
function tool_mulib_muform_fixture_flags(): array {
    return [
        'required' => optional_param('required', 0, PARAM_BOOL),
        'frozen' => optional_param('frozen', 0, PARAM_BOOL),
        'prefill' => optional_param('prefill', 0, PARAM_BOOL),
        'default' => optional_param('default', 0, PARAM_BOOL),
    ];
}

/**
 * Set up the fixture page and return its URL with the flags.
 *
 * @param string $type element type, the page file is muform_element_<type>.php
 * @param array $flags result of tool_mulib_muform_fixture_flags()
 * @return \core\url
 */
function tool_mulib_muform_fixture_setup(string $type, array $flags): \core\url {
    global $PAGE;

    $params = array_filter($flags);
    $pageurl = new \core\url('/admin/tool/mulib/tests/behat/fixtures/muform_element_' . $type . '.php', $params);
    $PAGE->set_url($pageurl);
    $PAGE->set_context(\core\context\system::instance());
    $PAGE->set_pagelayout('standard');
    $PAGE->set_title('muform ' . $type);
    $PAGE->set_heading('muform ' . $type);

    return $pageurl;
}

/**
 * Fixture file area in system context, seeded with given files.
 *
 * @param int $itemid
 * @param string[] $filenames
 * @return file_area
 */
function tool_mulib_muform_fixture_area(int $itemid, array $filenames): file_area {
    $area = new file_area(\core\context\system::instance(), 'tool_mulib', 'muform_fixture', $itemid);
    $fs = get_file_storage();
    foreach ($filenames as $filename) {
        $record = [
            'contextid' => $area->get_contextid(), 'component' => $area->get_component(), 'filearea' => $area->get_filearea(),
            'itemid' => $itemid, 'filepath' => '/', 'filename' => $filename,
        ];
        if (!$fs->file_exists($record['contextid'], $record['component'], $record['filearea'], $itemid, '/', $filename)) {
            $fs->create_file_from_string($record, 'seeded');
        }
    }
    return $area;
}

/**
 * Handle cancellation, print the form state, the submitted data and the form.
 *
 * @param form $form
 * @param \core\url $pageurl
 * @param callable|null $ondata called with the submitted data object and the form,
 *                              use it to save file areas and to replace values that cannot be printed
 */
function tool_mulib_muform_fixture_finish(form $form, \core\url $pageurl, ?callable $ondata = null): void {
    global $OUTPUT;

    if ($form->is_cancelled()) {
        $pageurl->param('cancelled', 1);
        redirect($pageurl);
    }

    echo $OUTPUT->header();

    if (optional_param('cancelled', 0, PARAM_BOOL)) {
        echo html_writer::div('Form cancelled', 'alert alert-info', ['id' => 'muform_state']);
    } else if ($data = $form->get_data()) {
        echo html_writer::div('Form submitted', 'alert alert-success', ['id' => 'muform_state']);
        if ($ondata) {
            $ondata($data, $form);
        }
        $items = [];
        foreach ((array)$data as $name => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            } else if ($value === null) {
                $value = 'null';
            } else if (is_bool($value)) {
                $value = (int)$value;
            }
            // Line breaks are printed as a literal backslash n, the same notation the Behat tables use.
            $value = str_replace("\n", '\n', (string)$value);
            $items[] = html_writer::tag('li', s($name . ': ' . $value), ['id' => 'submitted_' . $name]);
        }
        echo html_writer::tag('ul', implode('', $items), ['id' => 'submitted']);
    } else if ($form->is_reloaded()) {
        echo html_writer::div('Form reloaded', 'alert alert-info', ['id' => 'muform_state']);
    } else if ($form->is_invalid()) {
        echo html_writer::div('Form is invalid', 'alert alert-danger', ['id' => 'muform_state']);
    } else {
        echo html_writer::div('Form is new', 'alert alert-info', ['id' => 'muform_state']);
    }

    echo $form->render($OUTPUT);

    echo $OUTPUT->footer();
}
