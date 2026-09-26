@tool @tool_mulib @MuTMS
Feature: muform multiselect element
  In order to pick several options from a list in muform forms
  As a developer
  I need the multiselect element to render, validate, hide and lock correctly

  Background:
    Given I log in as "admin"

  Scenario: New form has nothing selected unless the element has a default
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_multiselect.php"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And the following muform fields match:
      | languages | |
      | tags      | |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "languages:" in the "#submitted_languages" "css_element"
    And I should not see "en" in the "#submitted_languages" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_multiselect.php" with parameters "default=1"
    Then the following muform fields match:
      | languages | en |
    When I press "Save changes"
    Then I should see "languages: en" in the "#submitted_languages" "css_element"

  Scenario: Current data is shown and submitted in option order
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_multiselect.php" with parameters "prefill=1"
    Then the following muform fields match:
      | Languages | cs, en |
      | tags      | Hot    |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "languages: en, cs" in the "#submitted_languages" "css_element"
    And I should see "tags: hot" in the "#submitted_tags" "css_element"

  Scenario: Options are selected by key or exact label
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_multiselect.php"
    When I set the following muform fields:
      | languages | de, English, cs |
      | tags      | New, hot        |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Too many languages" in the "[data-muform-name='languages'] .invalid-feedback" "css_element"
    And the following muform fields match:
      | languages | cs, de, en |
      | tags      | hot, new   |
    When I set the following muform fields:
      | languages | de |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "languages: de" in the "#submitted_languages" "css_element"
    And I should see "tags: new, hot" in the "#submitted_tags" "css_element"

  Scenario: Required value is enforced by the server
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_multiselect.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='languages'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | languages | cs |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "languages: cs" in the "#submitted_languages" "css_element"

  Scenario: Frozen element shows the current labels and is not posted
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_multiselect.php" with parameters "frozen=1&prefill=1"
    Then I should see "Czech" in the "[data-muform-name='languages']" "css_element"
    And I should see "English" in the "[data-muform-name='languages']" "css_element"
    And "[data-muform-name='languages'] select" "css_element" should not exist
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "languages: en, cs" in the "#submitted_languages" "css_element"

  Scenario: Reload keeps the selection and cancel leaves the form
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_multiselect.php" with parameters "required=1"
    When I set the following muform fields:
      | languages | de, cs |
    And I press "Refresh"
    Then I should see "Form reloaded" in the "#muform_state" "css_element"
    And the following muform fields match:
      | languages | cs, de |
    When I press "Cancel"
    Then I should see "Form cancelled" in the "#muform_state" "css_element"

  @javascript
  Scenario: Hidden element still submits its value
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_multiselect.php" with parameters "prefill=1"
    Then "[data-muform-name='languages']" "css_element" should be visible
    When I set the following muform fields:
      | hide | 1 |
    Then "[data-muform-name='languages']" "css_element" should not be visible
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "languages: en, cs" in the "#submitted_languages" "css_element"
    And "[data-muform-name='languages']" "css_element" should not be visible

  @javascript
  Scenario: Locked element is not submitted so the current value stays
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_multiselect.php" with parameters "prefill=1"
    When I set the following muform fields:
      | languages | de |
      | lock      | 1  |
    Then the "Languages" "select" should be disabled
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "languages: en, cs" in the "#submitted_languages" "css_element"
    And the following muform fields match:
      | languages | en, cs |
    When I set the following muform fields:
      | lock | 0 |
    Then the "Languages" "select" should be enabled

  @javascript
  Scenario: Client side validation blocks the submit until something is selected
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_multiselect.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='languages'] .invalid-feedback" "css_element"
    And the focused element is "Languages" "select"
    When I set the following muform fields:
      | languages | de |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "languages: de" in the "#submitted_languages" "css_element"
