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
use core\url;
use stdClass;
use core\exception\moodle_exception;
use core_renderer;
use tool_mulib\hook\muform_definition;
use tool_mulib\muform\util\display_manager;
use tool_mulib\muform\util\template;

/**
 * A form base class.
 *
 * The constructor does all the work: reads submitted data, calls definition(),
 * dispatches the muform_definition hook, finalises the form, resolves the state
 * (cancelled, reloaded, invalid, valid) and validates submitted data.
 *
 * Rules that make forms predictable:
 *  1. An element value never changes after the element is attached, add() parses it immediately,
 *     so definition() may branch on $element->get_value().
 *  2. Hiding and disabling via display manager are cosmetic, hidden elements are still submitted
 *     and validated, disabled elements are not submitted by browsers so current data is used.
 *  3. Extra validation belongs to validators and validation(), they run after the form is finalised.
 *  4. Public API is intentionally small, non-public means "not for you".
 *
 * See docs/muform.md for usage examples.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class form {
    /** @var int form is being processed or never submitted */
    private const int STATE_NEW = 0;
    /** @var int form editing was cancelled */
    private const int STATE_CANCELLED = 1;
    /** @var int "no reload" button was pressed */
    private const int STATE_RELOADED = 2;
    /** @var int validation errors detected during submission */
    private const int STATE_INVALID = 3;
    /** @var int form was submitted and all data is valid */
    private const int STATE_VALID = 4;
    /** @var int internal form state */
    private int $state = self::STATE_NEW;

    /** @var url form submission URL */
    private url $targeturl;
    /** @var string internal form identifier based on class name */
    private string $formid;
    /** @var string unique suffix for html element id creation */
    private string $idsuffix;
    /** @var bool true when form constructor finished data processing and form value changes not allowed */
    private bool $finalised = false;
    /** @var display_manager resolves initial hidden and locked state of elements */
    private display_manager $display;
    /** @var element[] all elements indexed by name in order they were added */
    private array $elements = [];
    /** @var string[] names of top level elements */
    private array $topelements = [];
    /** @var array|null data submitted via form _POST, excludes __sesskey and other helpers */
    private ?array $postdata = null;
    /** @var array data from elements */
    private array $data = [];
    /** @var array all form validation errors for each element */
    private array $allerrors = [];

    /** @var array form data before editing, usually from database */
    protected array $currentdata;
    /** @var array additional data passed to form definition */
    protected array $extradata;
    /** @var string template for form rendering */
    protected string $template;
    /** @var array additional data added in callback or definition for form template */
    protected array $templatedata = [];

    /**
     * Form constructor.
     *
     * @param url $targeturl
     * @param array|stdClass $currentdata
     * @param array $extradata
     */
    final public function __construct(url $targeturl, array|stdClass $currentdata, array $extradata = []) {
        $this->currentdata = (array)$currentdata;
        $this->targeturl = $targeturl;
        $this->formid = str_replace('\\', '-', static::class);
        $this->idsuffix = uniqid('', true);
        $this->extradata = $extradata;
        $this->template = 'tool_mulib/muform/form';
        $this->display = new display_manager($this);

        if (isset($_POST['__sesskey']) && ($_POST['__formid'] ?? null) === $this->formid) {
            if (!is_string($_POST['__sesskey']) || sesskey() !== $_POST['__sesskey']) {
                throw new moodle_exception('invalidsesskey');
            }
            $this->postdata = [];
            foreach ($_POST as $k => $v) {
                if (!preg_match(element::NAME_REGEX, $k)) {
                    continue;
                }
                $this->postdata[$k] = fix_utf8($v);
            }
        }

        $this->definition();

        $hook = new muform_definition($this);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);

        $this->finalised = true;

        foreach ($this->elements as $elname => $element) {
            if ($element->returns_data()) {
                $this->data[$elname] = $element->get_value();
            }
            foreach ($element->get_additional_data() as $key => $value) {
                if (!preg_match(element::NAME_REGEX, $key) || isset($this->elements[$key]) || array_key_exists($key, $this->data)) {
                    throw new coding_exception('Invalid additional data key "' . $key . '" in element ' . $elname);
                }
                $this->data[$key] = $value;
            }
        }

        if ($this->postdata !== null) {
            $submitted = null;
            foreach ($this->elements as $element) {
                if ($element->is_cancelling() && $element->get_value()) {
                    // Cancelling always wins.
                    $submitted = false;
                    $this->state = self::STATE_CANCELLED;
                    break;
                }
                if ($element->is_reloading() && $element->get_value()) {
                    $submitted = false;
                    $this->state = self::STATE_RELOADED;
                    continue;
                }
                if ($element->is_submitting() && $element->get_value()) {
                    if ($submitted === null) {
                        $submitted = true;
                    }
                }
            }
            if ($submitted) {
                foreach ($this->elements as $element) {
                    $element->validate($this->allerrors);
                }
                $this->validation($this->data, $this->allerrors);
                foreach (array_keys($this->allerrors) as $elname) {
                    if (!isset($this->elements[$elname])) {
                        debugging('Unknown error element name: ' . $elname, DEBUG_DEVELOPER);
                    }
                }
                if ($this->allerrors) {
                    $this->state = self::STATE_INVALID;
                } else {
                    $this->state = self::STATE_VALID;
                }
            }
        }
    }

    /**
     * Returns suffix to be used for all html element ids.
     *
     * @return string
     */
    final public function get_idsuffix(): string {
        return $this->idsuffix;
    }

    /**
     * Returns current data, usually straight from database.
     *
     * @return array
     */
    final public function get_current_data(): array {
        return $this->currentdata;
    }

    /**
     * Returns additional data from form handler.
     *
     * @return array
     */
    final public function get_extra_data(): array {
        return $this->extradata;
    }

    /**
     * Returns data received via _POST request.
     *
     * @return array|null
     */
    final public function get_post_data(): ?array {
        return $this->postdata;
    }

    /**
     * Is the form editing finalised?
     * If yes, then no changes that affect returned data are allowed.
     *
     * @return bool
     */
    final public function is_finalised(): bool {
        return $this->finalised;
    }

    /**
     * Is this a new , never submitted form?
     *
     * @return bool
     */
    final public function is_new(): bool {
        return self::STATE_NEW === $this->state;
    }

    /**
     * Was the form editing cancelled?
     *
     * @return bool
     */
    final public function is_cancelled(): bool {
        return self::STATE_CANCELLED === $this->state;
    }

    /**
     * Was tehis form reloaded from server intentionally?
     *
     * @return bool
     */
    final public function is_reloaded(): bool {
        return self::STATE_RELOADED === $this->state;
    }

    /**
     * Was the data submitted from browser invalid?
     *
     * @return bool
     */
    final public function is_invalid(): bool {
        return self::STATE_INVALID === $this->state;
    }

    /**
     * Was the submitted data valid?
     *
     * @return bool
     */
    final public function is_valid(): bool {
        return self::STATE_VALID === $this->state;
    }

    /**
     * Returns any kind of submitted data, not fully validated.
     *
     * @return stdClass
     */
    final public function get_non_validated_data(): stdClass {
        if (!$this->is_finalised()) {
            throw new coding_exception('Form must be finalised before reading data');
        }
        return (object)$this->data;
    }

    /**
     * Returns submitted valid data, values are typed by elements (int, string, etc.).
     *
     * @return stdClass|null null if form was not submitted, was cancelled, reloaded or is invalid
     */
    final public function get_data(): ?stdClass {
        if (!$this->is_finalised()) {
            throw new coding_exception('Form must be finalised before reading data');
        }
        if (self::STATE_VALID !== $this->state) {
            return null;
        }
        return (object)$this->data;
    }

    /**
     * Add element to form, the element value is parsed immediately.
     *
     * Configure the element (attributes, required, frozen) before adding it,
     * these cannot be changed afterwards. Cosmetic settings such as hints,
     * help button, template and validators may be added later.
     *
     * @param element $element
     * @param string|null $parent parent element, null means to level element (usually only sections or button group)
     * @param int|null $position child position, null means end of list
     */
    final public function add(element $element, ?string $parent = null, ?int $position = null): void {
        if ($this->is_finalised()) {
            throw new coding_exception('Finalised form cannot be modified');
        }
        $elname = $element->get_name();
        if (isset($this->elements[$elname])) {
            throw new coding_exception('Duplicate element name: ' . $elname);
        }

        if ($parent !== null) {
            if (!isset($this->elements[$parent])) {
                throw new coding_exception('Unknown parent element: ' . $parent);
            }
            $this->elements[$parent]->attach_child($elname, $position);
        } else {
            $this->topelements[] = $elname;
        }

        $this->elements[$elname] = $element;
        $element->attach_to_form($this);
    }

    /**
     * Returns all form elements in order they were added.
     *
     * @return element[]
     */
    final public function get_elements(): array {
        return $this->elements;
    }

    /**
     * Returns element with given name.
     *
     * @param string $elname
     * @return element|null
     */
    final public function get_element(string $elname): ?element {
        return $this->elements[$elname] ?? null;
    }

    /**
     * Returns display manager for hide/disable rules.
     *
     * @return display_manager
     */
    final public function get_display_manager(): display_manager {
        return $this->display;
    }

    /**
     * Form definition, add elements and display rules here.
     *
     * Elements are attached with $this->add() and parse their value immediately,
     * so it is safe to read $element->get_value() right after adding it and build
     * the rest of the definition based on submitted data (use a reload button to resubmit).
     */
    abstract protected function definition(): void;

    /**
     * Additional form validation, called only when the form was submitted
     * and after all element validators.
     *
     * @param array $data submitted data indexed by element names, already parsed by elements
     * @param array $allerrors all form errors indexed by element names, add errors as $allerrors['name'][] = 'text'
     */
    protected function validation(array $data, array &$allerrors): void {
    }

    /**
     * Override form template.
     *
     * @param string $template
     * @return void
     */
    final public function set_template(string $template): void {
        $this->template = $template;
    }

    /**
     * Returns template context data.
     *
     * @param core_renderer $output
     * @param string $variant template variant
     * @return array
     * @throws coding_exception
     */
    private function get_template_data(core_renderer $output, string $variant): array {
        if (!$this->is_finalised()) {
            throw new coding_exception('Only finalised forms can be rendered');
        }

        $context = $this->templatedata;
        $context['formid'] = $this->formid;
        $context['idsuffix'] = $this->get_idsuffix();
        $context['targeturl'] = $this->targeturl->out(false);
        $context['sesskey'] = sesskey();
        $context['rulesjson'] = $this->display->get_rules_json();
        $context['elements_byname'] = [];
        $context['elements'] = [];
        $context['has_errors'] = !empty($this->allerrors);
        $context['errors'] = [];

        foreach ($this->allerrors as $elname => $errors) {
            if (isset($this->elements[$elname])) {
                continue;
            }
            foreach ($errors as $error) {
                $context['errors'][] = clean_string($error);
            }
        }

        $this->display->apply_flags();

        // Custom form templates place elements by name: {{#elements_byname.x}}{{{html}}}{{/elements_byname.x}}.
        foreach ($this->topelements as $elname) {
            $html = $this->elements[$elname]->render($output, $this->allerrors, $variant);
            $context['elements_byname'][$elname] = ['name' => $elname, 'html' => $html];
        }
        $context['elements'] = array_values($context['elements_byname']);

        return $context;
    }

    /**
     * Render the form.
     *
     * Variant templates have "-variant" suffix and are used for the form and all elements
     * when they exist, other templates fall back to the standard ones.
     *
     * @param core_renderer $output
     * @param string $variant optional template variant such as 'compact'
     * @return string
     */
    final public function render(core_renderer $output, string $variant = ''): string {
        $context = $this->get_template_data($output, $variant);
        return $output->render_from_template(template::resolve($this->template, $variant), $context);
    }
}
