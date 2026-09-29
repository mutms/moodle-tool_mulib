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
// phpcs:disable moodle.Files.LineLength.TooLong
// phpcs:disable moodle.Commenting.DocblockDescription.Missing

// NOTE: No MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\NodeElement;

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');
require_once(__DIR__ . '/../classes/muform/element/base.php');

/**
 * Library mulib behat steps.
 *
 * @package     tool_mulib
 * @copyright   2022 Open LMS (https://www.openlms.net/)
 * @copyright   2025 Petr Skoda
 * @author      Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_tool_mulib extends behat_base {
    /**
     * Migration aid: core form steps used on a page with a muform fail, which is how tests
     * that still drive a migrated form with legacy steps are found.
     *
     * Enabled with define('BEHAT_MULIB_MUFORM_DETECT_WRONGTESTS', true) in config.php.
     * Features of tool_mulib are exempt, their element features use core steps on purpose.
     *
     * @BeforeStep
     * @param BeforeStepScope $scope
     */
    public function check_legacy_form_steps(BeforeStepScope $scope): void {
        if (!defined('BEHAT_MULIB_MUFORM_DETECT_WRONGTESTS') || BEHAT_MULIB_MUFORM_DETECT_WRONGTESTS !== true) {
            return;
        }
        if (str_contains(str_replace('\\', '/', $scope->getFeature()->getFile()), '/admin/tool/mulib/')) {
            return;
        }
        $text = $scope->getStep()->getText();
        $legacy = '/^(I set the field|I set the following fields|the field|the following fields|I expand all fieldsets|I select .* from the .* singleselect)/';
        if (!preg_match($legacy, $text)) {
            return;
        }
        try {
            $muform = $this->getSession()->getPage()->find('css', 'form.muform');
        } catch (\Throwable $e) {
            return;
        }
        if ($muform) {
            throw new ExpectationException(
                'Legacy form step "' . $text . '" used on a page with a muform, update the test to the muform steps',
                $this->getSession()
            );
        }
    }

    /**
     * Click header action
     *
     * @Given I click on :action action from :header dropdown
     *
     * @param string $action
     * @param string $header
     */
    public function i_click_dropdown_action(string $action, string $header) {
        $this->get_selected_node('button', $header)->click();
        $this->get_selected_node('link', $action)->click();
    }

    /**
     * Execute a scheduled task via CURL.
     *
     * @Given I run the :taskname task
     *
     * @param string $taskname
     */
    public function execute_scheduled_task(string $taskname) {
        global $CFG;

        $task = \core\task\manager::get_scheduled_task($taskname);

        if (!$task) {
            throw new DriverException('The "' . $taskname . '" scheduled task does not exist');
        }
        $taskname = get_class($task);

        $ch = new curl();
        $options = [
            'FOLLOWLOCATION' => true,
            'RETURNTRANSFER' => true,
            'SSL_VERIFYPEER' => false,
            'SSL_VERIFYHOST' => 0,
            'HEADER' => 0,
        ];

        $content = $ch->get(
            "$CFG->wwwroot/admin/tool/mulib/tests/behat/task_runner.php",
            ['behat_task' => $taskname],
            $options
        );

        if (!str_contains($content, "Scheduled task '$taskname' completed")) {
            throw new ExpectationException("Scheduled task '$taskname' did not complete successfully, content : " . $content, $this->getSession());
        }

        $this->look_for_exceptions();
    }

    /**
     * Execute a scheduled task via CURL.
     *
     * @Given I run all ad-hoc tasks
     */
    public function execute_adhoc_tasks() {
        global $CFG;

        $ch = new curl();
        $options = [
            'FOLLOWLOCATION' => true,
            'RETURNTRANSFER' => true,
            'SSL_VERIFYPEER' => false,
            'SSL_VERIFYHOST' => 0,
            'HEADER' => 0,
        ];

        $content = $ch->get(
            "$CFG->wwwroot/admin/tool/mulib/tests/behat/adhoc_runner.php",
            [],
            $options
        );

        if (!str_contains($content, 'Ad-hoc tasks completed')) {
            throw new ExpectationException("Ad-hoc tasks did not complete successfully, content : " . $content, $this->getSession());
        }

        $this->look_for_exceptions();
    }

    /**
     * Admin bookmark takes way too much space on admin pages,
     * so get rid of it.
     *
     * @Given unnecessary Admin bookmarks block gets deleted
     */
    public function delete_admin_bookmarks_block(): void {
        global $CFG, $DB;
        require_once("$CFG->libdir/blocklib.php");

        $instance = $DB->get_record('block_instances', ['blockname' => 'admin_bookmarks']);
        if ($instance) {
            blocks_delete_instance($instance);
        }
    }

    /**
     * @Given I skip tests if :plugin is not installed
     *
     * @param string $plugin
     */
    public function skip_if_plugin_missing($plugin): void {
        if (!get_config($plugin, 'version')) {
            throw new \Moodle\BehatExtension\Exception\SkippedException("Tests were skipped because plugin '$plugin' is not installed");
        }
    }

    /**
     * @Given I skip tests if :plugin is installed
     *
     * @param string $plugin
     */
    public function skip_if_plugin_installed($plugin): void {
        if (get_config($plugin, 'version')) {
            throw new \Moodle\BehatExtension\Exception\SkippedException("Tests were skipped because plugin '$plugin' is installed");
        }
    }

    /**
     * @Given I skip tests if :constant is defined and not empty
     *
     * @param string $constant
     */
    public function skip_if_constant_not_empty($constant): void {
        if (defined($constant) && constant($constant)) {
            throw new \Moodle\BehatExtension\Exception\SkippedException("Tests were skipped because constant '$constant' is defined and non-empty");
        }
    }

    /**
     * Looks for definition of a term in a list.
     *
     * @Then I should see :text in the :label definition list item
     *
     * @param string $text
     * @param string $label
     */
    public function list_term_contains_text($text, $label): void {

        $labelliteral = behat_context_helper::escape($label);
        $xpath = "//dl/dt[text()=$labelliteral]/following-sibling::dd[1]";

        $nodes = $this->getSession()->getPage()->findAll('xpath', $xpath);
        if (empty($nodes)) {
            throw new ExpectationException(
                'Unable to find a term item with label = ' . $labelliteral,
                $this->getSession()
            );
        }
        if (count($nodes) > 1) {
            throw new ExpectationException(
                'Found more than one term item with label = ' . $labelliteral,
                $this->getSession()
            );
        }
        $node = reset($nodes);

        $xpathliteral = behat_context_helper::escape($text);
        $xpath = "/descendant-or-self::*[contains(., $xpathliteral)]" .
            "[count(descendant::*[contains(., $xpathliteral)]) = 0]";

        // Wait until it finds the text inside the container, otherwise custom exception.
        try {
            $nodes = $this->find_all('xpath', $xpath, false, $node);
        } catch (ElementNotFoundException $e) {
            throw new ExpectationException('"' . $text . '" text was not found in the "' . $label . '" term', $this->getSession());
        }

        // If we are not running javascript we have enough with the
        // element existing as we can't check if it is visible.
        if (!$this->running_javascript()) {
            return;
        }

        // We also check the element visibility when running JS tests. Using microsleep as this
        // is a repeated step and global performance is important.
        $this->spin(
            function ($context, $args) {

                foreach ($args['nodes'] as $node) {
                    if ($node->isVisible()) {
                        return true;
                    }
                }

                throw new ExpectationException('"' . $args['text'] . '" text was found in the "' . $args['label'] . '" element but was not visible', $context->getSession());
            },
            ['nodes' => $nodes, 'text' => $text, 'label' => $label],
            false,
            false,
            true
        );
    }

    /**
     * Looks into definition of a term in a list and makes sure text is not there.
     *
     * @Then I should not see :text in the :label definition list item
     *
     * @param string $text
     * @param string $label
     */
    public function list_term_note_contains_text($text, $label): void {

        $labelliteral = behat_context_helper::escape($label);
        $xpath = "//dl/dt[text()=$labelliteral]/following-sibling::dd[1]";

        $nodes = $this->getSession()->getPage()->findAll('xpath', $xpath);
        if (empty($nodes)) {
            throw new ExpectationException(
                'Unable to find a term item with label = ' . $labelliteral,
                $this->getSession()
            );
        }
        if (count($nodes) > 1) {
            throw new ExpectationException(
                'Found more than one term item with label = ' . $labelliteral,
                $this->getSession()
            );
        }
        $node = reset($nodes);

        $xpathliteral = behat_context_helper::escape($text);
        $xpath = "/descendant-or-self::*[contains(., $xpathliteral)]" .
            "[count(descendant::*[contains(., $xpathliteral)]) = 0]";

        $nodes = null;
        try {
            $nodes = $this->find_all('xpath', $xpath, false, $node, 0);
        } catch (ElementNotFoundException $e) {
            // Good!
            $nodes = null;
        }
        if ($nodes) {
            throw new ExpectationException('"' . $text . '" text was found in the "' . $label . '" element', $this->getSession());
        }
    }

    /**
     * Opens user profile page.
     *
     * @Given I am on the profile page of user :username
     *
     * @param string $username
     */
    public function i_am_on_user_profile_page(string $username): void {
        global $DB;
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $url = new moodle_url('/user/profile.php', ['id' => $user->id]);
        $this->execute('behat_general::i_visit', [$url]);
    }

    /**
     * Emulate clicking on email change confirmation link from the email.
     *
     * @When /^I confirm changed email for "(?P<username>(?:[^"]|\\")*)"$/
     *
     * @param string $username
     */
    public function i_confirm_changed_email_for(string $username): void {
        global $DB;

        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $key = $DB->get_field('user_private_key', 'value', ['userid' => $user->id, 'script' => 'core_user/email_change']);

        $url = new moodle_url('/user/emailupdate.php', ['id' => $user->id, 'key' => $key]);

        $this->execute('behat_general::i_visit', [$url->out(false)]);
    }

    /**
     * Add BEHAT_VISUAL_CHECK_PAUSE constant to config.php to interactively confirm test result.
     *
     * @When I perform a visual check :assert
     *
     * @param string $assert
     */
    public function i_pause_for_visual_check(string $assert) {
        if (!$this->has_tag('_visual_check')) {
            throw new DriverException('Visual check tests must have @_visual_check tag');
        }
        if (!defined('BEHAT_VISUAL_CHECK_PAUSE') || !BEHAT_VISUAL_CHECK_PAUSE) {
            return;
        }
        if (function_exists('posix_isatty') && !@posix_isatty(STDOUT)) {
            return;
        }

        $message = "<colour:lightRed>Press Enter/Return after confirming that: <colour:normal>$assert";
        behat_util::pause($this->getSession(), $message);
    }

    /**
     * @Given site is prepared for documentation screenshots
     */
    public function prepare_for_documentation_screenshots() {
        global $DB;
        $this->delete_admin_bookmarks_block();
        $this->execute('behat_general::i_change_window_size_to', ['window', '1208x780']);
        $DB->set_field('course', 'shortname', 'MuTMS', ['category' => 0]);
        $DB->set_field('course', 'fullname', 'MuTMS test site', ['category' => 0]);

        if (defined('BEHAT_MUTMS_UPDATE_WIKI_SCREENSHOTS') && BEHAT_MUTMS_UPDATE_WIKI_SCREENSHOTS) {
            // Hide theme footer only if actually taking the screenshot.
            purge_all_caches();
            $this->getSession()->reload();

            set_config('scss', '#page-footer { display: none }', 'theme_boost');

            purge_all_caches();
            theme_build_css_for_themes([theme_config::load('boost')], ['ltr']);
        }

        $this->getSession()->reload();
    }

    /**
     * @Then site is restored after documentation screenshots
     */
    public function restore_for_documentation_screenshots() {
        if (defined('BEHAT_MUTMS_UPDATE_WIKI_SCREENSHOTS') && BEHAT_MUTMS_UPDATE_WIKI_SCREENSHOTS) {
            // Undo hiding of footer to prevent other tests from failing.
            set_config('scss', '', 'theme_boost');
            purge_all_caches();
            theme_build_css_for_themes([theme_config::load('boost')], ['ltr']);
        }

        $this->getSession()->reload();
    }

    /**
     * Take screenshot for and save it as image for plugin documentation.
     *
     * NOTE: does nothing if BEHAT_MUTMS_UPDATE_WIKI_SCREENSHOTS not defined in config
     *
     * @When I make documentation screenshot :image for :plugin plugin
     *
     * @param string $image
     * @param string $plugin
     * @return void
     */
    public function create_documentation_screenshot(string $image, string $plugin) {
        if (!defined('BEHAT_MUTMS_UPDATE_WIKI_SCREENSHOTS') || !BEHAT_MUTMS_UPDATE_WIKI_SCREENSHOTS) {
            return;
        }
        $basedir = core_component::get_component_directory($plugin);
        if (!file_exists("$basedir/wiki")) {
            mkdir("$basedir/wiki");
        }

        file_put_contents("$basedir/wiki/$image", $this->getSession()->getScreenshot());
    }

    /**
     * Helper for adding of custom fields.
     *
     * @When I click add custom field of type :field
     *
     * @param string $field
     * @return void
     */
    public function add_custom_field(string $field) {
        $this->execute("behat_general::i_click_on", [get_string('createnewcustomfield', 'core_customfield'), 'link']);
        $this->execute("behat_general::i_click_on", [$field, 'link']);
    }

    /**
     * Helper for editing of custom fields.
     *
     * @When I click Edit custom field :field
     *
     * @param string $field
     * @return void
     */
    public function edit_custom_field(string $field) {
        global $CFG;

        if ($CFG->version >= 2026032000) {
            $this->execute(
                'behat_general::i_click_on_in_the',
                ['Actions', 'link', $field, 'table_row']
            );
            $this->execute(
                'behat_general::i_click_on_in_the',
                ['Edit', 'link', $field, 'table_row']
            );
        } else {
            $this->execute(
                'behat_general::i_click_on_in_the',
                ['Edit custom field: Test field', 'button', $field, 'table_row']
            );
        }
    }

    /**
     * Helper for deleting of custom fields.
     *
     * @When I click Delete custom field :field
     *
     * @param string $field
     * @return void
     */
    public function delete_custom_field(string $field) {
        global $CFG;

        if ($CFG->version >= 2026032000) {
            $this->execute(
                'behat_general::i_click_on_in_the',
                ['Actions', 'link', $field, 'table_row']
            );
            $this->execute(
                'behat_general::i_click_on_in_the',
                ['Delete', 'link', $field, 'table_row']
            );
        } else {
            $this->execute(
                'behat_general::i_click_on_in_the',
                ['Delete custom field: Test field', 'button', $field, 'table_row']
            );
        }
    }

    /**
     * Open a fixture page with query parameters, core step does not accept query strings.
     *
     * @Given I am on fixture page :url with parameters :params
     *
     * @param string $url fixture page path such as /admin/tool/mulib/tests/behat/fixtures/muform_element_text.php
     * @param string $params query string such as prefill=1&required=1
     */
    public function i_am_on_fixture_page_with_parameters(string $url, string $params): void {
        if (!preg_match('|^/[a-z0-9_\-/]*/tests/behat/fixtures/[a-z0-9_\-]*\.php$|', $url)) {
            throw new coding_exception("URL {$url} is not a fixture URL");
        }
        if (!preg_match('/^[a-z0-9_]+=[a-zA-Z0-9_.\-]*(&[a-z0-9_]+=[a-zA-Z0-9_.\-]*)*$/', $params)) {
            throw new coding_exception("Invalid fixture page parameters {$params}");
        }
        $this->execute('behat_general::i_visit', [$url . '?' . $params]);
    }

    /**
     * Set values of muform elements, first column is element name (or exact label), second column is value.
     *
     * Option elements take exact option keys, comma separated when multiple are possible,
     * exact option labels are accepted only when no option has the given key.
     *
     * @Given /^I set the following muform fields:$/
     *
     * @param TableNode $data
     */
    public function i_set_the_following_muform_fields(TableNode $data): void {
        $this->set_muform_fields($data, null);
    }

    /**
     * Set values of muform elements inside given container.
     *
     * @Given /^I set the following muform fields in the "(?P<element_string>(?:[^"]|\\")*)" "(?P<selector_string>[^"]*)":$/
     *
     * @param string $element
     * @param string $selectortype
     * @param TableNode $data
     */
    public function i_set_the_following_muform_fields_in_the(string $element, string $selectortype, TableNode $data): void {
        $this->set_muform_fields($data, $this->get_text_selector_node($selectortype, $element));
    }

    /**
     * Check values of muform elements, first column is element name (or exact label), second column is expected value.
     *
     * @Then /^the following muform fields match:$/
     *
     * @param TableNode $data
     */
    public function the_following_muform_fields_match(TableNode $data): void {
        $this->match_muform_fields($data, null);
    }

    /**
     * Check values of muform elements inside given container.
     *
     * @Then /^the following muform fields in the "(?P<element_string>(?:[^"]|\\")*)" "(?P<selector_string>[^"]*)" match:$/
     *
     * @param string $element
     * @param string $selectortype
     * @param TableNode $data
     */
    public function the_following_muform_fields_in_the_match(string $element, string $selectortype, TableNode $data): void {
        $this->match_muform_fields($data, $this->get_text_selector_node($selectortype, $element));
    }

    /**
     * Type into the search field of a muform picker without picking any result.
     *
     * @When I type :text into the :locator muform search field
     *
     * @param string $text
     * @param string $locator element name or exact label
     */
    public function i_type_into_muform_search_field(string $text, string $locator): void {
        $this->get_muform_element_helper($locator, null)->type_search($text);
    }

    /**
     * Check that the open muform result list or calendar is not clipped or covered by anything, dialogs included.
     *
     * The corners of the list must be inside the window and the topmost element there must be the list.
     *
     * @Then the open muform list should be fully visible
     */
    public function the_open_muform_list_should_be_fully_visible(): void {
        $js = <<<'JS'
(() => {
    const lists = Array.from(document.querySelectorAll('[role="listbox"], .muform-datetime-panel')).filter((l) => l.getClientRects().length);
    if (lists.length !== 1) {
        return 'expected one open list, found ' + lists.length;
    }
    const list = lists[0];
    const r = list.getBoundingClientRect();
    if (r.top < 0 || r.left < 0 || r.bottom > window.innerHeight || r.right > window.innerWidth) {
        return 'list is outside of the window: ' + JSON.stringify(r);
    }
    const points = [[r.left + 3, r.top + 3], [r.right - 3, r.top + 3], [r.left + 3, r.bottom - 3], [r.right - 3, r.bottom - 3]];
    for (const [x, y] of points) {
        const e = document.elementFromPoint(x, y);
        if (!e || !list.contains(e)) {
            return 'list is covered at ' + x + ',' + y + ' by ' + (e ? e.outerHTML.slice(0, 100) : 'nothing');
        }
    }
    return 'ok';
})()
JS;
        $result = $this->getSession()->evaluateScript('return ' . $js);
        if ($result !== 'ok') {
            throw new ExpectationException('Open muform list is not fully visible: ' . $result, $this->getSession());
        }
    }

    /**
     * Set muform element values.
     *
     * @param TableNode $data
     * @param NodeElement|null $container
     */
    private function set_muform_fields(TableNode $data, ?NodeElement $container): void {
        foreach ($data->getRowsHash() as $locator => $value) {
            $helper = $this->get_muform_element_helper((string)$locator, $container);
            $helper->set_value((string)$value);
            if ($this->running_javascript()) {
                $this->wait_for_pending_js();
            }
        }
    }

    /**
     * Check muform element values.
     *
     * @param TableNode $data
     * @param NodeElement|null $container
     */
    private function match_muform_fields(TableNode $data, ?NodeElement $container): void {
        foreach ($data->getRowsHash() as $locator => $value) {
            $helper = $this->get_muform_element_helper((string)$locator, $container);
            if (!$helper->matches((string)$value)) {
                throw new ExpectationException(
                    'muform element "' . $helper->get_name() . '" value "' . $helper->get_value()
                    . '" does not match expected "' . $value . '"',
                    $this->getSession()
                );
            }
        }
    }

    /**
     * Find muform element wrapper by element name or exact label and create its Behat helper.
     *
     * Helper classes are looked up in <plugin>/tests/classes/muform/element/<type>.php
     * of the component in data-muform-component attribute.
     *
     * @param string $locator element name or exact label text
     * @param NodeElement|null $container
     * @return \tool_mulib\tests\muform\element\base
     */
    private function get_muform_element_helper(string $locator, ?NodeElement $container): \tool_mulib\tests\muform\element\base {
        $container = $container ?? $this->getSession()->getPage();

        // Wait once for the form, then look up without retries, label lookup must not wait for a missing name.
        try {
            $this->find_all('css', '[data-muform-element]', false, $container);
        } catch (ElementNotFoundException $e) {
            throw new ExpectationException('No muform elements found for "' . $locator . '"', $this->getSession());
        }
        $wrappers = $container->findAll('css', '[data-muform-element][data-muform-name="' . $locator . '"]');
        if (!$wrappers) {
            foreach ($container->findAll('css', '[data-muform-element]') as $wrapper) {
                // Only the own label, containers such as sections include labels of their children.
                $labelid = substr((string)$wrapper->getAttribute('id'), strlen('fitem_')) . '_label';
                $label = $wrapper->find('css', '[id="' . $labelid . '"]');
                if ($label && trim($label->getText()) === $locator) {
                    $wrappers[] = $wrapper;
                }
            }
        }
        if (count($wrappers) !== 1) {
            throw new ExpectationException(
                count($wrappers) . ' muform elements found for "' . $locator . '", expected exactly one',
                $this->getSession()
            );
        }
        $wrapper = reset($wrappers);

        $type = (string)$wrapper->getAttribute('data-muform-element');
        $component = (string)$wrapper->getAttribute('data-muform-component');
        $file = core_component::get_component_directory($component) . '/tests/classes/muform/element/' . $type . '.php';
        $helperclass = $component . '\\tests\\muform\\element\\' . $type;
        if (!class_exists($helperclass)) {
            if (!file_exists($file)) {
                throw new ExpectationException(
                    'muform element "' . $locator . '" of type ' . $component . '/' . $type . ' has no Behat helper ' . $file,
                    $this->getSession()
                );
            }
            require_once($file);
        }

        return new $helperclass($this, $wrapper);
    }
}
