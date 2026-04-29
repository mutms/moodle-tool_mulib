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

namespace tool_mulib\local;

use tool_mulib\local\generator\base;
use tool_mulib\local\generator\core_course_generator;
use tool_mulib\local\generator\mod_page_generator;
use tool_mulib\local\generator\mod_book_generator;
use tool_mulib\local\generator\mod_forum_generator;
use tool_mulib\local\generator\mod_assign_generator;
use tool_mulib\local\generator\mod_label_generator;
use tool_mulib\local\generator\mod_folder_generator;
use tool_mulib\local\generator\mod_url_generator;
use tool_mulib\local\generator\mod_resource_generator;
use tool_mulib\local\generator\mod_quiz_generator;
use tool_mulib\local\generator\mod_scorm_generator;
use tool_mulib\local\generator\mod_lesson_generator;
use tool_mulib\local\generator\mod_lti_generator;
use tool_mulib\local\generator\mod_glossary_generator;
use tool_mulib\local\generator\mod_bigbluebutton_generator;
use tool_mulib\local\generator\mod_wiki_generator;

/**
 * Generator registry and factory.
 *
 * Usage:
 *   $generator = \core\di::get(\tool_mulib\local\generator::class);
 *   $course = $generator->core_course->create_course(['category' => $catid]);
 *   $page = $generator->mod_page->create_activity(['course' => $course, 'name' => 'Hello']);
 *
 * @package    tool_mulib
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @property-read core_course_generator $core_course
 * @property-read mod_page_generator $mod_page
 * @property-read mod_book_generator $mod_book
 * @property-read mod_forum_generator $mod_forum
 * @property-read mod_assign_generator $mod_assign
 * @property-read mod_label_generator $mod_label
 * @property-read mod_folder_generator $mod_folder
 * @property-read mod_url_generator $mod_url
 * @property-read mod_resource_generator $mod_resource
 * @property-read mod_quiz_generator $mod_quiz
 * @property-read mod_scorm_generator $mod_scorm
 * @property-read mod_lesson_generator $mod_lesson
 * @property-read mod_lti_generator $mod_lti
 * @property-read mod_glossary_generator $mod_glossary
 * @property-read mod_bigbluebutton_generator $mod_bigbluebutton
 * @property-read mod_wiki_generator $mod_wiki
 */
final class generator {

    /** @var array<string, base> cached generator instances */
    private array $generators = [];

    /** @var array<string, class-string<base>> component => class map */
    private array $classes;

    /**
     * Constructor — collects built-in and hook-registered generators.
     */
    public function __construct() {
        // Built-in generators.
        $this->classes = [
            'core_course' => core_course_generator::class,
            'mod_page' => mod_page_generator::class,
            'mod_book' => mod_book_generator::class,
            'mod_forum' => mod_forum_generator::class,
            'mod_assign' => mod_assign_generator::class,
            'mod_label' => mod_label_generator::class,
            'mod_folder' => mod_folder_generator::class,
            'mod_url' => mod_url_generator::class,
            'mod_resource' => mod_resource_generator::class,
            'mod_quiz' => mod_quiz_generator::class,
            'mod_scorm' => mod_scorm_generator::class,
            'mod_lesson' => mod_lesson_generator::class,
            'mod_lti' => mod_lti_generator::class,
            'mod_glossary' => mod_glossary_generator::class,
            'mod_bigbluebutton' => mod_bigbluebutton_generator::class,
            'mod_wiki' => mod_wiki_generator::class,
        ];

        // Hook-registered generators from external plugins.
        $hook = new \tool_mulib\hook\generator_classes();
        foreach ($hook->get_classes() as $component => $classname) {
            $this->classes[$component] = $classname;
        }
    }

    /**
     * Get a generator for the given component.
     *
     * @param string $component e.g. 'core_course', 'mod_page', 'tool_muprog'
     * @return base
     * @throws \coding_exception if no generator registered for this component
     */
    public function get_generator(string $component): base {
        if (isset($this->generators[$component])) {
            return $this->generators[$component];
        }
        if (!isset($this->classes[$component])) {
            throw new \coding_exception("No generator registered for component '$component'");
        }
        $this->generators[$component] = new $this->classes[$component]($this);
        return $this->generators[$component];
    }

    /**
     * Property shortcut for get_generator().
     *
     * Allows $generator->mod_page instead of $generator->get_generator('mod_page').
     *
     * @param string $name component name
     * @return base
     */
    public function __get(string $name): base {
        return $this->get_generator($name);
    }

    /**
     * Reset all instantiated component generators (defaults, placeholders).
     */
    public function reset(): void {
        foreach ($this->generators as $gen) {
            $gen->reset();
        }
    }

    /**
     * Returns all registered component names.
     *
     * @return string[]
     */
    public function get_registered_components(): array {
        return array_keys($this->classes);
    }
}
