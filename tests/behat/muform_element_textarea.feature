@tool @tool_mulib @MuTMS
Feature: muform textarea element
  In order to use multi line text inputs in muform forms
  As a developer
  I need the textarea element to render, validate, hide and lock correctly

  Background:
    Given I log in as "admin"

  Scenario: New form is empty unless the element has a default
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_textarea.php"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And the following muform fields match:
      | notes | |
      | code  | |
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_textarea.php" with parameters "default=1"
    Then the following muform fields match:
      | notes | Default line one\nDefault line two |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "notes: Default line one\nDefault line two" in the "#submitted_notes" "css_element"

  Scenario: Current data is shown with normalised line ends and submitted unchanged
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_textarea.php" with parameters "prefill=1"
    Then the following muform fields match:
      | notes | Line one\nLine two |
      | Code  | {"a": "<b>"}       |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "notes: Line one\nLine two" in the "#submitted_notes" "css_element"
    And I should see "code: {\"a\": \"<b>\"}" in the "#submitted_code" "css_element"
    And the following muform fields match:
      | notes | Line one\nLine two |

  Scenario: Typed values are cleaned and line breaks are kept
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_textarea.php"
    When I set the following muform fields:
      | notes | First <b>bold</b>\nSecond line |
      | code  | <b>raw</b>\n{"x": 1}          |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "notes: First bold\nSecond line" in the "#submitted_notes" "css_element"
    And I should see "code: <b>raw</b>\n{\"x\": 1}" in the "#submitted_code" "css_element"
    And the following muform fields match:
      | notes | First bold\nSecond line |
      | code  | <b>raw</b>\n{"x": 1}    |

  Scenario: Required value is enforced by the server
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_textarea.php" with parameters "required=1"
    When I set the following muform fields:
      | notes | \n   |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='notes'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | notes | Some notes |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "notes: Some notes" in the "#submitted_notes" "css_element"

  Scenario: Invalid values are reported and kept for correction
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_textarea.php"
    When I set the following muform fields:
      | notes | These notes are much longer than the fifty characters allowed |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Error" in the "[data-muform-name='notes'] .invalid-feedback" "css_element"
    And the following muform fields match:
      | notes | These notes are much longer than the fifty characters allowed |
    When I set the following muform fields:
      | notes | invalid |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "These notes are not allowed" in the "[data-muform-name='notes'] .invalid-feedback" "css_element"

  Scenario: Frozen element shows the current value and is not posted
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_textarea.php" with parameters "frozen=1&prefill=1"
    Then I should see "Line one" in the "[data-muform-name='notes']" "css_element"
    And I should see "Line two" in the "[data-muform-name='notes']" "css_element"
    And "[data-muform-name='notes'] textarea" "css_element" should not exist
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "notes: Line one\nLine two" in the "#submitted_notes" "css_element"

  Scenario: Reload keeps typed values and cancel leaves the form
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_textarea.php" with parameters "required=1"
    When I set the following muform fields:
      | notes | Draft\nmore |
    And I press "Refresh"
    Then I should see "Form reloaded" in the "#muform_state" "css_element"
    And the following muform fields match:
      | notes | Draft\nmore |
    When I press "Cancel"
    Then I should see "Form cancelled" in the "#muform_state" "css_element"

  @javascript
  Scenario: Hidden element still submits its value
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_textarea.php" with parameters "prefill=1"
    Then "[data-muform-name='notes']" "css_element" should be visible
    When I set the following muform fields:
      | hide | 1 |
    Then "[data-muform-name='notes']" "css_element" should not be visible
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "notes: Line one\nLine two" in the "#submitted_notes" "css_element"
    And I should see "hide: 1" in the "#submitted_hide" "css_element"
    And "[data-muform-name='notes']" "css_element" should not be visible

  @javascript
  Scenario: Locked element is not submitted so the current value stays
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_textarea.php" with parameters "prefill=1"
    When I set the following muform fields:
      | notes | Changed |
      | lock  | 1       |
    Then the "Notes" "field" should be disabled
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "notes: Line one\nLine two" in the "#submitted_notes" "css_element"
    And the following muform fields match:
      | notes | Line one\nLine two |
    And the "Notes" "field" should be disabled
    When I set the following muform fields:
      | lock | 0 |
    Then the "Notes" "field" should be enabled

  @javascript
  Scenario: Client side validation blocks the submit until the value is valid
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_textarea.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='notes'] .invalid-feedback" "css_element"
    And the focused element is "Notes" "field"
    When I set the following muform fields:
      | notes | Line one\nLine two |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "notes: Line one\nLine two" in the "#submitted_notes" "css_element"
