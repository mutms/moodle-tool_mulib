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

namespace tool_mulib\local\generator;

use stdClass;

/**
 * Quiz activity generator.
 *
 * Creates an empty quiz shell — questions to be added manually.
 *
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_quiz_generator extends mod_base {
    /**
     * Module name.
     *
     * @return string always 'quiz'
     */
    public function get_modulename(): string {
        return 'quiz';
    }

    /**
     * Create a quiz activity.
     *
     * @param array{
     *     course: int|stdClass,
     *     name?: string,
     *     section?: int,
     *     intro?: string,
     *     introformat?: int,
     *     introfiles?: array<string, \stored_file|string|array{content: string}>,
     *     visible?: bool,
     *     grade?: int,
     *     questionids?: int[],
     * } $record
     * @return stdClass quiz record from DB with extra ->cmid field
     */
    public function create_activity(array $record): stdClass {
        global $CFG;
        [$record, $course] = $this->prepare_record($record);

        $moduleinfo = $this->build_moduleinfo(
            $course,
            (int)($record['section'] ?? 0),
            $record['name'],
            $record['intro'] ?? '',
            $record['introformat'] ?? null,
            !isset($record['visible']) || $record['visible'],
            $record['introfiles'] ?? null,
        );

        $moduleinfo->preferredbehaviour = 'deferredfeedback';
        $moduleinfo->grade = (int)($record['grade'] ?? 100);
        $moduleinfo->questionsperpage = 1;
        $moduleinfo->quizpassword = '';
        $moduleinfo->timeopen = 0;
        $moduleinfo->timeclose = 0;
        $moduleinfo->overduehandling = 'autosubmit';
        $moduleinfo->attempts = 0;
        $moduleinfo->attemptonlast = 0;
        $moduleinfo->grademethod = 1; // QUIZ_GRADEHIGHEST.
        $moduleinfo->decimalpoints = 2;
        $moduleinfo->questiondecimalpoints = -1;
        $moduleinfo->attemptduring = 1;
        $moduleinfo->correctnessduring = 1;
        $moduleinfo->maxmarksduring = 1;
        $moduleinfo->marksduring = 1;
        $moduleinfo->specificfeedbackduring = 1;
        $moduleinfo->generalfeedbackduring = 1;
        $moduleinfo->rightanswerduring = 1;
        $moduleinfo->overallfeedbackduring = 0;
        $moduleinfo->attemptimmediately = 1;
        $moduleinfo->correctnessimmediately = 1;
        $moduleinfo->maxmarksimmediately = 1;
        $moduleinfo->marksimmediately = 1;
        $moduleinfo->specificfeedbackimmediately = 1;
        $moduleinfo->generalfeedbackimmediately = 1;
        $moduleinfo->rightanswerimmediately = 1;
        $moduleinfo->overallfeedbackimmediately = 1;
        $moduleinfo->attemptopen = 1;
        $moduleinfo->correctnessopen = 1;
        $moduleinfo->maxmarksopen = 1;
        $moduleinfo->marksopen = 1;
        $moduleinfo->specificfeedbackopen = 1;
        $moduleinfo->generalfeedbackopen = 1;
        $moduleinfo->rightansweropen = 1;
        $moduleinfo->overallfeedbackopen = 1;
        $moduleinfo->attemptclosed = 1;
        $moduleinfo->correctnessclosed = 1;
        $moduleinfo->maxmarksclosed = 1;
        $moduleinfo->marksclosed = 1;
        $moduleinfo->specificfeedbackclosed = 1;
        $moduleinfo->generalfeedbackclosed = 1;
        $moduleinfo->rightanswerclosed = 1;
        $moduleinfo->overallfeedbackclosed = 1;

        $instance = $this->add($moduleinfo, $course);

        if (!empty($record['questionids'])) {
            require_once($CFG->dirroot . '/mod/quiz/locallib.php');
            // page=0 → append after current last slot; quiz_add_quiz_question
            // handles page assignment via the quiz's questionsperpage setting.
            foreach ($record['questionids'] as $questionid) {
                quiz_add_quiz_question((int)$questionid, $instance, 0);
            }
        }

        return $instance;
    }
}
