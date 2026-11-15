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

use core\url;
use tool_mulib\muform\element\wizardback;
use stdClass;

/**
 * Page of one wizard, see docs/muform.md.
 *
 * The request flow of all wizards is the same: load or start the stored state, find the current stage,
 * show its form, handle Cancel, Back, other reloads and valid submissions. Subclasses describe
 * the wizard - its stages, forms and what is stored - in the hooks, the entry script only reads
 * the page parameters, requires login and calls execute().
 *
 * Access control is not part of this class, subclasses check it before execute(), usually in their constructor.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class wizard_page {
    /** @var wizard the stored state, available in all hooks */
    protected wizard $wizard;
    /** @var array data stored in the wizard, hooks storing a stage change it */
    protected array $state = [];
    /** @var string current stage, available after the stages were resolved */
    protected string $stage;
    /** @var url URL of the current stage, the target of its form */
    protected url $formurl;

    /**
     * Wizard name including the page parameters, different parameters must never share one state.
     *
     * @return string
     */
    abstract protected function get_name(): string;

    /**
     * Stages of the wizard, they never change.
     *
     * @return array<string,string> labels indexed by stage names
     */
    abstract protected function get_stages(): array;

    /**
     * URL of the wizard page with its page parameters.
     *
     * @return url
     */
    abstract protected function get_page_url(): url;

    /**
     * Where to go when the wizard is cancelled.
     *
     * @return url
     */
    abstract protected function get_return_url(): url;

    /**
     * Set up $PAGE - context, URL, layout, title, heading and navigation.
     */
    abstract protected function setup_page(): void;

    /**
     * Does the stored data make the stage complete? Called only for stages the user submitted.
     *
     * @param string $stage
     * @return bool
     */
    abstract protected function is_stage_valid(string $stage): bool;

    /**
     * Form of the stage with its current data.
     *
     * @param string $stage
     * @param url $formurl
     * @return form
     */
    abstract protected function create_form(string $stage, url $formurl): form;

    /**
     * Valid data of a stage were submitted.
     *
     * Store them in $this->state and return null, the stage is then completed and the wizard continues.
     * A stage that finishes the wizard does the job, deletes the wizard and returns where to go;
     * it may also return the URL of a stage to stay in the wizard without completing anything.
     *
     * @param string $stage
     * @param stdClass $data
     * @param form $form
     * @return url|null
     */
    abstract protected function submit_stage(string $stage, stdClass $data, form $form): ?url;

    /**
     * A new wizard was started, store defaults with $this->wizard->set_data() or complete
     * stages that do not have to be submitted.
     *
     * @return string|null stage to open, null means the first stage that is not completed
     */
    protected function start_state(): ?string {
        return null;
    }

    /**
     * Why the wizard cannot be used now, for example the edited record does not allow it any more.
     *
     * Checked before the state is used, $this->state holds the stored data of a running wizard:
     * the stored wizard is deleted, the reason is shown as a notification on the next page
     * and the user goes to the return URL. Access control stays in the constructor.
     *
     * @return string|null null when the wizard can be used
     */
    protected function get_unavailable_reason(): ?string {
        return null;
    }

    /**
     * Check the loaded state before anything else, for example that the edited record was not changed
     * by somebody else meanwhile; delete the wizard and redirect away if it cannot continue.
     */
    protected function check_state(): void {
    }

    /**
     * Stages that do not apply now, they count as completed.
     *
     * @return string[]
     */
    protected function get_skipped_stages(): array {
        return [];
    }

    /**
     * Stages that cannot be changed once they are completed, for example uploaded data
     * that later stages depend on. Stages before a locked stage are locked too.
     *
     * A locked stage is still shown, create_form() renders it read-only (see is_stage_locked()),
     * Continue and Back only move to the next or previous stage and nothing is submitted.
     *
     * @return string[]
     */
    protected function get_locked_stages(): array {
        return [];
    }

    /**
     * Is the stage read-only because it or a later stage is locked and completed?
     *
     * @param string $stage
     * @return bool
     */
    final protected function is_stage_locked(string $stage): bool {
        $names = array_keys($this->get_stages());
        $position = array_search($stage, $names, true);
        foreach ($this->get_locked_stages() as $locked) {
            $lockedposition = array_search($locked, $names, true);
            if ($lockedposition === false) {
                throw new \coding_exception('Unknown locked wizard stage: ' . $locked);
            }
            if ($lockedposition >= $position && $this->wizard->is_completed($locked)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Heading printed above the stepper.
     *
     * @return string|null null means no heading, for example when the page layout shows the title
     */
    protected function get_heading(): ?string {
        return null;
    }

    /**
     * All stages are completed and no stage was requested - wizards without a last stage
     * that finishes them do the job here, delete the wizard and return where to go.
     *
     * @return url|null null means the wizard is finished from the form of its last stage
     */
    protected function finish(): ?url {
        return null;
    }

    /**
     * The form was reloaded with another button than Back, for example a row was added.
     *
     * Change $this->state only: a changed state is stored and the stage is marked as changed,
     * so that Back validates it and leaving the stage asks for confirmation.
     *
     * @param string $stage
     * @param form $form
     * @return bool true if the stage is to be shown again from the stored data, false shows the submitted values
     */
    protected function reload_stage(string $stage, form $form): bool {
        return false;
    }

    /**
     * Do later stages stay completed when a stage is submitted? The wizard then goes to the following stage.
     *
     * For wizards editing existing data, where all stages are completed from the start
     * and the last stage reviews everything.
     *
     * @return bool false means later stages must be completed again
     */
    protected function keeps_later_stages(): bool {
        return false;
    }

    /**
     * Print the page.
     *
     * @param form $form form of the current stage
     */
    protected function render_page(form $form): void {
        global $OUTPUT;

        echo $OUTPUT->header();
        $heading = $this->get_heading();
        if ($heading !== null) {
            echo $OUTPUT->heading($heading, 2, 'mb-3');
        }
        echo $this->wizard->render($OUTPUT, $this->get_page_url(), $form);
        echo $OUTPUT->footer();
    }

    /**
     * The stage after the current one that is not skipped.
     *
     * @return string|null null after the last stage
     */
    private function get_next_stage(): ?string {
        $skipped = $this->get_skipped_stages();
        $after = false;
        foreach (array_keys($this->get_stages()) as $name) {
            if ($after && !in_array($name, $skipped, true)) {
                return $name;
            }
            $after = $after || ($name === $this->stage);
        }
        return null;
    }

    /**
     * Handle the request.
     */
    final public function execute(): void {
        $pageurl = $this->get_page_url();
        $this->setup_page();

        $name = $this->get_name();
        $stages = $this->get_stages();
        $wizard = wizard::load($name, $stages);
        if ($wizard) {
            $this->wizard = $wizard;
            $this->state = $wizard->get_data();
        }
        $reason = $this->get_unavailable_reason();
        if ($reason !== null) {
            $wizard?->delete();
            \core\notification::warning($reason);
            redirect($this->get_return_url());
        }
        if (!$wizard) {
            $this->wizard = wizard::start($name, $stages);
            redirect($this->wizard->get_url($pageurl, $this->start_state()));
        }
        $this->check_state();

        $isvalid = fn(string $stage): bool => $this->is_stage_valid($stage);
        $this->stage = $wizard->resolve_stages($isvalid, $this->get_skipped_stages());
        if ($wizard->is_finished()) {
            $url = $this->finish();
            if ($url !== null) {
                redirect($url);
            }
        }
        $this->formurl = $wizard->get_url($pageurl, $this->stage);
        $locked = $this->is_stage_locked($this->stage);
        $form = $this->create_form($this->stage, $this->formurl);
        if (!$locked && $wizard->is_changed($this->stage)) {
            // Rows were changed without validation before.
            $form->set_changed(true);
        }
        $previous = $wizard->get_previous_stage();
        $back = $form->get_element(wizardback::NAME);

        if ($form->is_cancelled()) {
            $wizard->delete();
            redirect($this->get_return_url());
        }
        if ($locked && !$form->is_new()) {
            // Nothing can be changed, the buttons only move to the previous or next stage.
            $goback = $previous !== null && $back?->get_value();
            redirect($wizard->get_url($pageurl, $goback ? $previous : $this->get_next_stage()));
        }
        if ($form->is_reloaded()) {
            if ($previous !== null && $back?->get_value()) {
                // Nothing was changed, or the stage is never stored when going back.
                redirect($wizard->get_url($pageurl, $previous));
            }
            $before = $this->state;
            $again = $this->reload_stage($this->stage, $form);
            if ($this->state !== $before) {
                $wizard->set_data($this->state);
                $wizard->mark_changed($this->stage);
            }
            if ($again) {
                redirect($this->formurl);
            }
        }
        if ($data = $form->get_data()) {
            $url = $this->submit_stage($this->stage, $data, $form);
            if ($url !== null) {
                redirect($url);
            }
            // Back pressed after changes: the stage was validated and is stored like with Continue.
            $goback = $previous !== null && $back instanceof wizardback && $back->get_value() && $back->is_submitting();
            if ($this->keeps_later_stages()) {
                $names = array_keys($stages);
                $wizard->mark_changed($this->stage, false);
                $wizard->complete_stage(end($names), $this->state);
                $next = $names[array_search($this->stage, $names, true) + 1] ?? null;
                redirect($wizard->get_url($pageurl, $goback ? $previous : $next));
            }
            $wizard->complete_stage($this->stage, $this->state);
            redirect($wizard->get_url($pageurl, $goback ? $previous : null));
        }

        $this->render_page($form);
    }
}
