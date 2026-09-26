@tool @tool_mulib @MuTMS
Feature: muform duration element
  In order to enter time spans in muform forms
  As a developer
  I need the duration element to render, validate, hide and lock correctly

  Background:
    Given I log in as "admin"

  Scenario: New form has zero duration unless the element has a default
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_duration.php"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And the following muform fields match:
      | timelimit | |
      | delay     | 0 |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "timelimit: 0" in the "#submitted_timelimit" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_duration.php" with parameters "default=1"
    Then the following muform fields match:
      | timelimit | 5400 |
    And the field with xpath "//input[@name='timelimit[h]']" matches value "1"
    And the field with xpath "//input[@name='timelimit[i]']" matches value "30"
    And the field with xpath "//input[@name='timelimit[d]']" matches value ""
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "timelimit: 5400" in the "#submitted_timelimit" "css_element"

  Scenario: Current data is shown normalised with extra units added and submitted unchanged
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_duration.php" with parameters "prefill=1"
    Then the following muform fields match:
      | Time limit | 90000 |
      | delay      | 90    |
    And the field with xpath "//input[@name='timelimit[d]']" matches value "1"
    And the field with xpath "//input[@name='timelimit[h]']" matches value "1"
    And "//input[@name='timelimit[s]']" "xpath_element" should not exist
    And the field with xpath "//input[@name='delay[i]']" matches value "1"
    And the field with xpath "//input[@name='delay[s]']" matches value "30"
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "timelimit: 90000" in the "#submitted_timelimit" "css_element"
    And I should see "delay: 90" in the "#submitted_delay" "css_element"
    And the field with xpath "//input[@name='delay[s]']" matches value "30"

  Scenario: Any combination of units is added up and normalised
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_duration.php"
    When I set the field with xpath "//input[@name='timelimit[h]']" to "1"
    And I set the field with xpath "//input[@name='timelimit[i]']" to "90"
    And I set the following muform fields:
      | delay | 694920 |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "timelimit: 9000" in the "#submitted_timelimit" "css_element"
    And I should see "delay: 694920" in the "#submitted_delay" "css_element"
    And the following muform fields match:
      | timelimit | 9000   |
      | delay     | 694920 |
    And the field with xpath "//input[@name='timelimit[h]']" matches value "2"
    And the field with xpath "//input[@name='timelimit[i]']" matches value "30"
    And "//input[@name='delay[w]']" "xpath_element" should not exist
    And the field with xpath "//input[@name='delay[d]']" matches value "8"
    And the field with xpath "//input[@name='delay[h]']" matches value "1"
    And the field with xpath "//input[@name='delay[i]']" matches value "2"

  Scenario: Required value is enforced by the server
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_duration.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='timelimit'] .invalid-feedback" "css_element"
    When I set the field with xpath "//input[@name='timelimit[i]']" to "0"
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='timelimit'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | timelimit | 60 |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "timelimit: 60" in the "#submitted_timelimit" "css_element"

  Scenario: Invalid values are reported and kept for correction
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_duration.php"
    When I set the field with xpath "//input[@name='timelimit[h]']" to "1.5"
    And I set the field with xpath "//input[@name='timelimit[i]']" to "-5"
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Error" in the "[data-muform-name='timelimit'] .invalid-feedback" "css_element"
    And the field with xpath "//input[@name='timelimit[h]']" matches value "1.5"
    And the field with xpath "//input[@name='timelimit[i]']" matches value "-5"
    When I set the following muform fields:
      | timelimit | 691200 |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Time limit is too long" in the "[data-muform-name='timelimit'] .invalid-feedback" "css_element"
    And the following muform fields match:
      | timelimit | 691200 |

  Scenario: Frozen element shows the current value and is not posted
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_duration.php" with parameters "frozen=1&prefill=1"
    Then I should see "1 day 1 hour" in the "[data-muform-name='timelimit']" "css_element"
    And "[data-muform-name='timelimit'] input" "css_element" should not exist
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "timelimit: 90000" in the "#submitted_timelimit" "css_element"

  Scenario: Reload keeps typed values and cancel leaves the form
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_duration.php" with parameters "required=1"
    When I set the field with xpath "//input[@name='timelimit[i]']" to "75"
    And I press "Refresh"
    Then I should see "Form reloaded" in the "#muform_state" "css_element"
    And the following muform fields match:
      | timelimit | 4500 |
    And the field with xpath "//input[@name='timelimit[h]']" matches value "1"
    And the field with xpath "//input[@name='timelimit[i]']" matches value "15"
    When I press "Cancel"
    Then I should see "Form cancelled" in the "#muform_state" "css_element"

  @javascript
  Scenario: Hidden element still submits its value
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_duration.php" with parameters "prefill=1"
    Then "[data-muform-name='timelimit']" "css_element" should be visible
    When I set the following muform fields:
      | hide | 1 |
    Then "[data-muform-name='timelimit']" "css_element" should not be visible
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "timelimit: 90000" in the "#submitted_timelimit" "css_element"
    And I should see "hide: 1" in the "#submitted_hide" "css_element"
    And "[data-muform-name='timelimit']" "css_element" should not be visible

  @javascript
  Scenario: Locked element is not submitted so the current value stays
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_duration.php" with parameters "prefill=1"
    When I set the following muform fields:
      | timelimit | 3600 |
      | lock      | 1    |
    Then the "input[name='timelimit[h]']" "css_element" should be disabled
    And the "input[name='timelimit[i]']" "css_element" should be disabled
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "timelimit: 90000" in the "#submitted_timelimit" "css_element"
    And the following muform fields match:
      | timelimit | 90000 |
    And the "input[name='timelimit[h]']" "css_element" should be disabled
    When I set the following muform fields:
      | lock | 0 |
    Then the "input[name='timelimit[h]']" "css_element" should be enabled

  @javascript
  Scenario: Client side validation blocks the submit until the value is valid
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_duration.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='timelimit'] .invalid-feedback" "css_element"
    And the focused element is "input[name='timelimit[d]']" "css_element"
    When I set the field with xpath "//input[@name='timelimit[h]']" to "1.5"
    And I press "Save changes"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And I should see "Error" in the "[data-muform-name='timelimit'] .invalid-feedback" "css_element"
    When I set the field with xpath "//input[@name='timelimit[h]']" to "2"
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "timelimit: 7200" in the "#submitted_timelimit" "css_element"
