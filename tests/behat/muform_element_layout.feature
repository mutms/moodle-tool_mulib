@tool @tool_mulib @MuTMS
Feature: muform layout elements
  In order to structure muform forms
  As a developer
  I need sections, info and raw html elements to render and follow display rules

  Background:
    Given I log in as "admin"

  Scenario: Sections group elements and info shows defaults or current data
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_layout.php"
    Then I should see "Basic details" in the "[data-muform-name='basics'] legend" "css_element"
    And I should see "Extra details" in the "[data-muform-name='extras'] legend" "css_element"
    And "[data-muform-name='basics'] [data-muform-name='fullname']" "css_element" should exist
    And "[data-muform-name='extras'] [data-muform-name='nickname']" "css_element" should exist
    And the following muform fields match:
      | created | Not created yet |
      | notice  | Raw html notice |
    And "[data-muform-name='notice'] em" "css_element" should exist
    And I should see "Full width banner" in the "[data-muform-name='banner'] .alert" "css_element"
    When I set the following muform fields:
      | fullname | Jane |
      | nickname | JD   |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "fullname: Jane" in the "#submitted_fullname" "css_element"
    And I should see "nickname: JD" in the "#submitted_nickname" "css_element"
    And "#submitted_created" "css_element" should not exist
    And "#submitted_notice" "css_element" should not exist
    And "#submitted_basics" "css_element" should not exist
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_layout.php" with parameters "prefill=1"
    Then the following muform fields match:
      | created  | Created yesterday |
      | fullname | Jane Doe          |
    And "[data-muform-name='created'] strong" "css_element" should exist

  Scenario: Required elements inside sections are validated
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_layout.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='basics'] [data-muform-name='fullname'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | fullname | Jane |
    And I press "Refresh"
    Then I should see "Form reloaded" in the "#muform_state" "css_element"
    And the following muform fields match:
      | fullname | Jane |
    When I press "Cancel"
    Then I should see "Form cancelled" in the "#muform_state" "css_element"

  @javascript
  Scenario: Display rules hide and lock whole sections
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_layout.php" with parameters "prefill=1"
    Then "[data-muform-name='extras']" "css_element" should be visible
    When I set the following muform fields:
      | nickname | JD |
      | hide     | 1  |
    Then "[data-muform-name='extras']" "css_element" should not be visible
    When I set the following muform fields:
      | hide | 0 |
      | lock | 1 |
    Then "[data-muform-name='extras']" "css_element" should be visible
    And the "Nickname" "field" should be disabled
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "nickname:" in the "#submitted_nickname" "css_element"
    And I should not see "JD" in the "#submitted_nickname" "css_element"
    And I should see "fullname: Jane Doe" in the "#submitted_fullname" "css_element"
    When I set the following muform fields:
      | lock | 0 |
    Then the "Nickname" "field" should be enabled
