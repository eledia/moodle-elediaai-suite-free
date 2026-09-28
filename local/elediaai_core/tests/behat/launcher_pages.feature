@local @local_elediaai_core
Feature: AI Suite Plugin Shell pages
  In order to find and inspect AI features
  As a user with the right capability
  I need the AI Suite dashboard, feature info and audit pages to render
  inside the AI Suite Core Plugin Shell.

  Background:
    Given the following "users" exist:
      | username   | firstname | lastname |
      | regular    | Regular   | User     |

  Scenario: Admin sees the AI Suite dashboard with feature cards
    Given I log in as "admin"
    When I open the AI Suite
    Then I should see "AI Suite"
    And I should see "Overview"
    And I should see "Audit"
    # Nur Kacheln aus dem freien Paket: Frueher standen hier "Questions",
    # "Translate" und "AI Feedback" -- Premium-Werkzeuge, mit denen das freie
    # Paket rot wurde, obwohl ihm nichts fehlte. Die vier decken weiterhin
    # mehrere Plugintypen ab (block, local, webservice).
    And I should see "AI Tutor" in the "article[data-feature-id=tutor]" "css_element"
    And I should see "LiteRAG" in the "article[data-feature-id=literag]" "css_element"
    And I should see "Model Context Protocol" in the "article[data-feature-id=mcp]" "css_element"
    And I should see "AI Sources" in the "article[data-feature-id=sources]" "css_element"
    And "body.pagelayout-report" "css_element" should exist
    And ".elediaai-core-shell--wide" "css_element" should exist

  # Bis hierher pruefte diese Datei eine Erklaerseite im Kern, die es nicht
  # mehr gibt: was ein Werkzeug ist, schreibt jetzt das Plugin selbst ins
  # Handbuch. Geprueft wird deshalb, was an ihre Stelle getreten ist -- der
  # Status auf der Kachel und wohin die Kachel fuehrt.

  Scenario: A tool with a page of its own is opened by its card
    Given I log in as "admin"
    When I open the AI Suite
    And I click on "Model Context Protocol" "link" in the "article[data-feature-id=mcp]" "css_element"
    Then I should see "MCP configuration"

  # Das Kapitel gehoert dem Wegweiser, und der ist eine weiche Abhaengigkeit:
  # ohne ihn fuehrt die Kachel auf die Uebersicht statt ins Leere (siehe
  # feature_grid::handbook_url). Die Pipeline haengt ihn nicht ein.
  # AI Feedback gehoert nicht zum freien Paket; ohne die Zeile fiele das
  # Szenario dort, statt uebersprungen zu werden.
  Scenario: A course capability leads to its chapter in the handbook
    Given the "local_elediaai_guide" plugin is installed
    And the "mod_aifeedback" plugin is installed
    And I log in as "admin"
    When I open the AI Suite
    And I click on "AI Feedback" "link" in the "article[data-feature-id=aifeedback]" "css_element"
    Then I should see "Feedback you release"

  # Vier Flaechen, vier Namen. Sie stehen hier wortgleich, weil sie auf der
  # Seite dreimal auftauchen -- im Reiter, auf der Karte und im Seitentitel --
  # und weil genau das die Zusage ist: ein Ziel, ein Name.
  Scenario: Admin sees the audit overview and the four surfaces it leads to
    Given I log in as "admin"
    When I open the AI Suite audit
    Then I should see "AI audit"
    And I should see "Overview"
    And I should see "What learners asked"
    And I should see "What the AI did"
    And I should see "Every AI request"
    And I should see "Access and retention"

  Scenario: Regular user does not see the audit section tab
    Given I log in as "regular"
    When I open the AI Suite
    Then I should see "AI Suite"
    And I should see "Overview"
    And "Audit" "link" should not exist in the ".lh-plugin-section-nav" "css_element"
    And "[data-region=suite-components]" "css_element" should not exist

  Scenario: Admin sees every installed suite component with version and status
    Given I log in as "admin"
    When I open the AI Suite
    Then I should see "Installed components"
    And I should see "local_elediaai_core" in the "[data-region=suite-components]" "css_element"
    And I should see "local_elediaai_chatengine" in the "[data-region=suite-components]" "css_element"
    And "[data-component-status]" "css_element" should exist in the "[data-region=suite-components]" "css_element"
