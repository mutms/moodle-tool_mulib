@tool @tool_mulib @MuTMS
Feature: Language customisations of MuTMS strings
  In order to adapt shipped MuTMS translations
  As an admin
  I need language customisations to work with shipped translations and fake language packs

  Background:
    # Checkout of the language customisation tool is very slow.
    Given I mark this test as slow setting a timeout factor of 4
    And fake "cs" language pack is installed

  @javascript
  Scenario: Customise MuTMS string in the language customisation tool
    Given I log in as "admin"
    And I navigate to "Language > Language customisation" in site administration
    And I set the field "lng" to "cs"
    And I press "Open language pack for editing"
    And I press "Continue"
    And I set the field "Show strings of these components" to "tool_mulib.php"
    And I set the field "String identifier" to "purgedownloadedlang"
    And I press "Show strings"
    And I set the field "tool_mulib/purgedownloadedlang" to "Kouzelný skřítek MuTMS"
    And I press "Save changes to the language pack"
    And I should see "There are 1 modified strings."
    And I click on "Continue" "button"
    When I visit "/admin/settings.php?section=tool_mulib_lang&lang=cs"
    Then I should see "Kouzelný skřítek MuTMS"
    # Not customised strings come from the shipped translation.
    And I should see "Překlady MuTMS"
    # Customisations are never purged.
    And I run the "tool_mulib\task\lang_cleanup" task
    And I visit "/admin/settings.php?section=tool_mulib_lang&lang=cs"
    And I should see "Kouzelný skřítek MuTMS"
    And I visit "/admin/settings.php?section=tool_mulib_lang&lang=en"
    And I should see "Use translations shipped with MuTMS"

  @javascript
  Scenario: Generated customisations are kept by the language customisation tool
    Given the following "tool_mulib > lang_customisations" exist:
      | lang | component  | stringid      | value          |
      | cs   | tool_mulib | lang_settings | Ukecaný rohlík |
    And I log in as "admin"
    And I navigate to "Language > Language customisation" in site administration
    And I set the field "lng" to "cs"
    And I press "Open language pack for editing"
    And I press "Continue"
    And I set the field "Show strings of these components" to "tool_mulib.php"
    And I set the field "String identifier" to "lang_settings"
    And I press "Show strings"
    # Checkout imports customisations from files.
    And the field "tool_mulib/lang_settings" matches value "Ukecaný rohlík"
    And I set the field "String identifier" to "purgedownloadedlang"
    And I press "Show strings"
    And I set the field "tool_mulib/purgedownloadedlang" to "Kouzelný skřítek MuTMS"
    And I press "Save changes to the language pack"
    And I click on "Continue" "button"
    When I visit "/admin/settings.php?section=tool_mulib_lang&lang=cs"
    Then I should see "Ukecaný rohlík"
    And I should see "Kouzelný skřítek MuTMS"

  @javascript
  Scenario: Customisations generated after checkout are kept by the language customisation tool
    Given I log in as "admin"
    And I navigate to "Language > Language customisation" in site administration
    And I set the field "lng" to "cs"
    And I press "Open language pack for editing"
    And I press "Continue"
    # Database records of the tool exist now, they must be updated by the generator.
    And the following "tool_mulib > lang_customisations" exist:
      | lang | component  | stringid      | value          |
      | cs   | tool_mulib | lang_settings | Ukecaný rohlík |
    And I set the field "Show strings of these components" to "tool_mulib.php"
    And I set the field "String identifier" to "purgedownloadedlang"
    And I press "Show strings"
    And I set the field "tool_mulib/purgedownloadedlang" to "Kouzelný skřítek MuTMS"
    And I press "Save changes to the language pack"
    And I click on "Continue" "button"
    When I visit "/admin/settings.php?section=tool_mulib_lang&lang=cs"
    Then I should see "Ukecaný rohlík"
    And I should see "Kouzelný skřítek MuTMS"
