@tool @tool_mulib @javascript @MuTMS
Feature: Report pager
  In order to know how many records a system report has
  As a user
  I need a pager with the number of records, page size and page numbers below the report

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | person01 | Person    | N01     | person01@example.com |
      | person02 | Person    | N02     | person02@example.com |
      | person03 | Person    | N03     | person03@example.com |
      | person04 | Person    | N04     | person04@example.com |
      | person05 | Person    | N05     | person05@example.com |
      | person06 | Person    | N06     | person06@example.com |
      | person07 | Person    | N07     | person07@example.com |
      | person08 | Person    | N08     | person08@example.com |
      | person09 | Person    | N09     | person09@example.com |
      | person10 | Person    | N10     | person10@example.com |
      | person11 | Person    | N11     | person11@example.com |
      | person12 | Person    | N12     | person12@example.com |
      | person13 | Person    | N13     | person13@example.com |
      | person14 | Person    | N14     | person14@example.com |
      | person15 | Person    | N15     | person15@example.com |
      | person16 | Person    | N16     | person16@example.com |
      | person17 | Person    | N17     | person17@example.com |
      | person18 | Person    | N18     | person18@example.com |
      | person19 | Person    | N19     | person19@example.com |
      | person20 | Person    | N20     | person20@example.com |
      | person21 | Person    | N21     | person21@example.com |
      | person22 | Person    | N22     | person22@example.com |
      | person23 | Person    | N23     | person23@example.com |
      | person24 | Person    | N24     | person24@example.com |
      | person25 | Person    | N25     | person25@example.com |
      | person26 | Person    | N26     | person26@example.com |
      | person27 | Person    | N27     | person27@example.com |
      | person28 | Person    | N28     | person28@example.com |
      | person29 | Person    | N29     | person29@example.com |
      | person30 | Person    | N30     | person30@example.com |
      | person31 | Person    | N31     | person31@example.com |
      | person32 | Person    | N32     | person32@example.com |
      | person33 | Person    | N33     | person33@example.com |
      | person34 | Person    | N34     | person34@example.com |
    And I change window size to "large"
    And I log in as "admin"

  Scenario: Pages and page size of a report are changed in the pager
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/report_pager.php"
    Then I should see "Showing 1–30 of 35" in the ".tool_mulib-report-pager" "css_element"
    And I should not see "matching filters" in the ".tool_mulib-report-pager" "css_element"
    And the field "Per page" matches value "30 per page"
    And ".reportbuilder-report nav.pagination" "css_element" should not be visible
    And I should not see "Show all" in the ".reportbuilder-report" "css_element"
    And I should see "Person N01" in the "reportbuilder-table" "table"
    And I should not see "Person N30" in the "reportbuilder-table" "table"

    When I click on "Next" "button" in the ".tool_mulib-report-pager" "css_element"
    Then I should see "Showing 31–35 of 35" in the ".tool_mulib-report-pager" "css_element"
    And I should see "Person N30" in the "reportbuilder-table" "table"
    And I should not see "Person N01" in the "reportbuilder-table" "table"

    When I set the field "Per page" to "10 per page"
    Then I should see "Showing 1–10 of 35" in the ".tool_mulib-report-pager" "css_element"
    And I should see "Person N09" in the "reportbuilder-table" "table"
    And I should not see "Person N10" in the "reportbuilder-table" "table"

    When I click on "4" "button" in the ".tool_mulib-report-pager" "css_element"
    Then I should see "Showing 31–35 of 35" in the ".tool_mulib-report-pager" "css_element"
    And I click on "Previous" "button" in the ".tool_mulib-report-pager" "css_element"
    And I should see "Showing 21–30 of 35" in the ".tool_mulib-report-pager" "css_element"
    And I should see "Person N20" in the "reportbuilder-table" "table"

    # All pages are downloaded.
    And ".reportbuilder-report .dataformatselector" "css_element" should not be visible
    And I should not see "Download table data as"
    When I click on "Download all" "button" in the ".tool_mulib-report-pager" "css_element"
    Then I should see "Microsoft Excel (.xlsx)" in the ".tool_mulib-report-pager" "css_element"
    And following "Comma separated values (.csv)" should download between "1300" and "3000" bytes

    # Page size is remembered in the browser tab, the page number is not.
    When I reload the page
    Then I should see "Showing 1–10 of 35" in the ".tool_mulib-report-pager" "css_element"
    And the field "Per page" matches value "10 per page"
    And I should not see "Person N10" in the "reportbuilder-table" "table"

  Scenario: Number of records is shown when all of them fit one page
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/report_pager.php"
    When I set the field "Per page" to "50 per page"
    Then I should see "Showing 1–35 of 35" in the ".tool_mulib-report-pager" "css_element"
    And ".tool_mulib-report-pager nav" "css_element" should not exist
    And the field "Per page" matches value "50 per page"
    And I should see "Person N34" in the "reportbuilder-table" "table"

  Scenario: Pager tells that records are filtered
    Given I am on fixture page "/admin/tool/mulib/tests/behat/fixtures/report_pager.php"
    When I press "+ Add filter"
    And I click on "Username" "button" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    And I set the field "Username" in the ".tool_mulib-report-filter-bar-popover" "css_element" to "person0"
    And I click on "Apply" "button" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    Then I should see "Showing 1–9 of 9 matching filters" in the ".tool_mulib-report-pager" "css_element"
    And ".tool_mulib-report-pager nav" "css_element" should not exist
    And "Download all" "button" should not exist
    And I click on "Download filtered" "button" in the ".tool_mulib-report-pager" "css_element"
    And following "Comma separated values (.csv)" should download between "300" and "1200" bytes

    When I reload the page
    Then I should see "Showing 1–9 of 9 matching filters" in the ".tool_mulib-report-pager" "css_element"

    When I click on "Username: person0" "button"
    And I set the field "Username" in the ".tool_mulib-report-filter-bar-popover" "css_element" to "nobody"
    And I click on "Apply" "button" in the ".tool_mulib-report-filter-bar-popover" "css_element"
    Then ".tool_mulib-report-pager" "css_element" should not be visible

    When I click on "Remove filter: Username" "button"
    Then I should see "Showing 1–30 of 35" in the ".tool_mulib-report-pager" "css_element"
    And "Download all" "button" should exist in the ".tool_mulib-report-pager" "css_element"
    And "Download filtered" "button" should not exist
    And I should not see "matching filters" in the ".tool_mulib-report-pager" "css_element"
