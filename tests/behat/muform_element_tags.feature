@tool @tool_mulib @MuTMS
Feature: muform tags element
  In order to tag items in muform forms
  As a developer
  I need the tags element to load, validate, save, hide and lock correctly

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "tags" exist:
      | name      | isstandard |
      | Physics   | 1          |
      | Chemistry | 1          |
    And I log in as "admin"

  Scenario: Stored tags are loaded and saved again
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_tags.php"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And the following muform fields match:
      | topics | |
    When I set the following muform fields:
      | Topics | Quantum   physics, Physics, physics |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "topics: Quantum physics, Physics" in the "#submitted_topics" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_tags.php"
    Then the following muform fields match:
      | topics | Quantum physics, Physics |
    When I set the following muform fields:
      | topics | |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_tags.php"
    Then the following muform fields match:
      | topics | |

  Scenario: Current data is shown and submitted unchanged
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_tags.php" with parameters "prefill=1"
    Then the following muform fields match:
      | topics | Prefilled |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "topics: Prefilled" in the "#submitted_topics" "css_element"

  Scenario: Invalid names and custom validation are reported
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_tags.php"
    When I set the following muform fields:
      | topics | Good, Bad<b> |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Bad<b>: Error" in the "[data-muform-name='topics'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | topics | Good, Forbidden |
    And I press "Save changes"
    Then I should see "Forbidden topic" in the "[data-muform-name='topics'] .invalid-feedback" "css_element"

  Scenario: Only standard tags may be used when the area says so
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_tags.php" with parameters "standard=1"
    When I set the following muform fields:
      | topics | physics, Biology |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Biology: Only standard tags can be used" in the "[data-muform-name='topics'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | topics | physics |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"

  Scenario: Required value is enforced and frozen tags are not posted
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_tags.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Required" in the "[data-muform-name='topics'] .invalid-feedback" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_tags.php" with parameters "frozen=1&prefill=1"
    Then I should see "Prefilled" in the "[data-muform-name='topics']" "css_element"
    And "[data-muform-name='topics'] input" "css_element" should not exist
    When I press "Save changes"
    Then I should see "topics: Prefilled" in the "#submitted_topics" "css_element"

  Scenario: Reload keeps the tags and cancel leaves the form
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_tags.php"
    When I set the following muform fields:
      | topics | Alpha, Beta |
    And I press "Refresh"
    Then I should see "Form reloaded" in the "#muform_state" "css_element"
    And the following muform fields match:
      | topics | Alpha, Beta |
    When I press "Cancel"
    Then I should see "Form cancelled" in the "#muform_state" "css_element"

  @javascript
  Scenario: Tags are typed, suggested and removed in the browser
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_tags.php"
    When I set the following muform fields:
      | topics | Quantum physics, Biology |
    Then I should see "Quantum physics" in the "[data-muform-name='topics']" "css_element"
    When I click on "Remove Biology" "button" in the "[data-muform-name='topics']" "css_element"
    And I type "chem" into the "topics" muform search field
    Then I should see "Chemistry" in the "[data-muform-tags-option='Chemistry']" "css_element"
    When I click on "[data-muform-tags-option='Chemistry']" "css_element"
    Then the following muform fields match:
      | topics | Quantum physics, Chemistry |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "topics: Quantum physics, Chemistry" in the "#submitted_topics" "css_element"

  @javascript
  Scenario: Only suggested tags can be picked with standard tags only
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_tags.php" with parameters "standard=1"
    When I set the following muform fields:
      | topics | Physics |
    Then the following muform fields match:
      | topics | Physics |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "topics: Physics" in the "#submitted_topics" "css_element"

  @javascript
  Scenario: Hidden element still submits and locked element keeps its value
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_tags.php" with parameters "prefill=1"
    When I set the following muform fields:
      | hide | 1 |
    Then "[data-muform-name='topics']" "css_element" should not be visible
    When I press "Save changes"
    Then I should see "topics: Prefilled" in the "#submitted_topics" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_tags.php" with parameters "prefill=1"
    And I set the following muform fields:
      | lock | 1 |
    Then the "[data-muform-name='topics'] input[role='combobox']" "css_element" should be disabled
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"

  @javascript
  Scenario: Client side validation blocks the submit until a tag is entered
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_tags.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='topics'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | topics | Alpha |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
