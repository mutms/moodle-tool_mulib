@tool @tool_mulib @MuTMS @_file_upload
Feature: muform filemanager element
  In order to attach files to records through muform forms
  As a developer
  I need the filemanager element to list, upload, validate and save files

  Background:
    Given I log in as "admin"

  Scenario: Current files are listed and saved again without JavaScript
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_filemanager.php"
    Then I should see "Form is new" in the "#muform_state" "css_element"
    And the following muform fields match:
      | attachments | |
      | photo       | |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "attachments:" in the "#submitted_attachments" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_filemanager.php" with parameters "prefill=1"
    Then the following muform fields match:
      | Attachments | seeded.txt |
      | photo       |            |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "attachments: seeded.txt" in the "#submitted_attachments" "css_element"
    And I should see "photo:" in the "#submitted_photo" "css_element"

  Scenario: Required value is enforced by the server
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_filemanager.php" with parameters "required=1"
    When I press "Save changes"
    Then I should see "Form is invalid" in the "#muform_state" "css_element"
    And I should see "Required" in the "[data-muform-name='attachments'] .invalid-feedback" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_filemanager.php" with parameters "required=1&prefill=1"
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "attachments: seeded.txt" in the "#submitted_attachments" "css_element"

  Scenario: Frozen element lists the current files and is not posted
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_filemanager.php" with parameters "frozen=1&prefill=1"
    Then I should see "seeded.txt" in the "[data-muform-name='attachments']" "css_element"
    And "[data-muform-name='attachments'] input" "css_element" should not exist
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "attachments: seeded.txt" in the "#submitted_attachments" "css_element"

  Scenario: Reload keeps the draft files and cancel leaves the form
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_filemanager.php" with parameters "prefill=1"
    When I press "Refresh"
    Then I should see "Form reloaded" in the "#muform_state" "css_element"
    And the following muform fields match:
      | attachments | seeded.txt |
    When I press "Cancel"
    Then I should see "Form cancelled" in the "#muform_state" "css_element"

  @javascript
  Scenario: Upload files, reload and save them
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_filemanager.php" with parameters "prefill=1"
    Then the following muform fields match:
      | attachments | seeded.txt |
      | photo       |            |
    When I upload "lib/tests/fixtures/empty.txt" file to "Attachments" muform filemanager
    And I upload "lib/tests/fixtures/gd-logo.png" file to "photo" muform filemanager
    Then the following muform fields match:
      | attachments | seeded.txt, empty.txt |
      | photo       | gd-logo.png           |
    When I press "Refresh"
    Then I should see "Form reloaded" in the "#muform_state" "css_element"
    And the following muform fields match:
      | attachments | empty.txt, seeded.txt |
      | photo       | gd-logo.png           |
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "attachments: empty.txt, seeded.txt" in the "#submitted_attachments" "css_element"
    And I should see "photo: gd-logo.png" in the "#submitted_photo" "css_element"
    When I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_filemanager.php" with parameters "prefill=1"
    Then the following muform fields match:
      | attachments | empty.txt, seeded.txt |
      | photo       | gd-logo.png           |

  @javascript
  Scenario: Hidden element still submits its files
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_filemanager.php" with parameters "prefill=1"
    Then "[data-muform-name='attachments']" "css_element" should be visible
    When I set the following muform fields:
      | hide | 1 |
    Then "[data-muform-name='attachments']" "css_element" should not be visible
    When I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "attachments: seeded.txt" in the "#submitted_attachments" "css_element"
    And "[data-muform-name='attachments']" "css_element" should not be visible

  @javascript
  Scenario: Locked element is not submitted so the current files stay
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/muform_element_filemanager.php" with parameters "prefill=1"
    When I upload "lib/tests/fixtures/empty.txt" file to "attachments" muform filemanager
    And I set the following muform fields:
      | lock | 1 |
    And I press "Save changes"
    Then I should see "Form submitted" in the "#muform_state" "css_element"
    And I should see "attachments: seeded.txt" in the "#submitted_attachments" "css_element"
