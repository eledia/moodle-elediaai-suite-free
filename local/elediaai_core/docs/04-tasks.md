# local_elediaai_core — Tasks

Operative Liste. Implementierte Tasks landen in der `Completed`-Sektion am Ende; offene Punkte oben sortiert nach Priorität.

---

## Offene Tasks

### task22 — Token nach Core umziehen, Rückfälle entfernen (adr05 §1–2)

**Status:** erledigt (05.09.2026).

Umgesetzt: Token-Schicht in `local_elediaai_core`, das Theme überschreibt nur
noch die drei einstellbaren Töne und die Schrift, die es ausliefert. 288
Rückfallwerte entfallen. Alle 22 Suite-Plugins deklarieren die Abhängigkeit.
96 Schriftgrößen aus fünf Plugins auf die Skala; `pdf_exporter.php` und
`worksheet_design.php` bleiben bewusst in pt/px (Druck).

Drei Funde, die den Umzug erst wirksam machten und für task23 wichtig sind:

- **Zehn Plugins laden ihre eigene `styles.css` per `$PAGE->requires->css()`
  nach.** Auf ihren Seiten steht Cores Vorgabe damit hinter dem Theme. Das
  Theme schreibt seine Überschreibungen deshalb auf `:root:root`.
- **CSS aus PHP und aus Mustache-Vorlagen** (`feature_grid.php`,
  `feature_info.mustache`) — kein Lint sieht es, und als Seiten-`<style>` steht
  es hinter jedem Stylesheet. Die Lint-Prüfung aus task23 muss `.php` und
  `.mustache` mitlesen, nicht nur `.css`.
- **Bausteine der geteilten Shell waren pfad-gescoped** (`.path-local-
  elediaai_core .lh-plugin-card__title`) und griffen auf Seiten anderer
  Plugins nicht.

- `--eai-*` und `--font-size-*` aus `theme/elediaai/scss/elediaai/_tokens.scss`
  als `:root`-Vorgaben nach `local_elediaai_core/styles.css`; das Theme
  überschreibt nur noch Werte.
- In Theme, Chat-Engine, Tutor-Block und Quellen die Rückfallwerte aus
  `var(--font-size-*, …)` entfernen — eine Quelle.
- Die fünf Plugins mit 76 freien Schriftgrößen (`core`, `strategy`,
  `teachertools`, `literag`, `elediamcp`) auf die Skala bringen; Vorgehen wie
  im Typografie-Audit vom 2026-09-04: Rolle je Stelle, nicht Zahl je Stelle.
- Fehlende Abhängigkeit auf `local_elediaai_core` ergänzen: `sources`,
  `literag`, `subscription`, `elediamcp`, `activityfilter`, `aitransparency`.
  **Entschieden (PO, 2026-09-04):** alle Suite-Plugins hängen an Core, ohne
  Ausnahme. Damit entfallen Rückfallwerte überall — es gibt keine
  Installation mehr, in der die Token fehlen könnten. Ausgeräumt wurden sie
  am 05.09.2026 in task27: 301 Stellen, nachweislich ohne jede Wirkung.
- Nachweis: Messlauf auf Boost **und** `theme_elediaai` — Plugin-Flächen
  identisch bis auf den Rahmen.

### task23 — CI-Prüfung für das Design-System (adr05 §5b–c)

**Status:** erledigt (05.09.2026).

**Erledigt — der Lint.** `ci/design-lint.php` prüft die vier Regeln (rohe
`font-size`, rohe Hex-Farbe in Wertstellung, `border-radius` außerhalb der
Token, `!important` ohne Kommentar darüber) und läuft als Job `design
<plugin>` in der Stage `lint`. Dokumentiert in `ci/README.md`.

Drei Entscheidungen, die vom Aufschrieb abweichen:

- **Eigenes Skript statt Stylelint.** Stylelint liest Stylesheets. Die Suite
  gibt CSS aber auch aus PHP aus (`feature_grid.php`) und aus
  `<style>`-Blöcken in Mustache-Vorlagen — genau dort standen die meisten
  freien Werte. Das Skript liest `.css`, `.scss`, `.php` und `.mustache`.
- **Altbestand statt sofortiger roter Karte.** Der erste Lauf fand 942
  Fundstellen in 27 Dateien. Als hartes Gate hätte der Lint jeden Merge
  Request der Suite blockiert, auch die, die damit nichts zu tun haben.
  `ci/design-lint-baseline.txt` führt die bekannten Zahlen je Datei und
  Regel; rot wird nur, was **dazukommt**. Die Zahlen dürfen fallen, und der
  Lauf sagt es, wenn sie es tun. Der Altbestand ist damit eine Messgröße für
  die Farbrunde in `strategy`, `teachertools`, `literag`, `elediamcp`.
- **Dokumentiert in `ci/README.md`, nicht in `ci-cd.md`.** `ci-cd.md`
  beschreibt die Forgejo-Pipeline des Ursprungs-Repos; der Lint läuft in der
  GitLab-Pipeline der Suite. In `ci-cd.md` steht jetzt ein Zeiger.

Verteilung des Altbestands (05.09.2026): `color` 676, `radius` 105,
`important` 93, `font-size` 68. Die dicksten Brocken sind
`blocks/elediaai_tutor/styles.css` (150), `filter/eledia_translate/styles.css`
(143), `webservice/elediamcp/styles.css` (68).

**Erledigt — der Messlauf.** `ci/design-measure.mjs` misst sechs Seiten im
Browser: Schriftgrößen je Fläche gegen die Skala, Öffnungshöhe der
Leisten-Menüs, waagerechter Überlauf bei 390px. `--vergleich` stellt zwei
Läufe gegenüber und **benennt** die Flächen, die sich unterscheiden — eine
blosse Zahl liesse offen, ob es der Rahmen war oder die Fläche. Dokumentiert
in `ci/README.md`, Abschnitt „Messlauf gegen zwei Themes".

Er läuft von Hand, nicht in der CI: er braucht eine laufende Moodle-Instanz
mit Anmeldung und zwei Themes, und die Suite-Pipeline installiert je Job nur
das geprüfte Plugin. Der Lint deckt den täglichen Fall ab; der Messlauf gehört
vor ein Release und nach jeder Arbeit am Theme.

Ergebnis vom 05.09.2026 (Moodle 5.2, sechs Seiten):

- **Skala:** alle gemessenen Suite-Flächen liegen auf der Skala, unter beiden
  Themes. Gefunden hat der Lauf `local_elediaai_tactics` mit 21 von 28 Flächen
  daneben — gleich unter Boost und unter dem Theme, also im Plugin und nicht
  im Rahmen. In diesem Zug behoben: Rolle je Stelle, nicht Zahl je Stelle
  (Abschnittstitel h3, Kartentitel h4, gedämpfter Kartentext small,
  Zustandsmarke caption). Der Altbestand des Lints fiel dadurch von 942 auf
  927. Ein Schliesskreuz behält seine 1.5rem mit Begründung — ein Zeichen ist
  keine Überschrift.
- **Menüs:** unter `theme_elediaai` öffnen alle vier Leisten-Menüs auf
  derselben Höhe. Unter Boost nicht (60px gegen 62px). Die gleichen Abstände,
  um die es im September ging, sind eine Eigenschaft unseres Themes.
- **Überlauf bei 390px:** keiner, auf keiner Seite, unter keinem Theme.
- **Boost gegen `theme_elediaai`:** die Plugin-Flächen sind gleich. Jeder
  Unterschied ist der Rahmen — `.eai-coursehead__*` ist die Dashboard-Kopfzeile
  des Themes, `.lh-ai-launcher__title`/`__subtitle` gehören zum Launcher-Wirt
  aus Core, den das Theme ausblendet, weil es einen eigenen Launcher zeichnet.
  Damit steht die Zusage aus adr05 gemessen da und nicht nur behauptet.

Zwei Messfehler, die der Lauf zuerst selbst produziert hat und die im Skript
begründet stehen: die Menüabstände wurden vom geklickten Element gemessen
statt vom äusseren Bedienelement (Knopfhöhen, als Menüabstände ausgegeben),
und der Überlauf bei 390px wurde ohne Neuladen gemessen — Moodles Leiste
rechnet erst beim Laden aus, was ins „Mehr"-Menü wandert.

### task24 — Entwicklerseite in Core (feat14, adr05 §6)

**Status:** erledigt (05.09.2026).

- Setting `local_elediaai_core/developerdocs` (checkbox, Vorgabe 0);
  Seite `developer.php` in der Suite-Shell, `require_capability
  ('moodle/site:config')`; 404-gleich, wenn das Setting aus ist.
- Reihenfolge des Nutzens: Umgebung → Token aufgelöst mit Herkunft →
  Skalen gerendert → Bausteine live → Vertrag aus `03-dev-doc.md`.
