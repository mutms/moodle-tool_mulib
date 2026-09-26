@tool @tool_mulib @MuTMS
Feature: muform sharedkey element
  In order to manage keys handed to people through muform forms
  As a developer
  I need the sharedkey element to keep, reveal, replace and clear keys

  Background:
    Given I log in as "admin"

  Scenario: Nothing typed keeps the current key, typed text replaces it
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_sharedkey.php"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "enrolkey: null" in the "#submitted_enrolkey" "css_element"
    When I set the following muform fields:
      | enrolkey | key123 |
      | guestkey | g2     |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "enrolkey: key123" in the "#submitted_enrolkey" "css_element"
    And I should see "guestkey: g2" in the "#submitted_guestkey" "css_element"
    And the field "Enrolment key" matches value ""

  Scenario: Current key matches without being typed and can be cleared
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_sharedkey.php" with parameters "prefill=1"
    Then the following muform fields match:
      | enrolkey | abc |
      | guestkey | g1  |
    And the field "Enrolment key" matches value ""
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "enrolkey: null" in the "#submitted_enrolkey" "css_element"
    When I set the following muform fields:
      | enrolkey | [clear] |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "enrolkey: " in the "#submitted_enrolkey" "css_element"
    And I should not see "null" in the "#submitted_enrolkey" "css_element"
    And "[data-muform-name='guestkey'] input[type='checkbox']" "css_element" should not exist

  Scenario: Required value is satisfied by the current key or a new one
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_sharedkey.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='enrolkey'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | enrolkey | k3y |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "enrolkey: k3y" in the "#submitted_enrolkey" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_sharedkey.php" with parameters "required=1&prefill=1"
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "enrolkey: null" in the "#submitted_enrolkey" "css_element"

  Scenario: Invalid values are reported without echoing the typed key back
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_sharedkey.php"
    When I set the following muform fields:
      | enrolkey | weak     |
      | guestkey | toolong7 |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "This key is too weak" in the "[data-muform-name='enrolkey'] .invalid-feedback" "css_element"
    And I should see "Error" in the "[data-muform-name='guestkey'] .invalid-feedback" "css_element"
    And the field "Enrolment key" matches value ""
    And the field "Guest key" matches value ""

  Scenario: Frozen element shows only whether a key exists
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_sharedkey.php" with parameters "frozen=1&prefill=1"
    Then "[data-muform-name='enrolkey'] input" "css_element" should not exist
    And I should not see "abc" in the "[data-muform-name='enrolkey']" "css_element"
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "enrolkey: null" in the "#submitted_enrolkey" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_sharedkey.php" with parameters "frozen=1"
    Then I should see "Not set" in the "[data-muform-name='enrolkey']" "css_element"

  Scenario: Reload drops the typed key and cancel leaves the form
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_sharedkey.php"
    When I set the following muform fields:
      | enrolkey | draft |
    And I press "Refresh"
    Then I should see "Form reloaded" in the "#muform_state" "css_element"
    And the field "Enrolment key" matches value ""
    When I press "Cancel"
    Then I should see "Form cancelled" in the "#muform_state" "css_element"

  @javascript
  Scenario: Show reveals the current key and typed keys can be shown too
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_sharedkey.php" with parameters "prefill=1"
    When I click on "Show" "button" in the "[data-muform-name='enrolkey']" "css_element"
    Then the field "Enrolment key" matches value "abc"
    When I set the following muform fields:
      | enrolkey | shown key |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "enrolkey: shown key" in the "#submitted_enrolkey" "css_element"

  @javascript
  Scenario: Hidden element still keeps the current key
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_sharedkey.php" with parameters "prefill=1"
    Then "[data-muform-name='enrolkey']" "css_element" should be visible
    When I set the following muform fields:
      | hide | 1 |
    Then "[data-muform-name='enrolkey']" "css_element" should not be visible
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "enrolkey: null" in the "#submitted_enrolkey" "css_element"

  @javascript
  Scenario: Locked element is not submitted so the current key stays
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_sharedkey.php" with parameters "prefill=1"
    When I set the following muform fields:
      | enrolkey | changed |
      | lock     | 1       |
    Then the "Enrolment key" "field" should be disabled
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "enrolkey: null" in the "#submitted_enrolkey" "css_element"
    When I set the following muform fields:
      | lock | 0 |
    Then the "Enrolment key" "field" should be enabled

  @javascript
  Scenario: Client side validation blocks the submit until a key is typed
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_sharedkey.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='enrolkey'] .invalid-feedback" "css_element"
    And the focused element is "Enrolment key" "field"
    When I set the following muform fields:
      | enrolkey | k3y |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "enrolkey: k3y" in the "#submitted_enrolkey" "css_element"
