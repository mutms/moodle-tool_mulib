@tool @tool_mulib @MuTMS @javascript
Feature: muform collapsible sections
  In order to work with long forms
  As a user
  I need to collapse sections without losing or hiding their data and errors

  Background:
    Given I log in as "admin"

  Scenario: Collapsible sections are expanded by default and collapsed content is submitted
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_collapsible.php"
    Then I should see "Summary of first" in the "[data-muform-name='first'] summary" "css_element"
    And "Full name" "field" should be visible
    And "Nickname" "field" should be visible
    When I set the following muform fields:
      | fullname | Jane |
      | nickname | JD   |
    And I click on "First part" "text" in the "[data-muform-name='first'] summary" "css_element"
    And I click on "Second part" "text" in the "[data-muform-name='second'] summary" "css_element"
    Then "Full name" "field" should not be visible
    And "Nickname" "field" should not be visible
    And I press "Save changes"
    And I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "fullname: Jane" in the "#submitted_fullname" "css_element"
    And I should see "nickname: JD" in the "#submitted_nickname" "css_element"

  Scenario: Collapsed state is remembered for the same key only
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_collapsible.php"
    When I click on "First part" "text" in the "[data-muform-name='first'] summary" "css_element"
    And I reload the page
    Then "Full name" "field" should not be visible
    And "Nickname" "field" should be visible
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_collapsible.php" with parameters "key=two"
    Then "Full name" "field" should be visible
    When I press "Collapse all"
    Then "Full name" "field" should not be visible
    And "Nickname" "field" should not be visible
    And I press "Expand all"
    And "Full name" "field" should be visible
    And "Nickname" "field" should be visible

  Scenario: Sections with errors are expanded
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_collapsible.php" with parameters "required=1"
    When I click on "Second part" "text" in the "[data-muform-name='second'] summary" "css_element"
    And I press "Save changes"
    Then "Nickname" "field" should be visible
    And "[data-muform-name='second'] summary .muform-collapsible-errors" "css_element" should be visible
    And "[data-muform-name='first'] summary .muform-collapsible-errors" "css_element" should not be visible
    When I set the following muform fields:
      | nickname | bad |
    And I click on "Second part" "text" in the "[data-muform-name='second'] summary" "css_element"
    And I press "Save changes"
    Then I should see "Nickname is bad" in the "[data-muform-name='nickname']" "css_element"
    And "Nickname" "field" should be visible
    And "[data-muform-name='second'] summary .muform-collapsible-errors" "css_element" should be visible

  Scenario: Buttons may ask for confirmation
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_collapsible.php"
    When I press "Remove second"
    Then I should see "Really remove the second part?" in the ".modal-dialog" "css_element"
    And I click on "Cancel" "button" in the ".modal-dialog" "css_element"
    And I should see "Form is new" in the "#muform_state" "css_element"
    When I press "Remove second"
    And I click on "Yes" "button" in the ".modal-dialog" "css_element"
    Then I should see "Form reloaded" in the "#muform_state" "css_element"
