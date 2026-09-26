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
namespace tool_mulib\muform;

use core\exception\coding_exception;
use core\output\core_renderer;
use core\url;
use stdClass;

/**
 * Handler side helper for multi-page forms.
 *
 * The state of a running wizard is one row in tool_mulib_muform_wizard, the row id
 * travels in the page URL and the row is bound to the wizard name, the owner and the
 * login session, so nobody else can load it, a re-login orphans it and the data of one
 * wizard can never be loaded by another one. Put the page parameters that the stored
 * data belongs to into the name (such as "auth_musaml_user_upload:" . $idpid), then a
 * row cannot be replayed against a different record. The stored data is the only
 * truth: the handler validates it stage by stage on every request and the first
 * invalid stage is the current one, a requested stage may only move back to a stage
 * that is already valid. Every stage is its own form class, it gets the stored data
 * as current data and the handler stores the validated result and redirects:
 *
 *   $name = 'auth_musaml_user_upload:' . $idp->id;
 *   $wizard = wizard::load($name, optional_param(wizard::PARAM, 0, PARAM_INT));
 *   if (!$wizard) {
 *       $wizard = wizard::start($name);
 *       redirect($wizard->get_url($pageurl));
 *   }
 *   $data = $wizard->get_data();
 *   $stages = wizard::resolve_stages($names, fn($stage) => my_import::is_stage_valid($stage, $data), $requested);
 *   $stage = wizard::current_stage($stages);
 *   ... build the stage form with $wizard->get_url($pageurl, $stage) and $data ...
 *   if ($form->is_cancelled()) { $wizard->delete(); redirect($returnurl); }
 *   if ($submitted = $form->get_data()) { $wizard->set_data(...); redirect($wizard->get_url($pageurl)); }
 *   echo $wizard->render($OUTPUT, $stages, $pageurl, $form);
 *
 * Rows older than TTL are deleted whenever a wizard starts, there is no cron.
 * A double click on the start link leaves one abandoned row behind, the purge takes care of it.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class wizard {
    /** @var string page parameter carrying the row id */
    public const string PARAM = 'wizard';
    /** @var string page parameter with the requested stage name */
    public const string STAGE_PARAM = 'stage';
    /** @var int rows older than this are purged when a wizard starts */
    public const int TTL = DAYSECS;

    /**
     * Constructor.
     *
     * @param stdClass $record row from tool_mulib_muform_wizard
     */
    private function __construct(
        /** @var stdClass row from tool_mulib_muform_wizard */
        private stdClass $record,
    ) {
    }

    /**
     * Start a new wizard for the current user, old rows of all users are purged first.
     *
     * @param string $name wizard name, include the page parameters the data belongs to
     * @return self
     */
    public static function start(string $name): self {
        global $DB, $USER;

        if (!isloggedin() || isguestuser()) {
            throw new coding_exception('Wizards require a logged in user');
        }
        if (trim($name) === '') {
            throw new coding_exception('Wizard name is required');
        }

        $now = \core\di::get(\core\clock::class)->time();
        $DB->delete_records_select('tool_mulib_muform_wizard', 'timecreated < :cutoff', ['cutoff' => $now - self::TTL]);

        $record = new stdClass();
        $record->userid = (int)$USER->id;
        $record->jsondata = '{}';
        $record->timecreated = $now;
        $record->sessionhash = self::hash($name, $record->userid, $record->timecreated);
        $record->id = $DB->insert_record('tool_mulib_muform_wizard', $record);

        return new self($record);
    }

    /**
     * Load a running wizard of the current user, null when it does not exist, belongs
     * to somebody else, was started by another wizard or in another login session.
     *
     * @param string $name the same name that started the wizard
     * @param int $id row id, usually from the page parameter
     * @return self|null
     */
    public static function load(string $name, int $id): ?self {
        global $DB, $USER;

        if ($id <= 0 || trim($name) === '' || !isloggedin() || isguestuser()) {
            return null;
        }
        $record = $DB->get_record('tool_mulib_muform_wizard', ['id' => $id, 'userid' => $USER->id]);
        if (!$record) {
            return null;
        }
        if (!hash_equals($record->sessionhash, self::hash($name, (int)$record->userid, (int)$record->timecreated))) {
            return null;
        }
        return new self($record);
    }

    /**
     * Row id, the identifier used in URLs.
     *
     * @return int
     */
    public function get_id(): int {
        return (int)$this->record->id;
    }

    /**
     * Stored state.
     *
     * @return array<string,mixed> decoded JSON object, empty when nothing was stored yet or the data is broken
     */
    public function get_data(): array {
        $data = json_decode($this->record->jsondata, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Replace the stored state, store only data that the handler has validated.
     *
     * @param array $data string keys with JSON serialisable values, stored as a JSON object
     */
    public function set_data(array $data): void {
        global $DB;

        $this->record->jsondata = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $DB->set_field('tool_mulib_muform_wizard', 'jsondata', $this->record->jsondata, ['id' => $this->record->id]);
    }

    /**
     * Delete the state when the wizard finishes or is cancelled.
     */
    public function delete(): void {
        global $DB;

        $DB->delete_records('tool_mulib_muform_wizard', ['id' => $this->record->id]);
    }

    /**
     * Page URL with the wizard id and optionally a requested stage.
     *
     * @param url $pageurl
     * @param string|null $stage stage name, null means the stage resolved from the stored data
     * @return url
     */
    public function get_url(url $pageurl, ?string $stage = null): url {
        $url = new url($pageurl);
        $url->param(self::PARAM, $this->get_id());
        $url->remove_params(self::STAGE_PARAM);
        if ($stage !== null) {
            $url->param(self::STAGE_PARAM, $stage);
        }
        return $url;
    }

    /**
     * Decide which stage is current from the stored data.
     *
     * Stages are validated in order until the first invalid one, later stages are not
     * validated at all. The first invalid stage is current, the last stage when all
     * are valid; a requested stage wins only when it is valid or the first invalid one.
     *
     * @param array $names stage names in order as a list of strings, or labels indexed by stage names
     * @param callable $isvalid callable(string $name): bool validating the stored data of the given stage
     * @param string|null $requested requested stage name, usually from the page parameter
     * @return array<int,array{name:string,label:string,valid:bool,current:bool}> stages in order
     */
    public static function resolve_stages(array $names, callable $isvalid, ?string $requested): array {
        if (!$names) {
            throw new coding_exception('Wizard needs at least one stage');
        }
        $stages = [];
        $current = null;
        foreach ($names as $key => $value) {
            $name = is_string($key) ? $key : (string)$value;
            $label = is_string($key) ? (string)$value : $name;
            $valid = ($current === null) && (bool)$isvalid($name);
            if ($current === null && !$valid) {
                $current = $name;
            }
            $stages[] = ['name' => $name, 'label' => $label, 'valid' => $valid, 'current' => false];
        }
        if ($current === null) {
            $current = $stages[array_key_last($stages)]['name'];
        }
        foreach ($stages as $stage) {
            if ($stage['name'] === $requested) {
                if ($stage['valid'] || $stage['name'] === $current) {
                    $current = $stage['name'];
                }
                break;
            }
        }
        foreach ($stages as $i => $stage) {
            $stages[$i]['current'] = ($stage['name'] === $current);
        }
        return $stages;
    }

    /**
     * Name of the current stage.
     *
     * @param array $stages result of resolve_stages(), a list of array{name:string,label:string,valid:bool,current:bool}
     * @return string
     */
    public static function current_stage(array $stages): string {
        foreach ($stages as $stage) {
            if ($stage['current']) {
                return $stage['name'];
            }
        }
        throw new coding_exception('No current wizard stage');
    }

    /**
     * Render the stage form inside the wizard layout: the step list on the left, the form on the right.
     *
     * Every stage the user may go to is a link, those are the valid stages and the first
     * invalid one, which is exactly what resolve_stages() accepts as a requested stage;
     * the current stage is highlighted, later stages are disabled.
     *
     * @param core_renderer $output
     * @param array $stages result of resolve_stages(), a list of array{name:string,label:string,valid:bool,current:bool}
     * @param url $pageurl
     * @param form $form the form of the current stage
     * @return string
     */
    public function render(core_renderer $output, array $stages, url $pageurl, form $form): string {
        $items = [];
        $invalidseen = false;
        foreach ($stages as $i => $stage) {
            $reachable = $stage['valid'] || !$invalidseen;
            $invalidseen = $invalidseen || !$stage['valid'];
            $items[] = [
                'number' => $i + 1,
                'label' => clean_string($stage['label']),
                'is_current' => $stage['current'],
                'is_link' => $reachable && !$stage['current'],
                'url' => $this->get_url($pageurl, $stage['name'])->out(false),
            ];
        }
        return $output->render_from_template('tool_mulib/muform/wizard', [
            'title' => clean_string(get_string('muform_steps', 'tool_mulib')),
            'stages' => $items,
            'formhtml' => $form->render($output),
        ]);
    }

    /**
     * Hash binding a row to the wizard name, the owner and the login session; the name
     * and the sesskey are never stored.
     *
     * @param string $name
     * @param int $userid
     * @param int $timecreated
     * @return string
     */
    private static function hash(string $name, int $userid, int $timecreated): string {
        return sha1($name . '/' . $userid . '/' . sesskey() . '/' . $timecreated);
    }
}
