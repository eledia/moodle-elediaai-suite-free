@webservice @webservice_elediamcp
Feature: MCP web service protocol is available
  In order to use the MCP web service
  As an admin
  I need the MCP protocol to be listed in the web service protocols

  @javascript
  Scenario: MCP protocol appears in web service protocol settings
    # The "Web services" branch of the admin tree only appears once web services
    # are switched on; without this the navigation step cannot find the link.
    Given the following config values are set as admin:
      | enablewebservices | 1 |
    And I log in as "admin"
    When I navigate to "Server > Web services > Manage protocols" in site administration
    Then I should see "Model Context Protocol"
