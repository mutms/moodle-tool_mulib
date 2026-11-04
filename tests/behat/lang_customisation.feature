@tool @tool_mulib @MuTMS
Feature: Language customisations with fake language packs
  In order to adapt translations
  As an admin
  I need language customisations to work with fake language packs and generated customisations

  # NOTE: language customisation tool checks out only core, core_langconfig and tool_customlang
  # strings when running Behat (MDL-70014), so core strings are customised here,
  # MuTMS strings and the purge of downloaded translations are covered by PHPUnit tests.

  Background:
    Given I mark this test as slow setting a timeout factor of 4
    And fake "cs" language pack is installed

  @javascript
  Scenario: Customise string in fake language pack via the language customisation tool
    Given I log in as "admin"
    And I navigate to "Language > Language customisation" in site administration
    And I set the field "lng" to "cs"
    And I press "Open language pack for editing"
    And I press "Continue"
    And I set the field "Show strings of these components" to "moodle.php"
    And I set the field "String identifier" to "preferences"
    And I press "Show strings"
    And I set the field "core/preferences" to "Převelice překrásné předvolby"
    And I press "Save changes to the language pack"
    And I should see "There are 1 modified strings."
    And I click on "Continue" "button"
    When I visit "/user/preferences.php?lang=cs"
    Then I should see "Převelice překrásné předvolby"
    And I should see "Upřednostňovaný jazyk"
    # Shipped MuTMS translations are used together with the fake pack.
    And I visit "/admin/settings.php?section=tool_mulib_lang"
    And I should see "Překlady MuTMS"
    # Customisations are never purged.
    And I run the "tool_mulib\task\lang_cleanup" task
    And I visit "/user/preferences.php"
    And I should see "Převelice překrásné předvolby"
    And I visit "/user/preferences.php?lang=en"
    And I should see "Preferences"
    And I should not see "Převelice překrásné předvolby"

  @javascript
  Scenario: Generated customisations are imported and kept by the language customisation tool
    Given the following "tool_mulib > lang_customisations" exist:
      | lang | component | stringid    | value                         |
      | cs   | core      | preferences | Převelice překrásné předvolby |
    And I log in as "admin"
    And I navigate to "Language > Language customisation" in site administration
    And I set the field "lng" to "cs"
    And I press "Open language pack for editing"
    And I press "Continue"
    And I set the field "Show strings of these components" to "moodle.php"
    And I set the field "String identifier" to "preferences"
    And I press "Show strings"
    # Checkout imports customisations from files.
    And the field "core/preferences" matches value "Převelice překrásné předvolby"
    And I set the field "String identifier" to "preferredlanguage"
    And I press "Show strings"
    And I set the field "core/preferredlanguage" to "Ukecaný rohlík"
    And I press "Save changes to the language pack"
    And I click on "Continue" "button"
    When I visit "/user/preferences.php?lang=cs"
    Then I should see "Převelice překrásné předvolby"
    And I should see "Ukecaný rohlík"

  @javascript
  Scenario: Customisations generated after checkout are kept by the language customisation tool
    Given I log in as "admin"
    And I navigate to "Language > Language customisation" in site administration
    And I set the field "lng" to "cs"
    And I press "Open language pack for editing"
    And I press "Continue"
    # Database records of the tool exist now, they must be updated by the generator.
    And the following "tool_mulib > lang_customisations" exist:
      | lang | component | stringid    | value                         |
      | cs   | core      | preferences | Převelice překrásné předvolby |
    And I set the field "Show strings of these components" to "moodle.php"
    And I set the field "String identifier" to "preferredlanguage"
    And I press "Show strings"
    And I set the field "core/preferredlanguage" to "Ukecaný rohlík"
    And I press "Save changes to the language pack"
    And I click on "Continue" "button"
    When I visit "/user/preferences.php?lang=cs"
    Then I should see "Převelice překrásné předvolby"
    And I should see "Ukecaný rohlík"
