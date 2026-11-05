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
 * Multi-page forms, see docs/muform.md.
 *
 * State is one row per running wizard, bound to the user, login session and wizard name.
 * A stage is completed only after the user submitted it, valid data never skips a stage.
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

    /** @var string[]|null stage names from the last resolve_stages() call */
    private ?array $stagenames = null;

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
     * Start a new wizard, old rows are deleted.
     *
     * @param string $name wizard name including page parameters
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
        $record->jsondata = '{"data":{},"completed":[]}';
        $record->timecreated = $now;
        $record->sessionhash = self::hash($name, $record->userid, $record->timecreated);
        $record->id = $DB->insert_record('tool_mulib_muform_wizard', $record);

        return new self($record);
    }

    /**
     * Load a running wizard of the current user.
     *
     * @param string $name name used in start()
     * @param int $id row id
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
     * Row id used in URLs.
     *
     * @return int
     */
    public function get_id(): int {
        return (int)$this->record->id;
    }

    /**
     * Stored data.
     *
     * @return array<string,mixed>
     */
    public function get_data(): array {
        return $this->decode()['data'];
    }

    /**
     * Replace the stored data without completing a stage, for example defaults.
     *
     * @param array<string,mixed> $data
     */
    public function set_data(array $data): void {
        $this->store($data, $this->decode()['completed']);
    }

    /**
     * Replace the stored data and complete the stage, later stages must be completed again.
     *
     * @param string $stage
     * @param array<string,mixed> $data
     */
    public function complete_stage(string $stage, array $data): void {
        if ($this->stagenames === null) {
            throw new coding_exception('Stages must be resolved before completing a stage');
        }
        $index = array_search($stage, $this->stagenames, true);
        if ($index === false) {
            throw new coding_exception('Unknown wizard stage: ' . $stage);
        }
        $this->store($data, array_slice($this->stagenames, 0, $index + 1));
    }

    /**
     * Data and completed stages from the row.
     *
     * @return array{data:array,completed:string[]}
     */
    private function decode(): array {
        $decoded = json_decode($this->record->jsondata, true);
        $data = (is_array($decoded) && is_array($decoded['data'] ?? null)) ? $decoded['data'] : [];
        $completed = (is_array($decoded) && is_array($decoded['completed'] ?? null)) ? $decoded['completed'] : [];
        return ['data' => $data, 'completed' => array_values(array_filter($completed, 'is_string'))];
    }

    /**
     * Save data and completed stages to the row.
     *
     * @param array $data
     * @param string[] $completed names of completed stages
     */
    private function store(array $data, array $completed): void {
        global $DB;

        // An empty array must stay a JSON object.
        $json = ['data' => (object)$data, 'completed' => array_values($completed)];
        $this->record->jsondata = \tool_mulib\local\mulib::json_encode($json);
        $DB->set_field('tool_mulib_muform_wizard', 'jsondata', $this->record->jsondata, ['id' => $this->record->id]);
    }

    /**
     * Delete the row when the wizard finishes or is cancelled.
     */
    public function delete(): void {
        global $DB;

        $DB->delete_records('tool_mulib_muform_wizard', ['id' => $this->record->id]);
    }

    /**
     * Page URL with the wizard id and optionally a requested stage.
     *
     * @param url $pageurl
     * @param string|null $stage null means the current stage
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
     * Resolve stages, the current one is the first stage that is not completed.
     *
     * @param array<string,string> $labels stage labels indexed by stage names
     * @param callable $isvalid fn(string $name): bool
     * @param string|null $requested requested stage, used only if it is not after the current one
     * @return array<int,array{name:string,label:string,completed:bool,current:bool}>
     */
    public function resolve_stages(array $labels, callable $isvalid, ?string $requested): array {
        if (!$labels) {
            throw new coding_exception('Wizard needs at least one stage');
        }
        $completed = $this->decode()['completed'];
        $stages = [];
        $current = null;
        foreach ($labels as $name => $label) {
            if (!is_string($name) || !is_string($label)) {
                throw new coding_exception('Wizard stages must be labels indexed by stage names');
            }
            $done = ($current === null) && in_array($name, $completed, true) && ($isvalid($name) === true);
            if ($current === null && !$done) {
                $current = $name;
            }
            $stages[] = ['name' => $name, 'label' => $label, 'completed' => $done, 'current' => false];
        }
        if ($current === null) {
            $current = $stages[array_key_last($stages)]['name'];
        }
        foreach ($stages as $stage) {
            if ($stage['name'] === $requested) {
                if ($stage['completed'] || $stage['name'] === $current) {
                    $current = $stage['name'];
                }
                break;
            }
        }
        foreach ($stages as $i => $stage) {
            $stages[$i]['current'] = ($stage['name'] === $current);
        }
        $this->stagenames = array_column($stages, 'name');
        return $stages;
    }

    /**
     * Are all stages completed?
     *
     * @param array $stages result of resolve_stages()
     * @return bool
     */
    public static function is_finished(array $stages): bool {
        return $stages && !in_array(false, array_column($stages, 'completed'), true);
    }

    /**
     * Name of the current stage.
     *
     * @param array $stages result of resolve_stages()
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
     * Render the stepper and the form, with completed stages the form is marked dirty.
     *
     * @param core_renderer $output
     * @param array $stages result of resolve_stages()
     * @param url $pageurl
     * @param form $form the form of the current stage
     * @return string
     */
    public function render(core_renderer $output, array $stages, url $pageurl, form $form): string {
        $items = [];
        $pendingseen = false;
        foreach ($stages as $i => $stage) {
            $reachable = $stage['completed'] || !$pendingseen;
            $pendingseen = $pendingseen || !$stage['completed'];
            $items[] = [
                'number' => $i + 1,
                'label' => clean_string($stage['label']),
                'is_active' => $stage['completed'] || $stage['current'],
                'is_current' => $stage['current'],
                'is_link' => $reachable && !$stage['current'],
                'url' => $this->get_url($pageurl, $stage['name'])->out(false),
            ];
        }
        // Leaving the page loses completed stages, nothing links back to a running wizard.
        if (array_filter($stages, fn(array $stage): bool => $stage['completed'])) {
            $form->set_dirty(true);
        }
        return $output->render_from_template('tool_mulib/muform/wizard', [
            'title' => clean_string(get_string('muform_steps', 'tool_mulib')),
            'stages' => $items,
            'formhtml' => $form->render($output),
        ]);
    }

    /**
     * Hash binding the row to the wizard name, user and login session.
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
