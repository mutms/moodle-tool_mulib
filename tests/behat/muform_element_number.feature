@tool @tool_mulib @MuTMS
Feature: muform number element
  In order to use numeric inputs in muform forms
  As a developer
  I need the number element to render, validate, hide and lock correctly

  Background:
    Given I log in as "admin"

  Scenario: New form is empty unless the element has a default
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_number.php"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And the following muform fields match:
      | quantity | |
      | price    | |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "quantity: null" in the "#submitted_quantity" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_number.php" with parameters "default=1"
    Then the following muform fields match:
      | quantity | 5 |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "quantity: 5" in the "#submitted_quantity" "css_element"

  Scenario: Current data is shown and submitted unchanged
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_number.php" with parameters "prefill=1"
    Then the following muform fields match:
      | Quantity | 5   |
      | price    | 9.5 |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "quantity: 5" in the "#submitted_quantity" "css_element"
    And I should see "price: 9.5" in the "#submitted_price" "css_element"

  Scenario: Typed values are converted to integers and floats
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_number.php"
    When I set the following muform fields:
      | quantity | 7    |
      | price    | 12.5 |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "quantity: 7" in the "#submitted_quantity" "css_element"
    And I should see "price: 12.5" in the "#submitted_price" "css_element"
    And the following muform fields match:
      | quantity | 7    |
      | price    | 12.5 |

  Scenario: Required value is enforced by the server
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_number.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='quantity'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | quantity | 0 |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "quantity: 0" in the "#submitted_quantity" "css_element"

  Scenario: Invalid and out of range values are reported and kept for correction
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_number.php"
    When I set the following muform fields:
      | quantity | 2.5 |
      | price    | -1  |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Error" in the "[data-muform-name='quantity'] .invalid-feedback" "css_element"
    And I should see "Error" in the "[data-muform-name='price'] .invalid-feedback" "css_element"
    And the following muform fields match:
      | quantity | 2.5 |
      | price    | -1  |
    When I set the following muform fields:
      | quantity | 101 |
      | price    |     |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Error" in the "[data-muform-name='quantity'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | quantity | 13 |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Unlucky number" in the "[data-muform-name='quantity'] .invalid-feedback" "css_element"

  Scenario: Frozen element shows the current value and is not posted
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_number.php" with parameters "frozen=1&prefill=1"
    Then I should see "5" in the "[data-muform-name='quantity']" "css_element"
    And "[data-muform-name='quantity'] input" "css_element" should not exist
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "quantity: 5" in the "#submitted_quantity" "css_element"

  Scenario: Reload keeps typed values and cancel leaves the form
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_number.php" with parameters "required=1"
    When I set the following muform fields:
      | quantity | 42 |
    And I press "Refresh"
    Then I should see "Form reloaded" in the "#muform_state" "css_element"
    And the following muform fields match:
      | quantity | 42 |
    When I press "Cancel"
    Then I should see "Form cancelled" in the "#muform_state" "css_element"

  @javascript
  Scenario: Hidden element still submits its value
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_number.php" with parameters "prefill=1"
    Then "[data-muform-name='quantity']" "css_element" should be visible
    When I set the following muform fields:
      | hide | 1 |
    Then "[data-muform-name='quantity']" "css_element" should not be visible
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "quantity: 5" in the "#submitted_quantity" "css_element"
    And "[data-muform-name='quantity']" "css_element" should not be visible

  @javascript
  Scenario: Locked element is not submitted so the current value stays
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_number.php" with parameters "prefill=1"
    When I set the following muform fields:
      | quantity | 9 |
      | lock     | 1 |
    Then the "Quantity" "field" should be disabled
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "quantity: 5" in the "#submitted_quantity" "css_element"
    And the following muform fields match:
      | quantity | 5 |
    When I set the following muform fields:
      | lock | 0 |
    Then the "Quantity" "field" should be enabled

  @javascript
  Scenario: Client side validation blocks the submit until the value is valid
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_number.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='quantity'] .invalid-feedback" "css_element"
    And the focused element is "Quantity" "field"
    When I set the following muform fields:
      | quantity | 101 |
    And I press "Save changes"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And I should see "Error" in the "[data-muform-name='quantity'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | quantity | 100 |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "quantity: 100" in the "#submitted_quantity" "css_element"
