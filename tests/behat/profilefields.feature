@tool @tool_mulib @MuTMS @javascript
Feature: Cached profile fields management
  Background:
    Given the following "custom profile fields" exist:
      | datatype | shortname | name  |
      | text     | dept      | Dept  |
      | textarea | notes     | Notes |
    And unnecessary Admin bookmarks block gets deleted

  Scenario: Administrator may cache profile fields and stop caching them
    Given I log in as "admin"
    And I navigate to "Users > Accounts > Cached profile fields" in site administration
    Then the following should exist in the "generaltable" table:
      | Name  | Type       | Status           |
      | Dept  | Text input | Not cached       |
      | Notes | Text area  | Unsupported type |
    And I should not see "Cache field" in the "Notes" "table_row"

    When I click on "Cache field" "link" in the "Dept" "table_row"
    And I click on "Cache field" "button" in the "dialog[open]" "css_element"
    Then I should see "Being prepared" in the "Dept" "table_row"
    When I run all adhoc tasks
    And I reload the page
    Then I should see "Ready, refreshed" in the "Dept" "table_row"

    When I click on "Stop caching" "link" in the "Dept" "table_row"
    And I click on "Stop caching" "button" in the "dialog[open]" "css_element"
    Then I should see "Not cached" in the "Dept" "table_row"

  Scenario: Administrator may change profile field cache lifetime
    Given I log in as "admin"
    And I navigate to "Users > Accounts > Cached profile fields" in site administration
    When I set the field "s_tool_mulib_profilefieldlifetime[v]" to "30"
    And I set the field "s_tool_mulib_profilefieldlifetime[u]" to "minutes"
    And I press "Save changes"
    Then I should see "Some settings were not changed due to an error."
    When I set the field "s_tool_mulib_profilefieldlifetime[v]" to "2"
    And I set the field "s_tool_mulib_profilefieldlifetime[u]" to "hours"
    And I press "Save changes"
    Then I should see "Changes saved"
    And the field "s_tool_mulib_profilefieldlifetime[v]" matches value "2"
