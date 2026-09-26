@tool @tool_mulib @MuTMS
Feature: muform customfields element
  In order to edit custom fields in muform forms
  As a developer
  I need the customfields element to load, validate and save custom field values

  Background:
    Given the following "custom field categories" exist:
      | name            | component        | area   | itemid |
      | Course details  | core_course      | course | 0      |
      | Shared category | core_customfield | shared | 0      |
    And the following "custom fields" exist:
      | name     | category        | type     | shortname | description   | configdata                                           |
      | Code     | Course details  | text     | code      | Unique code   | {"maxlength":5,"uniquevalues":1}                     |
      | Agree    | Course details  | checkbox | agree     |               | {"checkbydefault":1}                                 |
      | Level    | Course details  | select   | level     |               | {"options":"Low\nMedium\nHigh","defaultvalue":"Low"} |
      | Start    | Course details  | date     | start     |               | {"includetime":0}                                    |
      | Size     | Course details  | number   | size      |               | {"decimalplaces":1,"maximumvalue":"100"}             |
      | Notes    | Course details  | textarea | notes     |               |                                                      |
      | Colour   | Shared category | text     | colour    |               |                                                      |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
      | Course 2 | C2        |
    And I log in as "admin"

  Scenario: Custom field values are saved and loaded again
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_customfields.php" with parameters "course=C1"
    Then I should see "Course details"
    And I should see "Unique code"
    And I should not see "Colour"
    And the following muform fields match:
      | Code              |     |
      | customfield_agree | 1   |
      | customfield_level | 1   |
      | customfield_start |     |
      | customfield_size  |     |
    When I set the following muform fields:
      | customfield_code  | AB1              |
      | Level             | High             |
      | customfield_start | 2026-09-28       |
      | customfield_size  | 12.5             |
      | customfield_notes | <p>Some text</p> |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "customfield_agree: 1" in the "#submitted_customfield_agree" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_customfields.php" with parameters "course=C1"
    Then the following muform fields match:
      | customfield_code  | AB1              |
      | customfield_agree | 1                |
      | customfield_level | 3                |
      | customfield_start | 2026-09-28       |
      | customfield_size  | 12.5             |
      | customfield_notes | <p>Some text</p> |
    When I am on the "Course 1" "course editing" page
    And I expand all fieldsets
    Then the field "Code" matches value "AB1"
    And the field "Agree" matches value "1"
    And the field "Level" matches value "High"
    And the field "customfield_start[day]" matches value "28"
    And the field "customfield_start[month]" matches value "September"
    And the field "customfield_start[year]" matches value "2026"
    And the field "Size" matches value "12.5"

  Scenario: Custom field values are validated
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_customfields.php" with parameters "course=C1"
    When I set the following muform fields:
      | customfield_code | AB1 |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_customfields.php" with parameters "course=C2"
    And I set the following muform fields:
      | customfield_code | AB1 |
      | customfield_size | 101 |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "This value is already used." in the "[data-muform-name='customfield_code'] .invalid-feedback" "css_element"
    And I should see "Value must be less than or equal to 100.0" in the "[data-muform-name='customfield_size'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | customfield_code | AB2 |
      | customfield_size | 100 |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"

  @javascript
  Scenario: Custom fields are edited with JavaScript
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_customfields.php" with parameters "course=C1"
    When I set the following muform fields:
      | customfield_code  | JS1              |
      | customfield_agree | 0                |
      | customfield_level | Medium           |
      | customfield_start | 2026-12-24       |
      | customfield_notes | <p>Tiny text</p> |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_customfields.php" with parameters "course=C1"
    Then the following muform fields match:
      | customfield_code  | JS1              |
      | customfield_agree | 0                |
      | customfield_level | 2                |
      | customfield_start | 2026-12-24       |
      | customfield_notes | <p>Tiny text</p> |
