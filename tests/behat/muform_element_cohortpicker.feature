@tool @tool_mulib @MuTMS
Feature: muform cohortpicker and cohortpickermany elements
  In order to pick cohorts in a panel with the tree of course categories in muform forms
  As a developer
  I need the cohort picker elements to render, validate and submit correctly

  Background:
    Given the following "categories" exist:
      | name       | category | idnumber |
      | Faculty    | 0        | FAC      |
      | Department | FAC      | DEP      |
      | Empty      | 0        | EMP      |
    And the following "cohorts" exist:
      | name            | idnumber | contextlevel | reference |
      | Preset cohort   | preset   | System       |           |
      | Site students   | site1    | System       |           |
      | Faculty staff   | fac1     | Category     | FAC       |
      | Dept students   | dep1     | Category     | DEP       |
      | Dept teachers   | dep2     | Category     | DEP       |
    And I log in as "admin"

  Scenario: Current data and defaults are shown and submitted unchanged
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_cohortpicker.php" with parameters "prefill=1"
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should not see "main: null" in the "#submitted_main" "css_element"

  @javascript
  Scenario: One cohort is picked in the tree of categories
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_cohortpicker.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Required" in the "[data-muform-name='main'] .invalid-feedback" "css_element"
    When I click on "+ Select" "button" in the "[data-muform-name='main']" "css_element"
    Then I should see "Select cohort" in the "dialog.tool_mulib-picker-panel" "css_element"
    And I should see "Found: 5" in the "dialog.tool_mulib-picker-panel" "css_element"
    And "System" "text" should appear before "Site students" "text" in the "dialog.tool_mulib-picker-panel" "css_element"
    And "Site students" "text" should appear before "Faculty" "text" in the "dialog.tool_mulib-picker-panel" "css_element"
    And "Faculty staff" "text" should appear before "Department" "text" in the "dialog.tool_mulib-picker-panel" "css_element"
    And "Department" "text" should appear before "Dept students" "text" in the "dialog.tool_mulib-picker-panel" "css_element"
    And I should not see "Empty" in the "dialog.tool_mulib-picker-panel" "css_element"
    When I type "teachers" into the "main" muform search field
    Then I should see "Found: 1" in the "dialog.tool_mulib-picker-panel" "css_element"
    And I should see "Faculty" in the "dialog.tool_mulib-picker-panel" "css_element"
    And I should see "Department" in the "dialog.tool_mulib-picker-panel" "css_element"
    And I should see "Dept teachers" in the "dialog.tool_mulib-picker-panel" "css_element"
    And I should not see "Dept students" in the "dialog.tool_mulib-picker-panel" "css_element"
    And I should not see "Faculty staff" in the "dialog.tool_mulib-picker-panel" "css_element"
    And I should not see "Site students" in the "dialog.tool_mulib-picker-panel" "css_element"
    When I click on "Dept teachers" "button" in the "dialog.tool_mulib-picker-panel" "css_element"
    Then "dialog.tool_mulib-picker-panel" "css_element" should not exist
    And the following muform fields match:
      | main | Dept teachers |
    When I set the following muform fields:
      | main | fac1 |
    Then the following muform fields match:
      | main | Faculty staff |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And the following muform fields match:
      | main | Faculty staff |

  @javascript
  Scenario: Several cohorts are ticked, added and removed
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_cohortpicker.php" with parameters "prefill=1"
    When I click on "+ Add" "button" in the "[data-muform-name='others']" "css_element"
    Then I should see "Select cohorts" in the "dialog.tool_mulib-picker-panel" "css_element"
    And I should not see "Preset cohort" in the "dialog.tool_mulib-picker-panel" "css_element"
    When I type "Dept" into the "others" muform search field
    Then I should see "Found: 2" in the "dialog.tool_mulib-picker-panel" "css_element"
    When I click on "Dept students" "text" in the "dialog.tool_mulib-picker-panel" "css_element"
    And I click on "Dept teachers" "text" in the "dialog.tool_mulib-picker-panel" "css_element"
    And I click on "Add selected (2)" "button" in the "dialog.tool_mulib-picker-panel" "css_element"
    And I wait until "dialog.tool_mulib-picker-panel" "css_element" does not exist
    Then the following muform fields match:
      | others | Preset cohort, Dept students, Dept teachers |
    When I click on "Remove: Dept students" "button" in the "[data-muform-name='others']" "css_element"
    And I set the following muform fields:
      | main | Site students |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And the following muform fields match:
      | main   | Site students                |
      | others | Preset cohort, Dept teachers |