- Wegweiser: Eintrag „Entwicklung" nur bei aktivem Setting. `topic` trägt
  dafür `configflag` (`plugin/name`), geprüft in `guide_service::may_read`.

  **Entschieden (PO, 05.09.2026):** von drei Wegen — Core liest `get_config`
  selbst, der Wegweiser lernt die Bedingung, oder gar kein Riegel am Eintrag —
  wurde der zweite gewählt. Der Datenbankzugriff landet dort, wo der Wegweiser
  ohnehin welchen macht, die Registry bleibt sauber, und jedes spätere Plugin
  kann dasselbe.

  **Kein Provider in Core.** Der erste Entwurf hätte das Kapitel über
  `\local_elediaai_core\elediaai_guide\guide_provider` geliefert. Ein
  Provider **ersetzt** aber die mitgelieferten Kapitel seiner Komponente
  vollständig (`03-dev-doc.md` des Wegweisers, „Kein Mischen") — Core hätte
  damit seine gesamte Nutzerhilfe gelöscht. Das Kapitel steht deshalb als
  `suite_developer` in `bundled.php`, neben den anderen Kapiteln über Core,
  und der Riegel sitzt am `configflag`. `guide_provider` bleibt damit
  weiterhin ungenutzt.
- Abschnitt „Design-System" in `03-dev-doc.md`: geschrieben. Die Token, die
  Skalen, was ein Plugin darf und was nicht, und wer es nachhält. Die
  Entwicklerseite rendert genau diesen Abschnitt — eine Quelle, zwei Ausgaben.
  Das Theme-README bleibt, wo es ist: es beschreibt das Theme (Farben,
  Navigation, Dashboard), nicht den Vertrag.

**Zwei Funde beim Bauen, beide im Code vermerkt:**

- Seit CSS-Nesting trägt **jede** `CSSStyleRule` eine (meist leere)
  `cssRules`-Liste. Der übliche Griff `if (rule.cssRules) { rekursiv; continue; }`
  hält damit jede gewöhnliche Regel für einen Gruppenblock und findet gar
  nichts. Die Herkunftsbestimmung lief deshalb zuerst leer.
- Der **erste** Seitenaufruf nach `purge_caches.php` führt kein AMD-Modul aus;
  ab dem zweiten läuft alles. Das betrifft jedes Modul der Website
  gleichermaßen und ist kein Fehler dieser Seite — kostet beim Prüfen aber
  Zeit, wenn man es nicht weiß.

### task25 — `help.php` überdenken: ein Hilfesystem (adr05 §7)

**Status:** erster Schritt erledigt (06.09.2026), die Zusammenführung offen.

**Eine Korrektur an diesem Aufschrieb.** Hier stand, Core rendere „rohe
Entwicklerdokumentation im Nutzerrahmen". Das stimmt so nicht: alle **sechs**
Hilfeseiten (Core, Tutor, Quellen, elediamcp, literag, eledia_translate)
rendern `docs/02-user-doc.md` — also die *Nutzer*dokumentation. Der
Widerspruch ist ein anderer und feiner: es sind **Repository-Dokumente**, für
das Team geschrieben, und sie tragen zwei Dinge mit sich, die auf einer
Hilfeseite nichts verloren haben.

**Erledigt — die beiden konkreten Verstöße.** `output/help_document` bereitet
das Dokument beim Rendern auf, nicht in der Datei:

- Der **Meta-Block** am Anfang („Quelle der Wahrheit für sichtbares
  Verhalten") ist eine Aussage über ein Dokument an Menschen, die kein
  Dokument sehen. Er fällt — ein `## Meta` weiter unten dagegen ist eine
  Überschrift wie jede andere und bleibt.
- **Verweise auf Nachbardokumente** (`00-master.md`, `01-features.md`,
  `03-dev-doc.md`) sind Sackgassen: die Dateien liegen im Repository, nicht
  auf der Website. Ein Einschub in Klammern fällt für sich; ein ganzer Satz,
  der auf ein Dokument zeigt, fällt als Satz — halb stehen wäre schlimmer als
  der Verweis.

Alle sechs Hilfeseiten benutzen den Aufbereiter. Nachgemessen auf `/523`: in
Core, Tutor und literag kein Meta-Block und null Dateiverweise mehr.

**Schritt 1 ist erledigt — von anderer Seite.** Hier stand, der Wegweiser
kenne nur zwei der sechs Komponenten und eine Weiterleitung ginge für die
anderen vier ins Leere. Das gilt seit `97ca451` / `248a9ee` (06.09.2026,
`feature/plugins-erklaeren-sich`) nicht mehr: die Kolleginnen und Kollegen
haben in derselben Woche jedem Plugin einen `guide_provider` gegeben.
Nachgezählt am 06.09.2026 — alle sechs `help.php`-Komponenten haben jetzt
Handbuchdeckung:

| Komponente | Deckung |
|---|---|
| `local_elediaai_core` | `bundled.php` |
| `block_elediaai_tutor` | `bundled.php` |
| `local_elediaai_sources` | `guide_provider` |
| `local_literag` | `guide_provider` |
| `filter_eledia_translate` | `guide_provider` (neu) |
| `webservice_elediamcp` | `guide_provider` |

Wer hier weiterarbeitet, schreibt diese Kapitel also **nicht** noch einmal.

**Offen — und die Frage ist dadurch schärfer geworden.** Die neuen Kapitel
sind je *eines* pro Plugin: Titel, Zusammenfassung, ein Fließtext. Die
`help.php`-Dokumente sind Langfassungen — Cores `02-user-doc.md` hat 312
Zeilen. Der Verlust, der vorher nur für Core und Tutor galt, gilt jetzt für
alle sechs: **wer weiterleitet, nimmt Lesenden den Rest weg.**

Damit ist die Reihenfolge nicht mehr „erst Kapitel, dann entscheiden",
sondern nur noch die Entscheidung:

1. Entweder das Handbuchkapitel wird je Plugin zur Langfassung ausgebaut und
   `help.php` leitet dorthin weiter — dann gibt es ein Hilfesystem, aber
   jemand muss sechs Texte schreiben.
2. Oder `help.php` bleibt als Langfassung bestehen und das Handbuchkapitel
   verlinkt sie — dann bleiben es zwei Orte, aber mit klarer Rollenteilung
   (Kapitel = Einstieg, Hilfeseite = Nachschlagewerk).

Beides ist vertretbar; „zwei Hilfesysteme ohne Absprache" ist es nicht. Die
Wahl gehört den Menschen, denen die Texte gehören — nicht dieser Task.
- Vorschlag: der Hilfe-Slot der Shell (`plugin_shell::action_slots`,
  `section_nav::SECTION_HELP`) führt zum Handbuch des Wegweisers, wenn er
  installiert ist; `help.php` wird zur Weiterleitung dorthin oder entfällt.
  Entwicklerinhalte gehen auf die Seite aus task24.
- Je Plugin entscheiden: was in `help.php` steht, ist entweder Nutzerhilfe
  (→ Wegweiser, `bundled.php` oder `guide_provider`, Wegweiser adr02) oder
  Entwicklerhilfe (→ task24). Nichts davon bleibt in `help.php`.

### task26 — Tutor-Vorgaben aus den Suite-Token ableiten (adr05 §3)

**Status:** erledigt (05.09.2026).

23 Vorgaben der Chat-Engine lesen `--eai-*`. Gemessen nach dem Löschen aller
43 Farbeinstellungen: `--eac-accent` `#eb7306`, `--eac-brand` `#f98012`,
Senden-Knopf `rgb(235,115,6)`. Nicht abgeleitet: Status, Fehler, Fundierung,
Backdrop, Overlays — eigene Achsen, die keine Markenfarben werden sollen.

- Die `:root`-Vorgaben der Chat-Engine (`--eac-accent: #1e3f59` und die
  übrigen in `local/elediaai_chatengine/styles.css`) auf
  `var(--eai-…)` umstellen, sodass ein unkonfigurierter Tutor die Suite-Farben
  trägt. Konfigurierte Instanzen bleiben unberührt — Inline-Style gewinnt.
- `--eac-bubble-font-size` u. a. lesen bereits `--font-size-*`; dasselbe für
  Farben, Radius, Schatten.
- Nachweis: frische Installation ohne Theme und ohne Tutor-Konfiguration —
  Hero und Kurs-Panel in Suite-Farben.

### task30 — Die Radien auf die Skala (adr05 §2)

**Status:** erledigt (06.09.2026).

102 rohe Radien, aber nur 23 verschiedene Werte — und die Verteilung sagt
etwas über das Vokabular:

| Wert | Anzahl | Verhältnis zur Skala |
|---|---|---|
| `999px` | 38 | **wertgleich** mit `--eai-radius-pill` |
| `8px` | 22 | gegen `--eai-radius` (10px) |
| Rest | 42 | 21 Werte, meist ein- bis viermal benutzt |

**Eine vierte Stufe, aus Befund und nicht aus Geschmack.** Die Häufungen lagen
bei 4, 10, 18 und der Pille. Eine kleine Stufe fehlte im *Vokabular*, nicht in
der Praxis — 16 Fundstellen lagen zwischen 3 und 6,4 px. Sie heißt jetzt
`--eai-radius-sm` (0.25rem) und ist für das, was in einer Karte liegt: Marken,
Code-Blöcke, Symbolknöpfe.

Abgebildet wurde in drei Bändern: bis 6,5 px auf `sm`, 8 bis 14 px auf
`--eai-radius`, ab 20 px auf `--eai-radius-lg`. Der Schieber der Quellen
(`1.5rem` auf einem Gleis in voller Höhe) bekam die Pille — das ist seine
Form, keine Kante.

**Zwei Bereiche bleiben ausgenommen, einer davon härter als bisher:**

- **Das Tutor-Widget und `mod_elli`** (17 Radien). `--eac-radius` und
  `--eac-bubble-radius` stehen im Token-Register des Tutors, und `8px` ist
  dort die angebotene Option „klein". Sie anzufassen hieße, Kundinnen und
  Kunden eine Einstellung wegzunehmen.
- **Der PDF-Pfad** (3 Pillen in `worksheet_design.php`). TCPDF löst keine
  Variablen auf.

*Nachweis:* Radien vor und nach der Änderung auf drei Seiten gezählt. Suite:
21 Elemente von 10,4 auf 10 px, eines von 6,4 auf 4. Audit: sechs von 8 auf
10. Werkzeuge: unverändert. Die sichtbare Wirkung ist klein — was den Punkt
nicht schmälert: 23 Werte waren kein Entwurf, sondern eine Ansammlung.

Altbestand 264 → 190.

---

### task33 — Zwei Systeme von Zustandsfarben (offen, Entscheidung ausstehend)

**Status:** gemessen (06.09.2026), nichts geändert — die Frage gehört nicht mir.

Moodles Kern färbt Meldungen, Marken und Knöpfe über Bootstraps
`$success`/`$warning`/`$danger`/`$info`. `theme_elediaai` setzt **keine** davon,
also gelten die Vorgaben. Die Suite hat daneben ihre eigenen `--eai-*`.
Gemessen unter `theme_elediaai`:

| Rolle | Moodle | Suite | weiß darauf |
|---|---|---|---|
| Erfolg | `#357a32` | `#157f3f` | 5,27 / 5,07 |
| Warnung | `#f0ad4e` | `#92400e` | 1,95 / 7,09 |
| Gefahr | `#ca3120` | `#ab1d79` | 5,29 / 6,60 |
| Info | `#008196` | *keine Entsprechung* | — |

Der auffälligste Unterschied ist **Gefahr**: Moodle meint ein Rot, die Suite
ein Magenta. Das liest sich nicht als dieselbe Aussage.

**Warum ich es nicht angefasst habe.** Erstens ist die Kollision auf den
erreichbaren Seiten heute noch theoretisch: auf Übersicht, Audit und Handbuch
kommt genau **eine** Kern-Zustandsklasse vor (ein `alert-info`) und **null**
Elemente in Suite-Zustandsfarben — die Audit-Zellen brauchen echte
KI-Nutzungsdaten. Zweitens ist die Reichweite groß: `$danger` im Theme zu
setzen färbt jede Fehlermeldung der ganzen Website magenta, und ob das
gewollt ist, ist eine Gestaltungsfrage, keine Messfrage.

Zwei vertretbare Antworten: das Theme leitet die vier Bootstrap-Farben aus der
Suite-Palette ab (eine Website, ein System), oder die Suite nähert ihre
Zustandsfarben den Konventionen an (Rot heißt Fehler). Beides ist begründbar,
und beides gehört entschieden, bevor es jemand tut.

**Ein Nebenbefund, behoben:** der Kommentar über `$primary` in
`_variables.scss` endete mit „so the fill keeps the compliant tone". Das
stimmte nicht mehr, seit `$elediaai-accent-fill` auf `#f98012` gesetzt wurde —
durch dieselbe ausdrückliche Entscheidung des Auftraggebers wie der Textton.
Nachgemessen: unter diesem Theme rendern Links `#f98012` (über 50 auf einer
Admin-Seite) und `.btn-primary` ist Weiß auf `#f98012`, beides 2,58:1. Das
**ist** die festgehaltene Entscheidung und kein Fehler — aber ein Kommentar,
der das Gegenteil behauptet, schickt den nächsten Lesenden auf die Suche nach
einem Fehler, den es nicht gibt.

---

### task32 — Das Dashboard nach dem Umbau: nachgemessen und entschlackt

**Status:** erledigt (06.09.2026).

Der Umbau selbst kam von anderer Seite (`feature/index-suchen-filtern`,
`feature/eine-erklaerquelle`): das Dashboard lässt sich durchsuchen und nach
Zielgruppe filtern, `feature.php` und `help.php` sind abgelöst. Geprüft wurde
das Ergebnis auf `/523`, unter `boost` und unter `theme_elediaai`, bei 1440px
und bei 390px. Kein waagerechter Überlauf, die Bedienleiste bricht sauber um,
der Lint meldet null neue Verstöße. Vier Befunde blieben.

**1. Die gewählte Filterpille fiel durch WCAG 1.4.3.** Ihre Beschriftung ist
14px fett — also Fließtext, nicht „große Schrift" — und Weiß auf
`--eai-accent` hält **3,01:1**. Das genügt einem Symbol oder einem Rand
(1.4.11), nicht einem Wort.

Die Lücke war im Theme längst beschrieben (`#eb7306` für Symbole, `#bb5c05`
für Fließtext); es fehlte nur der Token dafür in der Suite. Neu:

| Token | Wert | Weiß darauf |
|---|---|---|
| `--eai-accent` | `#eb7306` | 3,01:1 — Symbole, Ränder |
| `--eai-accent-strong` | `#bb5c05` | **4,52:1** — Füllung unter Fließtext |
| `--eai-accent-strong-hover` | `#a85205` | 5,42:1 |

**2. Die vier Kategoriepunkte waren unsichtbar** — 1,05:1 bis 1,15:1 gegen
die weiße Pille. Dazu kam: ihre Farben kamen auf der Seite kein zweites Mal
vor, und zwei von ihnen waren `--eai-accent-wash` und `#e8fff3`, also die
Wash-Töne von Akzent und Erfolg — Rollen mit anderer Bedeutung, obwohl der
Kommentar daneben ausdrücklich sagte, sie sollten keiner Rolle folgen.

Entfernt, mit dieser Begründung: eine Legende braucht einen Schlüssel — der
Punkt zeigt, welche Farbe anderswo welche Gruppe meint. Ein Filter braucht
ihn nicht, die Pille trägt den Gruppennamen im Klartext.

**3. Der Suchknopf trug unter Boost Bootstraps 8px**, das Eingabefeld daneben
die 10px der Suite. Unter `theme_elediaai` stimmten sie schon überein — jetzt
themenunabhängig.

**4. Die Zustandszeile auf jeder Kachel sagte nichts.** Der Hinweis kam vom
Auftraggeber, und das Nachzählen auf der gerenderten Seite gab ihm recht:

| | vorher | nachher |
|---|---|---|
| Kacheln | 21 | 21 |
| Zustandszeilen | 21 | 0 |
| verschiedene Werte | 2 (14× „Verfügbar", 7× „Im Kurs") | — |
| Kachelhöhe | 352px | 328px |

Beide Werte waren **innerhalb ihres Abschnitts konstant**, und die Seite ist
nach genau dieser Grenze geteilt: die Überschriften sagen es bereits, und wo
etwas zu finden ist, steht im Nutzungshinweis darunter. Eine Angabe, die auf
allen Nachbarn dieselbe ist, unterscheidet nichts.

Die Zeile erscheint jetzt nur noch, wenn sie etwas sagt — „Installierbar" und
„In Vorbereitung" bleiben, weil die auf *wenigen* Kacheln stehen und genau das
ihr Wert ist. `feature_status_incourse` wurde damit heimatlos und ist aus DE
und EN heraus, vermerkt in `lang/en/deprecated.txt`.
`feature_status_ready` bleibt: `local_elediaai_strategy/plugins.php` zieht ihn
noch.

**Nachtrag: die CI fand drei Fehler, die lokal nicht auffallen konnten.**
Fünfzehn Suiten liefen lokal grün, und trotzdem war `tests local/elediaai_core`
rot. Alle drei Fehlschläge sind derselbe Typ — **Tests, die von den
eingehängten Plugins abhängen**. Die Pipeline installiert nur das *geprüfte*
Plugin; lokal liegt die ganze Suite.

| Test | warum er nur in der CI fiel |
|---|---|
| „a card says what state its feature is in" | `registry::kind('tutorpremium')` liefert ohne das Plugin `in_course`, also entsteht der zweite Abschnitt — und dessen Überschrift heißt „**Available** in your courses". Meine Zusicherung suchte die Zeichenkette „Available". |
| „a card without a page of its own leads to the handbook" | `handbook_url()` fällt ohne `local_elediaai_guide` auf die Übersicht zurück |
| „page shell renders standalone markup" | `help_url()` gibt ohne den Wegweiser die leere Zeichenkette zurück, der Knopf entfällt |

Der erste war meiner und der lehrreichste: **auf das Element prüfen, nicht auf
das Wort.** Die Zeichenkette „Available" kommt in einer Überschrift vor, die
mit der Zustandszeile nichts zu tun hat. Die Fassung prüft jetzt
`<div class="lh-plugin-card__kicker…">…</div>` und ist damit unabhängig davon,
was sonst auf der Seite steht. Nachgestellt auf `/523` mit einem Raster, das
beide Abschnitte erzeugt: HTML enthält „Available", die Elementprüfung liefert
`["Coming soon"]` — die alte Zusicherung hätte versagt.

Die beiden anderen prüfen jetzt **beide Lagen** statt nur der, die auf der
Entwicklungsinstanz gilt: der Produktionscode kennt den Fall
(`core_component::get_component_directory(...) === null`), also kennt ihn auch
der Test. Ironischerweise warnt der Docblock eines dieser Tests genau davor:
„Ein Test, der von den eingehängten Plugins abhängt, sagt dort nichts, wo es
darauf ankäme."

**Zwei Behat-Szenarien fielen aus demselben Grund** (Pipeline 58243): eines
brauchte das Handbuchkapitel aus `local_elediaai_guide`, das andere besuchte
die Einstellungsseiten von `local_elediaai_tactics`, `_strategy` und
`block_elediaai_path` — in der Pipeline gibt es keine davon, also
„Section error!".

Moodle bringt für genau diesen Fall einen Kernschritt mit:
`Given the "<plugin>" plugin is installed` überspringt das Szenario, statt es
scheitern zu lassen. Das Einstellungs-Szenario wurde dafür in **drei** geteilt,
damit jedes für sich übersprungen wird. Lokal (alles eingehängt) laufen sie
weiterhin vollständig: 8 Szenarien, 47 Schritte, grün.

**Ein Versuch, der nichts taugte.** Um diese Fehlerklasse mechanisch zu
finden, habe ich alle Tests nach Nennungen fremder Plugins durchsucht. Der
Scan meldete 125 Treffer und war wertlos: er zählte eigene Komponenten als
fremd (die Wurzelbestimmung greift bei verschachtelten Testordnern daneben),
und „nennt" ist ohnehin nicht „setzt voraus". Verworfen. **Für diese Klasse
ist die Pipeline das Instrument** — lokal ist immer die ganze Suite
eingehängt, ein lokaler Vollauf über fünfzehn Suiten kann sie strukturell
nicht finden.

**Und der `design`-Job lief zum ersten Mal in einer echten Pipeline** (58236
und 58243, je 6,7 s, grün) — die Prüflücke aus task23 ist damit belegt
geschlossen.

**Noch eine Falle beim Nachsehen:** ich hatte zweimal gepusht, auf `52` und
auf den Feature-Branch. Beide zeigen dann auf denselben Commit, `ci/generate.sh`
findet für den Branch keinen Unterschied gegen `52` und erzeugt eine
Kind-Pipeline mit einem Job namens „nichts zu prüfen" — **success**. Fast
hätte ich die als grün gemeldet. Immer die Pipeline zur **ref `52`** ansehen,
nicht die neueste zur SHA.

**Eine Regel, die sich hier zum dritten Mal bestätigt hat:** *eine Angabe, die
auf allen Nachbarn dieselbe ist, ist keine Angabe.* Sie traf die
Zustandszeile, die vier Kategoriepunkte (vier Farben, die niemand
unterscheiden konnte) und den Meta-Block der Hilfeseiten aus task25.

---

### task31 — Fügt sich die Suite in eine fremde Website ein?

**Status:** gemessen und die eine Fundstelle behoben (06.09.2026).

Die Frage kam von außen und war schlicht: eine Moodle-Website mit
installierter Suite, egal unter welchem Theme, soll **einheitlich** wirken.
Kernseiten und Suite-Seiten dürfen keinen Sprung machen — insbesondere die
Suite-Übersicht und die Seiten daran.

**Gemessen statt vermutet.** Drei Moodle-Kernseiten gegen drei Suite-Seiten,
je einmal unter `boost` und unter `theme_elediaai`, auf `/523`:

| Seite | Familie | Body | Fließtext | Zeilenhöhe |
|---|---|---|---|---|
| Moodle: Dashboard | Theme | 16px | 16px | 24px |
| Moodle: Einstellungen | Theme | 16px | 16px | 24px |
| Suite: Übersicht | Theme | 16px | 16px | 24px |
| Suite: Audit | Theme | 16px | 16px | 24px |
| Suite: Hilfe | Theme | 16px | 16px | 24px |

Familie, Grundgröße, Fließtext und Zeilenhöhe sind auf allen sechs Seiten
gleich, unter beiden Themes — unter `boost` `system-ui`, unter
`theme_elediaai` `Manrope`. Die Suite setzt keine eigene Schrift und erbt die
des Themes; genau das war die Absicht der Token-Schicht und sie hält.

**Ein Sprung war da, und nur einer: die Hilfeseite.** Sie rendert ein
Repository-Dokument, und das beginnt mit `#`. Die Seite trägt aber schon eine
Überschrift. Zwei Folgen:

- Zwei `h1` auf einer Seite — semantisch falsch.
- Die Überschrift des Dokuments war mit **40px größer als der Seitentitel mit
  32px** (Bootstrap-`h1` gegen Moodles gedeckelten Seitentitel). Genau das
  liest sich als „diese Seite gehört nicht dazu".

`help_document::demote_headings()` schiebt jede Ebene um eine nach unten;
Ebene sechs bleibt sechs. Danach:

| | vorher | nachher |
|---|---|---|
| `h1` auf der Seite | 2 | 1 |
| Titelzeile / Dokumenttitel | 32px / 40px | 32px / 32px |
| Abschnitte | 32px | 28px |
| Unterabschnitte | 28px | 24px |

**Warum hier keine Größe gesetzt wird.** Es wäre einfacher gewesen, den
Überschriften der Hilfeseite Werte aus der Suite-Skala zu geben. Das hätte den
Sprung nur verschoben: die Hilfeseite hätte dann eine eigene Skala gehabt und
wäre unter einem Kundentheme wieder aufgefallen. Die Ebene zu verschieben
überlässt die Größen dem Theme — eine Hilfeseite sieht damit aus wie jede
andere Inhaltsseite *derselben* Website, welche das auch ist.

*Nachweis:* `mess2/fuegtsichein.mjs`, zwei Läufe (`boost`, `elediaai`),
identische Zeilenform. Drei PHPUnit-Tests decken die Verschiebung ab,
einschließlich „Ebene sechs bleibt sechs" und „eine Raute mitten im Text ist
keine Überschrift".

**Was stehen bleibt.** Der Dokumenttitel ist jetzt so groß wie der Seitentitel
(beide 32px) — das ist Bootstraps `h2` und dieselbe Größe, die Moodles
Dashboard für seine Überschriften benutzt. Kein Sprung mehr, aber flach. Der
saubere Schnitt wäre, die erste Überschrift des Dokuments ganz wegzulassen,
weil die Seite sie schon trägt — das setzt aber voraus, dass sie in allen
sechs Dokumenten wirklich der Seitentitel ist. Ungeprüft, deshalb nicht
getan.

---

### task29 — `!important`: begründen oder wegnehmen (adr05 §2)

**Status:** begonnen (06.09.2026), `webservice_elediamcp` erledigt.

Die dritte Regel des Lints verlangt keine Wertersetzung, sondern eine
Entscheidung je Stelle: trägt der Nachdruck etwas, oder ist er totes Gewicht?
Von 93 Fundstellen sind 30 in `webservice_elediamcp`, keine davon begründet.

**Wie man es misst — und wie nicht.** Der erste Versuch war eine Sonde im
Browser: den Nachdruck über die CSSOM entfernen, den berechneten Wert davor und
danach vergleichen. Sie meldete alle dreißig als überflüssig. **Das war zweimal
falsch:**

1. Sie prüfte je Regel nur das **erste** passende Element. Bei einer
   Selektorliste ist das oft eines ohne Konflikt.
2. Auch nachgebessert meldete sie „überflüssig", weil das Plugin-CSS
   **zweimal** ausgeliefert wird — einmal im Theme-Bündel, einmal spät
   nachgeladen. Die Sonde entschärft eine Kopie, die andere gewinnt weiter.

Das steht seit Langem als Kommentar in `filter/eledia_translate/styles.css`
(„the plugin CSS is delivered twice (theme aggregate + raw require_css)").
Wer vor dem Messen liest, spart sich zwei Fehlschläge.

**Was wirklich misst:** die Datei austauschen und die Seite Element für Element
vergleichen. Das trifft beide Kopien. Ergebnis für `elediamcp`:

| Deklaration | Urteil |
|---|---|
| `display: block` auf `.felement`, `.checkbox` | **trägt** — Bootstraps `.d-flex` hat selbst `!important`, 19 Felder springen sonst auf `flex` |
| `display: none` auf `.femptylabel .col-form-label` | **trägt** — zehn leere Spalten würden sichtbar |
| `background`, `border-color` auf `pre` | **trägt** — Moodle färbt `<pre>` selbst |
| `border-radius` auf `pre` | überflüssig |
| `padding-left` (2×), `margin-left` in Formularen | überflüssig |
| Schubladen-Regeln (5) | **tragen einen Zustand ab** — im Ruhezustand messbar wirkungslos, aber sie sichern die geöffnete Schublade |

Vier entfernt, neun begründet, siebzehn unbeurteilt (ihre Elemente kommen auf
der Konfigurationsseite nicht vor). Endstand identisch zum Ausgangsstand,
Element für Element nachgemessen: 129 Elemente, null Unterschiede.

**Zwei Verfeinerungen am Lint**, beide aus dieser Runde:

- Ein Kommentar deckt jetzt seinen **ganzen Deklarationsblock**, nicht nur die
  nächste Zeile. Die alte Regel bestrafte gerade die guten Erklärungen: wer
  drei zusammengehörende Nachdrücke in einem Satz begründet, musste den Satz
  dreimal schreiben.
- **Kommentarzeilen zählen nicht mehr als Fundstelle.** Wer in einer
  Begründung das Wort `!important` schreibt — und das tut man dort ständig —
  wurde dafür gemeldet.

Altbestand 299 → 285.

**Zweite Runde (06.09.2026): die erreichbaren Fundstellen.** 14 weitere
Nachdrücke weggenommen, zwei begründet stehen gelassen — jeder einzelne
gemessen, keiner nach Augenmaß.

**Gruppe 1: `X[hidden] { display: none !important }`** (Wegweiser,
Questiongen, Launcher — 3 Stück). Bootstraps Reboot hat das global:
`_reboot.scss:609` sagt `[hidden] { display: none !important; }`. Jede
Plugin-Wiederholung davon ist totes Gewicht — und der Plugin-Selektor
(`.eai-guide-host[hidden]`, 0-2-0) schlägt den Sichtbar-Zustand
(`.eai-guide-host`, 0-1-0) ohnehin. Zwei unabhängige Gründe.

Die Datei des Wegweisers hatte das Muster schon zweimal, einmal **mit** und
einmal **ohne** Nachdruck (`.eai-guide-host__dot[hidden]`). Die Variante ohne
funktioniert seit jeher. Das war die eingebaute Kontrollprobe.

*Nachweis:* ein Element mit denselben Klassen und `hidden` in die echte Seite
eingesetzt und die berechnete Anzeige gelesen — vor und nach der Änderung, vier
Fälle, alle `none`. Ein eingesetztes Element misst die echte Kaskade und
umgeht damit nicht das Problem der doppelt ausgelieferten Datei; es hat es
nicht.

**Gruppe 2: die Schubladen-Unterdrückung** auf den Einstellungsseiten von
Quellen und LiteRAG (10 Stück, in beiden Plugins dasselbe Idiom). Sie
verstecken die rechte Block-Schublade und nehmen den Rand zurück, den Boost
für sie freihält.

*Nachweis in zwei Zuständen*, weil der Ruhezustand hier nicht reicht:

| Zustand | Ergebnis |
|---|---|
| Ruhezustand, 3205 Elemente auf zwei Seiten, Eigenschaft für Eigenschaft | **0 Unterschiede** |
| Zielelemente vorhanden und weiterhin `display: none`? | ja, 12 von 14 (zwei gibt es auf der Seite nicht) |
| Schublade offen (`drawer-open-right` + `show-drawer-right` gesetzt) | `body` und `#page` bleiben bei `margin-right: 0` |

Der zweite Zustand war der eigentliche Test: der Schaltknopf ist auf diesen
Seiten verborgen, aber der Zustand reist als Nutzereinstellung von anderen
Seiten mit. Er hält ohne Nachdruck, weil der Plugin-Selektor eine Id trägt
(`body#page-admin-setting-local_literag #page.drawers.show-drawer-right`,
2-2-1) und Boosts (`#page.drawers.show-drawer-right`, 1-2-0) damit unterliegt.

**Gruppe 3: zwei, die tragen und deshalb bleiben.** `audit_page.php` färbt
`.text-success`/`.text-danger` um. Das sind Bootstrap-Utilities, und Boost
baut sie mit `$enable-important-utilities: true` — also selbst mit Nachdruck.
Ohne Gegen-Nachdruck gewinnt die Utility, und die Farbe steht wieder unlesbar
auf ihrer eigenen Wash-Fläche: genau der Kontrastfehler aus task27. Beide
Stellen haben jetzt diesen Satz als Kommentar über sich.

**Ein Fund nebenbei, der größer ist als die Regel** (→ q08). Der Nachdruck auf
der Kachelfarbe der KI-Aktivitäten (`hook_callbacks::tint_ai_chooser_icons`)
konnte weg, weil er nichts überstimmt. Beim Messen kam heraus, *warum*: der
Kommentar darüber sagt „Boost rendert jedes Aktivitätssymbol auf einem
zweckgefärbten Quadrat" — **in Moodle 5.2 stimmt das nicht mehr.** Gemessen
auf `/523`:

| Aktivität | Kachelhintergrund |
|---|---|
| `mod_aichat`, `mod_elli` (KI-Suite) | `rgb(58, 173, 170)` — türkis |
| `mod_forum`, `mod_quiz` (Moodle) | `rgba(0, 0, 0, 0)` — **transparent** |

Im gebauten Theme-CSS setzt **keine einzige** Regel einen Hintergrund auf
`.activityiconcontainer`; Moodle 5.2 färbt stattdessen das Symbol selbst
(`recolor-icon`). Die Suite malt also ein gefülltes Quadrat, wo Moodle keines
mehr malt — die KI-Aktivitäten stechen im Aktivitätswähler heraus statt sich
einzufügen. Das ist keine Lint-Frage, sondern eine Gestaltungsentscheidung,
und deshalb steht sie als q08 offen statt hier entschieden zu werden.

**Was jetzt noch offen ist:** 62 Nachdrücke, davon 21 im Tutor-Widget (dessen
Aussehen Kundinnen und Kunden einstellen), 17 in elediamcp (Elemente, die auf
der Konfigurationsseite nicht vorkommen), 16 in der Chat-Engine und 9 im
Übersetzungsfilter — alle vier brauchen einen Kurs mit eingerichtetem Backend.

Altbestand 190 → 176.

---

### task28 — Die Schriftgrößen der übrigen Plugins (adr05 §2)

**Status:** erledigt (06.09.2026).

task22 hatte fünf Plugins auf die Skala gebracht; der Lint fand danach noch 53
rohe Schriftgrößen. Umgestellt sind 33 in sechs Plugins — 18 in
`eledia_translate`, 6 in `mod_elli`, je 3 in `elediaai_selfstudy` und
`elediaai_questiongen`, 2 in `qtype_aitext`, eine in `activityfilter`. Rolle je
Stelle: Aufgabentext und Eingaben tragen `body`, Metazeilen `small`, Marken und
Versalien `caption`, Titel `h4`.

Zwei Stellen waren mehr als eine Ersetzung:

- Die **Hilfeseite** von `eledia_translate` hatte `h1`, `h2` und `h3` bei 1.35,
  1.1 und 1 rem — drei Größen, die sich kaum unterscheiden ließen. Sie tragen
  jetzt `h2`, `h3` und `h4`: 1.5, 1.25, 1.125. Eine Ordnung statt dreier fast
  gleicher Schriften.
- Die **Spaltentabelle** dort lag bei 0.78 rem und bekam `caption` (0.75), nicht
  `small` (0.875). Die Doku des Plugins legt für diese Tabellen ein
  Spaltenbudget in Pixeln fest; eine größere Schrift hätte den Umbruch
  verändert, eine um 0,03 rem kleinere tut das nicht.

Stehen bleiben 20, alle mit Grund: 13 im Arbeitsblatt und PDF-Export (TCPDF
löst keine Variablen), 6 im erzeugten `lh-core`-Block, eine in der Chat-Blase
des Tutors, die zum einstellbaren Styling gehört.

**Zwei Nebenwirkungen, die hier festgehalten gehören:**

- Der Commit trägt die Überschrift „Eine richtige Fußzeile für das Theme"
  (`5bf1ce3`). Eine zweite Sitzung arbeitete im selben Arbeitsbaum, meine
  dreizehn Dateien lagen vorgemerkt im Index, und ihr `git commit` nahm sie
  mit. Der Inhalt stimmt, die Begründung stand in einer Nachricht, die nie
  geschrieben wurde — deshalb steht sie hier. **How to apply:** In einem
  geteilten Arbeitsbaum nichts vorgemerkt liegen lassen; erst stagen, wenn der
  Commit unmittelbar folgt.
- `activityfilter/styles.css` hatte gemischte Zeilenenden (CRLF und LF). Mein
  Python-Skript liest mit `read_text()` und schreibt mit `write_text()`, was
  alles auf LF vereinheitlicht — der Diff zeigt deshalb 65 statt einer
  geänderten Zeile. LF ist für Moodle richtig, aber es war unbeabsichtigt und
  unangekündigt.

---

### task27 — Die Farbrunde: rohe Farben auf Rollen bringen (adr05 §2)

**Status:** erledigt (05.09.2026), **ein Fehler daraus am 06.09.2026 behoben.**

> **Nachtrag: ein Token, das sich selbst benannte.** `cfebc58` („Die kleineren
> Plugins bekommen Rollen: 98 Stellen in elf Dateien") ersetzte den Farbwert
> `#e8fff3` in der Breite durch `var(--eai-success-wash)` — und traf dabei
> auch die Zeile, in der das Token **definiert** wird. Übrig blieb
> `--eai-success-wash: var(--eai-success-wash);`.
>
> Eine Eigenschaft, die sich selbst benennt, ist zum Zeitpunkt der Berechnung
> ungültig. Sie fällt aus, und jede Stelle, die sie benutzt, bekommt gar
> nichts. Gemessen am 06.09.2026 auf `/523`:
>
> | Token | aufgelöst zu |
> |---|---|
> | `--eai-success-wash` | `""` — leer |
> | `--lh-success-light` (leitet sich davon ab) | `""` — leer |
> | `--eai-warning-wash` | `#fef3c7` |
> | `--eai-danger-wash` | `#f5e4ef` |
>
> Eine Probefläche mit `background: var(--eai-success-wash)` kam als
> `rgba(0, 0, 0, 0)` an: **durchsichtig**. Neun Stellen in sechs Plugins
> hatten still ihre grüne Fläche verloren — darunter die Erfolgszelle des
> Audits und das Tutor-Widget. Sichtbar war es nicht, weil eine fehlende
> Fläche wie eine gewollt schlichte aussieht.
>
> Wert wiederhergestellt (`#e8fff3`, 4,84:1 unter `--eai-success`), live
> nachgemessen: Fläche `rgb(232, 255, 243)`, Text `rgb(21, 127, 63)`.
>
> **Und eine fünfte Lint-Regel**, damit es nicht wiederkommt. `selbstbezug`
> ist die einzige Regel, die **vor** der `:root`-Ausnahme läuft — der Fehler
> entsteht ja genau dort, wo die Ausnahme greift. Sie ist zugleich der Beleg
> dafür, dass die anderen vier Regeln blind für eine ganze Fehlerklasse
> waren: sie prüfen, ob ein Wert *roh* ist, nicht ob ein Token *funktioniert*.

Der Design-Lint hat den Bestand zum ersten Mal gezählt: 942 Fundstellen, davon
676 Farben. Die zerfallen in zwei Arbeiten, die nichts miteinander zu tun
haben.

**Erledigt — die Rückfallwerte (301 Stellen in 11 Dateien).** Überall stand
`var(--lh-muted, #5f6b77)` statt `var(--lh-muted)`. Zwei Gründe, das
wegzunehmen:

1. *Unerreichbar.* Ein an `:root` deklariertes Token ist die Wurzel jeder
   Vererbungskette — der Rückfall greift nie. Seit task22 hängt jedes
   Suite-Plugin an Core, und Moodle bündelt jedes `styles.css` in die
   Theme-CSS, also gibt es keine Seite ohne den Token-Block.
2. *Falsch.* Die Rückfälle waren fast nie Cores Wert, sondern die alte
   LernHive-Palette: `--lh-primary` versprach `#194866`, Core liefert
   `var(--eai-accent)` = `#eb7306`. Eine zweite Wahrheit, die niemand pflegt,
   ist schlimm; eine falsche zweite Wahrheit ist schlimmer.

Stehen geblieben sind 19 Rückfälle, deren Token **nirgends an `:root`** steht
— dort ist der Rückfall das, was rendert. Sie sind die Liste für die zweite
Hälfte: `--lh-pastel-ai` (7×), `--lh-border-strong`, `--lh-text-muted`,
`--lh-success`, `--lh-danger-dark`, `--lh-surface-subtle`, die vier `--eac-*`
(im Bauteil-Geltungsbereich der Chat-Engine deklariert, nicht an `:root`) und
zwei `--bs-*`, die Bootstrap gehören und nicht uns.

*Nachweis:* Farbabzug von 881 Elementen auf acht Suite-Seiten, vorher und
nachher — **null Unterschiede**. Das Argument allein wäre richtig gewesen; der
Abzug macht es überprüfbar. Der Altbestand des Lints fiel von 927 auf 714.

**Erledigt — Weiß (80 Stellen in 14 Dateien).** Der größte einzelne Posten der
Farbrunde und zugleich der eindeutigste. Zwei Rollen, beide wertgleich mit
`#ffffff`, die Änderung ist also optisch wirkungslos:

- `background` / `background-color: #fff` → `var(--eai-surface)` (38×)
- `color: #fff` → `var(--eai-accent-ink)` (42×)

Alle 42 Textstellen sitzen auf gefülltem Grund — Primärknöpfe, aktive Marken,
Schrittzahlen, Zähler, Fortschrittsbalken. Eine Vorbehalt bleibt: die Suite
kennt nur **eine** Tinte, `--eai-accent-ink` („Text auf Akzent"). Ein paar
Stellen sitzen auf dunklem Navy statt auf dem Akzent; die Absicht (Tinte auf
gefülltem Grund) ist dieselbe, der Name trifft es dort nicht ganz. Braucht ein
zweiter Grund je eine eigene Tinte, ist das die Rolle, die dann fehlt.

Angefasst wurden nur ganze Deklarationen (`eigenschaft: #fff;`) — Kommentare,
PHP-Vorgabewerte und zusammengesetzte Werte wie Verläufe blieben außen vor,
dort steht Weiß oft aus anderem Grund.

*Nachweis:* derselbe Farbabzug von 881 Elementen, vorher und nachher — null
Unterschiede. Altbestand 714 → 634.

**Nebenbei geschlossen: `filter_eledia_translate` hing an gar nichts.** Das
Plugin liest die Token der Suite, führte aber keine Abhängigkeit auf
`local_elediaai_core` — task22 hatte es in seiner Liste nicht. Seit die
Rückfallwerte weg sind, wäre das ein Loch gewesen: eine Installation ohne
Core hätte gar keine Werte mehr vorgefunden. Jetzt trägt es
`'local_elediaai_core' => 2026090801`, die Fassung, die den Token-Block
einführte.

**Erledigt — Zustandsrollen (PO, 05.09.2026).** Die drei erzeugten
`lh-core`-Blöcke führten eigene Statusfarben. Zwei davon waren kaputt:

| Rolle | bisher | auf Weiß | jetzt | auf Weiß |
|---|---|---|---|---|
| Erfolg | `#3aadaa` | 2,71:1 | `--eai-success` `#157f3f` | **5,07:1** |
| Warnung | `#f98012` — die Markenfarbe selbst | 2,58:1 | `--eai-warning` `#92400e` | **7,09:1** |
| Gefahr | `#ab1d79` | 6,60:1 | `--eai-danger`, unverändert | 6,60:1 |

Beide alten Werte fielen durch WCAG 1.4.3 für Text, und eine Warnung, die
aussieht wie der Akzent, ist keine Warnung. Die neuen Werte stammen aus dem
Bestand der Suite (`feature_info.mustache`), sind also nicht erfunden. Jede
Rolle hat ein `-wash` als Fläche; dazu `--eai-danger-hover`.

Sichtbar ist davon vorerst wenig: `--lh-success-light` färbt
`.lh-plugin-tag--active`, eine Zustandsmarke, die auf keiner der geprüften
Seiten gerade gerendert wird, und `--lh-warning*` wird über `var()` nirgends
gelesen. Die Runde räumt zwei durchgefallene Werte aus dem System und gibt
der Suite die fehlende Vokabel — sichtbar wird sie, wenn eine Fläche einen
Zustand anzeigt.

*Eine Fehlzuschreibung unterwegs:* Der türkise Kasten „Nothing to display"
auf der Übersetzungsseite sah nach unserer Erfolgsfarbe aus und wurde als
Beleg angeführt. Nachgemessen ist es Moodles eigenes `.alert-info` aus
Bootstrap. **Offen als eigene Frage:** die Bootstrap-Zustandsfarben kommen
aus dem Theme und folgen der Suite-Palette nicht.

**Erledigt — die Audit-Seite von Core (38 Stellen).** Core war der
drittgrößte Posten in seiner eigenen Prüfung; die Design-Schicht sollte das
nicht sein. Eine zusammenhängende Blaugrau-Palette, abgebildet auf die Rollen:
Ränder auf `--eai-line`, Flächen auf `--eai-bg`, Text auf `--eai-fg` /
`--eai-muted` / `--eai-faint`, Erfolg und Fehler auf die neuen Zustandsrollen,
der Schatten auf `--eai-shadow-card`.

Zwei Stellen entschied die **Messung** und nicht die Systematik:

- Die **aktive Filtermarke** war weiße Schrift auf Navy (9,51:1). Der Akzent
  mit weißer Schrift käme auf 3,01:1 und fiele durch WCAG 1.4.3. Sie ist
  jetzt Akzentfläche mit dunkler Schrift: **5,16:1**.
- Die **Augenbraue** ist 12px fett auf Weiß. Der Akzent trägt keinen kleinen
  Text — das steht schon im Token-Kommentar von Core. Sie ist `--eai-fg-dim`:
  **9,77:1**, praktisch der bisherige Wert.

Wer stur „Navy → Akzent" ersetzt hätte, hätte die Seite unlesbarer gemacht
und dabei den Vertrag eingehalten. Das ist die Lehre für die restlichen
Dateien: die Rolle sagt, *welche* Familie; der Kontrast sagt, *welches
Glied*.

**Erledigt — die Verwaltungsflächen des Tutors (28 Stellen).** Von 51 rohen
Farben im Tutor sind 28 umgestellt. Zwei Bereiche bleiben, jeweils mit Grund:

- **Die Widget-Regeln** (`.elediaai-chat-*`, 18 Stellen). Der Tutor in den
  Kursen hat sein eigenes einstellbares Styling, das nicht angetastet wird
  (PO, 04.09.2026). Dazu gehören die vier Startfarben der Hero-Kacheln: ein
  bewusster Vierklang als Dekor, für den es keine Rolle gibt und geben soll.
- **Der erzeugte `lh-core`-Block** (6 Stellen). Dort wurde nur die Palette
  umgehängt; alles weitere wäre ein Eingriff in ein fremdes System.

Drei Stellen entschied wieder die Messung:

- Der Statuspunkt „bereit" war `#22c55e` und kam gegen Weiß auf **2,28:1** —
  unter der Grenze von WCAG 1.4.11 für Bedienelemente. `--eai-success`: 5,07.
- Der Rand des Warnrufs war `#fed7aa` auf `#fff7ed`: **1,27:1**, praktisch
  unsichtbar. `--eai-warning`: 6,37.
- Der Fortschrittsbalken war ein Navy-Verlauf mit weißer Schrift (5,31 bis
  10,99). Der Akzent mit weißer Schrift käme auf 3,01. Er ist jetzt eine
  einfarbige Akzentfläche mit dunkler Schrift: 5,16. Der Verlauf entfällt.

**Teilweise erledigt — `elediaai_teachertools` (7 von 48).** Hier lag der
interessanteste Befund der Runde: 41 der 48 Farben gehören zum
**Arbeitsblatt**, und dieselben zweiundzwanzig Werte stehen als Konstanten in
`classes/local/worksheet_design.php`, aus denen der PDF-Export rendert.

Sie bleiben wörtlich, aus zwei Gründen:

1. TCPDF löst keine CSS-Variablen auf. Die eine Hälfte würde den Token folgen,
   die andere nicht — Bildschirm und Druck liefen auseinander.
2. Es wäre auch inhaltlich falsch. Ein Arbeitsblatt ist ein gedrucktes
   Dokument, keine Bedienoberfläche. Der warme Akzent der Suite gehört an die
   Knöpfe, nicht auf das Papier, das jemand kopiert.

Umgestellt sind die 7 Stellen der Formularoberfläche (`#fgroup_id_focuses`),
die zur App gehören und nicht zum Dokument. Der Grund für den Rest steht als
Kommentar über dem Arbeitsblatt-Abschnitt der CSS, damit ihn niemand
„aufräumt".

**Nachfolgearbeit — erledigt am 06.09.2026.** Das Arbeitsblatt hatte zwei
Quellen für einen Entwurf: die Konstanten in `worksheet_design.php` und
dieselben Werte als Zahlen im Stylesheet. Jetzt erzeugt
`worksheet_design::css_variables()` einen Block mit `--lhtw-*`, der zwischen
Markern in `styles.css` steht; die 41 Literale des Arbeitsblatt-Abschnitts
lesen ihn. Ein Test hält beide Seiten zusammen — läuft er rot, sind Druck und
Bildschirm auseinandergelaufen.

Bewusst **nicht** an `:root`: das sind die Farben eines gedruckten Dokuments,
keine Token der Suite. Sie gelten im Geltungsbereich des Arbeitsblatts und
nirgendwo sonst. Eine Farbe ohne Namen (`#d3e5ef`, der linke Strich neben
einer Aufgabe im modernen Layout) hat einen bekommen: `TASK_RULE`.

**Dabei ein größerer Fund, noch offen.** Der Entwurf reist als *Inline-Stil*
mit dem Inhalt in die gespeicherte `mod_page` und ins PDF — deshalb gibt es
die Konstanten überhaupt. Ein Inline-Stil schlägt aber jeden Selektor. Der
Abgleich der Rollen in PHP gegen die CSS-Regeln ergibt: **114 der 167
CSS-Deklarationen setzen etwas, das der Inline-Stil ohnehin schon setzt** —
sie tun nichts. Nur 53 sind wirksam.

Das aufzuräumen wäre die eigentliche Ersparnis, braucht aber den Nachweis am
gerenderten Arbeitsblatt: statische Namensgleichheit ist ein Indiz, kein
Beweis. Dafür muss der Assistent erst Inhalt erzeugen.

**Erledigt — `elediaai_strategy` (42 Stellen, restlos) und die
KI-Herkunftsrolle.** Die Zustandspaare des Strategieassistenten gingen glatt
auf die neuen Rollen. Dabei kam eine dritte Rolle heraus:

`--lh-pastel-ai` war **benannt, aber nirgends definiert** — sechs Stellen
hielten ein Lavendel `#eef2ff` nur über ihren Rückfallwert am Leben, und
dieselbe Farbe stand in `elediaai_tactics`, `elediaai_chatengine` und im
Launcher von Core. Eine plugin-übergreifende Konvention ohne Zuhause. Sie ist
jetzt `--eai-ai-wash` / `--eai-ai` (7,81:1) und heißt **Herkunft**, nicht
Zustand: sie sagt „hier ist KI im Spiel", nicht „so steht es".

Bewusst **nicht** umgestellt: die vier Punkte der Zielgruppen-Legende in
`feature_grid.php` und die Kachelfarben in `feature_info.mustache`. Das sind
kategoriale Reihen — ihre Aussage ist „diese vier sind verschieden", nicht
„dies ist Erfolg". Der Grund steht im Code.

**Erledigt — die kleineren Plugins (98 Stellen in elf Dateien).** Zustandspaare,
Flächen, Ränder und gedämpfter Text in `eledia_translate`, `literag`,
`elediaai_sources`, `elediaai_selfstudy`, `elediaai_questiongen`,
`elediaai_tactics`, `elediaai_chatengine`, `elediaai_path`, `qtype_aitext` und
Core. Alles vom selben Zuschnitt wie `strategy`.

Zwei Stellen sind erwähnenswert:

- **`elediaai_path`** trug Bootstraps eigene Alarmfarben (`#f8d7da`,
  `#fff3cd`, `#d4edda`) für eine Kohortentabelle. Jetzt die Zustandsrollen —
  dieselbe Aussage, aber die der Suite.
- **Ein Kommentar in Core wurde falsch.** Über `.lh-plugin-tag--*` stand, die
  drei Marken lägen „auf einer anderen Achse als die Suite-Token und bekommen
  deshalb bewusst keinen". Seit es Zustandsrollen gibt, stimmt das nur noch
  für eine der drei: „aktiv" *ist* ein Erfolg, „neutral" *ist* eine ruhende
  Fläche. Beide lesen jetzt Rollen, „Hinweis" bleibt wörtlich, und der
  Kommentar sagt, warum sich das geändert hat. Ein stehengebliebener
  Kommentar neben geändertem Code wäre schlimmer als beides.

Nach diesem Durchgang bleiben **99 rohe Farben**, und jede einzelne ist
begründet:

| Gruppe | Zahl | Warum |
|---|---|---|
| Arbeitsblatt und PDF-Export | 46 | TCPDF löst keine Variablen; Papier ist keine Oberfläche |
| Tutor: Widget und erzeugter Block | 24 | einstellbares Styling; fremdes System |
| `mod_elli` | 15 | eigener Durchgang, sichtbare Änderung |
| kategoriale Reihen in Core | 7 | „diese vier sind verschieden", nicht „dies ist Erfolg" |
| einstellbares Tutor-Design | 2 | `--eac-grounded-*`, siehe unten |
| Token ohne Definition | 2 | erledigt am 06.09.2026, siehe unten |
| Bootstrap-Rückfälle | 2 | gehören Bootstrap, nicht uns |
| `.lh-plugin-tag--info` | 1 | ein Blau, für das es keine Rolle gibt |

**Nachfolgearbeit, erledigt am 06.09.2026 — und dabei eine eigene Annahme
widerlegt.**

Ich hatte vier „Token ohne Definition" gezählt und für `--eac-grounded-*` eine
neue Rolle vorgeschlagen: „die Antwort steht auf Quellen" sei ein Zustand ohne
Vokabel. Beim Nachsehen stellte sich heraus, dass die beiden **sehr wohl
definiert** sind — in `chatengine/styles.css` auf `.elediaai-chat-launch` —
und dass sie zum **einstellbaren Tutor-Design** gehören:
`block_elediaai_tutor/classes/local/registry.php` führt sie als
`tok_groundedbg` und `tok_groundedline`, und vier Vorlagen in `presets.php`
geben ihnen verschiedene Werte. Eine Suite-Rolle hätte eine Farbe festgelegt,
die Kundinnen und Kunden einstellen dürfen. Sie bleiben, wo sie sind.

Übrig blieben zwei echte:

- **`--eac-bg-soft`** stand nirgends — weder in der Registry noch in den
  Vorlagen —, also rendert immer sein Rückfall. Jetzt `var(--eai-bg)`.
- **`--lh-border-strong`** in `eledia_translate` ebenso. Der Name sagt, was
  gemeint war: ein stärkerer Rand als die Vorgabe des Knopfes. Der Rückfall
  `#cfe0eb` trug 1,3:1 gegen Weiß und riss als Umriss eines Bedienelements
  WCAG 1.4.11. Der Text des Knopfes ist ohnehin der Akzent; der Rand ist es
  jetzt auch: **3,01:1**.

Die Lehre ist dieselbe wie beim Arbeitsblatt: bevor eine Farbe eine Rolle
bekommt, muss geklärt sein, wem sie gehört. Ein Token, das jemand einstellen
darf, ist kein Kandidat für das Vokabular.

**Erledigt — `mod_elli` (17 Stellen).** Fünfzehn Token-Definitionen leiten
sich jetzt aus den Suite-Rollen ab: Akzent, Flächen, Linien, Schrift, die
Sprechblasen und die Zustände. Dazu zwei Knöpfe, die weiße Schrift auf dem
Akzent trugen — 3,01:1, durchgefallen nach WCAG 1.4.3 — und jetzt dunkle
tragen: 5,16:1.

**Nicht durch ein Bild belegt.** Die Elli-Aktivität rendert ihre Oberfläche
nur mit konfiguriertem KI-Backend; ohne eines zeigt sie allein den Hinweis
„The AI backend is not configured". Auf `/522` gibt es keines, also auch
keinen Vorher-/Nachher-Vergleich. Belegt ist die Änderung durch den Lint
(23 → 8 in `mod_elli`) und die Tests (89/89); wie sie aussieht, muss auf einer
Installation mit Backend nachgesehen werden.

**Damit ist task27 abgeschlossen.** Von 942 Fundstellen zu Beginn bleiben 334,
und jede einzelne ist begründet — Arbeitsblatt und PDF-Export, Tutor-Widget
und erzeugter Block, kategoriale Reihen, Token ohne Definition,
Bootstrap-Rückfälle. Was übrig bleibt, ist kein Rest, sondern eine Liste von
Entscheidungen. Vorgehen wie beim
Typografie-Audit: Rolle je Stelle, nicht Zahl je Stelle. 175 verschiedene
Farben, davon 98 nur ein einziges Mal benutzt. Die Verteilung vor der
Weiß-Runde:

| Datei | Rohe Farben |
|---|---|
| `blocks/elediaai_tutor/styles.css` | 99 |
| `filter/eledia_translate/styles.css` | 52 |
| `local/elediaai_teachertools/styles.css` | 50 |
| `local/elediaai_core/classes/local/audit_page.php` | 41 |
| `local/literag/styles.css` | 39 |
| `local/elediaai_strategy/styles.css` | 27 |
| `mod/elli/styles.css` | 21 |
| Rest (12 Dateien) | 115 |

Nicht jede davon ist ein Fehler: Zustandsfarben (Fehler, Erfolg, geerdet) sind
bewusst wörtlich, weil es dafür keine Rolle im System gibt. Die Runde muss
also je Stelle entscheiden — und wo eine Rolle fehlt, ist die Antwort
womöglich, eine anzulegen, statt die Farbe zu verstecken.

### task19 — Bildguthaben für `generate_image`

**Status:** erledigt (SUI-613, 2026-08-04).

Token-äquivalente Preisstufen, atomare Action-Reservierung/Verbuchung und die
optionale Core-Audit-Erweiterung für `generate_image` sind implementiert und
dokumentiert. Der Processor-Contract nutzt `size`/`quality` plus `numimages`.

### task18 — Doku nachziehen: Token-Quota und LLM-Session-Composer dokumentieren

**Status:** erledigt (SUI-82, 2026-08-06).

`03-dev-doc.md` hat jetzt je einen eigenen Abschnitt „Token-Quota" und
„LLM-Session-Composer" mit Public-API-Tabelle, Reservierungs-Lebenszyklus,
Tabellenschema, Grounding-Vertrag und Fallback-Verhalten; `01-features.md`
beschreibt die anwendernahe Seite als feat12 (KI-Guthabenlimits) und feat13
(KI-Session-Komposition). Dabei mitkorrigiert: die Doku behauptete an drei
Stellen, das Plugin lege keine eigenen DB-Tabellen an, und `db/access.php` sei
leer — beides stimmte seit `local_elediaai_core_usage` bzw.
`manageteacherdashboard` nicht mehr.

Offen geblieben und bewusst als Lücke dokumentiert statt zugesagt:
`quota_manager::prune()` ist implementiert und getestet, hängt aber an keinem
Scheduled Task (`db/tasks.php` fehlt), die 90-Tage-Retention greift also nur bei
manuellem Aufruf.

**Kontext:** Zwei wesentliche, getestete Code-Bestandteile haben keine
Entsprechung in `01-features.md`/`03-dev-doc.md`:

- `classes/local/quota_manager.php` — Per-User-Token-Quota mit Student-/
  Teacher-Bucket, Stunden-/Tages-/Monats-Fenster, 90-Tage-Retention; eigene
  Tabelle `local_elediaai_core_usage` (`db/install.xml`). Harte Credit-Grenze:
  `reserve()` bucht die Reserve (Prompt-Schaetzung + Completion-Puffer) atomar
  pro Fenster, `commit()` korrigiert auf die tatsaechlichen Tokens, `release()`
  gibt im Fehlerfall frei. Public-API u. a. `reserve()`, `commit()`, `release()`,
  `assert_can_request()`, `record_usage()`, `used_tokens()`, `prune()`,
  `delete_for_user()`, `role_bucket()`. Bislang nur als Stichwort
  „Token-Quotas" in der task07-Snapshot-Tabelle erwähnt, nicht in Features/
  Dev-Doc beschrieben. Test: `tests/quota_manager_test.php` (12 Tests).
- ~~`classes/local/session_composer.php`~~ (entfernt 05.09.2026) — Provider für den
  `lernhive_session_compose`-Hook von `local_lernhive` (ADR-P15 Stufe 2):
  wählt aus dem gegroundeten Kandidatenpool bis zu drei Elemente, ordnet sie
  didaktisch, passt Zeitschätzungen an und schreibt eine Motivationszeile;
  Grounding-Vertrag (nur Kandidat-Indizes), Fallback bei Fehlern auf die
  deterministische Reihenfolge. Public-API `compose()`, `apply_selection()`.
  Test: `tests/session_composer_test.php` (2 Tests).

**Ziel:** Beide Bestandteile in `03-dev-doc.md` (Architektur/Klassen) und —
soweit anwendernah — in `01-features.md` beschreiben. Diese Doku wird bewusst
nicht durch den DevFlow-Sync erfunden.

### task15 — Kundenfähige Produktdoku (`content/docs/plugins/`)

**Status:** umgesetzt (13 deutsche Handbücher; seit 2026-07-31 zentral unter
`content/docs/plugins/` und damit außerhalb des Moodle-Webroots).

**Architektur:** Katalog und öffentliche Handbücher liegen unter `content/`;
`apps/docs/scripts/sync-plugin-docs.mjs` erzeugt daraus nur für den Astro-Build
nicht versionierte Kopien. Interne DevFlow-Dokumente bleiben bei den Plugins,
sind aber keine Quelle für docs.eledia.ai.

**Inventar:** Für alle 13 Katalogprodukte existiert ein Handbuch unter
`content/docs/plugins/<slug>.md`, einschließlich KI Kursautor und KI Tutor mit
klar gekennzeichnetem Status „In Vorbereitung“. Der Katalog ordnet jedes
Handbuch über sein Feld `docs` eindeutig zu.

**Verifikation:**

- `npm run content:check --workspace docs` in `apps/`: 13 vollständige
  Katalogeinträge und Handbücher.
- `npm run build` in `apps/`: 17 statische Docs-Seiten einschließlich aller
  13 Plugin-Routen und deutschem Pagefind-Index.
- Der Cache-Cleanup berücksichtigt den Astro-Store im Projekt- und im
  Workspace-`node_modules`-Pfad; der Kontrollbuild läuft ohne Duplicate-ID-
  Warnungen.
- `git diff --check`.

### task16 — Wissensbasis-Kurs auf demo mit neuem Katalog neu syncen

**Status:** erledigt (2026-08-06).

**Kontext:** Der Suite-Katalog trägt seit 2026-07-08 die kanonische
„KI …"-Benennung und neue Einträge (13 statt 7 Plugins). Der Wissensbasis-Kurs
auf demo.eledia.ai (Quelle des Website-Chats) lag noch auf einem älteren
Katalog-Stand.

**Ziel:** Kurs-Abschnitte + `mod_page`-Inhalte auf den neuen Katalog bringen,
damit Chat-Antworten die aktuelle Benennung und die neuen Plugins kennen.

**Ergebnis:**
- Kurs `eledia-ai-knowledge` (Kurs-ID 48) trägt 14 Abschnitte — Abschnitt 0
  „Über die eledia.ai Suite" plus je einen Abschnitt pro Katalogeintrag — und
  14 verwaltete `mod_page`-Aktivitäten mit `idnumber eledia-ai-<slug>`.
- Alle Slugs des aktuellen Katalogs sind vorhanden (`aktivitaetensuche`,
  `audit`, `h5p-autor`, `freitext`, `kursautor`, `tutor` eingeschlossen);
  verwaiste Abschnitte alter Slugs gibt es keine (`MAX(section) = 13`).
- Gastzugang ohne Passwort aktiv, Kurs sichtbar.
- `--reindex` hat alle 14 Seiten neu in `local_elediaai_sources` eingespielt.

**Verifikation:**
- Zwei aufeinanderfolgende Sync-Läufe mit identischem Katalog erzeugen
  identische Abschnittsnamen, Summary-Hashes und Seiten-Content-Hashes —
  Idempotenz nachgewiesen.
- RAG-Index nach dem Lauf: 14 Quellen zu Kurs 48, 0 verwaiste Quellen über
  alle Kurse.
- Chat über den Proxy auf suite.eledia.ai antwortet mit der kanonischen
  Benennung und kennt die neuen Einträge (Stichproben „KI Aktivitätensuche",
  „KI H5P-Autor", „KI Tutor" inkl. Status „In Vorbereitung"), Quellenlinks
  zeigen auf die aktuellen Seiten-IDs.

**Ausführungsnotiz 2026-08-06:** Der gescheiterte SSH-Versuch vom 2026-07-08
lag an einer falschen Host-Zuordnung auf dem Agenten-Rechner, nicht an demo;
demo ist über den in `docs/ci-cd.md` dokumentierten Deploy-Zugang erreichbar.
Auf demo läuft noch der Code-Stand vom 2026-07-02 (Plugin dort weiterhin
`local_lernhive_ai`, CLI unter `local/lernhive_ai/cli/`), deshalb wurde der
kanonische `content/suite-catalog.json` gegen die aktuelle CLI-Fassung über
STDIN gefahren. Sobald demo regulär deployt ist, gilt wieder der Standardweg:

```bash
docker compose -p moodle --env-file .env -f infra/docker-compose.yml exec -T moodle \
  php public/local/elediaai_core/cli/sync_knowledge_course.php \
  --catalog=- --guest --reindex < content/suite-catalog.json
```

**Restrisiko:** Der Moodle-Cron auf demo ist seit 2026-07-27 dauerhaft
suspendiert („Moodle upgrade pending"), weil der `cron`-Container auf einem
anderen Image läuft als der `moodle`-Container und sein Versions-Hash von
`allversionshash` in der DB abweicht. Die verwaisten Index-Einträge dieses
Syncs wurden deshalb einmalig über `admin/cli/adhoc_task.php` im
`moodle`-Container abgeräumt. Der Cron-Ausfall ist ein eigener Vorgang und
nicht Teil dieses Tasks.

### task13 — Repo-Architektur und tote Linien bereinigen

**Status:** erledigt (2026-06-30).

**Ergebnis:**
- Lokale Artefakte sind in `.gitignore` gekapselt: `public/`, `_staging-*/`
  und generierte Public-Screenshots.
- `local_lernhive` ist als externe Runtime-Integration dokumentiert; keine
  harte Moodle-Dependency im Suite-Plugin.
- Externe Plugins wie Translate/LiteRAG/RAGIngest/MCP bleiben ausserhalb dieses
  Repos und melden sich nur per `feature_provider` an.
- ActivityFilter oeffnet die KI-Aktivitaetssuche nur noch im Moodle
  Activity-Chooser, nicht mehr im Kurs-Plus-Menue.
- Suite-Karten nutzen Icon-Actions statt Text-CTA; Feature-Seiten zeigen
  wesentliche Funktionen pro Plugin.

**Verifikation:**
- `git diff --check`
- PHP-/JS-Syntaxchecks fuer geaenderte Dateien
- `rg` auf alte Plus-Menue-Hooks (`newContentDropdown`, `open-activityfilter`)

### task14 — AI Feedback Assignment-Quellen

**Status:** umgesetzt (2026-06-30).

**Problem:** `mod_aifeedback` konnte nur `mod_feedback`-Fragebogen als Quelle
auswaehlen. Der Hauptnutzen ist aber KI-Feedback auf echte Lernenden-Abgaben,
also Moodle-Aufgaben.

**Scope:**
- Neues Quellmodell `sourcetype/sourceid` mit Rueckwaertskompatibilitaet zu
  `feedbackid`.
- Setup-Form listet Moodle-Aufgaben und Moodle-Feedback-Aktivitaeten im Kurs.
- Sync liest `assign_submission` mit Status `submitted` und verarbeitet
  Online-Text sowie Dateinamen als Prompt-Kontext.
- Bestehender Review-/Release-/PDF-Workflow bleibt unveraendert.
- Backup/Restore und Testgenerator kennen die neuen Source-Felder.

**Bewusste Grenze:**
- Datei-Inhalte werden in diesem Schritt noch nicht extrahiert; aktuell gehen
  Dateinamen in den Prompt. Volltext aus PDF/DOCX/Uploads ist ein eigener
  Folgeausbau.

**Verifikation:**
- PHP-Lint fuer `mod/aifeedback`.
- PHPUnit-Test `test_sync_creates_draft_from_assignment_online_text`.
- Lokaler Moodle-Upgrade auf `mod_aifeedback=2026063001`.

### task09 — Lokales Deploy mit externem `local_lernhive`

**Status:** umgesetzt (2026-06-30), **abgelöst (2026-07-12)** — der
`LOCAL_LERNHIVE_PATH`-Inject wurde entfernt und durch Bind-Mounts in
`infra/docker-compose.local.yml` ersetzt (`local_lernhive` aus dem benachbarten
LernHive-Checkout).
Der Inject per `docker cp` schrieb durch den Mount hindurch aufs Host-Tree.
Details: [local-dev.md](local-dev.md).

**Scope:**
- `.env.local.example` dokumentiert `LOCAL_LERNHIVE_PATH`.
- `scripts/local-deploy.sh` kopiert bei gesetztem Pfad `local_lernhive` in
  `/public/local/lernhive`.
- Da `local_elediaai_tactics`, `local_elediaai_strategy` und
  `block_elediaai_tactics` im Image-Build ohne externen Core gepruned
  werden, werden sie nach dem Core ebenfalls lokal injiziert.

**Verifikation:**
- `bash -n scripts/local-deploy.sh`
- lokaler Deploy/Upgrade mit gesetztem `LOCAL_LERNHIVE_PATH`
- DB-Versionen: `local_lernhive`, `local_elediaai_tactics`,
  `local_elediaai_strategy`, `block_elediaai_tactics`

### task10 — Dashboard-Shell-Breite und Kartenraster angleichen

**Status:** umgesetzt (2026-06-30), Wide-Shell-Verdrahtung nachgezogen
(2026-08-04, SUI-602) — Dashboard nutzt das 3-Spalten-Raster
(`lh-plugin-grid--cols-3`); `index.php` öffnet die Plugin-Shell jetzt auch
tatsächlich mit `plugin_page::MODIFIER_WIDE` (88rem statt 72rem), damit das
`--cols-3`-Raster den Platz für drei Spalten bekommt statt bei Default-Breite
auf zwei zu kollabieren. Zone A und Kartenfläche passen so visuell zusammen.

**Verifikation:**
- PHP-Lint `public/local/elediaai_core/index.php`
- lokale Sichtprüfung `/local/elediaai_core/index.php`

Die Dashboard-Seite nutzt zusaetzlich das Moodle-`report`-Layout, damit der
aeussere Inhaltsbereich die Wide-Shell nicht wieder auf die schmale Standard-
Darstellung begrenzt (SUI-660).

### task11 — Translation-Span Follow-up fuer qtype_aitext-Felder

**Status:** umgesetzt (2026-07-27).

**Ergebnis:** `filter_eledia_translate\column_definition::format_fields()`
ordnet `qtype_aitext.graderinfo` und `qtype_aitext.responsetemplate` explizit
ihren Schemafeldern `graderinfoformat` und `responsetemplateformat` zu. Alle
drei Wartungstasks nutzen diese zentrale Zuordnung. PHPUnit prueft die
tatsaechliche Span-Verarbeitung sowie unveraenderte Formatwerte.

**Weiterhin separat:** `quiz.name` und `question.name` bleiben ein
Sonderfall, weil sie kein `...format`-Feld besitzen und daher nicht wie Rich-Text
stabilisiert werden.

### task12 — Kursgenerator-Track `local_elediaai_coursegen` vorbereiten

**Status:** umgesetzt (Stand 2026-07-25) — KG.1 bis KG.7 einschließlich
Lehrplanquelle, Auswahl-UI, KI-Pipeline, Kompetenz-Mapping, Review/Write,
Quiz-Befüllung sowie Tests/Doku sind im Kursgenerator abgeschlossen.

**Erledigt:**
- Neues Plugin `local_elediaai_coursegen` als separates Suite-Plugin angelegt.
- Feature-Provider meldet `coursegen` live in der AI Suite an und ersetzt den
  bisherigen Coming-soon-Stub.
- Shell-Einstieg `/local/elediaai_coursegen/index.php` nutzt die LernHive
  Plugin-Shell, wenn `local_lernhive` installiert ist, und faellt sonst auf die
  Suite-Missing-Core-Seite zurueck.
- Admin-Settings fuer den ersten System-Prompt registriert.
- Lehrplan-Provider und Auswahlformular, strukturierte `core_ai`-Pipeline,
  Kompetenz-Mapping und Review-vor-Schreiben umgesetzt.
- Quiz-Befüllung über Questiongen sowie PHPUnit-/Behat-Dokumentation ergänzt.
- Offen bleibt nur der optionale UX-Ausbau „Vorschlag vor dem Schreiben
  bearbeiten“ (`local_elediaai_coursegen` task07).

**Verifikation:**
- PHP-Lint fuer alle Dateien in `local_elediaai_coursegen`
- aktueller Pluginstand `local_elediaai_coursegen=2026070200` / `0.2.0`
- Suite-Registry meldet `coursegen=local_elediaai_coursegen:comingsoon=no`

### task08 — Premium-Policy-Gate und Addon `local_elediaai_core_premium`

**Status:** erledigt (2026-06-30) — PR #5 mit falscher `local_eledia`-/`local_eledia_premium`-Architektur wurde geschlossen; der Umbau laeuft im zentralen Plugin `local_elediaai_core` plus Addon `local_elediaai_core_premium`.

**Scope:**
- `local_elediaai_core` bleibt die zentrale Suite-/Launcher-Shell.
- Feature-Descriptors erhalten ein Tier (`free` default, `premium` optional).
- `registry::visible()` beruecksichtigt eine Policy-Schicht vor Capability-/Toggle-Pruefung.
- `local_elediaai_core_premium` liefert Premium-Feature-Descriptors und eine Site-Policy auf Basis expliziter Admin-Grants.
- CI-/Deploy-Matrix enthaelt `local/elediaai_core_premium`.

**Nicht-Ziel:**
- Kein neues `local_eledia` als zweites Umbrella-Plugin.
- Kein `local_eledia_premium`; der Component-Name ist `local_elediaai_core_premium`.

**Verifikation:**
- PHPUnit: `local_elediaai_core/tests/registry_test.php`
- PHPUnit: `local_elediaai_core_premium/tests/policy_test.php`
- Lokal per Moodle-CLI verifiziert: `registry::all()` kennt `tutorpremium`,
  ohne Grant ist es unsichtbar, mit Grant sichtbar, per
  `feature_tutorpremium_enabled = 0` wieder ausgeblendet.

### task07 — AI-Suite Devflow-Snapshot

**Status:** aktualisiert (2026-07-08) — zentrale Übersicht aller aktuell angebundenen AI-Plugins, Versionen, CI-/Deploy-Stand und offene Nacharbeiten.

**Aktueller Komponentenstand:**

| Bereich | Komponente | Version / Release | Status |
|---|---:|---:|---|
| AI Suite / Launcher | `local_elediaai_core` | `2026072300` / `0.2.9` | verfügbar; Feature-Discovery, Launcher, Audit, Shell, Token-Quotas, Core-Fallback, Feature-Keyfunctions |
| Strategie | `local_elediaai_strategy` | `2026072500` / `0.1.3` | MVP; Zielinterpretation, Matrix, Konfigurationsskizze, capability-gehärtetes Direct-Create für `mod_aichat` |
| Fragen-Generator | `local_elediaai_questiongen` | `2026070800` / `0.3.14` | verfügbar; Guided/Expert Flow, Review vor Import, Stale-Regenerate |
| Fragenbank-Button | `qbank_elediaai_questiongen` | `2026052300` / `0.1.1` | verfügbar; Einstieg in den Fragen-Generator aus der Fragenbank |
| AI-Text-Fragetyp | `qtype_aitext` | `2026072500` / `2.02-lernhive.7` | verfügbar; core_ai-Fork, Export/Backup/Restore, Settings-/External-/Spellcheck-Tests |
| KI-Chat Aktivität | `mod_aichat` | `2026072500` / `0.2.2` | verfügbar; persistente Threads, Prompt-Drift, Privacy und vollständige External-Tests |
| KI-Chat Block | `block_elediaai_chat` | `2026072501` / `0.2.3` | verfügbar; Persistenz/Services, External-/Privacy-Tests, Eingabe- und Burst-Schutz |
| AI Feedback | `mod_aifeedback` | `2026070900` / `0.7.1` | verfügbar; Sync aus `mod_assign` oder `mod_feedback`, Review, Release, PDF, Backup/Restore, Behat |
| Übersetzen | `filter_eledia_translate` | `2026072500` / `3.0.3` | verfügbar; DeepL, transaktionale Vorgänger-Migration, Async-Queue und TinyMCE-Begleitplugin |
| eLeDia.ai Tutor | `block_elediaai_tutor` | `2026072500` / `0.19.1` | verfügbar; Suite-Descriptor, globaler Tutor, Privacy-Löschung bis zum RAG-Dienst |
| Tutor Premium | `local_elediaai_tutor_premium` | `2026070700` / `0.3.1` | verfügbar; Premium-Erweiterung für den Tutor |
| H5P Author | `local_elediaai_h5pauthor` | `2026071200` / `0.4.0` | verfügbar; Wizard + MCP-Funktionen + AI-Suite-Descriptor |
| Tactics | `local_elediaai_tactics` | `2026072501` / `0.1.8` | Alpha; gemeinsame Modal-Laufzeit und deklarierter `core_ai`-Datenfluss |
| Tactics Kurs-Tools | `block_elediaai_tactics` | `2026072500` / `0.1.2` | Kurs-Launcher-Block mit gemeinsamem Tactics-Modal |
| Activity Filter | `local_activityfilter` | `2026072500` / `1.1.2` | Suite-Plugin; Activity-Chooser-Einstieg, `core_ai`-Privacy-Link, PHP-8.4-sauberer NLP-Pfad |
| Selfstudy | `local_elediaai_selfstudy` | `2026072500` / `0.2.1` | Beta; Standalone-UI, atomare Quota und manipulationsfeste Kompetenzempfehlungen |
| Teacher Tools | `local_elediaai_teachertools` | `2026072500` / `0.4.4` | Alpha; Wizard/Arbeitsblatt-Generator und deklarierter `core_ai`-Datenfluss |
| Lernpfad | `block_elediaai_path` | `2026070900` / `0.2.1` | Alpha; AI-Suite-Feature-Provider vorhanden |
| Kursgenerator | `local_elediaai_coursegen` | `2026070200` / `0.2.0` | Alpha; Lehrplan-Kompetenz-Kursgenerator |
| LiteRAG | `local_literag` | `2026072501` / `0.6.2` | Beta; Tutor-RAG/MCP-Laufzeit mit Kostenlimits und validierten Ingestion-Quellen |
| RAG Ingest | `local_elediaai_sources` | `2026070900` / `0.12.3` | Beta; Kursinhalte in die Tutor-Wissensbasis |
| MCP Webservice | `webservice_elediamcp` | `2026072400` / `1.5.1` | Stable; Moodle-Tooling fuer Tutor/MCP |

**Feature-Provider im Repo:** `block_elediaai_tutor`, `block_elediaai_chat`,
`block_elediaai_path`, `filter_eledia_translate`, `local_activityfilter`,
`local_elediaai_tutor_premium`, `local_elediaai_h5pauthor`, `local_elediaai_core`,
`local_elediaai_coursegen`, `local_elediaai_questiongen`,
`local_elediaai_selfstudy`, `local_elediaai_strategy`,
`local_elediaai_tactics`, `local_elediaai_teachertools`, `mod_aichat`,
`mod_aifeedback`, `qtype_aitext`.

**Externe Feature-Provider:** Weitere Plugins duerfen eine
`*\elediaai_core\feature_provider`-Klasse liefern, muessen aber ohne Suite
funktionsfaehig bleiben. Im aktuellen Repo sind Translate, LiteRAG, RAGIngest
und MCP bereits enthalten.

**CI-/Deploy-Stand:**
- Forgejo-Workflow `.forgejo/workflows/deploy.yml` ist kanonisch fuer dev/demo.
- Dev-Deploy laeuft bei Push auf `main`; Demo-Promotion laeuft bei Tags `v*`.
- Upgrade und Cache-Purge sind bewusst fatal (`set -e`, kein `|| true`), damit ein inkonsistenter Moodle-Stand den Deploy abbricht.
- Demo-Promotion startet danach den Wissensbasis-Sync (`sync_knowledge_course.php --guest --reindex`) nicht-fatal.
- Translation-Wartung nach Deploy nutzt nach dem Rename
  `filter_eledia_translate\task\insert_spans` und
  `filter_eledia_translate\task\copy_translations`.
- Der frühere Log-Befund zu `qtype_aitext.graderinfo` und `qtype_aitext.responsetemplate` ist mit task11 behoben; die gemeinsame Schemaerkennung ordnet beide Formatfelder explizit zu.

**Repo-Stand lokal:**
- Aktueller Arbeitsbranch dieser Runde: `main` auf `98c1dcd` plus uncommitted
  Arbeitsstand fuer Review-Fixes, A11y-Audit und KB-Sync-Dokumentation.
- `local_lernhive` ist kein Bestandteil dieses Repos; lokaler Dev-Deploy mountet
  es aus dem Nachbar-Checkout (`../Lernhive/lernhive`), siehe [local-dev.md](local-dev.md).

**Offene Nacharbeiten:**
- `quiz.name` und `question.name` bleiben Sonderfall: kein `...format`-Feld, daher nicht durch Rich-Text-Span-Task stabilisiert.
- Third-Party-Fragetypen jenseits der aktuellen Core-/AI-Text-Abdeckung nur ergänzen, wenn in der Demo/Produktion konkrete Tabellen/Felder auftauchen.

### task02 — mod_aifeedback (Track B)

**Status:** erledigt — Activity, AI-Suite-Kachel, `mod_feedback`-Sync, asynchrone `core_ai`-Generierung mit lokalem Fallback, Review/Edit/Release, Moodle-Notification, Learner-Webansicht und PDF-Download. Aus Code-Review + Folgearbeiten (2026-06-20) ergänzt: voller Privacy-Request-Provider, `course_module_viewed`-Event, **Backup/Restore**, **optionaler PDF-Anhang in der Notification**, **Regenerate-Button + Stale-Banner**, **PHPUnit-Coverage** (submission_sync + privacy) und **Behat-E2E-Workflow** (sync→review→edit→release→Student-Sicht, Regenerate/Stale, Released-Schutz). Verifikation auf der lokalen Instanz (PHPUnit/Behat-Lauf) steht noch aus.
**Plugin:** neu `mod_aifeedback`

Siehe Feature-Beschreibung. Voraussetzung: feat10 (Notification + PDF-Pipeline) zumindest teilweise — initial reicht Web-Ansicht + Messaging-Notification, PDF als follow-up.

**Code-Review-Befunde (2026-06-20):**
- ✅ behoben: Privacy-API nur `metadata_provider`, obwohl personenbezogene Tabelle deklariert → voller Request-Provider (Export/Delete, userlist) ergänzt.
- ✅ behoben: `view.php` löste kein `course_module_viewed`-Event aus (Logging/Reports) → Event-Klasse + Trigger ergänzt.
- ✅ behoben: veraltete Hilfetexte (`notifybody_help`, `prompt_help` verwiesen auf „nächsten MVP-Schnitt") an den umgesetzten Stand angepasst.
- ✅ behoben: kein Backup/Restore → `backup/moodle2/` (Backup-/Restore-Steplib + Tasks, Link-Encoding, intro-Files, optionale Result-Rows mit userinfo), `FEATURE_BACKUP_MOODLE2 => true`.
- ✅ behoben: keine Tests → PHPUnit für `submission_sync` (lokaler Fallback, regenerate, is_stale) und `privacy\provider` (Export/Delete/userlist) + Test-Generator.
- ✅ behoben: vollständiger Behat-Course-End-to-End-Workflow (task05/task02) — `tests/behat/teacher_workflow.feature` + Context `behat_mod_aifeedback` (seedet `mod_feedback`-Abgabe, verlinkt/ändert Prompt).
- ⚠️ bekannt/akzeptiert: `feedbackid` (FK auf `mod_feedback`) wird beim Restore bewusst nicht remappt — Lehrkraft wählt die Quell-Feedback-Aktivität nach Kurskopie neu.
- ⚠️ geringes Risiko: doppeltes Sync kann denselben Result-Row erneut als Adhoc-Task einreihen (idempotent in `generate_result`, daher unkritisch).

**Neue Features (2026-06-20):**
- Backup/Restore inkl. optionaler Lernenden-Ergebnisse (`userinfo`).
- Instanz-Option `attachpdf` → PDF-Anhang an die Freigabe-Notification (Messaging-Attachment via Modul-Filearea `notification`).
- Regenerate-Workflow: Stale-Banner bei geändertem Prompt (Hash-Vergleich), „Neu generieren" verwirft Cache und reiht neu ein; freigegebene Ergebnisse bleiben geschützt (feat09/task03 für `mod_aifeedback`).

**Aufwand:** ~3-5 Tage.

### task03 — Stale-/Regenerate-Pattern (feat09)

**Status:** erledigt (2026-06-21) — gemeinsamer Helper extrahiert, alle drei Anwendungsstellen umgesetzt.

Drei Anwendungsstellen:
- `local_elediaai_questiongen`: Generated-Hash + „neu generieren möglich" Banner — done für `coursecontents`
- `mod_aichat`: System-Prompt-Drift erkennen — done (2026-06-21): pro (Instanz, Nutzer:in) Thread-Hash in neuer Tabelle `aichat_thread`, Drift-Banner in `view.php` + sesskey-geschützter „Unterhaltung neu beginnen"-Reset
- `mod_aifeedback`: Stale-Banner über Prompt-Hash + Regenerate-Button — done (2026-06-20), auf Helper umgestellt

Gemeinsamer Helper `local_elediaai_core\local\stale_marker` (Hash + is_stale) ist gelandet; Ein-Element-Hash bleibt byte-kompatibel zu `sha1(trim($prompt))`, daher keine Migration nötig.

### task04 — Devflow-Docs 02..05 fertigstellen

**Status:** erledigt (2026-06-21) — `02-user-doc.md`, `03-dev-doc.md`, `05-quality.md` vollständig auf Deutsch neu geschrieben (Vorlage: `filter/translations`-Doc-Satz), korrekt zum aktuellen Code (Plugin-Shell, Feature-Descriptor/Provider, Audit-Reportbuilder, `stale_marker`, Tests). `00-master.md`/`01-features.md` bestanden bereits.

### task05 — Behat-Smoke-Coverage

**Status:** erledigt (2026-06-21) — `tests/behat/launcher_smoke.feature`: AI Suite öffnen → Feature-Karte → Settings-Cog → zurück; Audit-Tab nur für Admins (regulärer Nutzer sieht ihn nicht). Ohne `@javascript` (Launcher/Karten/Cog sind reine Links).

### task06 — Audit Privacy, Logging Settings und Anonymisierung

**Status:** erledigt (2026-06-21) — alle drei Bestandteile umgesetzt; Defaults erhalten das Bestandsverhalten (Opt-in). UX-Review vor aktivem Einsatz der Anonymisierung weiterhin empfohlen.

- ✅ Administrierbare Audit-Bestandteile (Prompt/Antwort/Fehlermeldung/Tokenwerte) über Admin-Settings + Shell-Form `audit_settings.php`, zentrale Leselogik `classes/local/audit_config.php`; Default alle sichtbar (= heute). Fehlertext-Export an bestehende `show_error()`-Sichtbarkeit gekoppelt.
- ✅ Anonymisierungsmodus `audit_anonymize_users` (Default `0` = Klarnamen): im anonymen Modus Rollen-/Kontextlabel statt Namen (`ai_action_audit::format_anonymized_actor`), User-Spalte/Filter konsistent getauscht.
- ✅ Privacy-API: `classes/privacy/provider.php` jetzt metadata-only mit `add_subsystem_link('core_ai', …)` — dokumentiert, dass die Audit-Basistabellen Moodle Core `core_ai` gehören; lokal liegen nur personenfreie Site-Settings (kein Export/Delete nötig). version `2026062101` / `0.2.3`.

---

## Offene Fragen (qXX)

### q08 — Sollen die KI-Aktivitäten ein türkises Quadrat behalten?

Gemessen am 06.09.2026 auf `/523`: Moodle 5.2 gibt Aktivitätssymbolen **keinen**
Kachelhintergrund mehr (`mod_forum`, `mod_quiz`: transparent) und färbt
stattdessen das Symbol. Die Suite malt auf `mod_aichat`, `mod_aifeedback` und
`mod_elli` weiterhin ein gefülltes türkises Quadrat — geschrieben für ein
Moodle, das zweckgefärbte Quadrate hatte.

Damit stechen die KI-Aktivitäten im Aktivitätswähler heraus, statt sich
einzureihen. Zwei vertretbare Antworten:

1. **Wie der Kern**: Quadrat weg, Symbol türkis einfärben. Die Suite fügt sich
   ein, die Zugehörigkeit bleibt an der Farbe erkennbar.
2. **Absicht**: das Quadrat bleibt, weil KI-Aktivitäten bewusst auffallen
   sollen — dann gehört der Satz als Begründung an die Stelle, denn der
   jetzige Kommentar begründet sie mit einem Moodle-Verhalten, das es nicht
   mehr gibt.

Die Entscheidung ist gestalterisch und gehört nicht in einen Lint-Task.


(keine — Tracks A und B können beide alleine starten)

---

## Completed (chronologisch absteigend)

- 2026-08-03 — task21: Suite-weiter Komponenten-Rename auf den `elediaai`-
  Namensraum (SUI-560). Zwölf Plugins haben einen neuen Frankenstyle-Namen:
  `local_lernhive_ai` → `local_elediaai_core`, `local_h5pauthor` →
  `local_elediaai_h5pauthor`, `block_lernhive_ai_chat` → `block_elediaai_chat`,
  `local_lernhive_teacher_tools` → `local_elediaai_teachertools`,
  `block_lernhive_tactics_tools` → `block_elediaai_tactics`, die übrigen
  nach dem Muster `lernhive_<x>` → `elediaai_<x>`. Der Tactics-Block trägt
  damit denselben Suffix wie `local_elediaai_tactics`; das ist kollisionsfrei,
  weil Moodle Komponenten nach (Typ, Name) schlüsselt — `block/…:addinstance`
  und `local/…:use` bleiben getrennte Capabilities. Mitgezogen sind
  Verzeichnisse, Namespaces, Sprachdateien, Capabilities, Webservice-Funktionen,
  Tasks, Events, AMD-Module und die vier betroffenen DB-Tabellen; die kurzen
  Tabellen-Aliasse (`local_lhqgen_*`, `local_lhss_*`, `local_lhstrat_*`)
  bleiben, weil sie den Komponentennamen ohnehin nicht tragen. Der
  Provider-Namensraum heißt jetzt `<frankenstyle>\elediaai_core` (Policy:
  `elediaai_core_policy`). **Kein Migrationspfad** — Moodle sieht neue Plugins,
  dev und demo werden neu installiert (Entscheidung Johannes im Issue).
  Bewusst *nicht* umbenannt: `mod_aichat`/`mod_aifeedback`/`mod_elli`
  (Modulnamen dürfen keine Unterstriche tragen), `local_literag`,
  `local_elediaai_sources`, die `eledia_*`-Altnamen und die Fremd-Plugins
  `qtype_aitext`, `tool_dynamic_cohorts`, `local_activityfilter` — letztere
  liegen bereits in Kundenrepos. `local_lernhive` und `local_customerportal`
  gehören dem lernhive-Repo und bleiben unangetastet.

- 2026-08-01 — task20: Nightly-Matrix und Moodle-5.1-Build korrigiert (SUI-429).
  Ein `schedule` liefert keinen `moodle`-Input; die Standardverzweigung im
  Workflow fiel deshalb auf die 4.5/5.2-Grenzen der schnellen Spur zurück und
  ließ Moodle 5.1 nachts für die meisten Gruppen aus (17 statt 26 Zellen).
  Die Auswahl liegt jetzt in `matrix_request()` in `scripts/ci-matrix.php`,
  gespeist aus `EVENT_NAME`/`MOODLE_INPUT`; der Workflow reicht nur noch Event,
  Input und geänderte Dateien durch. Außerdem stand für `MOODLE_501_STABLE` der
  Build von Moodle 5.0 (`2025041400`) statt `2025100600` — ein korrekt auf 5.1
  begrenztes Plugin wäre aus seiner eigenen Zelle gefallen.
  `scripts/ci-matrix-test.php` prüft Schedule-, Push- und Dispatch-Auswahl,
  die vollständige 26-Zellen-Nachtmatrix und die 5.1-Mindestversion.

- 2026-07-31 — task19: CI-Matrix an deklarierte Plugin-Versionen gebunden.
  `scripts/ci-matrix.php` liest `$plugin->requires` und den inklusiven
  `$plugin->supported`-Bereich aus den lokalen `version.php`-Dateien und erzeugt
  keine Installationszelle mehr außerhalb dieser Grenzen. Die Elli-Vertragstests
  und das 5.2-only Subscription-Plugin laufen in getrennten Gruppen. Der
  Regressionstest `scripts/ci-matrix-test.php` deckt Mindest- und Obergrenzen ab
  und wird im Forgejo-Changes-Job ausgeführt.

- 2026-07-24 — task17: Website-UX/UI- und Code-Review umgesetzt. Mobile Navigation,
  Skip-Link/Fokusführung, Kontrast und Touch-Ziele, semantische Heading-Struktur,
  gruppierte Pluginübersicht, Breadcrumbs, korrekte Bilddimensionen,
  Reduced-Motion und Chat-Fehler-/Retry-UX ergänzt. Chat-Quellen werden
  protokollbeschränkt validiert; Astro-Styles dynamischer Nachrichten greifen
  korrekt. Chat-Proxy validiert Konfiguration, Pfad, Content-Type und Bodygröße,
  zählt nur gültige Requests gegen Limits und besitzt Node-Integrationstests.
  Impressum und sitespezifischer Datenschutz ergänzt. Bericht:
  `website-ux-review-2026-07-24.md`.
- 2026-06-21 — task06: Audit-Privacy/Settings/Anonymisierung abgeschlossen. Admin-Settings für gespeicherte/angezeigte Audit-Bestandteile + Anonymisierungsmodus (Defaults = Bestandsverhalten), Privacy-Provider auf metadata-only mit `core_ai`-Subsystem-Link umgestellt (EN/DE-String ergänzt), version `2026062101`/`0.2.3`. UX-Freigabe vor aktiver Anonymisierung weiterhin empfohlen.
- 2026-06-21 — task04: DevFlow-Docs `02-user-doc.md`, `03-dev-doc.md`, `05-quality.md` für `local_elediaai_core` vollständig (DE, an `filter/translations` ausgerichtet, code-korrekt).
- 2026-06-21 — task05: Behat-Smoke für den AI-Suite-Launcher (`local_elediaai_core/tests/behat/launcher_smoke.feature`): Happy-Path (Suite → Karte → Settings-Cog → zurück) + Audit-Tab-Sichtbarkeit (Admin vs. regulär). Nutzt bestehende Custom-Steps, kein `@javascript`.
- 2026-06-21 — task03: Stale/Regenerate konsolidiert. Neuer Helper `local_elediaai_core\local\stale_marker` (+ PHPUnit), `mod_aifeedback` darauf umgestellt (verhaltensgleich), `mod_aichat` System-Prompt-Drift: neue Tabelle `aichat_thread` (Hash pro Instanz/Nutzer:in), Pinning beim ersten Turn (`send_message`), Drift-Banner + Reset in `view.php`, Privacy-Provider erweitert, EN/DE-Strings, version-Bumps auf 2026062100 (aichat/aifeedback/local_elediaai_core), Dependencies hochgezogen.
- 2026-06-20 — `mod_aifeedback` Behat-E2E-Workflow: `tests/behat/teacher_workflow.feature` (3 Szenarien: sync→review→edit→release→Student-Sicht inkl. PDF-Download-Link; Prompt-Änderung → Stale-Banner → Regenerate; Released-Schutz) + Context `tests/behat/behat_mod_aifeedback.php` (seedet `mod_feedback`-Abgabe, verlinkt Quell-Feedback, ändert Prompt). Läuft ohne `@javascript` (Regenerate-Confirm ist Progressive Enhancement).
- 2026-06-20 — `mod_aifeedback` offene Features: Backup/Restore (`backup/moodle2/`, `FEATURE_BACKUP_MOODLE2 => true`), optionaler PDF-Anhang in der Freigabe-Notification (`attachpdf` + Modul-Filearea), Regenerate-Button + Stale-Banner (Prompt-Hash), PHPUnit-Tests (`submission_sync`, `privacy\provider`) + Test-Generator, DB-Feld `attachpdf` (install.xml + upgrade), Version-Bump auf 2026062006 / Release 0.6.0.
- 2026-06-20 — Code-Review `mod_aifeedback` + Folgearbeiten: voller Privacy-Request-Provider (Export/Delete/Userlist analog `mod_aichat`), modul-eigene `course_module_viewed`-Event-Klasse + Trigger in `view.php` (Logging/Reports), veraltete Hilfetexte (`notifybody_help`, `prompt_help`) korrigiert, Version-Bump auf 2026062005.
- 2026-05-25 — Audit-Härtung nach Review: Quick-Filter allowlisted, Teilnehmerfilter auf den Aktionskontext begrenzt, Provider-Discovery gegen defekte Sibling-Plugins abgesichert und Fehlertext-Export an die Audit-Sichtbarkeitseinstellung gekoppelt.
- 2026-05-25 — Privacy-API-Baseline für KI-nahe LernHive-Plugins ergänzt: Chat-Block, Chat-Aktivität, Questiongen, Notifications, Addons-Audit, Testdaten sowie Null-Provider für reine Shell-/Link-Plugins. Untracked Compliance/Strategy bewusst ausgespart.
- 2026-05-25 — Audit in zwei Ebenen erweitert: Technisches Audit mit Requests, Fehlern, Unterstützungsaktionen und Tokens; didaktisches Audit mit besonders häufig zusammengefassten/erklärten Kontexten als erster Indikator für Unterstützungsbedarf.
- 2026-05-25 — KI-Suite-Hilfe bleibt in der KI-Suite-Shell: `/local/elediaai_core/help.php` rendert das Handbuch mit KI-Suite-Zone-A und Navigation.
- 2026-05-25 — Launcher-Konzept vereinfacht: Astroid-Icon öffnet direkt die KI-Suite-Übersicht; Feature-Auswahl lebt nicht mehr im Launcher-Flyout.
- 2026-05-25 — KI-Suite-Texte getrennt: kurze Übersichtskarten, längere Nutzenbeschreibung auf Feature-Seiten, Handbuch als Bedienhilfe. Audit-Feature verlinkt direkt zum Report.
- 2026-05-25 — Audit-UX Schritt 3: kompakte Tabelle im LernHive-Stil, Prompt/Antwort/Fehler als Icon-Modal, Action als Icon, Status zusammengeführt, Tokens mit Tooltip, Inhaltskontext verlinkt und One-Click-Filter ergänzt.
- 2026-05-25 — Audit-UX Schritt 2: Fehlgeschlagene KI-Aktionen werden nach Reportbuilder-Render per AMD markiert und in der Tabelle mit roter Zeilenbetonung hervorgehoben.
- 2026-05-23 — task07: Questiongen in die Plugin Shell integriert. Feature-Karte entfernt Body-Actions („Funktion öffnen", Inline-Einstellungen, Zurück-Link), Section-Nav bekommt „KI-Suite", Settings laufen über `/local/elediaai_questiongen/configuration.php`, Help routet auf das `local_elediaai_questiongen`-Handbuch. Zusätzlich Support-Hub-Kontext früh gesetzt, damit Help-Seiten ohne `$PAGE->context`-Notice rendern.
- 2026-05-23 — task01: Edit-before-release für `local_elediaai_questiongen` umgesetzt und auf strukturierte Review-Vorschau erweitert. Async-Task setzt Jobs auf `review`, `review.php` zeigt pro Frage Fragetyp, Name, Fragetext, Antwortoptionen, korrekte Antwort und Feedback mit Auswahl, Original-Hash und Buttons „Auswahl importieren" / „Alle importieren" / „Verwerfen"; XML bleibt Experten-Fallback. Import passiert erst nach Lehrerfreigabe.
- 2026-05-23 — task06: Deploy-Hotfix für Moodle OpenAI-Provider ergänzt. `playbooks/deploy.sh` patcht `process_generate_text.php` idempotent auf null-safe `system_fingerprint`, damit GPT-5/OpenAI-kompatible Responses ohne Fingerprint nicht mehr crashen.
- 2026-05-16 — Audit-UX Schritt 1: Eye-Icon-Modal für Prompt/Antwort, ✓/✗-Erfolg-Icon, Tokens als kombinierte Spalte, Pagesize 25 (siehe test11/test12/test13)
- 2026-05-16 — local_elediaai_core Seiten in Plugin-Shell wrappen + feature-scope Section-Nav
- 2026-05-16 — Audit Stufe 2 (Prompt/Response-Spalten via eigene Entity-Subclass)
- 2026-05-16 — qtype_aitext auf Plugin-Shell umgestellt, Backend-Dropdown raus
- 2026-05-16 — Audit Stufe 1 (core_ai usage-Report im Suite-Dashboard)
- 2026-05-16 — Launcher-Polish (Renames, KI-Chat-Dublette aufgelöst, lint-Whitelist)
- 2026-05-16 — Launcher-Visuals an LH-Stil + Feature-Infoseiten
- 2026-05-16 — Launcher-Pille neben LernHive-Launcher in der Navbar
- 2026-05-16 — Astroid-Icon Launcher mit Coming-Soon-Roadmap-Tiles
- 2026-05-16 — mod_aichat-Activity-Scaffolding
- 2026-05-15 — Persistente Chat-Threads (Phase 1)
