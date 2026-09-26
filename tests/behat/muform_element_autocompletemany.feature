@tool @tool_mulib @MuTMS
Feature: muform autocompletemany element
  In order to pick several records through a search in muform forms
  As a developer
  I need the autocompletemany element to render, validate, hide and lock correctly

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             | suspended |
      | user1    | User      | One      | user1@example.com | 0         |
      | user2    | User      | Two      | user2@example.com | 1         |
    And I log in as "admin"

  Scenario: New form is empty unless the element has a default
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_autocompletemany.php"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And the following muform fields match:
      | members   | |
      | reviewers | |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "members:" in the "#submitted_members" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_autocompletemany.php" with parameters "default=1"
    Then the following muform fields match:
      | members | 2 |
    And I should see "Admin User" in the "[data-muform-name='members']" "css_element"
    When I press "Save changes"
    Then I should see "members: 2" in the "#submitted_members" "css_element"

  Scenario: Current data is shown and submitted unchanged
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_autocompletemany.php" with parameters "prefill=1"
    Then the following muform fields match:
      | Members   | 2 |
      | reviewers | 2 |
    And I should see "Admin User" in the "[data-muform-name='members']" "css_element"
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "members: 2" in the "#submitted_members" "css_element"
    And I should see "reviewers: 2" in the "#submitted_reviewers" "css_element"

  Scenario: Values are validated by the source and kept in order
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_autocompletemany.php"
    When I set the following muform fields:
      | members   | 2, 999 |
      | reviewers | 1      |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Error" in the "[data-muform-name='members'] .invalid-feedback" "css_element"
    And I should see "Error" in the "[data-muform-name='reviewers'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | members   | 2, 2 |
      | reviewers |      |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "members: 2" in the "#submitted_members" "css_element"
    And I should see "reviewers:" in the "#submitted_reviewers" "css_element"

  Scenario: Required value is enforced by the server
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_autocompletemany.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='members'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | members | 2 |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "members: 2" in the "#submitted_members" "css_element"

  Scenario: Frozen element shows the current labels and is not posted
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_autocompletemany.php" with parameters "frozen=1&prefill=1"
    Then I should see "Admin User" in the "[data-muform-name='members']" "css_element"
    And "[data-muform-name='members'] input" "css_element" should not exist
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "members: 2" in the "#submitted_members" "css_element"

  Scenario: Reload keeps the selection and cancel leaves the form
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_autocompletemany.php" with parameters "required=1"
    When I set the following muform fields:
      | members | 2 |
    And I press "Refresh"
    Then I should see "Form reloaded" in the "#muform_state" "css_element"
    And the following muform fields match:
      | members | 2 |
    When I press "Cancel"
    Then I should see "Form cancelled" in the "#muform_state" "css_element"

  @javascript
  Scenario: Users are searched, picked and removed in the browser
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_autocompletemany.php" with parameters "prefill=1"
    Then I should see "Admin User" in the "[data-muform-name='members']" "css_element"
    When I set the following muform fields:
      | members | User One, User Two |
    Then the following muform fields match:
      | members | User Two, User One |
    When I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Too many members" in the "[data-muform-name='members'] .invalid-feedback" "css_element"
    And I should see "Suspended user" in the "[data-muform-name='members'] .invalid-feedback" "css_element"
    When I click on "Remove User Two" "button" in the "[data-muform-name='members']" "css_element"
    Then the following muform fields match:
      | members | User One |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should not see "null" in the "#submitted_members" "css_element"
    And the following muform fields match:
      | members | User One |

  @javascript
  Scenario: Hidden element still submits its value
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_autocompletemany.php" with parameters "prefill=1"
    Then "[data-muform-name='members']" "css_element" should be visible
    When I set the following muform fields:
      | hide | 1 |
    Then "[data-muform-name='members']" "css_element" should not be visible
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "members: 2" in the "#submitted_members" "css_element"
    And "[data-muform-name='members']" "css_element" should not be visible

  @javascript
  Scenario: Locked element is not submitted so the current value stays
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_autocompletemany.php" with parameters "prefill=1"
    When I set the following muform fields:
      | lock | 1 |
    Then the "[data-muform-name='members'] input[role='combobox']" "css_element" should be disabled
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "members: 2" in the "#submitted_members" "css_element"
    When I set the following muform fields:
      | lock | 0 |
    Then the "[data-muform-name='members'] input[role='combobox']" "css_element" should be enabled

  @javascript
  Scenario: Client side validation blocks the submit until a user is picked
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_autocompletemany.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='members'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | members | User One |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And the following muform fields match:
      | members | User One |
