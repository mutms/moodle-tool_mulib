@tool @tool_mulib @MuTMS
Feature: muform checkbox element
  In order to use yes or no choices in muform forms
  As a developer
  I need the checkbox element to render, validate, hide and lock correctly

  Background:
    Given I log in as "admin"

  Scenario: New form is unchecked unless the element has a default
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_checkbox.php"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And the following muform fields match:
      | agree     | 0 |
      | subscribe | 0 |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "agree: 0" in the "#submitted_agree" "css_element"
    And I should see "subscribe: 0" in the "#submitted_subscribe" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_checkbox.php" with parameters "default=1"
    Then the following muform fields match:
      | agree | 1 |
    When I press "Save changes"
    Then I should see "agree: 1" in the "#submitted_agree" "css_element"

  Scenario: Current data is shown and submitted unchanged
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_checkbox.php" with parameters "prefill=1"
    Then the following muform fields match:
      | Terms     | 1 |
      | subscribe | 1 |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "agree: 1" in the "#submitted_agree" "css_element"
    And I should see "subscribe: 1" in the "#submitted_subscribe" "css_element"

  Scenario: Ticked boxes are validated and submitted
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_checkbox.php"
    When I set the following muform fields:
      | subscribe | yes |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Agree to the terms first" in the "[data-muform-name='subscribe'] .invalid-feedback" "css_element"
    And the following muform fields match:
      | agree     | 0 |
      | subscribe | 1 |
    When I set the following muform fields:
      | agree | 1 |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "agree: 1" in the "#submitted_agree" "css_element"
    And I should see "subscribe: 1" in the "#submitted_subscribe" "css_element"

  @javascript
  Scenario: Unticking a box submits zero
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_checkbox.php" with parameters "prefill=1"
    When I set the following muform fields:
      | agree     | 0 |
      | subscribe | 0 |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "agree: 0" in the "#submitted_agree" "css_element"
    And I should see "subscribe: 0" in the "#submitted_subscribe" "css_element"
    And the following muform fields match:
      | agree     | 0 |
      | subscribe | 0 |

  Scenario: Required value is enforced by the server
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_checkbox.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='agree'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | agree | 1 |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "agree: 1" in the "#submitted_agree" "css_element"

  Scenario: Frozen element shows the current value and is not posted
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_checkbox.php" with parameters "frozen=1&prefill=1"
    Then the "I agree" "checkbox" should be disabled
    And the following muform fields match:
      | agree | 1 |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "agree: 1" in the "#submitted_agree" "css_element"

  Scenario: Reload keeps ticked values and cancel leaves the form
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_checkbox.php" with parameters "required=1"
    When I set the following muform fields:
      | subscribe | 1 |
    And I press "Refresh"
    Then I should see "Form reloaded" in the "#muform_state" "css_element"
    And the following muform fields match:
      | subscribe | 1 |
    When I press "Cancel"
    Then I should see "Form cancelled" in the "#muform_state" "css_element"

  @javascript
  Scenario: Hidden element still submits its value
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_checkbox.php" with parameters "prefill=1"
    Then "[data-muform-name='agree']" "css_element" should be visible
    When I set the following muform fields:
      | hide | 1 |
    Then "[data-muform-name='agree']" "css_element" should not be visible
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "agree: 1" in the "#submitted_agree" "css_element"
    And "[data-muform-name='agree']" "css_element" should not be visible

  @javascript
  Scenario: Locked element is not submitted so the current value stays
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_checkbox.php" with parameters "prefill=1"
    When I set the following muform fields:
      | agree | 0 |
      | lock  | 1 |
    Then the "I agree" "checkbox" should be disabled
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "agree: 1" in the "#submitted_agree" "css_element"
    And the following muform fields match:
      | agree | 1 |
    When I set the following muform fields:
      | lock | 0 |
    Then the "I agree" "checkbox" should be enabled

  @javascript
  Scenario: Client side validation blocks the submit until the box is ticked
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_checkbox.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='agree'] .invalid-feedback" "css_element"
    And the focused element is "I agree" "checkbox"
    When I set the following muform fields:
      | agree | 1 |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "agree: 1" in the "#submitted_agree" "css_element"
