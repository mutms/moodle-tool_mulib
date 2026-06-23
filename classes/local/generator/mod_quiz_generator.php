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
     * Slot population accepts two equivalent forms — pick whichever fits
     * the caller:
     *
     *  - `questionids`: int[] — each id becomes one specific-question slot
     *    (added via quiz_add_quiz_question).
     *  - `slots`: array<int, array> — richer shape supporting both specific
     *    and random selection from a category. Each entry is either
     *    `['specific' => questionid]` or `['random' => categoryid, 'count' => n]`.
     *    Mix freely.
     *
     * If both are supplied, `slots` runs first then `questionids` appends.
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
     *     attempts?: int,
     *     timelimit?: int,
     *     timeopen?: int,
     *     timeclose?: int,
     *     overduehandling?: string,
     *     quizpassword?: string,
     *     shuffleanswers?: int,
     *     questionids?: int[],
     *     slots?: array<int, array{specific?: int, random?: int, count?: int}>,
     * } $record
     * @return stdClass quiz record from DB with extra ->cmid field
     */
    public function create_activity(array $record): stdClass {
        global $CFG, $DB;
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
        $moduleinfo->quizpassword = (string)($record['quizpassword'] ?? '');
        $moduleinfo->timeopen = (int)($record['timeopen'] ?? 0);
        $moduleinfo->timeclose = (int)($record['timeclose'] ?? 0);
        $moduleinfo->timelimit = (int)($record['timelimit'] ?? 0);
        $moduleinfo->overduehandling = (string)($record['overduehandling'] ?? 'autosubmit');
        $moduleinfo->attempts = (int)($record['attempts'] ?? 0);
        $moduleinfo->attemptonlast = 0;
        $moduleinfo->shuffleanswers = (int)($record['shuffleanswers'] ?? 0);
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

        if (!empty($record['slots']) || !empty($record['questionids'])) {
            require_once($CFG->dirroot . '/mod/quiz/locallib.php');
            $settings = \mod_quiz\quiz_settings::create($instance->id);
            $structure = \mod_quiz\structure::create_for_quiz($settings);
            // page=0 → append after the current last slot; the quiz's
            // questionsperpage setting drives page assignment.
            foreach ($record['slots'] ?? [] as $slot) {
                if (isset($slot['specific'])) {
                    quiz_add_quiz_question((int)$slot['specific'], $instance, 0);
                } else if (isset($slot['random'])) {
                    $structure->add_random_questions(0,
                        (int)($slot['count'] ?? 1),
                        self::build_category_filter((int)$slot['random']));
                }
            }
            foreach ($record['questionids'] ?? [] as $questionid) {
                quiz_add_quiz_question((int)$questionid, $instance, 0);
            }
            // Recompute sumgrades from slot maxmarks. Without this the quiz
            // refuses to start attempts: "graded out of 100 but no questions
            // have a grade" (cannotstartgradesmismatch).
            $settings->get_grade_calculator()->recompute_quiz_sumgrades();
            $instance->sumgrades = (float)$DB->get_field('quiz', 'sumgrades', ['id' => $instance->id]);
        }

        return $instance;
    }

    /**
     * Build the filtercondition payload expected by
     * \mod_quiz\structure::add_random_questions(), pinning the random
     * selection to a single question category.
     *
     * Matches the shape used by mod_quiz's own UI flow in
     * mod_quiz\external\add_random_questions::execute.
     *
     * @param int $categoryid question_categories.id
     * @return array
     */
    private static function build_category_filter(int $categoryid): array {
        global $DB;
        $cat = $DB->get_record('question_categories', ['id' => $categoryid], 'id, contextid', MUST_EXIST);
        return [
            'qpage' => 0,
            'cat' => "{$cat->id},{$cat->contextid}",
            'qperpage' => DEFAULT_QUESTIONS_PER_PAGE,
            'tabname' => 'questions',
            'sortdata' => [],
            'filter' => [
                'category' => [
                    // JOINTYPE_DEFAULT = 1 (core\table\filter::JOINTYPE_DEFAULT).
                    // Hardcoded to avoid require_once on the qbank class.
                    'jointype' => 1,
                    'values' => [$categoryid],
                    'filteroptions' => ['includesubcategories' => false],
                ],
            ],
        ];
    }
}
