# UAT-Prozess — Ablauf einer Testrunde (Maintainer)

> Für die Person, die eine manuelle Testrunde organisiert und auswertet.
> Das Tester-Team bekommt stattdessen [uat-handbuch.md](uat-handbuch.md).
> Die Testfälle je Plugin liegen in `public/<typ>/<name>/docs/06-uat.md`.

## Überblick

Manuelles Testen läuft in **Runden gegen einen fixen Tag**. Nie gegen dev
testen — der Stand verschiebt sich sonst mitten in der Runde. Ergebnisse
fließen nach der Runde zurück in den DevFlow (`04-tasks.md`, `05-quality.md`).

```
Tag setzen → demo promoten → Issues erzeugen → Team testet → Auswertung → Fixes → nächste Runde
```

## Schritt für Schritt

### 1. Stand einfrieren
```bash
git tag vX.Y.Z && git push eledia vX.Y.Z
```
Der `v*`-Tag promotet automatisch auf **demo.eledia.ai** (siehe
[ci-cd.md](ci-cd.md)). Diesen Tag in allen Issues der Runde nennen.

### 2. Testzugänge bereitstellen
Je Rolle ein Konto (Admin / Trainer:in / Teilnehmer:in). Zugangsdaten
**separat** ans Team geben — nicht in Issues, nicht ins Repo.

### 3. Issues erzeugen
Ein Issue pro Plugin und Runde. Zwei unterstützte Tracker:

- **Jira (CSV-Import):**
  ```bash
  python3 scripts/uat-jira-export.py <runde> uat-runde-<runde>.csv
  ```
  erzeugt eine CSV (1 Task/Plugin, Labels `uat` + `uat-runde-<n>`,
  Component = Plugin-Komponente, Testfall-Checkliste + Verweis in der
  Beschreibung). Import: Jira → *Import issues from CSV*, Projekt-Key und
  Spalten zuordnen. Mapping speichern → nächste Runde geht schneller.
- **Forgejo:** Issue-Template `.forgejo/issue_template/uat-testrunde.md`
  je Plugin nutzen; uatNN-Checkliste aus der jeweiligen `06-uat.md` kopieren.

Für den Start ruhig einen kleinen Batch statt aller 27 Plugins wählen
(z.B. Suite-Shell + Tutor + questiongen + teacher_tools + selfstudy + die
testarmen taskrunner/languageselect).

### 4. Team testet
Das Team arbeitet nach [uat-handbuch.md](uat-handbuch.md): je Fall Status
(OK / Fehler / Unklar), Funde als Kommentar mit Severity S1–S3 + Screenshot.

### 5. Auswertung → zurück in den DevFlow
Nach der Runde alle Issues durchgehen und **mechanisch zurückschreiben**:

- **Bug / Unklarheit** → neue `taskXX`/`qXX` in der `04-tasks.md` des
  betroffenen Plugins, mit Verweis auf Issue-ID und Severity.
- **Testergebnis der Runde** → Abschnitt „UAT-Runden" in der `05-quality.md`
  des Plugins: Datum, getesteter Tag, Tester, Bestanden-Quote (x/N Fälle
  grün), offene Punkte. Optional dasselbe kurz am Ende der `06-uat.md`.

Damit bleibt der DevFlow die eine Wahrheit; die Tracker-Issues sind nur der
Erfassungskanal, nicht das Gedächtnis.

### 6. Fixes und nächste Runde
Gemeldete Bugs normal beheben (Version bumpen), neuen Tag setzen, betroffene
Plugins in der nächsten Runde erneut testen lassen.

## Rollen im Prozess

| Rolle | Tut was |
|---|---|
| Maintainer (du) | Tag, Promotion, Issues erzeugen, Auswertung → DevFlow |
| Test-Team | Fälle abarbeiten, Funde melden (kein Repo-Zugriff nötig) |
| Claude | Auf Zuruf „werte UAT-Runde aus": Issues/Kommentare → `04-tasks.md`/`05-quality.md`. Braucht dafür Zugriff auf die Ergebnisse (Atlassian-MCP freigeschaltet oder CSV-Export der gelösten Issues). |

## Dateien

- `public/<typ>/<name>/docs/06-uat.md` — Testfälle je Plugin
- `public/local/elediaai_core/docs/uat-handbuch.md` — Tester-Handbuch
- `.forgejo/issue_template/uat-testrunde.md` — Forgejo-Issue-Vorlage
- `scripts/uat-jira-export.py` — Jira-CSV-Generator
