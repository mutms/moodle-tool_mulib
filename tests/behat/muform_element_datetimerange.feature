@tool @tool_mulib @MuTMS
Feature: muform datetimerange element
  In order to enter sessions with start and end in muform forms
  As a developer
  I need the datetimerange element to parse, validate, pick and lock ranges correctly

  Background:
    Given I log in as "admin"

  Scenario: Current data is shown with the day offset and submitted unchanged
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_datetimerange.php" with parameters "prefill=1"
    Then the "value" attribute of "[data-muform-name='session'] input[type='text']" "css_element" should contain "2026-11-06 23:30 – 01:30 +1"
    And I should see "2 hours, ends 2026-11-07 01:30" in the "[data-muform-name='session']" "css_element"
    And the following muform fields match:
      | session  | 1793979000-1793986200 |
      | workshop |                       |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "session: 1793979000, 1793986200" in the "#submitted_session" "css_element"
    And I should see "workshop: null" in the "#submitted_workshop" "css_element"

  Scenario: Typed text is parsed on the server with defaults
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_datetimerange.php"
    When I set the following muform fields:
      | session  | 2026-11-06 23:30 - 1:30 |
      | workshop | 2026-11-10 - 17:00      |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "session: 1793979000, 1793986200" in the "#submitted_session" "css_element"
    And the following muform fields match:
      | session  | 2026-11-06 23:30 – 01:30 +1 |
      | workshop | 2026-11-10 09:00 – 17:00    |
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_datetimerange.php"
    And I set the following muform fields:
      | session | 2026-11-06 23:30 |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And the following muform fields match:
      | session | 2026-11-06 23:30 - 00:30 +1 |

  Scenario: Invalid ranges are reported and kept for correction
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_datetimerange.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='session'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | session  | sometime soon                  |
      | workshop | 2026-11-10 09:00 - 18:00       |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Invalid date and time range" in the "[data-muform-name='session'] .invalid-feedback" "css_element"
    And I should see "The maximum duration is 8 hours" in the "[data-muform-name='workshop'] .invalid-feedback" "css_element"
    And the following muform fields match:
      | session | sometime soon |
    When I set the following muform fields:
      | session  | 2026-11-06 11:30 - 12:30 +1 |
      | workshop | 2026-11-10 09:00 - 17:00    |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Invalid date and time range" in the "[data-muform-name='session'] .invalid-feedback" "css_element"

  Scenario: Frozen element shows the range for reading
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_datetimerange.php" with parameters "frozen=1&prefill=1"
    Then I should see "2026-11-06 23:30 – 01:30+1" in the "[data-muform-name='session']" "css_element"
    And "[data-muform-name='session'] sup" "css_element" should exist
    And "[data-muform-name='session'] input" "css_element" should not exist
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "session: 1793979000, 1793986200" in the "#submitted_session" "css_element"

  @javascript
  Scenario: Typed and pasted text is normalised in the browser
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_datetimerange.php"
    When I set the following muform fields:
      | session | 2026-11-06 23:30 – 01:30+1 |
    Then the "value" attribute of "[data-muform-name='session'] input[type='text']" "css_element" should contain "2026-11-06 23:30 – 01:30 +1"
    And I should see "2 hours, ends 2026-11-07 01:30" in the "[data-muform-name='session']" "css_element"
    When I set the following muform fields:
      | session | 2026-11-06 10:00 |
    Then the "value" attribute of "[data-muform-name='session'] input[type='text']" "css_element" should contain "2026-11-06 10:00 – 11:00"
    And I should see "1 hour" in the "[data-muform-name='session'] [data-muform-datetimerange-hint]" "css_element"
    When I set the following muform fields:
      | workshop | not a range |
    Then I should see "Invalid date and time range" in the "[data-muform-name='workshop'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | workshop | 2026-11-10 09:00 - 12:00 |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And the following muform fields match:
      | session  | 2026-11-06 10:00 - 11:00 |
      | workshop | 2026-11-10 09:00 - 12:00 |

  @javascript
  Scenario: The picker keeps the duration and sets the end time
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_datetimerange.php" with parameters "prefill=1"
    When I click on "Choose date and time range" "button" in the "[data-muform-name='session']" "css_element"
    Then I should see "November 2026" in the ".muform-datetime-panel" "css_element"
    And I should see "+1" in the ".muform-datetime-panel" "css_element"
    When I click on "10" "button" in the ".muform-datetime-panel" "css_element"
    And I set the field "Duration" to "3 hours"
    And I click on "Apply" "button" in the ".muform-datetime-panel" "css_element"
    Then the following muform fields match:
      | session | 2026-11-10 23:30 - 02:30 +1 |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And the following muform fields match:
      | session | 2026-11-10 23:30 - 2026-11-11 02:30 |

  @javascript
  Scenario: Locked element is not submitted so the current value stays
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_datetimerange.php" with parameters "prefill=1"
    When I set the following muform fields:
      | session | 2026-12-01 10:00 - 11:00 |
      | lock    | 1                        |
    Then the "Session" "field" should be disabled
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "session: 1793979000, 1793986200" in the "#submitted_session" "css_element"
