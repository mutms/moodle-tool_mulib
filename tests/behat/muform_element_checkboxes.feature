@tool @tool_mulib @MuTMS
Feature: muform checkboxes element
  In order to pick several options in muform forms
  As a developer
  I need the checkboxes element to render, validate, hide and lock correctly

  Background:
    Given I log in as "admin"

  Scenario: New form has nothing selected unless the element has a default
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_checkboxes.php"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And the following muform fields match:
      | roles | |
      | days  | |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "roles:" in the "#submitted_roles" "css_element"
    And I should not see "manager" in the "#submitted_roles" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_checkboxes.php" with parameters "default=1"
    Then the following muform fields match:
      | roles | student |
    When I press "Save changes"
    Then I should see "roles: student" in the "#submitted_roles" "css_element"

  Scenario: Current data is shown and submitted in option order
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_checkboxes.php" with parameters "prefill=1"
    Then the following muform fields match:
      | Roles | manager, teacher |
      | days  | wed, mon         |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "roles: manager, teacher" in the "#submitted_roles" "css_element"
    And I should see "days: mon, wed" in the "#submitted_days" "css_element"

  Scenario: Options are selected by key or exact label
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_checkboxes.php"
    When I set the following muform fields:
      | roles | manager, Student, Editing teacher |
      | days  | Tuesday                           |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Manager and student cannot be combined" in the "[data-muform-name='roles'] .invalid-feedback" "css_element"
    And the following muform fields match:
      | roles | editingteacher, manager, student |
    When I set the following muform fields:
      | roles | teacher |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "roles: teacher" in the "#submitted_roles" "css_element"
    And I should see "days: tue" in the "#submitted_days" "css_element"

  Scenario: Required value is enforced by the server
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_checkboxes.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='roles'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | roles | student |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "roles: student" in the "#submitted_roles" "css_element"

  Scenario: Frozen element shows the current selection and is not posted
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_checkboxes.php" with parameters "frozen=1&prefill=1"
    Then the "Manager" "checkbox" should be disabled
    And the "Student" "checkbox" should be disabled
    And the following muform fields match:
      | roles | manager, teacher |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "roles: manager, teacher" in the "#submitted_roles" "css_element"

  Scenario: Reload keeps the selection and cancel leaves the form
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_checkboxes.php" with parameters "required=1"
    When I set the following muform fields:
      | roles | teacher, student |
    And I press "Refresh"
    Then I should see "Form reloaded" in the "#muform_state" "css_element"
    And the following muform fields match:
      | roles | student, teacher |
    When I press "Cancel"
    Then I should see "Form cancelled" in the "#muform_state" "css_element"

  @javascript
  Scenario: Hidden element still submits its value
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_checkboxes.php" with parameters "prefill=1"
    Then "[data-muform-name='roles']" "css_element" should be visible
    When I set the following muform fields:
      | hide | 1 |
    Then "[data-muform-name='roles']" "css_element" should not be visible
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "roles: manager, teacher" in the "#submitted_roles" "css_element"
    And "[data-muform-name='roles']" "css_element" should not be visible

  @javascript
  Scenario: Locked element is not submitted so the current value stays
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_checkboxes.php" with parameters "prefill=1"
    When I set the following muform fields:
      | roles | student |
      | lock  | 1       |
    Then the "Student" "checkbox" should be disabled
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "roles: manager, teacher" in the "#submitted_roles" "css_element"
    And the following muform fields match:
      | roles | manager, teacher |
    When I set the following muform fields:
      | lock | 0 |
    Then the "Student" "checkbox" should be enabled

  @javascript
  Scenario: Client side validation blocks the submit until something is selected
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_checkboxes.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='roles'] .invalid-feedback" "css_element"
    And the focused element is "Manager" "checkbox"
    When I set the following muform fields:
      | roles | teacher |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "roles: teacher" in the "#submitted_roles" "css_element"
