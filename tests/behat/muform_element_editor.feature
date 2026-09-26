@tool @tool_mulib @MuTMS
Feature: muform editor element
  In order to edit rich text in muform forms
  As a developer
  I need the editor element to render, clean, validate, hide and lock correctly

  Background:
    Given I log in as "admin"

  Scenario: New form is empty and the format is submitted with the text
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_editor.php"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And the following muform fields match:
      | description | |
      | summary     | |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "description:" in the "#submitted_description" "css_element"
    And I should see "descriptionformat: 1" in the "#submitted_descriptionformat" "css_element"

  Scenario: Current data is shown and submitted unchanged
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_editor.php" with parameters "prefill=1"
    Then the following muform fields match:
      | Description | <p>Seeded <b>description</b></p> |
      | summary     | Plain summary                    |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "description: <p>Seeded <b>description</b></p>" in the "#submitted_description" "css_element"
    And I should see "descriptionformat: 1" in the "#submitted_descriptionformat" "css_element"
    And I should see "summary: Plain summary" in the "#submitted_summary" "css_element"
    And I should see "summaryformat: 2" in the "#submitted_summaryformat" "css_element"

  Scenario: Typed HTML is cleaned unless unsafe HTML is allowed
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_editor.php"
    When I set the following muform fields:
      | description | <p onclick="x()">Hello</p><script>alert(1)</script> |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "description: <p>Hello</p>" in the "#submitted_description" "css_element"
    And I should not see "alert(1)" in the "#submitted_description" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_editor.php" with parameters "unsafe=1"
    Then I should see "Unsafe HTML allowed" in the "[data-muform-name='description']" "css_element"
    When I set the following muform fields:
      | description | <p onclick="x()">Hello</p><script>alert(1)</script> |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "<script>alert(1)</script>" in the "#submitted_description" "css_element"

  Scenario: Required value is enforced by the server
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_editor.php" with parameters "required=1"
    When I set the following muform fields:
      | description | <p>  </p> |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='description'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | description | <p>Some text</p> |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "description: <p>Some text</p>" in the "#submitted_description" "css_element"

  Scenario: Frozen element shows the formatted text and is not posted
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_editor.php" with parameters "frozen=1&prefill=1"
    Then I should see "Seeded description" in the "[data-muform-name='description']" "css_element"
    And "[data-muform-name='description'] b" "css_element" should exist
    And "[data-muform-name='description'] textarea" "css_element" should not exist
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "description: <p>Seeded <b>description</b></p>" in the "#submitted_description" "css_element"

  Scenario: Reload keeps typed text and cancel leaves the form
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_editor.php" with parameters "required=1"
    When I set the following muform fields:
      | description | <p>Draft</p> |
    And I press "Refresh"
    Then I should see "Form reloaded" in the "#muform_state" "css_element"
    And the following muform fields match:
      | description | <p>Draft</p> |
    When I press "Cancel"
    Then I should see "Form cancelled" in the "#muform_state" "css_element"

  @javascript
  Scenario: Text typed into TinyMCE is submitted and a hidden editor keeps its text
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_editor.php" with parameters "prefill=1"
    When I set the following muform fields:
      | description | <p>Tiny text</p> |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "description: <p>Tiny text</p>" in the "#submitted_description" "css_element"
    And the following muform fields match:
      | description | <p>Tiny text</p> |
    When I set the following muform fields:
      | hide | 1 |
    Then "[data-muform-name='description']" "css_element" should not be visible
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "description: <p>Tiny text</p>" in the "#submitted_description" "css_element"

  @javascript
  Scenario: Client side validation blocks the submit until text is typed
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_editor.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='description'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | description | <p>Typed</p> |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "description: <p>Typed</p>" in the "#submitted_description" "css_element"
