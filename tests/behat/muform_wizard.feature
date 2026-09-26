@tool @tool_mulib @MuTMS
Feature: muform wizard helper
  In order to build multi-page forms
  As a developer
  I need the wizard helper to keep the state, resolve the stage from data and allow going back

  Background:
    Given I log in as "admin"

  Scenario: Stages are resolved from the stored data and the wizard finishes
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_wizard.php"
    Then I should see "Stage details" in the "#muform_state" "css_element"
    And I should see "{}" in the "#wizard_data" "css_element"
    And I should see "Details" in the ".muform-wizard-stages [aria-current='step']" "css_element"
    And "//nav[contains(@class, 'muform-wizard-stages')]//a[normalize-space()='Choice']" "xpath_element" should not exist
    When I press "Continue"
    Then I should see "Stage details is invalid" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='fullname'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | fullname | Jane Doe |
    And I press "Continue"
    Then I should see "Stage choice" in the "#muform_state" "css_element"
    And I should see "\"fullname\":\"Jane Doe\"" in the "#wizard_data" "css_element"
    And I should see "Choice" in the ".muform-wizard-stages [aria-current='step']" "css_element"
    And "//nav[contains(@class, 'muform-wizard-stages')]//a[normalize-space()='Details']" "xpath_element" should exist
    When I set the following muform fields:
      | colour | Green |
    And I press "Continue"
    Then I should see "Stage summary" in the "#muform_state" "css_element"
    And I should see "Jane Doe likes green, files: none" in the "[data-muform-name='summary']" "css_element"
    And I should see "Summary" in the ".muform-wizard-stages [aria-current='step']" "css_element"
    When I press "Finish"
    Then I should see "Wizard finished for Jane Doe" in the "#muform_state" "css_element"
    When I click on "Start again" "link"
    Then I should see "Stage details" in the "#muform_state" "css_element"
    And I should see "{}" in the "#wizard_data" "css_element"

  Scenario: Back and the stage parameter move to valid stages only
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_wizard.php"
    When I set the following muform fields:
      | fullname | Jane Doe |
    And I press "Continue"
    And I set the following muform fields:
      | colour | red |
    And I press "Continue"
    Then I should see "Stage summary" in the "#muform_state" "css_element"
    When I press "Back"
    Then I should see "Stage choice" in the "#muform_state" "css_element"
    And the following muform fields match:
      | colour | red |
    When I press "Back"
    Then I should see "Stage details" in the "#muform_state" "css_element"
    And the following muform fields match:
      | fullname | Jane Doe |
    And "//nav[contains(@class, 'muform-wizard-stages')]//a[normalize-space()='Choice']" "xpath_element" should exist
    When I click on "Choice" "link" in the ".muform-wizard-stages" "css_element"
    Then I should see "Stage choice" in the "#muform_state" "css_element"
    When I click on "Summary" "link" in the ".muform-wizard-stages" "css_element"
    Then I should see "Stage summary" in the "#muform_state" "css_element"
    When I click on "Details" "link" in the ".muform-wizard-stages" "css_element"
    And I set the following muform fields:
      | fullname | Renamed |
    And I press "Continue"
    Then I should see "Stage summary" in the "#muform_state" "css_element"
    And I should see "Renamed likes red" in the "[data-muform-name='summary']" "css_element"

  Scenario: A requested stage never moves forward past the first invalid stage
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_wizard.php"
    When I set the following muform fields:
      | fullname | Jane Doe |
    And I press "Continue"
    Then I should see "Stage choice" in the "#muform_state" "css_element"
    When I click on "Details" "link" in the ".muform-wizard-stages" "css_element"
    Then I should see "Stage details" in the "#muform_state" "css_element"
    When I press "Continue"
    Then I should see "Stage choice" in the "#muform_state" "css_element"
    And "//nav[contains(@class, 'muform-wizard-stages')]//a[normalize-space()='Summary']" "xpath_element" should not exist

  Scenario: Cancel deletes the state and a new wizard starts
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_wizard.php"
    When I set the following muform fields:
      | fullname | Jane Doe |
    And I press "Continue"
    And I press "Cancel"
    Then I should see "Wizard cancelled" in the "#muform_state" "css_element"
    When I click on "Start again" "link"
    Then I should see "Stage details" in the "#muform_state" "css_element"
    And I should see "{}" in the "#wizard_data" "css_element"

  @javascript @_file_upload
  Scenario: Uploaded files survive going back and forward
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_wizard.php"
    When I set the following muform fields:
      | fullname | Jane Doe |
    And I upload "lib/tests/fixtures/empty.txt" file to "attachments" muform filemanager
    And I press "Continue"
    Then I should see "Stage choice" in the "#muform_state" "css_element"
    When I press "Back"
    Then the following muform fields match:
      | fullname    | Jane Doe  |
      | attachments | empty.txt |
    When I press "Continue"
    And I set the following muform fields:
      | colour | Green |
    And I press "Continue"
    Then I should see "Jane Doe likes green, files: empty.txt" in the "[data-muform-name='summary']" "css_element"
    When I press "Finish"
    Then I should see "Wizard finished for Jane Doe" in the "#muform_state" "css_element"
