@tool @tool_mulib @MuTMS @javascript
Feature: Test external database servers management
  Background:
    Given the following "categories" exist:
      | name  | category | idnumber |
      | Cat 1 | 0        | CAT1     |
      | Cat 2 | 0        | CAT2     |
      | Cat 3 | CAT2     | CAT3     |

  Scenario: Administrator may create, update and delete external database servers
    Given unnecessary Admin bookmarks block gets deleted
    And I log in as "admin"
    And I navigate to "Server > External databases > External database servers" in site administration

    When I press "Add server"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | name   | Test server 1                   |
      | dsn    | pgsql:host=127.0.0.1;dbname=edb |
      | dbuser | root                            |
      | dbpass | secret                          |
    And I click on "Add server" "button" in the "dialog[open]" "css_element"
    And I press "Add server"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | name      | Test server 2                   |
      | dsn       | pgsql:host=127.0.0.2;dbname=edb |
      | dbuser    | root                            |
      | dbpass    | secret                          |
      | dboptions | {"3":2}                         |
      | note      | Some note                       |
    And I click on "Check connection" "button" in the "dialog[open]" "css_element"
    Then I should see "Connection status" in the "dialog[open]" "css_element"
    And the following muform fields in the "dialog[open]" "css_element" match:
      | name      | Test server 2 |
      | dboptions | {"3":2}       |
    When I click on "Add server" "button" in the "dialog[open]" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | Name          | PDO DSN                         | Database user | PDO options (JSON) | Note      |
      | Test server 1 | pgsql:host=127.0.0.1;dbname=edb | root          |                    |           |
      | Test server 2 | pgsql:host=127.0.0.2;dbname=edb | root          | {"3":2}            | Some note |

    When I click on "Actions" "link_or_button" in the "Test server 2" "table_row"
    And I click on "Edit" "link" in the ".dropdown-menu.show" "css_element"
    Then the following muform fields in the "dialog[open]" "css_element" match:
      | name      | Test server 2                   |
      | dsn       | pgsql:host=127.0.0.2;dbname=edb |
      | dbuser    | root                            |
      | dboptions | {"3":2}                         |
      | note      | Some note                       |
    And I should not see "secret" in the "dialog[open]" "css_element"
    When I set the following muform fields in the "dialog[open]" "css_element":
      | name      | Test server 3                   |
      | dsn       | pgsql:host=127.0.0.3;dbname=edb |
      | dbuser    | root3                           |
      | dbpass    | secret3                         |
      | dboptions | {"3":3}                         |
      | note      | Note 3                          |
    And I click on "Check connection" "button" in the "dialog[open]" "css_element"
    Then I should see "Connection status" in the "dialog[open]" "css_element"
    And the following muform fields in the "dialog[open]" "css_element" match:
      | name | Test server 3 |
    When I click on "Update server" "button" in the "dialog[open]" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | Name          | PDO DSN                         | Database user | PDO options (JSON) | Note      |
      | Test server 1 | pgsql:host=127.0.0.1;dbname=edb | root          |                    |           |
      | Test server 3 | pgsql:host=127.0.0.3;dbname=edb | root3         | {"3":3}            | Note 3    |

    When I click on "Actions" "link_or_button" in the "Test server 3" "table_row"
    And I click on "Edit" "link" in the ".dropdown-menu.show" "css_element"
    And I set the following muform fields in the "dialog[open]" "css_element":
      | dboptions | not json |
    And I click on "Update server" "button" in the "dialog[open]" "css_element"
    Then I should see "Error" in the "dialog[open] [data-muform-name='dboptions'] .invalid-feedback" "css_element"
    When I set the following muform fields in the "dialog[open]" "css_element":
      | dboptions | |
    And I click on "Update server" "button" in the "dialog[open]" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | Name          | PDO DSN                         | Database user | PDO options (JSON) | Note      |
      | Test server 3 | pgsql:host=127.0.0.3;dbname=edb | root3         |                    | Note 3    |

    When I click on "Actions" "link_or_button" in the "Test server 3" "table_row"
    And I click on "Delete" "link" in the ".dropdown-menu.show" "css_element"
    Then I should see "Test server 3" in the "dialog[open]" "css_element"
    When I click on "Delete server" "button" in the "dialog[open]" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | Name          | PDO DSN                         | Database user | PDO options (JSON) | Note      |
      | Test server 1 | pgsql:host=127.0.0.1;dbname=edb | root          |                    |           |
    And I should not see "Test server 3"
