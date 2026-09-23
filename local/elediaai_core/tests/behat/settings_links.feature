@local @local_elediaai_core
Feature: Every suite tool with a page of its own reaches its settings
  In order to configure a tool I found on the dashboard
  As an administrator
  I need the settings cog to lead to that tool's settings, not to an error.

  # Die drei sind die, die bis eben `configurl: null` hatten. Geprueft wird
  # positiv -- dass die Seite ihre eigene Ueberschrift zeigt --, denn eine
  # Pruefung auf das Ausbleiben einer Fehlermeldung geht auch bei einer
  # anderen Fehlermeldung durch.
  #
  # Je Plugin ein eigenes Szenario, jedes mit "the ... plugin is installed"
  # davor. Die Pipeline haengt nur das geprueste Plugin ein; ein Szenario, das
  # drei fremde Einstellungsseiten in einem Rutsch besucht, faellt dort mit
  # "Section error!" und sagt nichts ueber den Kern aus. Der Kernschritt
  # ueberspringt statt zu scheitern, und auf der Entwicklungsinstanz laeuft
  # weiterhin alles drei.
  Background:
    Given I log in as "admin"

  Scenario: The settings cog leads to the course tools' own settings page
    Given the "local_elediaai_tactics" plugin is installed
    When I visit "/admin/settings.php?section=local_elediaai_tactics"
    Then I should see "eLeDia.ai | Course Tools"
    And I should see "Save changes"

  Scenario: The settings cog leads to the strategy helper's own settings page
    Given the "local_elediaai_strategy" plugin is installed
    When I visit "/admin/settings.php?section=local_elediaai_strategy_settings"
    Then I should see "Save changes"

  Scenario: The settings cog leads to the learning path block's own settings page
    Given the "block_elediaai_path" plugin is installed
    When I visit "/admin/settings.php?section=blocksettingelediaai_path"
    Then I should see "Save changes"
