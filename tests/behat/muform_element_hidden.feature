@tool @tool_mulib @MuTMS
Feature: muform hidden element
  In order to carry ids and tokens through muform forms
  As a developer
  I need the hidden element to keep current data and clean typed values

  Background:
    Given I log in as "admin"

  Scenario: New form has no values unless the element has a default
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_hidden.php"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And the following muform fields match:
      | id    | |
      | token | |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "id: null" in the "#submitted_id" "css_element"
    And I should see "token:" in the "#submitted_token" "css_element"
    And I should not see "null" in the "#submitted_token" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_hidden.php" with parameters "default=1"
    Then the following muform fields match:
      | token | abc |
    When I press "Save changes"
    Then I should see "token: abc" in the "#submitted_token" "css_element"

  Scenario: Current data is carried through submit and reload
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_hidden.php" with parameters "prefill=1"
    Then the following muform fields match:
      | id    | 10  |
      | token | xyz |
    When I set the following muform fields:
      | fullname | Changed |
    And I press "Refresh"
    Then I should see "Form reloaded" in the "#muform_state" "css_element"
    And the following muform fields match:
      | id       | 10      |
      | token    | xyz     |
      | fullname | Changed |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "id: 10" in the "#submitted_id" "css_element"
    And I should see "token: xyz" in the "#submitted_token" "css_element"
    And I should see "fullname: Changed" in the "#submitted_fullname" "css_element"
    When I press "Cancel"
    Then I should see "Form cancelled" in the "#muform_state" "css_element"
