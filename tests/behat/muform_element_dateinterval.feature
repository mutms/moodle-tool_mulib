@tool @tool_mulib @MuTMS
Feature: muform dateinterval element
  In order to enter calendar periods in muform forms
  As a developer
  I need the dateinterval element to render, validate, hide and lock correctly

  Background:
    Given I log in as "admin"

  Scenario: New form has no interval unless the element has a default
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_dateinterval.php"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And the following muform fields match:
      | validity | |
      | delay    | |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "validity: null" in the "#submitted_validity" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_dateinterval.php" with parameters "default=1"
    Then the following muform fields match:
      | validity | P1Y6M |
    And the field with xpath "//input[@name='validity[y]']" matches value "1"
    And the field with xpath "//input[@name='validity[m]']" matches value "6"
    And the field with xpath "//input[@name='validity[d]']" matches value ""
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "validity: P1Y6M" in the "#submitted_validity" "css_element"

  Scenario: Current data is canonicalised, shown with extra units added and submitted unchanged
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_dateinterval.php" with parameters "prefill=1"
    Then the following muform fields match:
      | Validity | P2M         |
      | delay    | P0Y1W2DT36H |
    And the field with xpath "//input[@name='validity[m]']" matches value "2"
    And the field with xpath "//input[@name='validity[y]']" matches value ""
    And "//input[@name='validity[h]']" "xpath_element" should not exist
    And the field with xpath "//input[@name='delay[w]']" matches value "1"
    And the field with xpath "//input[@name='delay[d]']" matches value "2"
    And the field with xpath "//input[@name='delay[h]']" matches value "36"
    And "//input[@name='delay[i]']" "xpath_element" should not exist
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "validity: P2M" in the "#submitted_validity" "css_element"
    And I should see "delay: P1W2DT36H" in the "#submitted_delay" "css_element"

  Scenario: Any combination of units is kept as entered
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_dateinterval.php"
    When I set the field with xpath "//input[@name='validity[m]']" to "14"
    And I set the field with xpath "//input[@name='validity[d]']" to "0"
    And I set the following muform fields:
      | delay | P10W |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "validity: P14M" in the "#submitted_validity" "css_element"
    And I should see "delay: P10W" in the "#submitted_delay" "css_element"
    And the following muform fields match:
      | validity | P14M |
      | delay    | P10W |
    And the field with xpath "//input[@name='validity[m]']" matches value "14"
    And the field with xpath "//input[@name='validity[d]']" matches value ""
    And the field with xpath "//input[@name='delay[w]']" matches value "10"

  Scenario: Required value is enforced by the server
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_dateinterval.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='validity'] .invalid-feedback" "css_element"
    When I set the field with xpath "//input[@name='validity[d]']" to "0"
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='validity'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | validity | P1D |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "validity: P1D" in the "#submitted_validity" "css_element"

  Scenario: Invalid values are reported and kept for correction
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_dateinterval.php"
    When I set the field with xpath "//input[@name='validity[m]']" to "1.5"
    And I set the field with xpath "//input[@name='validity[d]']" to "-5"
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Error" in the "[data-muform-name='validity'] .invalid-feedback" "css_element"
    And the field with xpath "//input[@name='validity[m]']" matches value "1.5"
    And the field with xpath "//input[@name='validity[d]']" matches value "-5"
    When I set the following muform fields:
      | validity | P10Y |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Validity is too long" in the "[data-muform-name='validity'] .invalid-feedback" "css_element"
    And the following muform fields match:
      | validity | P10Y |

  Scenario: Frozen element shows the current value and is not posted
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_dateinterval.php" with parameters "frozen=1&prefill=1"
    Then I should see "2 months" in the "[data-muform-name='validity']" "css_element"
    And "[data-muform-name='validity'] input" "css_element" should not exist
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "validity: P2M" in the "#submitted_validity" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_dateinterval.php" with parameters "frozen=1"
    Then I should see "Not set" in the "[data-muform-name='validity']" "css_element"
    When I press "Save changes"
    Then I should see "validity: null" in the "#submitted_validity" "css_element"

  Scenario: Reload keeps typed values and cancel leaves the form
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_dateinterval.php" with parameters "required=1"
    When I set the field with xpath "//input[@name='validity[w]']" to "6"
    And I press "Refresh"
    Then I should see "Form reloaded" in the "#muform_state" "css_element"
    And the following muform fields match:
      | validity | P6W |
    And the field with xpath "//input[@name='validity[w]']" matches value "6"
    When I press "Cancel"
    Then I should see "Form cancelled" in the "#muform_state" "css_element"

  @javascript
  Scenario: Hidden element still submits its value
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_dateinterval.php" with parameters "prefill=1"
    Then "[data-muform-name='validity']" "css_element" should be visible
    When I set the following muform fields:
      | hide | 1 |
    Then "[data-muform-name='validity']" "css_element" should not be visible
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "validity: P2M" in the "#submitted_validity" "css_element"
    And I should see "hide: 1" in the "#submitted_hide" "css_element"
    And "[data-muform-name='validity']" "css_element" should not be visible

  @javascript
  Scenario: Locked element is not submitted so the current value stays
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_dateinterval.php" with parameters "prefill=1"
    When I set the following muform fields:
      | validity | P3Y |
      | lock     | 1   |
    Then the "input[name='validity[y]']" "css_element" should be disabled
    And the "input[name='validity[d]']" "css_element" should be disabled
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "validity: P2M" in the "#submitted_validity" "css_element"
    And the following muform fields match:
      | validity | P2M |
    And the "input[name='validity[y]']" "css_element" should be disabled
    When I set the following muform fields:
      | lock | 0 |
    Then the "input[name='validity[y]']" "css_element" should be enabled

  @javascript
  Scenario: Client side validation blocks the submit until the value is valid
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_dateinterval.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='validity'] .invalid-feedback" "css_element"
    And the focused element is "input[name='validity[y]']" "css_element"
    When I set the field with xpath "//input[@name='validity[m]']" to "1.5"
    And I press "Save changes"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And I should see "Error" in the "[data-muform-name='validity'] .invalid-feedback" "css_element"
    When I set the field with xpath "//input[@name='validity[m]']" to "18"
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "validity: P18M" in the "#submitted_validity" "css_element"
