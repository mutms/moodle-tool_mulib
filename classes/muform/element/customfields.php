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

namespace tool_mulib\muform\element;

use core\exception\coding_exception;
use core_customfield\api;
use core_customfield\handler;
use stdClass;
use tool_mulib\local\mulib;
use tool_mulib\muform\customfield;
use tool_mulib\muform\element;
use tool_mulib\muform\util\file_area;

/**
 * Custom fields of one instance, adds a section per category with an element per field
 * and stores the values directly in customfield_data.
 *
 * Only field types with a mapper in tool_mulib\muform\customfield are supported,
 * other types, shared categories, automatic number fields and fields the handler
 * does not allow to edit are skipped. Field elements are named "customfield_<shortname>",
 * their values are part of form data. Call save() with the instance id after the instance
 * itself was saved, deleting and displaying of custom fields data is left to the core API.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class customfields extends element {
    /** @var array mapper classes indexed by supported field types */
    private const array MAPPERS = [
        'checkbox' => customfield\checkbox::class,
        'date' => customfield\date::class,
        'mutrain' => customfield\mutrain::class,
        'number' => customfield\number::class,
        'select' => customfield\select::class,
        'text' => customfield\text::class,
        'textarea' => customfield\textarea::class,
    ];

    /** @var handler custom field area handler */
    private handler $handler;
    /** @var int|null instance id, null for new instances */
    private ?int $instanceid;
    /** @var array categories with name and list of editable fields (field controller, mapper and stored data record) */
    private array $categories = [];

    /**
     * Constructor.
     *
     * @param string $name
     * @param handler $handler
     * @param int|null $instanceid null when creating new instance
     */
    public function __construct(string $name, handler $handler, ?int $instanceid) {
        global $DB, $CFG;
        require_once("$CFG->libdir/filelib.php");

        parent::__construct($name);
        $this->handler = $handler;
        $this->instanceid = $instanceid;

        $datas = [];
        if ($instanceid) {
            $rs = $DB->get_recordset('customfield_data', [
                'instanceid' => $instanceid,
                'component' => $handler->get_component(),
                'area' => $handler->get_area(),
                'itemid' => $handler->get_itemid(),
            ]);
            foreach ($rs as $data) {
                $datas[$data->fieldid] = $data;
            }
            $rs->close();
        }

        $categories = api::get_categories_with_fields($handler->get_component(), $handler->get_area(), $handler->get_itemid());
        foreach ($categories as $category) {
            if ($category->get('shared')) {
                continue;
            }
            $category->set_handler($handler);
            $fields = [];
            foreach ($category->get_fields() as $field) {
                $class = self::MAPPERS[$field->get('type')] ?? null;
                if (!$class) {
                    continue;
                }
                /** @var customfield\base $mapper */
                $mapper = new $class($field);
                if (!$mapper->is_editable() || !$handler->can_edit($field, (int)$instanceid)) {
                    continue;
                }
                $fields[] = [
                    'field' => $field,
                    'mapper' => $mapper,
                    'data' => $datas[$field->get('id')] ?? null,
                ];
            }
            if ($fields) {
                $this->categories[$category->get('id')] = [
                    'name' => $category->get_formatted_name(),
                    'fields' => $fields,
                ];
            }
        }
    }

    #[\Override]
    public function accepts_children(): bool {
        return true;
    }

    #[\Override]
    public function returns_data(): bool {
        return false;
    }

    #[\Override]
    protected function parse_value(): void {
        $this->value = null;
    }

    #[\Override]
    protected function attached(): void {
        $form = $this->get_form();
        $context = $this->handler->get_instance_context((int)$this->instanceid);
        $configcontext = $this->handler->get_configuration_context();

        foreach ($this->categories as $categoryid => $category) {
            $section = new section($this->get_name() . '_category_' . $categoryid, $category['name']);
            $form->add($section, $this->get_name());

            foreach ($category['fields'] as ['field' => $field, 'mapper' => $mapper, 'data' => $data]) {
                $elname = 'customfield_' . $field->get('shortname');
                $element = $mapper->create_element($elname, $field->get_formatted_name(), $data, $context);
                $element->set_default($data ? $mapper->get_stored_value($data) : $mapper->get_default_value());
                $element->add_validator(function (element $element, array &$allerrors) use ($field, $mapper): void {
                    if (!empty($allerrors[$element->get_name()])) {
                        return;
                    }
                    $mapper->validate($element, $allerrors);
                    if (empty($allerrors[$element->get_name()]) && $field->get_configdata_property('uniquevalues')) {
                        $this->validate_unique($element, $field, $mapper, $allerrors);
                    }
                });
                $form->add($element, $section->get_name());

                $description = (string)$field->get('description');
                if (trim($description) !== '') {
                    $description = file_rewrite_pluginfile_urls(
                        $description,
                        'pluginfile.php',
                        $configcontext->id,
                        'core_customfield',
                        'description',
                        $field->get('id')
                    );
                    $description = format_text($description, $field->get('descriptionformat'), ['context' => $configcontext]);
                    $info = new inforawhtml($this->get_name() . '_description_' . $field->get('id'), '', $description);
                    $form->add($info, $section->get_name());
                }
            }
        }
    }

    /**
     * Make sure the value is not used in any other instance.
     *
     * @param element $element
     * @param \core_customfield\field_controller $field
     * @param customfield\base $mapper
     * @param array $allerrors
     */
    private function validate_unique(
        element $element,
        \core_customfield\field_controller $field,
        customfield\base $mapper,
        array &$allerrors
    ): void {
        global $DB;

        if ($mapper->is_empty($element->get_value())) {
            return;
        }
        $datafield = $mapper->get_datafield();
        $value = $mapper->get_data_columns($element)[$datafield];
        $column = ($datafield === 'value') ? $DB->sql_compare_text('value', 1333) : $datafield;

        $select = "fieldid = :fieldid AND $column = :value";
        $params = ['fieldid' => $field->get('id'), 'value' => $value];
        if ($this->instanceid) {
            $select .= " AND NOT (instanceid = :instanceid AND component = :component AND area = :area AND itemid = :itemid)";
            $params['instanceid'] = $this->instanceid;
            $params['component'] = $this->handler->get_component();
            $params['area'] = $this->handler->get_area();
            $params['itemid'] = $this->handler->get_itemid();
        }
        if ($DB->record_exists_select('customfield_data', $select, $params)) {
            $allerrors[$element->get_name()][] = get_string('erroruniquevalues', 'core_customfield');
        }
    }

    /**
     * Store submitted values of all field elements.
     *
     * @param int $instanceid id of the instance, required also for new instances
     */
    public function save(int $instanceid): void {
        global $DB;

        $form = $this->get_form();
        if (!$form || !$form->is_valid()) {
            throw new coding_exception('Custom fields can be saved only from valid forms');
        }
        if ($instanceid <= 0 || ($this->instanceid && $this->instanceid !== $instanceid)) {
            throw new coding_exception('Invalid custom fields instance id');
        }

        $context = $this->handler->get_instance_context($instanceid);
        $now = time();

        foreach ($this->categories as $category) {
            foreach ($category['fields'] as ['field' => $field, 'mapper' => $mapper]) {
                $element = $form->get_element('customfield_' . $field->get('shortname'));
                $unique = [
                    'instanceid' => $instanceid,
                    'fieldid' => $field->get('id'),
                    'component' => $this->handler->get_component(),
                    'area' => $this->handler->get_area(),
                    'itemid' => $this->handler->get_itemid(),
                ];
                $record = $unique + $mapper->get_data_columns($element) + ['timemodified' => $now];
                $insertonly = [
                    'contextid' => $context->id,
                    'timecreated' => $now,
                ];
                if (!array_key_exists('valueformat', $record)) {
                    $insertonly['valueformat'] = FORMAT_MOODLE;
                }
                if (!array_key_exists('valuetrust', $record)) {
                    $insertonly['valuetrust'] = 0;
                }
                mulib::upsert_record('customfield_data', $record, array_keys($unique), $insertonly);

                if ($element instanceof editor) {
                    $data = $DB->get_record('customfield_data', $unique, 'id, contextid', MUST_EXIST);
                    $element->export_to_file_area(
                        new file_area((int)$data->contextid, 'customfield_textarea', 'value', (int)$data->id)
                    );
                }
            }
        }
    }
}
