@tool @tool_mulib @MuTMS
Feature: muform text element
  In order to use single line text inputs in muform forms
  As a developer
  I need the text element to render, validate, hide and lock correctly

  Background:
    Given I log in as "admin"

  Scenario: New form is empty unless the element has a default
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_text.php"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And the following muform fields match:
      | fullname | |
      | email    | |
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_text.php" with parameters "default=1"
    Then the following muform fields match:
      | fullname | Default name |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "fullname: Default name" in the "#submitted_fullname" "css_element"

  Scenario: Current data is shown and submitted unchanged
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_text.php" with parameters "prefill=1"
    Then the following muform fields match:
      | fullname | Jane Doe             |
      | Email    | jane@example.com     |
      | website  | https://example.com/ |
      | code     | <b>raw</b>           |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "fullname: Jane Doe" in the "#submitted_fullname" "css_element"
    And I should see "email: jane@example.com" in the "#submitted_email" "css_element"
    And I should see "code: <b>raw</b>" in the "#submitted_code" "css_element"
    And the following muform fields match:
      | fullname | Jane Doe |

  Scenario: Typed values are cleaned and submitted
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_text.php"
    When I set the following muform fields:
      | fullname | John <b>Smith</b>        |
      | email    | john@example.com         |
      | website  | https://example.com/john |
      | code     | <b>abc</b>               |
      | slug     | abc                      |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "fullname: John Smith" in the "#submitted_fullname" "css_element"
    And I should see "website: https://example.com/john" in the "#submitted_website" "css_element"
    And I should see "code: <b>abc</b>" in the "#submitted_code" "css_element"
    And I should see "slug: abc" in the "#submitted_slug" "css_element"
    And the following muform fields match:
      | fullname | John Smith |
      | code     | <b>abc</b> |

  Scenario: Required value is enforced by the server
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_text.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='fullname'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | fullname | Jane Doe |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "fullname: Jane Doe" in the "#submitted_fullname" "css_element"

  Scenario: Invalid values are reported and kept for correction
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_text.php"
    When I set the following muform fields:
      | fullname | This name is longer than twenty |
      | email    | not an email                    |
      | website  | example.com                     |
      | slug     | ABC                             |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Error" in the "[data-muform-name='fullname'] .invalid-feedback" "css_element"
    And I should see "Error" in the "[data-muform-name='email'] .invalid-feedback" "css_element"
    And I should see "Error" in the "[data-muform-name='website'] .invalid-feedback" "css_element"
    And I should see "Error" in the "[data-muform-name='slug'] .invalid-feedback" "css_element"
    And the following muform fields match:
      | fullname | This name is longer than twenty |
      | email    | not an email                    |
      | slug     | ABC                             |
    When I set the following muform fields:
      | fullname | invalid |
      | email    |         |
      | website  |         |
      | slug     |         |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "This name is not allowed" in the "[data-muform-name='fullname'] .invalid-feedback" "css_element"

  Scenario: Frozen element shows the current value and is not posted
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_text.php" with parameters "frozen=1&prefill=1"
    Then I should see "Jane Doe" in the "[data-muform-name='fullname']" "css_element"
    And "[data-muform-name='fullname'] input" "css_element" should not exist
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "fullname: Jane Doe" in the "#submitted_fullname" "css_element"

  Scenario: Reload keeps typed values and cancel leaves the form
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_text.php" with parameters "required=1"
    When I set the following muform fields:
      | fullname | Draft |
    And I press "Refresh"
    Then I should see "Form reloaded" in the "#muform_state" "css_element"
    And the following muform fields match:
      | fullname | Draft |
    When I press "Cancel"
    Then I should see "Form cancelled" in the "#muform_state" "css_element"

  @javascript
  Scenario: Hidden element still submits its value
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_text.php" with parameters "prefill=1"
    Then "[data-muform-name='fullname']" "css_element" should be visible
    When I set the following muform fields:
      | hide | 1 |
    Then "[data-muform-name='fullname']" "css_element" should not be visible
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "fullname: Jane Doe" in the "#submitted_fullname" "css_element"
    And I should see "hide: 1" in the "#submitted_hide" "css_element"
    And "[data-muform-name='fullname']" "css_element" should not be visible

  @javascript
  Scenario: Locked element is not submitted so the current value stays
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_text.php" with parameters "prefill=1"
    When I set the following muform fields:
      | fullname | Changed |
      | lock     | 1       |
    Then the "Full name" "field" should be disabled
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "fullname: Jane Doe" in the "#submitted_fullname" "css_element"
    And the following muform fields match:
      | fullname | Jane Doe |
    And the "Full name" "field" should be disabled
    When I set the following muform fields:
      | lock | 0 |
    Then the "Full name" "field" should be enabled

  @javascript
  Scenario: Client side validation blocks the submit until the value is valid
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_text.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='fullname'] .invalid-feedback" "css_element"
    And the focused element is "Full name" "field"
    When I set the following muform fields:
      | fullname | Jane Doe     |
      | email    | not an email |
    And I press "Save changes"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And I should see "Error" in the "[data-muform-name='email'] .invalid-feedback" "css_element"
    And the focused element is "Email" "field"
    When I set the following muform fields:
      | email | jane@example.com |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "fullname: Jane Doe" in the "#submitted_fullname" "css_element"
