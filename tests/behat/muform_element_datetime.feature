@tool @tool_mulib @MuTMS
Feature: muform datetime element
  In order to enter dates and times in muform forms
  As a developer
  I need the datetime element to render, validate, hide and lock correctly

  Background:
    Given I log in as "admin"

  Scenario: New form is empty unless the element has a default
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_datetime.php"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And the following muform fields match:
      | starts | |
      | ends   | |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "starts: null" in the "#submitted_starts" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_datetime.php" with parameters "default=1"
    Then the following muform fields match:
      | starts | 1893462000 |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "starts: 1893462000" in the "#submitted_starts" "css_element"

  Scenario: Current data is shown in the user timezone and submitted unchanged
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_datetime.php" with parameters "prefill=1"
    Then the following muform fields match:
      | Starts | 1790395200 |
      | ends   |            |
    And the field "Starts" matches value "2026-09-26 12:00"
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "starts: 1790395200" in the "#submitted_starts" "css_element"
    And I should see "ends: null" in the "#submitted_ends" "css_element"

  Scenario: Typed text is parsed on the server
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_datetime.php"
    When I set the following muform fields:
      | starts | ##tomorrow noon## |
      | ends   | 2026-12-24 18:00  |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should not see "null" in the "#submitted_starts" "css_element"
    And the following muform fields match:
      | starts | ##tomorrow noon## |
      | ends   | 2026-12-24 18:00  |

  Scenario: Required value is enforced by the server
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_datetime.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='starts'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | starts | 2026-10-01 09:00 |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And the following muform fields match:
      | starts | 2026-10-01 09:00 |

  Scenario: Invalid text is reported and kept for correction
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_datetime.php"
    When I set the following muform fields:
      | starts | not a date |
      | ends   | 2026-02-30 10:00 |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Invalid date and time" in the "[data-muform-name='starts'] .invalid-feedback" "css_element"
    And I should see "Invalid date and time" in the "[data-muform-name='ends'] .invalid-feedback" "css_element"
    And the following muform fields match:
      | starts | not a date       |
      | ends   | 2026-02-30 10:00 |
    When I set the following muform fields:
      | starts | 2026-12-24 18:00 |
      | ends   | 2026-12-24 17:00 |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "End must be after start" in the "[data-muform-name='ends'] .invalid-feedback" "css_element"

  Scenario: Frozen element shows the current value and is not posted
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_datetime.php" with parameters "frozen=1&prefill=1"
    Then I should see "2026-09-26" in the "[data-muform-name='starts']" "css_element"
    And "[data-muform-name='starts'] input" "css_element" should not exist
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "starts: 1790395200" in the "#submitted_starts" "css_element"

  Scenario: Reload keeps typed values and cancel leaves the form
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_datetime.php" with parameters "required=1"
    When I set the following muform fields:
      | starts | 2026-10-01 09:00 |
    And I press "Refresh"
    Then I should see "Form reloaded" in the "#muform_state" "css_element"
    And the following muform fields match:
      | starts | 2026-10-01 09:00 |
    When I press "Cancel"
    Then I should see "Form cancelled" in the "#muform_state" "css_element"

  @javascript
  Scenario: Hidden element still submits its value
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_datetime.php" with parameters "prefill=1"
    Then "[data-muform-name='starts']" "css_element" should be visible
    When I set the following muform fields:
      | hide | 1 |
    Then "[data-muform-name='starts']" "css_element" should not be visible
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "starts: 1790395200" in the "#submitted_starts" "css_element"
    And "[data-muform-name='starts']" "css_element" should not be visible

  @javascript
  Scenario: Locked element is not submitted so the current value stays
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_datetime.php" with parameters "prefill=1"
    When I set the following muform fields:
      | starts | 2026-10-01 09:00 |
      | lock   | 1                |
    Then the "Starts" "field" should be disabled
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "starts: 1790395200" in the "#submitted_starts" "css_element"
    And the following muform fields match:
      | starts | 1790395200 |
    When I set the following muform fields:
      | lock | 0 |
    Then the "Starts" "field" should be enabled

  @javascript
  Scenario: Typed text is normalised in the browser and the picker sets dates
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_datetime.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='starts'] .invalid-feedback" "css_element"
    And the focused element is "Starts" "field"
    When I set the following muform fields:
      | starts | ##tomorrow 10:00## |
      | ends   | not a date         |
    Then I should see "Invalid date and time" in the "[data-muform-name='ends'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | ends | 2026-12-01 09:30 |
    And I click on "Choose date and time" "button" in the "[data-muform-name='ends']" "css_element"
    Then I should see "December 2026" in the ".muform-datetime-panel" "css_element"
    When I click on "Next year" "button" in the ".muform-datetime-panel" "css_element"
    Then I should see "December 2027" in the ".muform-datetime-panel" "css_element"
    When I click on "Previous year" "button" in the ".muform-datetime-panel" "css_element"
    And I click on "15" "button" in the ".muform-datetime-panel" "css_element"
    And I click on "Apply" "button" in the ".muform-datetime-panel" "css_element"
    Then the following muform fields match:
      | ends | 2026-12-15 09:30 |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should not see "null" in the "#submitted_starts" "css_element"
    And the following muform fields match:
      | ends | 2026-12-15 09:30 |
