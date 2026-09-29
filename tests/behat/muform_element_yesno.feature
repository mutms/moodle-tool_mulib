@tool @tool_mulib @MuTMS
Feature: muform yesno element
  In order to ask yes or no questions in muform forms
  As a developer
  I need the yesno element to render, validate, hide and lock correctly

  Background:
    Given I log in as "admin"

  Scenario: New form answers No unless the element has a default
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_yesno.php"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And the following muform fields match:
      | active  | No |
      | signups | 0  |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "active: 0" in the "#submitted_active" "css_element"
    And I should see "signups: 0" in the "#submitted_signups" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_yesno.php" with parameters "default=1"
    Then the following muform fields match:
      | active | Yes |
    When I press "Save changes"
    Then I should see "active: 1" in the "#submitted_active" "css_element"

  Scenario: Current data is shown and submitted unchanged
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_yesno.php" with parameters "prefill=1"
    Then the following muform fields match:
      | Active         | yes |
      | Allow sign-ups | 1   |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "active: 1" in the "#submitted_active" "css_element"
    And I should see "signups: 1" in the "#submitted_signups" "css_element"

  Scenario: Answers are validated and submitted, an empty cell keeps the answer
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_yesno.php"
    When I set the following muform fields:
      | signups | yes |
      | active  |     |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Sign-ups need an active source" in the "[data-muform-name='signups'] .invalid-feedback" "css_element"
    And the following muform fields match:
      | active  | no  |
      | signups | Yes |
    When I set the following muform fields:
      | active | 1 |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "active: 1" in the "#submitted_active" "css_element"
    And I should see "signups: 1" in the "#submitted_signups" "css_element"

  @javascript
  Scenario: Answering No submits zero
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_yesno.php" with parameters "prefill=1"
    When I set the following muform fields:
      | signups | No |
      | active  | No |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "active: 0" in the "#submitted_active" "css_element"
    And I should see "signups: 0" in the "#submitted_signups" "css_element"

  Scenario: Frozen element shows the answer as text and is not posted
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_yesno.php" with parameters "frozen=1&prefill=1"
    Then "[data-muform-name='active'] input[type='radio']" "css_element" should not exist
    And I should see "Yes" in the "[data-muform-name='active'] .form-control-plaintext" "css_element"
    And the following muform fields match:
      | active | 1 |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "active: 1" in the "#submitted_active" "css_element"

  Scenario: Reload keeps answers and cancel leaves the form
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_yesno.php"
    When I set the following muform fields:
      | signups | Yes |
    And I press "Refresh"
    Then I should see "Form reloaded" in the "#muform_state" "css_element"
    And the following muform fields match:
      | signups | Yes |
    When I press "Cancel"
    Then I should see "Form cancelled" in the "#muform_state" "css_element"

  @javascript
  Scenario: Hidden element still submits its value
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_yesno.php" with parameters "prefill=1"
    Then "[data-muform-name='active']" "css_element" should be visible
    When I set the following muform fields:
      | hide | 1 |
    Then "[data-muform-name='active']" "css_element" should not be visible
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "active: 1" in the "#submitted_active" "css_element"

  @javascript
  Scenario: Locked element is not submitted so the current value stays
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_yesno.php" with parameters "prefill=1"
    When I set the following muform fields:
      | active | No |
      | lock   | 1  |
    Then the "No" "radio" should be disabled
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "active: 1" in the "#submitted_active" "css_element"
    And the following muform fields match:
      | active | Yes |
    When I set the following muform fields:
      | lock | 0 |
    Then the "No" "radio" should be enabled
