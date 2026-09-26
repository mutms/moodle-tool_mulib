@tool @tool_mulib @MuTMS @javascript
Feature: Test external database queries management
  Background:
    Given the following "categories" exist:
      | name  | category | idnumber |
      | Cat 1 | 0        | CAT1     |
      | Cat 2 | 0        | CAT2     |
      | Cat 3 | CAT2     | CAT3     |

  Scenario: Administrator may create, update and delete external database queries
    Given I skip tests if "tool_muprog" is not installed
    And unnecessary Admin bookmarks block gets deleted
    And the following "tool_mulib > extdb_servers" exist:
      | name          |
      | Test server 1 |
      | Test server 2 |
    And I log in as "admin"
    And I navigate to "Server > External databases > External database queries" in site administration

    When I press "Add query"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | type | Program allocation |
    And I click on "Continue" "button" in the "dialog[open]" "css_element"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | serverid | Test server 1        |
      | name     | Test query 1         |
      | sqlquery | SELECT * FROM m_user |
    And I click on "Add query" "button" in the "dialog[open]" "css_element"
    And I press "Add query"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | type | Program allocation |
    And I click on "Continue" "button" in the "dialog[open]" "css_element"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | serverid  | Test server 2          |
      | name      | Test query 2           |
      | sqlquery  | SELECT * FROM m_course |
      | contextid | Cat 1                  |
      | note      | Some note              |
    And I click on "Add query" "button" in the "dialog[open]" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | Name         | Category | External database server | SQL query              | Note      |
      | Test query 1 | System   | Test server 1            | SELECT * FROM m_user   |           |
      | Test query 2 | Cat 1    | Test server 2            | SELECT * FROM m_course | Some note |

    When I click on "Actions" "link_or_button" in the "Test query 2" "table_row"
    And I click on "Edit" "link" in the ".dropdown-menu.show" "css_element"
    And the following muform fields in the "dialog[open]" "css_element" match:
      | serverid  | Test server 2          |
      | name      | Test query 2           |
      | sqlquery  | SELECT * FROM m_course |
      | contextid | Cat 1                  |
      | note      | Some note              |
    And I set the following muform fields in the "dialog[open]" "css_element":
      | serverid  | Test server 1          |
      | name      | Test query 3           |
      | sqlquery  | SELECT * FROM m_cours3 |
      | contextid | Cat 3                  |
      | note      | Note 3                 |
    And I click on "Update query" "button" in the "dialog[open]" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | Name         | Category | External database server | SQL query              | Note      |
      | Test query 1 | System   | Test server 1            | SELECT * FROM m_user   |           |
      | Test query 3 | Cat 3    | Test server 1            | SELECT * FROM m_cours3 | Note 3    |

    When I click on "Actions" "link_or_button" in the "Test query 3" "table_row"
    And I click on "Edit" "link" in the ".dropdown-menu.show" "css_element"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | contextid | System |
    And I click on "Update query" "button" in the "dialog[open]" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | Name         | Category | External database server | SQL query              | Note      |
      | Test query 1 | System   | Test server 1            | SELECT * FROM m_user   |           |
      | Test query 3 | System   | Test server 1            | SELECT * FROM m_cours3 | Note 3    |

    When I click on "Actions" "link_or_button" in the "Test query 3" "table_row"
    And I click on "Delete" "link" in the ".dropdown-menu.show" "css_element"
    And I click on "Delete query" "button" in the "dialog[open]" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | Name         | Category | External database server | SQL query              | Note      |
      | Test query 1 | System   | Test server 1            | SELECT * FROM m_user   |           |
    And I should not see "Test query 3"

  Scenario: Site manager may review external database queries
    Given I skip tests if "tool_muprog" is not installed
    And unnecessary Admin bookmarks block gets deleted
    And the following "tool_mulib > extdb_servers" exist:
      | name          |
      | Test server 1 |
    And the following "tool_mulib > extdb_queries" exist:
      | name         | server        | component   | type       | sqlquery               |
      | Test query 1 | Test server 1 | tool_muprog | allocation | SELECT * FROM m_user   |
    And the following "users" exist:
      | username  | firstname | lastname  | email                 |
      | manager1  | Manager   | 1         | manager1@example.com  |
    And the following "roles" exist:
      | name          | shortname |
      | Extdb manager | emanager  |
    And the following "permission overrides" exist:
      | capability                     | permission | role             | contextlevel | reference |
      | moodle/site:configview         | Allow      | emanager         | System       |           |
      | tool/mulib:useextdb            | Allow      | emanager         | System       |           |
    And the following "role assigns" exist:
      | user     | role     | contextlevel | reference |
      | manager1 | emanager | System       |           |

    When I log in as "manager1"
    And I navigate to "Server > External databases > External database queries" in site administration
    Then the following should exist in the "reportbuilder-table" table:
      | Name         | Category | External database server | SQL query              | Note      |
      | Test query 1 | System   | Test server 1            | SELECT * FROM m_user   |           |
