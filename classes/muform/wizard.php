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
 * State is one row per running wizard, bound to the user, login session, wizard name and stages.
 * Stages never change, a stage is completed only after the user submitted it or when it is skipped.
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

    /** @var array<string,array{completed:bool,current:bool,skipped:bool}>|null state of stages from resolve_stages() */
    private ?array $states = null;
    /** @var string|null stage requested in the page URL */
    private ?string $requested = null;

    /**
     * Constructor.
     *
     * @param stdClass $record row from tool_mulib_muform_wizard
     * @param array $stages labels indexed by stage names
     */
    private function __construct(
        /** @var stdClass row from tool_mulib_muform_wizard */
        private stdClass $record,
        /** @var array<string,string> labels indexed by stage names */
        private array $stages,
    ) {
    }

    /**
     * Start a new wizard, old rows are deleted.
     *
     * @param string $name wizard name including page parameters
     * @param array $stages labels indexed by stage names, names are lowercase letters
     * @return self
     */
    public static function start(string $name, array $stages): self {
        global $DB, $USER;

        if (!isloggedin() || isguestuser()) {
            throw new coding_exception('Wizards require a logged in user');
        }
        if (trim($name) === '') {
            throw new coding_exception('Wizard name is required');
        }
        self::validate_stages($stages);

        $now = \core\di::get(\core\clock::class)->time();
        $DB->delete_records_select('tool_mulib_muform_wizard', 'timecreated < :cutoff', ['cutoff' => $now - self::TTL]);

        $record = new stdClass();
        $record->userid = (int)$USER->id;
        $record->jsondata = '{"data":{},"completed":[]}';
        $record->timecreated = $now;
        $record->sessionhash = self::hash($name, $stages, $record->userid, $record->timecreated);
        $record->id = $DB->insert_record('tool_mulib_muform_wizard', $record);

        return new self($record, $stages);
    }

    /**
     * Load the running wizard from the page URL.
     *
     * @param string $name name used in start()
     * @param array $stages the same stages as in start()
     * @return self|null null when the wizard does not exist or belongs to another user, session, name or stages
     */
    public static function load(string $name, array $stages): ?self {
        global $DB, $USER;

        self::validate_stages($stages);
        $id = optional_param(self::PARAM, 0, PARAM_INT);
        if ($id <= 0 || trim($name) === '' || !isloggedin() || isguestuser()) {
            return null;
        }
        $record = $DB->get_record('tool_mulib_muform_wizard', ['id' => $id, 'userid' => $USER->id]);
        if (!$record) {
            return null;
        }
        $hash = self::hash($name, $stages, (int)$record->userid, (int)$record->timecreated);
        if (!hash_equals($record->sessionhash, $hash)) {
            return null;
        }
        return new self($record, $stages);
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
     * @param array $data
     */
    public function set_data(array $data): void {
        $this->store($data, $this->decode()['completed']);
    }

    /**
     * Replace the stored data and complete the stage, later stages must be completed again.
     *
     * @param string $stage
     * @param array $data
     */
    public function complete_stage(string $stage, array $data): void {
        $states = $this->get_states();
        if (!isset($states[$stage])) {
            throw new coding_exception('Unknown wizard stage: ' . $stage);
        }
        $completed = [];
        foreach ($states as $name => $state) {
            // Skipped stages are not submitted, they must be completed if they apply later.
            if (!$state['skipped']) {
                $completed[] = $name;
            }
            if ($name === $stage) {
                break;
            }
        }
        $this->store($data, $completed);
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
     * Resolve stages, the current one is the first stage that is not completed
     * or the stage requested in the page URL if it is not after it.
     *
     * @param callable $isvalid fn(string $name): bool
     * @param string[] $skipped stages that do not apply now, they count as completed
     * @return string the current stage
     */
    public function resolve_stages(callable $isvalid, array $skipped = []): string {
        foreach ($skipped as $name) {
            if (!is_string($name) || !isset($this->stages[$name])) {
                throw new coding_exception('Unknown skipped wizard stage');
            }
        }
        $requested = optional_param(self::STAGE_PARAM, null, PARAM_ALPHA);
        $completed = $this->decode()['completed'];

        $states = [];
        $current = null;
        foreach ($this->stages as $name => $label) {
            $isskipped = in_array($name, $skipped, true);
            if ($current !== null) {
                $done = false;
            } else if ($isskipped) {
                $done = true;
            } else {
                $done = in_array($name, $completed, true) && ($isvalid($name) === true);
                if (!$done) {
                    $current = $name;
                }
            }
            $states[$name] = ['completed' => $done, 'current' => false, 'skipped' => $isskipped];
        }
        if ($current === null) {
            // All stages completed, the last stage that is not skipped is current.
            foreach ($states as $name => $state) {
                if (!$state['skipped']) {
                    $current = $name;
                }
            }
            if ($current === null) {
                throw new coding_exception('All wizard stages are skipped');
            }
        }
        if ($requested !== null && isset($states[$requested]) && !$states[$requested]['skipped']) {
            if ($states[$requested]['completed'] || $requested === $current) {
                $current = $requested;
            }
        }
        $states[$current]['current'] = true;

        $this->states = $states;
        $this->requested = $requested;
        return $current;
    }

    /**
     * The last stage before the current one that is not skipped, for Back buttons.
     *
     * @return string|null null on the first stage
     */
    public function get_previous_stage(): ?string {
        $previous = null;
        foreach ($this->get_states() as $name => $state) {
            if ($state['current']) {
                return $previous;
            }
            if (!$state['skipped']) {
                $previous = $name;
            }
        }
        return null;
    }

    /**
     * Are all stages completed and no stage was requested?
     *
     * @return bool
     */
    public function is_finished(): bool {
        if ($this->requested !== null) {
            return false;
        }
        foreach ($this->get_states() as $state) {
            if (!$state['completed']) {
                return false;
            }
        }
        return true;
    }

    /**
     * Render the stepper and the form, with completed stages the form is marked dirty.
     *
     * @param core_renderer $output
     * @param url $pageurl
     * @param form $form the form of the current stage
     * @return string
     */
    public function render(core_renderer $output, url $pageurl, form $form): string {
        $items = [];
        $pendingseen = false;
        $number = 0;
        $anycompleted = false;
        foreach ($this->get_states() as $name => $state) {
            $number++;
            $reachable = !$state['skipped'] && ($state['completed'] || !$pendingseen);
            $pendingseen = $pendingseen || !$state['completed'];
            $anycompleted = $anycompleted || ($state['completed'] && !$state['skipped']);
            $items[] = [
                'number' => $number,
                'label' => clean_string($this->stages[$name]),
                'is_active' => ($state['completed'] && !$state['skipped']) || $state['current'],
                'is_current' => $state['current'],
                'is_skipped' => $state['skipped'],
                'is_link' => $reachable && !$state['current'],
                'url' => $this->get_url($pageurl, $name)->out(false),
            ];
        }
        // Leaving the page loses completed stages, nothing links back to a running wizard.
        if ($anycompleted) {
            $form->set_dirty(true);
        }
        return $output->render_from_template('tool_mulib/muform/wizard', [
            'title' => clean_string(get_string('muform_steps', 'tool_mulib')),
            'skippedhint' => clean_string(get_string('muform_stepskipped', 'tool_mulib')),
            'stages' => $items,
            'formhtml' => $form->render($output),
        ]);
    }

    /**
     * State of stages from resolve_stages().
     *
     * @return array<string,array{completed:bool,current:bool,skipped:bool}>
     */
    private function get_states(): array {
        if ($this->states === null) {
            throw new coding_exception('Wizard stages are not resolved yet');
        }
        return $this->states;
    }

    /**
     * Data and completed stages from the row.
     *
     * @return array{data:array,completed:string[]}
     */
    private function decode(): array {
        $decoded = json_decode($this->record->jsondata, true);
        $data = (is_array($decoded) && is_array($decoded['data'] ?? null)) ? $decoded['data'] : [];
        $completed = [];
        if (is_array($decoded) && is_array($decoded['completed'] ?? null)) {
            foreach ($decoded['completed'] as $name) {
                if (is_string($name)) {
                    $completed[] = $name;
                }
            }
        }
        return ['data' => $data, 'completed' => $completed];
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
     * Stages must be labels indexed by lowercase stage names.
     *
     * @param array $stages
     */
    private static function validate_stages(array $stages): void {
        if (!$stages) {
            throw new coding_exception('Wizard needs at least one stage');
        }
        foreach ($stages as $name => $label) {
            if (!is_string($name) || !is_string($label) || !preg_match('/^[a-z]+$/D', $name)) {
                throw new coding_exception('Wizard stages must be labels indexed by lowercase stage names');
            }
        }
    }

    /**
     * Hash binding the row to the wizard name, stages, user and login session.
     *
     * @param string $name
     * @param array $stages
     * @param int $userid
     * @param int $timecreated
     * @return string
     */
    private static function hash(string $name, array $stages, int $userid, int $timecreated): string {
        return sha1($name . '/' . implode(',', array_keys($stages)) . '/' . $userid . '/' . sesskey() . '/' . $timecreated);
    }
}
