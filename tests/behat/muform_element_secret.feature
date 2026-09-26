@tool @tool_mulib @MuTMS
Feature: muform secret element
  In order to store machine credentials through muform forms
  As a developer
  I need the secret element to keep, replace and clear values without ever showing them

  Background:
    Given I log in as "admin"

  Scenario: Nothing typed keeps the current secret, typed text replaces it
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_secret.php"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "dbsecret: null" in the "#submitted_dbsecret" "css_element"
    When I set the following muform fields:
      | dbsecret | new secret |
      | apikey   | key123     |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "dbsecret: new secret" in the "#submitted_dbsecret" "css_element"
    And I should see "apikey: key123" in the "#submitted_apikey" "css_element"
    And the field "Database secret" matches value ""

  Scenario: Current secret is never rendered and can be cleared
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_secret.php" with parameters "prefill=1"
    Then I should not see "existing-key" in the ".muform" "css_element"
    And the field "API key" matches value ""
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "dbsecret: null" in the "#submitted_dbsecret" "css_element"
    And I should see "apikey: null" in the "#submitted_apikey" "css_element"
    When I set the following muform fields:
      | dbsecret | [clear] |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "dbsecret: " in the "#submitted_dbsecret" "css_element"
    And I should not see "null" in the "#submitted_dbsecret" "css_element"
    And "[data-muform-name='apikey'] input[type='checkbox']" "css_element" should not exist

  Scenario: Required value is satisfied by the current secret or a new one
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_secret.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='dbsecret'] .invalid-feedback" "css_element"
    When I set the following muform fields:
      | dbsecret | s3cret |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "dbsecret: s3cret" in the "#submitted_dbsecret" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_secret.php" with parameters "required=1&prefill=1"
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "dbsecret: null" in the "#submitted_dbsecret" "css_element"
    When I set the following muform fields:
      | dbsecret | [clear] |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='dbsecret'] .invalid-feedback" "css_element"

  Scenario: Invalid values are reported and the typed values are kept for correction
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_secret.php"
    When I set the following muform fields:
      | dbsecret | weak                    |
      | apikey   | this key is far too long |
    And I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "This secret is too weak" in the "[data-muform-name='dbsecret'] .invalid-feedback" "css_element"
    And I should see "Error" in the "[data-muform-name='apikey'] .invalid-feedback" "css_element"
    And the field "Database secret" matches value "weak"
    And the field "API key" matches value "this key is far too long"

  Scenario: Frozen element shows only whether a secret exists
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_secret.php" with parameters "frozen=1&prefill=1"
    Then "[data-muform-name='dbsecret'] input" "css_element" should not exist
    And I should not see "Not set" in the "[data-muform-name='dbsecret']" "css_element"
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "dbsecret: null" in the "#submitted_dbsecret" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_secret.php" with parameters "frozen=1"
    Then I should see "Not set" in the "[data-muform-name='dbsecret']" "css_element"

  Scenario: Reload keeps the typed secret and cancel leaves the form
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_secret.php"
    When I set the following muform fields:
      | dbsecret | draft secret |
    And I press "Refresh"
    Then I should see "Form reloaded" in the "#muform_state" "css_element"
    And the field "Database secret" matches value "draft secret"
    When I press "Cancel"
    Then I should see "Form cancelled" in the "#muform_state" "css_element"

  @javascript
  Scenario: Hidden element still keeps the current secret
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_secret.php" with parameters "prefill=1"
    Then "[data-muform-name='dbsecret']" "css_element" should be visible
    When I set the following muform fields:
      | hide | 1 |
    Then "[data-muform-name='dbsecret']" "css_element" should not be visible
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "dbsecret: null" in the "#submitted_dbsecret" "css_element"

  @javascript
  Scenario: Locked element is not submitted so the current secret stays
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_secret.php" with parameters "prefill=1"
    When I set the following muform fields:
      | dbsecret | changed |
      | lock     | 1       |
    Then the "Database secret" "field" should be disabled
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "dbsecret: null" in the "#submitted_dbsecret" "css_element"
    When I set the following muform fields:
      | lock | 0 |
    Then the "Database secret" "field" should be enabled

  @javascript
  Scenario: Client side validation blocks the submit until a secret is typed
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_secret.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='dbsecret'] .invalid-feedback" "css_element"
    And the focused element is "Database secret" "field"
    When I set the following muform fields:
      | dbsecret | s3cret |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "dbsecret: s3cret" in the "#submitted_dbsecret" "css_element"
