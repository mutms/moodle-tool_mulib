@tool @tool_mulib @MuTMS
Feature: muform select element
  In order to pick one option from a dropdown in muform forms
  As a developer
  I need the select element to render, validate, hide and lock correctly

  Background:
    Given I log in as "admin"

  Scenario: New form has nothing selected unless the element has a default
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_select.php"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And the following muform fields match:
      | country | |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "country: null" in the "#submitted_country" "css_element"
    And I should see "color: red" in the "#submitted_color" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_select.php" with parameters "default=1"
    Then the following muform fields match:
      | country | de |
    When I press "Save changes"
    Then I should see "country: de" in the "#submitted_country" "css_element"

  Scenario: Current data is shown and submitted unchanged
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_select.php" with parameters "prefill=1"
    Then the following muform fields match:
      | Country | Germany |
      | color   | green   |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "country: de" in the "#submitted_country" "css_element"
    And I should see "color: green" in the "#submitted_color" "css_element"

  Scenario: Options are selected by key or exact label
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_select.php"
    When I set the following muform fields:
      | country | United States |
      | color   | Green         |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Not shipping to the United States" in the "[data-muform-name='country'] .invalid-feedback" "css_element"
    And the following muform fields match:
      | country | us    |
      | color   | green |
    When I set the following muform fields:
      | country | cz |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "country: cz" in the "#submitted_country" "css_element"

  Scenario: Required value is enforced by the server
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_select.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='country'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | country | de |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "country: de" in the "#submitted_country" "css_element"

  Scenario: Frozen element shows the current label and is not posted
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_select.php" with parameters "frozen=1&prefill=1"
    Then I should see "Germany" in the "[data-muform-name='country']" "css_element"
    And "[data-muform-name='country'] select" "css_element" should not exist
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "country: de" in the "#submitted_country" "css_element"

  Scenario: Reload keeps the selection and cancel leaves the form
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_select.php" with parameters "required=1"
    When I set the following muform fields:
      | country | cz |
    And I press "Refresh"
    Then I should see "Form reloaded" in the "#muform_state" "css_element"
    And the following muform fields match:
      | country | cz |
    When I press "Cancel"
    Then I should see "Form cancelled" in the "#muform_state" "css_element"

  @javascript
  Scenario: Hidden element still submits its value
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_select.php" with parameters "prefill=1"
    Then "[data-muform-name='country']" "css_element" should be visible
    When I set the following muform fields:
      | hide | 1 |
    Then "[data-muform-name='country']" "css_element" should not be visible
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "country: de" in the "#submitted_country" "css_element"
    And "[data-muform-name='country']" "css_element" should not be visible

  @javascript
  Scenario: Locked element is not submitted so the current value stays
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_select.php" with parameters "prefill=1"
    When I set the following muform fields:
      | country | cz |
      | lock    | 1  |
    Then the "Country" "select" should be disabled
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "country: de" in the "#submitted_country" "css_element"
    And the following muform fields match:
      | country | de |
    When I set the following muform fields:
      | lock | 0 |
    Then the "Country" "select" should be enabled

  @javascript
  Scenario: Client side validation blocks the submit until an option is selected
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_select.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='country'] .invalid-feedback" "css_element"
    And the focused element is "Country" "select"
    When I set the following muform fields:
      | country | de |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "country: de" in the "#submitted_country" "css_element"
