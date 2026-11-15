@tool @tool_mulib @MuTMS
Feature: muform userpicker and userpickermany elements
  In order to pick users in a panel with identity columns and paging in muform forms
  As a developer
  I need the user picker elements to render, validate and submit correctly

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             | suspended |
      | user1    | User      | One      | user1@example.com | 0         |
      | user2    | User      | Two      | user2@example.com | 0         |
      | user3    | Other     | Three    | other@example.com | 1         |
    And I log in as "admin"

  Scenario: Current data and defaults are shown and submitted unchanged
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_userpicker.php"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "owner: null" in the "#submitted_owner" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_userpicker.php" with parameters "prefill=1"
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "owner: 2" in the "#submitted_owner" "css_element"
    And I should see "members: 2" in the "#submitted_members" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_userpicker.php" with parameters "default=1"
    And I press "Save changes"
    Then I should see "owner: 2" in the "#submitted_owner" "css_element"

  Scenario: Frozen element shows the current label and is not posted
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_userpicker.php" with parameters "frozen=1&prefill=1"
    Then I should see "Admin User" in the "[data-muform-name='owner']" "css_element"
    And "[data-muform-name='owner'] input" "css_element" should not exist
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "owner: 2" in the "#submitted_owner" "css_element"

  @javascript
  Scenario: One user is picked in the panel
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_userpicker.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='owner'] .invalid-feedback" "css_element"
    When I click on "+ Select" "button" in the "[data-muform-name='owner']" "css_element"
    Then I should see "Select user" in the "dialog.tool_mulib-picker-panel" "css_element"
    And I should see "user1@example.com" in the "dialog.tool_mulib-picker-panel" "css_element"
    When I click on "User Two" "button" in the "dialog.tool_mulib-picker-panel" "css_element"
    Then "dialog.tool_mulib-picker-panel" "css_element" should not exist
    And the following muform fields match:
      | owner | User Two |
    When I set the following muform fields:
      | owner | user1@example.com |
    Then the following muform fields match:
      | owner | User One |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "owner: " in the "#submitted_owner" "css_element"
    And the following muform fields match:
      | owner | User One |

  @javascript
  Scenario: Several users are ticked, added and removed
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_userpicker.php" with parameters "prefill=1"
    When I click on "+ Add" "button" in the "[data-muform-name='members']" "css_element"
    Then I should see "Select users" in the "dialog.tool_mulib-picker-panel" "css_element"
    And I should not see "Admin User" in the "dialog.tool_mulib-picker-panel tbody" "css_element"
    When I type "User" into the "members" muform search field
    Then I should see "Found: 2" in the "dialog.tool_mulib-picker-panel" "css_element"
    When I click on "dialog.tool_mulib-picker-panel thead input[type='checkbox']" "css_element"
    And I click on "Add selected (2)" "button" in the "dialog.tool_mulib-picker-panel" "css_element"
    Then "dialog.tool_mulib-picker-panel" "css_element" should not exist
    And the following muform fields match:
      | members | Admin User, User One, User Two |
    When I click on "Remove: User One user1@example.com" "button" in the "[data-muform-name='members']" "css_element"
    And I set the following muform fields:
      | owner | Other Three |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    # Suspended users are allowed by site_users, the same as in autocomplete elements.
    And the following muform fields match:
      | owner   | Other Three          |
      | members | Admin User, User Two |

  @javascript
  Scenario: Hidden element still submits its value
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_userpicker.php" with parameters "prefill=1"
    When I set the following muform fields:
      | hide | 1 |
    Then "[data-muform-name='members']" "css_element" should not be visible
    When I press "Save changes"
    Then I should see "members: 2" in the "#submitted_members" "css_element"
