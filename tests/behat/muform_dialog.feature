@tool @tool_mulib @MuTMS
Feature: muform works in router controllers, as a full page and in native dialogs
  In order to edit records without leaving the page
  As an admin
  I need muform handlers to serve both full pages and dialogs

  Background:
    Given I log in as "admin"

  Scenario: Full page form served by a router controller
    Given I visit "/r.php/tool_mulib/muform/fixture"
    And I should see "Nothing yet" in the "#fixture_state" "css_element"
    When I click on "Edit full page" "link"
    Then I should see "Edit fixture"
    And the following muform fields match:
      | fullname | Jane Doe |
      | id       | 42       |
    When I set the following muform fields:
      | fullname | Router Jane        |
      | agree    | 1                  |
      | country  | cz                 |
      | starts   | ##tomorrow 10:00## |
    And I press "Save changes"
    Then I should see "Saved Router Jane" in the "#fixture_state" "css_element"
    When I click on "Edit full page" "link"
    And I click on "[data-muform-name='cancel']" "css_element"
    Then I should see "Cancelled" in the "#fixture_state" "css_element"

  @javascript
  Scenario: Form in a native dialog with reload after submission
    Given I visit "/r.php/tool_mulib/muform/fixture"
    When I click on "Edit in dialog" "button"
    Then I should see "Edit fixture" in the "dialog[open]" "css_element"
    And the following muform fields in the "dialog[open]" "css_element" match:
      | fullname | Jane Doe |
    When I set the following muform fields in the "dialog[open]" "css_element":
      | fullname | invalid |
      | agree    | 1       |
      | country  | cz      |
      | starts   | ##tomorrow 10:00## |
    And I click on "Save changes" "button" in the "dialog[open]" "css_element"
    Then I should see "This name is not allowed" in the "dialog[open]" "css_element"
    When I set the following muform fields in the "dialog[open]" "css_element":
      | fullname | Dialog Jane |
    And I click on "Save changes" "button" in the "dialog[open]" "css_element"
    Then "dialog[open]" "css_element" should not exist
    And I should see "Saved Dialog Jane"

  @javascript
  Scenario: Dialog that stays on the page and dialog cancel
    Given I visit "/r.php/tool_mulib/muform/fixture"
    When I click on "Edit and stay" "button"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | fullname | Stay Jane |
      | agree    | 1         |
      | country  | de        |
      | starts   | ##tomorrow 10:00## |
    And I click on "Save changes" "button" in the "dialog[open]" "css_element"
    Then "dialog[open]" "css_element" should not exist
    And I should see "Dialog saved Stay Jane" in the "#fixture_state" "css_element"
    When I click on "Edit as link" "link"
    And I click on "Cancel" "button" in the "dialog[open]" "css_element"
    Then "dialog[open]" "css_element" should not exist
    And I should see "Dialog saved Stay Jane" in the "#fixture_state" "css_element"

  @javascript
  Scenario: Long autocomplete lists in a dialog are not clipped and both ends can be picked
    Given the following "users" exist:
      | username   | firstname | lastname | email                  |
      | longlist01 | Longlist  | User 01 | longlist01@example.com |
      | longlist02 | Longlist  | User 02 | longlist02@example.com |
      | longlist03 | Longlist  | User 03 | longlist03@example.com |
      | longlist04 | Longlist  | User 04 | longlist04@example.com |
      | longlist05 | Longlist  | User 05 | longlist05@example.com |
      | longlist06 | Longlist  | User 06 | longlist06@example.com |
      | longlist07 | Longlist  | User 07 | longlist07@example.com |
      | longlist08 | Longlist  | User 08 | longlist08@example.com |
      | longlist09 | Longlist  | User 09 | longlist09@example.com |
      | longlist10 | Longlist  | User 10 | longlist10@example.com |
      | longlist11 | Longlist  | User 11 | longlist11@example.com |
      | longlist12 | Longlist  | User 12 | longlist12@example.com |
      | longlist13 | Longlist  | User 13 | longlist13@example.com |
      | longlist14 | Longlist  | User 14 | longlist14@example.com |
      | longlist15 | Longlist  | User 15 | longlist15@example.com |
      | longlist16 | Longlist  | User 16 | longlist16@example.com |
      | longlist17 | Longlist  | User 17 | longlist17@example.com |
      | longlist18 | Longlist  | User 18 | longlist18@example.com |
      | longlist19 | Longlist  | User 19 | longlist19@example.com |
      | longlist20 | Longlist  | User 20 | longlist20@example.com |
      | longlist21 | Longlist  | User 21 | longlist21@example.com |
      | longlist22 | Longlist  | User 22 | longlist22@example.com |
      | longlist23 | Longlist  | User 23 | longlist23@example.com |
      | longlist24 | Longlist  | User 24 | longlist24@example.com |
      | longlist25 | Longlist  | User 25 | longlist25@example.com |
      | longlist26 | Longlist  | User 26 | longlist26@example.com |
      | longlist27 | Longlist  | User 27 | longlist27@example.com |
      | longlist28 | Longlist  | User 28 | longlist28@example.com |
      | longlist29 | Longlist  | User 29 | longlist29@example.com |
      | longlist30 | Longlist  | User 30 | longlist30@example.com |
      | longlist31 | Longlist  | User 31 | longlist31@example.com |
      | longlist32 | Longlist  | User 32 | longlist32@example.com |
      | longlist33 | Longlist  | User 33 | longlist33@example.com |
      | longlist34 | Longlist  | User 34 | longlist34@example.com |
      | longlist35 | Longlist  | User 35 | longlist35@example.com |
      | longlist36 | Longlist  | User 36 | longlist36@example.com |
      | longlist37 | Longlist  | User 37 | longlist37@example.com |
      | longlist38 | Longlist  | User 38 | longlist38@example.com |
      | longlist39 | Longlist  | User 39 | longlist39@example.com |
      | longlist40 | Longlist  | User 40 | longlist40@example.com |
      | longlist41 | Longlist  | User 41 | longlist41@example.com |
      | longlist42 | Longlist  | User 42 | longlist42@example.com |
      | longlist43 | Longlist  | User 43 | longlist43@example.com |
      | longlist44 | Longlist  | User 44 | longlist44@example.com |
      | longlist45 | Longlist  | User 45 | longlist45@example.com |
    And I visit "/r.php/tool_mulib/muform/fixture"
    When I click on "Edit in dialog" "button"
    And I type "Longlist" into the "owner" muform search field
    Then I should see "Longlist User 45" in the "dialog[open] [role='listbox']" "css_element"
    And the open muform list should be fully visible
    When I click on "//dialog[@open]//li[@data-muform-autocomplete-option][contains(., 'Longlist User 45')]" "xpath_element"
    Then I should see "Longlist User 45" in the "dialog[open] [data-muform-name='owner']" "css_element"
    When I type "Longlist" into the "members" muform search field
    Then I should see "Longlist User 01" in the "dialog[open] [role='listbox']" "css_element"
    And the open muform list should be fully visible
    # Escape closes only the list, the dialog stays open.
    When I press the escape key
    Then "dialog[open] [role='listbox']" "css_element" should not exist
    And "dialog[open]" "css_element" should exist
    When I type "Longlist" into the "members" muform search field
    And I click on "//dialog[@open]//li[@data-muform-autocomplete-option][contains(., 'Longlist User 45')]" "xpath_element"
    And I type "Longlist" into the "members" muform search field
    And I click on "//dialog[@open]//li[@data-muform-autocomplete-option][contains(., 'Longlist User 01')]" "xpath_element"
    Then I should see "Longlist User 01" in the "dialog[open] [data-muform-name='members']" "css_element"
    And I should see "Longlist User 45" in the "dialog[open] [data-muform-name='members']" "css_element"

  @javascript
  Scenario: Calendar of a date element is not clipped by a small dialog
    Given I visit "/r.php/tool_mulib/muform/fixture"
    When I click on "Pick date" "button"
    And I click on "Choose date and time" "button" in the "dialog[open]" "css_element"
    Then the open muform list should be fully visible
    # Escape closes only the calendar, the dialog stays open.
    When I press the escape key
    Then ".muform-datetime-panel" "css_element" should not exist
    And "dialog[open]" "css_element" should exist
    When I click on "Cancel" "button" in the "dialog[open]" "css_element"
    Then "dialog[open]" "css_element" should not exist
