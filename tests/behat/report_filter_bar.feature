@tool @tool_mulib @javascript @MuTMS
Feature: Report filter bar
  In order to filter system reports easily
  As a user
  I need an always visible filter bar above the report

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             | country | suspended |
      | alpha    | Anna      | Alpha    | alpha@example.com | CZ      | 0         |
      | beta     | Bob       | Beta     | beta@example.com  | CZ      | 1         |
      | gamma    | Cyril     | Gamma    | gamma@example.com | DE      | 0         |
    And I change window size to "large"
    And I log in as "admin"

  Scenario: Text filter is added, changed, kept after reload and removed
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/report_filter_bar.php"
    When I press "+ Add filter"
    And I click on "Username" "button" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    And I set the field "Username" in the ".tool_mulib-report-filter-bar-popover" "css_element" to "alp"
    And I click on "Apply" "button" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    Then I should see "Username: alp" in the ".tool_mulib-report-filter-bar" "css_element"
    And I should see "Anna Alpha" in the "reportbuilder-table" "table"
    And I should not see "Bob Beta" in the "reportbuilder-table" "table"

    When I click on "Username: alp" "button"
    And I set the field "Username" in the ".tool_mulib-report-filter-bar-popover" "css_element" to "bet"
    And I click on "Apply" "button" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    Then I should see "Username: bet" in the ".tool_mulib-report-filter-bar" "css_element"
    And I should see "Bob Beta" in the "reportbuilder-table" "table"
    And I should not see "Anna Alpha" in the "reportbuilder-table" "table"

    When I reload the page
    Then I should see "Username: bet" in the ".tool_mulib-report-filter-bar" "css_element"
    And I should not see "Anna Alpha" in the "reportbuilder-table" "table"

    When I click on "Remove filter: Username" "button"
    Then I should not see "Username: bet"
    And I should see "Anna Alpha" in the "reportbuilder-table" "table"
    And I should see "Bob Beta" in the "reportbuilder-table" "table"

  Scenario: Select and boolean filters are combined and reset in advanced filtering
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/report_filter_bar.php"
    When I press "+ Add filter"
    And I click on "Country" "button" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    And I set the field "Search" in the ".tool_mulib-report-filter-bar-popover" "css_element" to "czech"
    And I click on "Czechia" "button" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    Then I should see "Country: Czechia" in the ".tool_mulib-report-filter-bar" "css_element"
    And I should see "Anna Alpha" in the "reportbuilder-table" "table"
    And I should see "Bob Beta" in the "reportbuilder-table" "table"
    And I should not see "Cyril Gamma" in the "reportbuilder-table" "table"

    When I press "+ Add filter"
    And I click on "Suspended" "button" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    And I click on "Yes" "button" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    Then I should see "Suspended: Yes" in the ".tool_mulib-report-filter-bar" "css_element"
    And I should see "Bob Beta" in the "reportbuilder-table" "table"
    And I should not see "Anna Alpha" in the "reportbuilder-table" "table"

    When I click on "Suspended: Yes" "button"
    And I click on "No" "button" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    Then I should see "Suspended: No" in the ".tool_mulib-report-filter-bar" "css_element"
    And I should see "Anna Alpha" in the "reportbuilder-table" "table"
    And I should not see "Bob Beta" in the "reportbuilder-table" "table"

    When I press "Advanced filtering"
    And I click on "Reset all" "button" in the "[data-region='report-filters']" "css_element"
    And I wait until the page is ready
    Then I should not see "Country: Czechia"
    And I should not see "Suspended: No"
    And I should see "Anna Alpha" in the "reportbuilder-table" "table"
    And I should see "Bob Beta" in the "reportbuilder-table" "table"
    And I should see "Cyril Gamma" in the "reportbuilder-table" "table"
    And I reload the page
    And I should see "Cyril Gamma" in the "reportbuilder-table" "table"

  Scenario: Advanced filtering form and the bar stay in sync
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/report_filter_bar.php"
    And "#dropdownFiltersButton.fw-bold" "css_element" should not exist
    When I press "Advanced filtering"
    And I set the field "Username operator" in the "Username" "core_reportbuilder > Filter" to "Starts with"
    And I set the field "Username value" in the "Username" "core_reportbuilder > Filter" to "gam"
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    And I wait until the page is ready
    Then I should see "Username: starts with gam" in the ".tool_mulib-report-filter-bar" "css_element"
    And "#dropdownFiltersButton.fw-bold" "css_element" should exist
    And I should see "Cyril Gamma" in the "reportbuilder-table" "table"
    And I should not see "Anna Alpha" in the "reportbuilder-table" "table"

    When I click on "Username: starts with gam" "button"
    Then the field "Username" in the ".tool_mulib-report-filter-bar-popover" "css_element" matches value ""
    And I set the field "Username" in the ".tool_mulib-report-filter-bar-popover" "css_element" to "lph"
    And I click on "Apply" "button" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    Then I should see "Username: lph" in the ".tool_mulib-report-filter-bar" "css_element"
    And "#dropdownFiltersButton.fw-bold" "css_element" should not exist
    And I should see "Anna Alpha" in the "reportbuilder-table" "table"
    And I should not see "Cyril Gamma" in the "reportbuilder-table" "table"

    When I press "Advanced filtering"
    Then the field "Username operator" in the "Username" "core_reportbuilder > Filter" matches value "Contains"
    And the field "Username value" in the "Username" "core_reportbuilder > Filter" matches value "lph"

  Scenario: Filters not supported by the bar are shown by name and kept
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/report_filter_bar.php"
    And I should see "Admin User" in the "reportbuilder-table" "table"
    When I press "Advanced filtering"
    And I set the field "Role name" in the "Course role" "core_reportbuilder > Filter" to "Teacher"
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    And I wait until the page is ready
    Then "#dropdownFiltersButton.fw-bold" "css_element" should exist
    And "[data-filter-key='user:courserole']" "css_element" should exist
    And I should not see "Admin User" in the ".reportbuilder-report" "css_element"

    When I click on "Course role" "button" in the ".tool_mulib-report-filter-bar" "css_element"
    Then I should see "Use advanced filtering to change this filter." in the ".tool_mulib-report-filter-bar-popover" "css_element"

    When I press "+ Add filter"
    And I click on "Username" "button" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    And I set the field "Username" in the ".tool_mulib-report-filter-bar-popover" "css_element" to "a"
    And I click on "Apply" "button" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    Then I should see "Username: a" in the ".tool_mulib-report-filter-bar" "css_element"
    And "[data-filter-key='user:courserole']" "css_element" should exist
    And I should not see "Admin User" in the ".reportbuilder-report" "css_element"
    And I reload the page
    And "[data-filter-key='user:courserole']" "css_element" should exist
    And "#dropdownFiltersButton.fw-bold" "css_element" should exist
    And I should not see "Admin User" in the ".reportbuilder-report" "css_element"

    When I click on "Remove filter: Course role" "button"
    Then I should see "Admin User" in the "reportbuilder-table" "table"
    And I should see "Username: a" in the ".tool_mulib-report-filter-bar" "css_element"
    And "#dropdownFiltersButton.fw-bold" "css_element" should not exist
    And I reload the page
    And I should not see "Course role" in the ".tool_mulib-report-filter-bar" "css_element"

  Scenario: Relative date filter is added, changed and synced with advanced filtering
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/report_filter_bar.php"
    When I press "+ Add filter"
    And I click on "Last access" "button" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    And I set the field "Last access operator" in the ".tool_mulib-report-filter-bar-popover" "css_element" to "Is empty"
    And I click on "Apply" "button" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    Then I should see "Last access: is empty" in the ".tool_mulib-report-filter-bar" "css_element"
    And "#dropdownFiltersButton.fw-bold" "css_element" should not exist
    And I should see "Anna Alpha" in the "reportbuilder-table" "table"
    And I should not see "Admin User" in the "reportbuilder-table" "table"

    When I click on "Last access: is empty" "button"
    And I set the field "Last access operator" in the ".tool_mulib-report-filter-bar-popover" "css_element" to "Last"
    And I set the field "Last access value" in the ".tool_mulib-report-filter-bar-popover" "css_element" to "2"
    And I set the field "Last access unit" in the ".tool_mulib-report-filter-bar-popover" "css_element" to "week(s)"
    And I click on "Apply" "button" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    Then I should see "Last access: last 2 weeks" in the ".tool_mulib-report-filter-bar" "css_element"
    And I should see "Admin User" in the "reportbuilder-table" "table"
    And I should not see "Anna Alpha" in the "reportbuilder-table" "table"

    When I press "Advanced filtering"
    Then the field "Last access operator" in the "Last access" "core_reportbuilder > Filter" matches value "Last"
    And the field "Last access value" in the "Last access" "core_reportbuilder > Filter" matches value "2"
    And the field "Last access unit" in the "Last access" "core_reportbuilder > Filter" matches value "week(s)"
    And I set the field "Last access operator" in the "Last access" "core_reportbuilder > Filter" to "Current"
    And I set the field "Last access unit" in the "Last access" "core_reportbuilder > Filter" to "year(s)"
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    And I wait until the page is ready
    Then I should see "Last access: current year" in the ".tool_mulib-report-filter-bar" "css_element"
    And "#dropdownFiltersButton.fw-bold" "css_element" should not exist
    And I should see "Admin User" in the "reportbuilder-table" "table"

    When I click on "Last access: current year" "button"
    And I set the field "Last access operator" in the ".tool_mulib-report-filter-bar-popover" "css_element" to "Last"
    And I set the field "Last access value" in the ".tool_mulib-report-filter-bar-popover" "css_element" to "0"
    And I click on "Apply" "button" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    Then ".tool_mulib-report-filter-bar-popover input.is-invalid" "css_element" should exist
    And I should see "Last access: current year" in the ".tool_mulib-report-filter-bar" "css_element"

    When I click on "Remove filter: Last access" "button"
    Then I should see "Anna Alpha" in the "reportbuilder-table" "table"
    And I should see "Admin User" in the "reportbuilder-table" "table"
    And I reload the page
    And I should not see "Last access" in the ".tool_mulib-report-filter-bar" "css_element"

  Scenario: Date range filter is typed and picked in user timezone
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/report_filter_bar.php"
    When I press "+ Add filter"
    And I click on "Last access" "button" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    And the field "Last access operator" in the ".tool_mulib-report-filter-bar-popover" "css_element" matches value "Range"
    And I set the field "Date from" in the ".tool_mulib-report-filter-bar-popover" "css_element" to "xyz"
    And I click on "Apply" "button" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    Then I should see "Invalid date and time" in the ".tool_mulib-report-filter-bar-popover" "css_element"

    When I set the field "Date from" in the ".tool_mulib-report-filter-bar-popover" "css_element" to "2020-01-01"
    And I click on "Apply" "button" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    Then I should see "Last access: from 2020-01-01 00:00" in the ".tool_mulib-report-filter-bar" "css_element"
    And I should see "Admin User" in the "reportbuilder-table" "table"
    And I should not see "Anna Alpha" in the "reportbuilder-table" "table"

    When I click on "Last access: from 2020-01-01 00:00" "button"
    Then the field "Date from" in the ".tool_mulib-report-filter-bar-popover" "css_element" matches value "2020-01-01 00:00"
    And I set the field "Date to" in the ".tool_mulib-report-filter-bar-popover" "css_element" to "2019-12-31"
    And I click on "Apply" "button" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    And I should see "Invalid date and time range" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    And I set the field "Date to" in the ".tool_mulib-report-filter-bar-popover" "css_element" to "2020-12-31"
    And I click on "Apply" "button" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    Then I should see "Last access: 2020-01-01 00:00 – 2020-12-31 23:59" in the ".tool_mulib-report-filter-bar" "css_element"
    And I should not see "Admin User" in the ".reportbuilder-report" "css_element"
    And I reload the page
    And I should see "Last access: 2020-01-01 00:00 – 2020-12-31 23:59" in the ".tool_mulib-report-filter-bar" "css_element"

    When I click on "Last access: 2020-01-01 00:00 – 2020-12-31 23:59" "button"
    And I set the field "Date from" in the ".tool_mulib-report-filter-bar-popover" "css_element" to ""
    And I set the field "Date to" in the ".tool_mulib-report-filter-bar-popover" "css_element" to ""
    And I click on "Choose date and time" "button" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    And I click on "Today" "button" in the ".muform-datetime-panel" "css_element"
    And I click on "Apply" "button" in the ".muform-datetime-panel" "css_element"
    And I click on "Apply" "button" in the ".tool_mulib-report-filter-bar-popover form" "css_element"
    Then I should see "Last access: from" in the ".tool_mulib-report-filter-bar" "css_element"
    And I should see "Admin User" in the "reportbuilder-table" "table"
    And I should not see "Anna Alpha" in the "reportbuilder-table" "table"

    When I click on "Last access: from" "button"
    And I set the field "Date from" in the ".tool_mulib-report-filter-bar-popover" "css_element" to ""
    And I click on "Apply" "button" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    Then I should not see "Last access" in the ".tool_mulib-report-filter-bar" "css_element"
    And I should see "Anna Alpha" in the "reportbuilder-table" "table"

  Scenario: Course role filter is shown by name and reset in advanced filtering
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/report_filter_bar.php"
    When I press "Advanced filtering"
    And I set the field "Role name" in the "Course role" "core_reportbuilder > Filter" to "Teacher"
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    And I wait until the page is ready
    Then I should see "Course role" in the "[data-filter-key='user:courserole']" "css_element"
    And I should not see "Anna Alpha"
    And I should not see "-124567"
    When I press "Advanced filtering"
    And I click on "Reset all" "button" in the "[data-region='report-filters']" "css_element"
    And I wait until the page is ready
    Then I should not see "Course role" in the ".tool_mulib-report-filter-bar" "css_element"
    And I should see "Anna Alpha" in the "reportbuilder-table" "table"
