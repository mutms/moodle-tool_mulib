@tool @tool_mulib @MuTMS
Feature: muform radios element
  In order to pick one option in muform forms
  As a developer
  I need the radios element to render, validate, hide and lock correctly

  Background:
    Given I log in as "admin"

  Scenario: New form has nothing selected unless the element has a default
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_radios.php"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And the following muform fields match:
      | frequency | |
      | size      | |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "frequency: null" in the "#submitted_frequency" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_radios.php" with parameters "default=1"
    Then the following muform fields match:
      | frequency | weekly |
    When I press "Save changes"
    Then I should see "frequency: weekly" in the "#submitted_frequency" "css_element"

  Scenario: Current data is shown and submitted unchanged
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_radios.php" with parameters "prefill=1"
    Then the following muform fields match:
      | Frequency | weekly |
      | size      | Medium |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "frequency: weekly" in the "#submitted_frequency" "css_element"
    And I should see "size: m" in the "#submitted_size" "css_element"

  Scenario: Options are selected by key or exact label
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_radios.php"
    When I set the following muform fields:
      | frequency | Monthly |
      | size      | s       |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Monthly is not available" in the "[data-muform-name='frequency'] .invalid-feedback" "css_element"
    And the following muform fields match:
      | frequency | monthly |
      | size      | s       |
    When I set the following muform fields:
      | frequency | daily |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "frequency: daily" in the "#submitted_frequency" "css_element"
    And I should see "size: s" in the "#submitted_size" "css_element"

  Scenario: Required value is enforced by the server
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_radios.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='frequency'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | frequency | daily |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "frequency: daily" in the "#submitted_frequency" "css_element"

  Scenario: Frozen element shows the current selection and is not posted
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_radios.php" with parameters "frozen=1&prefill=1"
    Then the "Weekly" "radio" should be disabled
    And the "Daily" "radio" should be disabled
    And the following muform fields match:
      | frequency | weekly |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "frequency: weekly" in the "#submitted_frequency" "css_element"

  Scenario: Reload keeps the selection and cancel leaves the form
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_radios.php" with parameters "required=1"
    When I set the following muform fields:
      | frequency | daily |
    And I press "Refresh"
    Then I should see "Form reloaded" in the "#muform_state" "css_element"
    And the following muform fields match:
      | frequency | daily |
    When I press "Cancel"
    Then I should see "Form cancelled" in the "#muform_state" "css_element"

  @javascript
  Scenario: Hidden element still submits its value
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_radios.php" with parameters "prefill=1"
    Then "[data-muform-name='frequency']" "css_element" should be visible
    When I set the following muform fields:
      | hide | 1 |
    Then "[data-muform-name='frequency']" "css_element" should not be visible
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "frequency: weekly" in the "#submitted_frequency" "css_element"
    And "[data-muform-name='frequency']" "css_element" should not be visible

  @javascript
  Scenario: Locked element is not submitted so the current value stays
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_radios.php" with parameters "prefill=1"
    When I set the following muform fields:
      | frequency | daily |
      | lock      | 1     |
    Then the "Daily" "radio" should be disabled
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "frequency: weekly" in the "#submitted_frequency" "css_element"
    And the following muform fields match:
      | frequency | weekly |
    When I set the following muform fields:
      | lock | 0 |
    Then the "Daily" "radio" should be enabled

  @javascript
  Scenario: Client side validation blocks the submit until an option is selected
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_radios.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='frequency'] .invalid-feedback" "css_element"
    And the focused element is "Daily" "radio"
    When I set the following muform fields:
      | frequency | weekly |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "frequency: weekly" in the "#submitted_frequency" "css_element"
