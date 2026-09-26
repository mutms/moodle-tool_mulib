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
// phpcs:disable moodle.NamingConventions.ValidFunctionName.LowercaseMethod

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Exception\ElementNotFoundException;

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');
require_once(__DIR__ . '/../../../../../repository/upload/tests/behat/behat_repository_upload.php');

/**
 * File upload steps for muform filemanager elements.
 *
 * Reuses the core upload repository steps, only the widget lookup is muform specific.
 *
 * @package     tool_mulib
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_tool_mulib_files extends behat_repository_upload {
    /** @var NodeElement|null container limiting the element lookup */
    private ?NodeElement $container = null;
    /** @var bool true only while a muform step runs, the inherited core steps keep the core lookup */
    private bool $muform = false;

    /**
     * Upload a file into a muform filemanager element, the path is relative to the Moodle root.
     *
     * @When I upload :filepath file to :locator muform filemanager
     *
     * @param string $filepath
     * @param string $locator element name or exact label
     */
    public function i_upload_file_to_muform_filemanager(string $filepath, string $locator): void {
        $this->upload_to_muform($filepath, $locator, null);
    }

    /**
     * Upload a file into a muform filemanager element inside given container.
     *
     * @When I upload :filepath file to :locator muform filemanager in the :element :selectortype
     *
     * @param string $filepath
     * @param string $locator element name or exact label
     * @param string $element
     * @param string $selectortype
     */
    public function i_upload_file_to_muform_filemanager_in_the(
        string $filepath,
        string $locator,
        string $element,
        string $selectortype
    ): void {
        $this->upload_to_muform($filepath, $locator, $this->get_text_selector_node($selectortype, $element));
    }

    /**
     * Upload with the muform widget lookup.
     *
     * @param string $filepath
     * @param string $locator
     * @param NodeElement|null $container
     */
    private function upload_to_muform(string $filepath, string $locator, ?NodeElement $container): void {
        $this->container = $container;
        $this->muform = true;
        try {
            $this->upload_file_to_filemanager($filepath, $locator, new TableNode([]), false);
        } finally {
            $this->muform = false;
            $this->container = null;
        }
    }

    #[\Override]
    protected function get_filepicker_node($filepickerelement) {
        if (!$this->muform) {
            // Core steps inherited from behat_repository_upload.
            return parent::get_filepicker_node($filepickerelement);
        }
        $container = $this->container ?? $this->getSession()->getPage();

        try {
            $wrappers = $this->find_all(
                'css',
                '[data-muform-element="filemanager"][data-muform-name="' . $filepickerelement . '"]',
                false,
                $container
            );
        } catch (ElementNotFoundException $e) {
            $wrappers = [];
        }
        if (!$wrappers) {
            foreach ($container->findAll('css', '[data-muform-element="filemanager"]') as $wrapper) {
                $label = $wrapper->find('css', '[id$="_label"]');
                if ($label && trim($label->getText()) === $filepickerelement) {
                    $wrappers[] = $wrapper;
                }
            }
        }
        if (count($wrappers) !== 1) {
            throw new ExpectationException(
                count($wrappers) . ' muform filemanager elements found for "' . $filepickerelement . '", expected exactly one',
                $this->getSession()
            );
        }
        $node = reset($wrappers)->find('css', '.filemanager');
        if (!$node) {
            throw new ExpectationException('File manager widget not found in "' . $filepickerelement . '"', $this->getSession());
        }
        return $node;
    }
}
