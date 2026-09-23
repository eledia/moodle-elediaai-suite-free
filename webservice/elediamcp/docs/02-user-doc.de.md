# Hilfe zu Model Context Protocol

Das eLeDia MCP-Plugin verbindet freigegebene MCP-Clients und KI-Agenten mit
Moodle über einen kuratierten Tool-Katalog. Jede Anfrage läuft unter dem
angemeldeten Moodle-Nutzerkonto und respektiert die normalen Moodle-Rechte,
Einschreibungen und Sichtbarkeiten.

## Was Administratoren konfigurieren

Administratoren wählen auf der MCP-Konfigurationsseite aus, welche externen
Dienste MCP-Tokens ausstellen dürfen. Dort werden außerdem Token- und
CORS-Regeln, Anfragegrenzen und die Verfügbarkeit von Premium-MCP-Tools über
das optionale Premium-Add-on gesteuert.

## Tokens

MCP-Clients authentifizieren sich mit Moodle-Tokens. Behandeln Sie jedes Token
wie ein Passwort: Erstellen Sie ein eigenes Token pro Client, widerrufen Sie
nicht mehr benötigte Tokens und verwenden Sie bevorzugt den
`Authorization: Bearer`-Header statt Token-Werten in URLs.

## Claude Desktop

Die MCP-Seite zeigt nach der Token-Erstellung eine sofort nutzbare
Claude-Desktop-Konfiguration. Kopieren Sie das Token direkt; Moodle zeigt den
Token-Wert nur einmal an.

## Anmeldung per OAuth (optional)

Wenn die Administration den OAuth-2.1-Ablauf aktiviert, kann sich ein konformer
MCP-Client verbinden, ohne dass Sie ein Token von Hand kopieren. Der Client
leitet Sie auf eine Moodle-Anmeldeseite; nach der Anmeldung erscheint ein kurzer
Zustimmungsdialog mit dem Namen der Anwendung. Stimmen Sie zu, übergibt Moodle
dem Client ein an Ihr Konto gebundenes Token, und der Client ist verbunden.

Mit der Zustimmung entsteht ein gewöhnliches MCP-Token — Sie behalten die
Kontrolle: Öffnen Sie jederzeit **Einstellungen → MCP-Tokens**, um das Token
(mit dem Anwendungsnamen beschriftet) zu sehen und zu widerrufen; das trennt den
Client sofort. Zustimmen können nur anmeldefähige Moodle-Konten, keine Gäste.
Ist OAuth ausgeschaltet, verbinden Sie sich wie oben beschrieben mit einem
manuell erstellten Token.

## Free- und Premium-Tools

Ohne Premium-Add-on stellt MCP die freien Basis-Tools für Identität,
Kursübersicht, Kursinhalte, Ressourcen, Ankündigungen, Kalender, Aufgaben,
Bewertungen, Fortschritt, Quiz-Informationen, Kurssuche, Inhaltssuche und
Nutzererstellung bereit, sofern das Moodle-Nutzerkonto die nötigen Rechte
besitzt.

Mit dem Premium-Feature `mcp_tools` wird der vollständige MCP-Katalog
freigeschaltet, einschließlich weiterer Kommunikations-, Forum-, Abgabe-,
Kurserstellungs- und roher Moodle-Webservice-Tools, sofern die Richtlinie dies
zulässt. Premium-Schreibtools umfassen Kurserstellung, Kursaktualisierung,
manuelle Kurseinschreibung und Aktivitätserstellung (Textseite, Textfeld,
Link, Buch, Aufgabe). Sie nutzen eine Vorschau und verändern Moodle erst
beim zweiten Aufruf mit `confirm=true`.

## Workflow-Prompts

MCP-Clients können geführte Slash-Commands wie **Wochenüberblick**,
**Kurs-Health-Check**, **Bewertungssitzung**, **Kursmaterial zusammenfassen**
und **Kurs aus Dokument erstellen** anzeigen. Der Server blendet einen Prompt
aus, wenn eines seiner erforderlichen Tools für das Token nicht verfügbar ist.
Die Auswahl liefert Anweisungen an den Client; Schreibtools erfordern weiterhin
die normale ausdrückliche Vorschau-/Bestätigung. Das Dokument für die
Kurserstellung bleibt auf dem Client und wird nie an diesen Webservice gesendet.

## KI-Generierungs-Tools (eledia.ai-Suite)

Auf Instanzen mit installierter eledia.ai-Suite erscheinen automatisch zwei
weitere Premium-Tools: `moodle_generate_h5p` (benötigt `local_elediaai_h5pauthor`)
erzeugt interaktive H5P-Inhalte und veröffentlicht sie in der Inhaltsbibliothek
oder als Kursaktivität; `moodle_generate_questions` (benötigt
`local_elediaai_questiongen`) erzeugt Quizfragen in einer Fragensammlung des
Kurses. Beide Tools generieren serverseitig über den konfigurierten
KI-Anbieter der Moodle-Instanz (Website-Administration > KI) und nutzen wie die
übrigen Schreibtools den Vorschau-Ablauf mit `confirm=true`.
