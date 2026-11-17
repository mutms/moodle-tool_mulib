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

namespace tool_mulib\muform\picker;

use tool_mulib\local\sql;

/**
 * Filter of a picker panel, shown as a pill above the list of candidates.
 *
 * A filter has one value picked from its options or no value at all, then it does not restrict the list.
 * The default value is used when the panel opens. Pinned filters stay visible without a value, the others
 * are then offered by the "Add filter" button. Filters only narrow down the candidates of the source,
 * they are not an access control: values are validated by the source without them.
 *
 * Any picker family can have filters: the source defines them, the family endpoint applies them with
 * filter_set and the panel shows them with the tool_mulib/muform/pickerfilters component.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class filter {
    /** @var string Yes and No buttons */
    public const string TYPE_BOOLEAN = 'boolean';

    /** @var string list of options */
    public const string TYPE_SELECT = 'select';

    /** @var string value searched in an autocomplete source */
    public const string TYPE_AUTOCOMPLETE = 'autocomplete';

    /** @var string value of Yes in boolean filters */
    public const string YES = '1';

    /** @var string value of No in boolean filters */
    public const string NO = '0';

    /** @var string[] option labels indexed with values */
    private array $options = [];

    /** @var \tool_mulib\muform\autocomplete\base|null source of values of autocomplete filters */
    private ?\tool_mulib\muform\autocomplete\base $source = null;

    /** @var string[] label html of values allowed by the source of autocomplete filters */
    private array $labels = [];

    /** @var bool stays visible without a value */
    private bool $pinned = false;

    /** @var string|null text of a pinned filter without a value */
    private ?string $anylabel = null;

    /**
     * Constructor.
     *
     * @param string $key
     * @param string $type
     * @param string $label
     * @param array $options labels indexed with values
     * @param \Closure $where
     * @param string|null $default
     */
    private function __construct(
        /** @var string unique name of the filter in the source */
        private readonly string $key,
        /** @var string one of the TYPE_ constants */
        private readonly string $type,
        /** @var string name of the filter */
        private readonly string $label,
        array $options,
        /** @var \Closure condition for a value */
        private readonly \Closure $where,
        /** @var string|null value used when the panel opens */
        private readonly ?string $default,
    ) {
        if (!preg_match('/^[a-z][a-z0-9_]*$/D', $key)) {
            throw new \core\exception\coding_exception('Invalid picker filter key: ' . $key);
        }
        foreach ($options as $value => $text) {
            $this->options[(string)$value] = (string)$text;
        }
        if ($default !== null && $type !== self::TYPE_AUTOCOMPLETE && !isset($this->options[$default])) {
            throw new \core\exception\coding_exception('Default value of picker filter is not an option: ' . $key);
        }
    }

    /**
     * Filter with Yes and No values.
     *
     * @param string $key unique name of the filter in the source, lowercase letters, digits and underscores
     * @param string $label
     * @param sql $yes condition for Yes, on the table aliases of the picker family
     * @param sql $no condition for No
     * @param bool|null $default value when the panel opens, null for no value
     * @return self
     */
    public static function boolean(string $key, string $label, sql $yes, sql $no, ?bool $default = null): self {
        return new self(
            $key,
            self::TYPE_BOOLEAN,
            $label,
            [self::YES => get_string('yes'), self::NO => get_string('no')],
            fn(string $value): sql => ($value === self::YES) ? $yes : $no,
            ($default === null) ? null : ($default ? self::YES : self::NO),
        );
    }

    /**
     * Filter with a list of options.
     *
     * @param string $key unique name of the filter in the source, lowercase letters, digits and underscores
     * @param string $label
     * @param array $options plain text labels indexed with values
     * @param \Closure $where function (string $value): sql, condition for one of the values
     * @param string|null $default value when the panel opens, null for no value
     * @return self
     */
    public static function select(string $key, string $label, array $options, \Closure $where, ?string $default = null): self {
        return new self($key, self::TYPE_SELECT, $label, $options, $where, $default);
    }

    /**
     * Filter with one value searched in an autocomplete source, for values that cannot be listed as options.
     *
     * The panel searches the source through the autocomplete endpoint, so the source must live
     * in a <component>\muform\autocomplete namespace and do its own access control;
     * only values known to labels() of the source are accepted.
     *
     * @param string $key unique name of the filter in the source, lowercase letters, digits and underscores
     * @param string $label
     * @param \tool_mulib\muform\autocomplete\base $source
     * @param \Closure $where function (string $value): sql, condition for a value
     * @param string|null $default value when the panel opens, null for no value
     * @return self
     */
    public static function autocomplete(
        string $key,
        string $label,
        \tool_mulib\muform\autocomplete\base $source,
        \Closure $where,
        ?string $default = null
    ): self {
        $filter = new self($key, self::TYPE_AUTOCOMPLETE, $label, [], $where, $default);
        $filter->source = $source;
        return $filter;
    }

    /**
     * Keep the filter in the bar when it has no value, with a text such as "Active: Any".
     *
     * @param bool $pinned
     * @param string|null $anylabel text shown instead of the value, "Any" by default
     * @return $this
     */
    public function set_pinned(bool $pinned = true, ?string $anylabel = null): static {
        $this->pinned = $pinned;
        $this->anylabel = $anylabel;
        return $this;
    }

    /**
     * Unique name of the filter in the source.
     *
     * @return string
     */
    public function get_key(): string {
        return $this->key;
    }

    /**
     * Value used when the panel opens.
     *
     * @return string|null
     */
    public function get_default(): ?string {
        return $this->default;
    }

    /**
     * Value that is one of the options or a value allowed by the autocomplete source.
     *
     * @param mixed $value submitted value
     * @return string|null null when the filter has no value
     */
    public function normalise(mixed $value): ?string {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $value = (string)$value;
        if ($this->source) {
            if ($value !== '' && !isset($this->labels[$value])) {
                $labels = $this->source->labels([$value]);
                if (isset($labels[$value])) {
                    $this->labels[$value] = (string)$labels[$value];
                }
            }
            return isset($this->labels[$value]) ? $value : null;
        }
        return isset($this->options[$value]) ? $value : null;
    }

    /**
     * Condition of a value.
     *
     * @param string|null $value normalised value
     * @return sql|null null when the filter does not restrict the list
     */
    public function get_sql(?string $value): ?sql {
        if ($value === null || $this->normalise($value) === null) {
            return null;
        }
        $sql = ($this->where)($value);
        if (!$sql instanceof sql) {
            throw new \core\exception\coding_exception('Picker filter must return sql: ' . $this->key);
        }
        return $sql;
    }

    /**
     * Data for the panel.
     *
     * @param string|null $value normalised current value
     * @return array with key, type, label, options (value, label), value, default, pinned and anylabel;
     *      autocomplete filters add source (class, args) and valuelabel (label html of the value)
     */
    public function export(?string $value): array {
        $options = [];
        foreach ($this->options as $optionvalue => $text) {
            $options[] = ['value' => (string)$optionvalue, 'label' => $text];
        }
        $result = [
            'key' => $this->key,
            'type' => $this->type,
            'label' => $this->label,
            'options' => $options,
            'value' => $value,
            'default' => $this->default,
            'pinned' => $this->pinned,
            'anylabel' => $this->anylabel ?? get_string('filterbar_any', 'tool_mulib'),
        ];
        if ($this->source) {
            $result['source'] = ['class' => $this->source::class, 'args' => array_values($this->source->get_args())];
            $result['valuelabel'] = ($value === null) ? null : ($this->labels[$value] ?? null);
        }
        return $result;
    }
}
