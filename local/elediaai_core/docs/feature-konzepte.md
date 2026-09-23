# Feature-Konzepte — Top 3 mit Marktanalyse

> Stand: 2026-06-28. Auswahl der drei aussichtsreichsten Features aus der Ideensammlung,
> geschärft anhand einer kurzen Marktanalyse (was existiert schon, wo ist die Lücke).
> Auswahlkriterium: **größte Marktlücke × beste Passung zu vorhandenen eLeDia-Assets**
> (Rahmenlehrplan-Wissen, MCP-Server, AI-Suite-Fundament).

---

## Marktanalyse (Kurzfassung)

### Was es schon gibt

**KI-Kursgeneratoren — stark besetzt.** CourseAI (offizielles Moodle-Plugin), Datacurso
Course Creator AI, Edwiser Smart AI Course Creator, Lumination AI, Dixeo Course Generator,
Tresipunt Course IA. Muster überall gleich: *Thema oder PDF rein → Kursgerüst mit Abschnitten,
Aktivitäten, Quizzen raus.* Generisch, nicht lehrplangebunden.

**RAG-Chatbots — zunehmend Commodity.** Terus RAG, uteluq Chatbot, block_ai_chat,
moodle-rag (pascalhuerten), Asyntai. Liefern „geerdete" Antworten aus Kursmaterial.
Differenzierung fast nur noch über Qualität, DSGVO und Sprache.

**Adaptive Lernpfade — fragmentiert, kein Standard.** IADLearning (externe Fremdinhalte),
Personalised Study Guide (`format_psg`, lernstil-basiert nach Felder-Silverman),
education-ai/adaptive-learning-path (Generator). Wenige binden an **native Moodle-Kompetenzen**
+ echte Quiz-Leistung.

**Kompetenz-Tooling (DE) — vorhanden, aber ohne KI.** Exabis Competencies, KOMET, DAKORA,
Moodle-Kompetenzrahmen (KMK). Curriculum-Bezug ja — aber manuelle Pflege, keine KI-Generierung.
KIULY bietet lehrplan-konforme Planung **für** Lehrkräfte, ist aber eine **externe App**, kein
Moodle-Plugin, das direkt im LMS Kurse baut.

### Wo die Lücke ist

1. **Kein Generator ist an offizielle deutsche Rahmenlehrpläne gebunden** und verknüpft das
   Ergebnis automatisch mit Moodle-Kompetenzrahmen. Genau hier hat eLeDia mit dem
   `mathe-rahmenlehrplaene`-Wissen (16 Bundesländer, alle Stufen) ein einzigartiges Asset.
2. **Adaptivität ist selten an native Moodle-Kompetenzen gekoppelt** — meist Lernstil oder
   externe Inhalte. Eine KMK-/kompetenzbasierte Steuerung fehlt.
3. **Fast alle KI-Plugins sind read-only** (Chat/Antwort). **Handelnde** Agenten, die kuratierte
   Moodle-Aktionen ausführen, gibt es kaum — und eLeDia besitzt mit `webservice_elediamcp`
   bereits die seltene Infrastruktur dafür.

**Fazit:** Nicht „noch ein Kursgenerator/Chatbot", sondern die deutsche Lehrplan-/Kompetenz-Bindung
und der MCP-Aktionslayer sind die verteidigbaren Alleinstellungsmerkmale.

---

## Feature 1 — Lehrplan-Kompetenz-Kursgenerator

**Einzeiler:** Aus einem offiziellen Rahmenlehrplan (Bundesland, Fach, Klasse) erzeugt die KI
ein vollständiges Moodle-Kursgerüst **und** legt automatisch die passenden Kompetenzen im
Moodle-Kompetenzrahmen an und verknüpft Aktivitäten damit.

**Warum gewinnen wir:** Alle Wettbewerber starten bei „Thema/PDF". Wir starten beim **verbindlichen
Lehrplan** und liefern direkt **kompetenzgekoppelte** Kurse — etwas, das Schulen/Berufsschulen in
DE wirklich brauchen (KMK-Konformität, Nachweisbarkeit). Asset: `mathe-rahmenlehrplaene`-Skill.

**Plugin-Typ:** `local`-Plugin (z. B. `local_elediaai_coursegen`), baut auf `local_elediaai_core`
(AI-Manager) auf; nutzt Moodle Competency-API + course-Erstellung.

**Kernkomponenten:**
- Auswahl-Dialog: Bundesland → Fach → Klasse/Stufe → Themenbereich (Datenquelle: Lehrplan-Wissen).
- Generierungs-Pipeline: Lernziele/Kompetenzen → Abschnittsstruktur → Aktivitäten (Seiten, Aufgaben,
  Quiz-Platzhalter) → optional Verknüpfung mit `local_elediaai_questiongen` für echte Fragen.
- **Kompetenz-Mapping:** automatisches Anlegen eines Kompetenzrahmens + Zuordnung der Kompetenzen
  zu den erzeugten Aktivitäten (das macht kein Wettbewerber).
- Teacher-Review-Schritt vor dem Schreiben in den Kurs (nichts wird ungefragt angelegt).

**MVP (klein halten):** ein Bundesland + Fach Mathematik (vorhandenes Lehrplan-Wissen), Ausgabe =
Abschnitte + Seiten + Kompetenzrahmen, ohne Quiz-Generierung. Danach Fächer/Länder erweitern.

**Abhängigkeiten:** `local_elediaai_core`. Optional `local_elediaai_questiongen` (Quizfragen),
`mathe-rahmenlehrplaene`-Wissen als Datengrundlage.

**Aufwand:** L–XL. **Differenzierungsgrad:** Sehr hoch (klare Lücke).

---

## Feature 2 — Kompetenzbasierter adaptiver Lernpfad

**Einzeiler:** Die KI wertet Quiz-/Aktivitätsergebnisse gegen die Moodle-Kompetenzen aus und
empfiehlt der/dem Lernenden kontinuierlich den nächsten Schritt — *vorrücken, festigen* oder
*Lücke schließen* — mit kurzer Begründung.

**Warum gewinnen wir:** Bestehende Lösungen sind lernstil-basiert oder an externe Inhalte gebunden.
Eine Steuerung über **native Moodle-Kompetenzen** (KMK-anschlussfähig) ist die Lücke — und sie
**nutzt direkt die Kompetenz-Mappings aus Feature 1** (starke Synergie: gemeinsames Datenmodell).

**Plugin-Typ:** Kombi aus `block` (Lerner-Sicht: „Dein nächster Schritt") + `local`-Logik;
ggf. Course-Format-Variante analog `format_psg`, aber kompetenz- statt lernstil-getrieben.

**Kernkomponenten:**
- Mastery-Tracking: Aggregation von Bewertungen/Quiz-Resultaten pro Kompetenz.
- Empfehlungs-Engine: Regel + KI-Begründung (advance / reinforce / remediate) mit Schwellenwerten.
- Lerner-Block mit konkreter nächster Aktivität + „warum"; Lehrer-Dashboard mit Kohortenüberblick.
- Frühwarnung an Lehrkraft bei stagnierender Mastery (verknüpfbar mit Moodle-Messaging).

**MVP:** ein Kurs, eine Kompetenz-Dimension, Empfehlung aus Quiz-Score-Schwellen + KI-Begründungstext;
Block zeigt „nächste Aktivität". Personalisierung später ausbauen.

**Abhängigkeiten:** `local_elediaai_core`; idealerweise Kompetenzdaten aus Feature 1.

**Aufwand:** L. **Differenzierungsgrad:** Hoch.

---

## Feature 3 — MCP-Aktions-Agent für Moodle (statt nur Chat)

**Einzeiler:** Ein KI-Agent, der Lehrer-/Admin-Aufgaben in natürlicher Sprache nicht nur
**beantwortet**, sondern über kuratierte, geprüfte MCP-Tools **ausführt** — Aktivität anlegen,
Abgaben vorbewerten, Nachricht senden, Kurs umstrukturieren — jeweils mit Bestätigungsschritt.
Antworten auf Inhaltsfragen sind per RAG aus Kursmaterial **mit Quellenbeleg** geerdet.

**Warum gewinnen wir:** Der RAG-Chat-Markt ist voll, aber fast alle Plugins sind **read-only**.
eLeDia besitzt mit `webservice_elediamcp` bereits einen MCP-Server — die seltene Grundlage für
**handelnde** Agenten. Das ist der eigentliche Burggraben, nicht das Antworten an sich.

**Plugin-Typ:** Erweiterung `webservice_elediamcp` (neue kuratierte Schreib-Tools) +
`block_elediaai_chat`/`mod_aichat` als Agenten-Frontend.

**Kernkomponenten:**
- Kuratierte MCP-Schreib-Tools mit Capability-Checks (Rolle/Recht) und Audit-Log.
- **Bestätigungs-Flow** („Diese 3 Aktivitäten anlegen? [Vorschau]") vor jeder schreibenden Aktion.
- RAG-Grounding über Kursmaterial mit **Quellenangabe** (reduziert Halluzination; DSGVO: nur
  freigegebene Quellen).
- Vollständiges Audit/Reporting (knüpft an die Audit-Seite in `local_elediaai_core` an).

**MVP:** 2–3 sichere Schreib-Tools (z. B. „Seite/Abschnitt anlegen", „Nachricht senden") mit
Bestätigung + Audit; RAG-Grounding mit Quellen für reine Fragen.

**Abhängigkeiten:** `webservice_elediamcp`, `local_elediaai_core`.

**Aufwand:** M–L. **Differenzierungsgrad:** Hoch (einzigartiges Asset MCP).

---

## Priorisierungs-Entscheidung (2026-06-28)

| Feature | Entscheidung | Begründung |
|---|---|---|
| **1 — Kursgenerator** | ✅ **GO, hohe Priorität** | Kein Moodle-natives Pendant am Markt → klare, verteidigbare Lücke. Umsetzung als Task-Track `KG.*` in `roadmap.md`. |
| **2 — Adaptiver Lernpfad** | ⏸ **Geparkt (Big Win, Scope-Risiko)** | Hoher Nutzen, aber zu groß. Später als schlanker MVP (1 Kurs, 1 Kompetenz-Dimension, Score-Schwellen) neu aufsetzen. |
| **3 — MCP-Aktions-Agent** | ↓ **Zurückgestellt** | MCP-Infrastruktur (`webservice_elediamcp`) existiert bereits — geringerer Neu-Nutzen, später punktuell ausbauen. |
| **Kompetenz-Mapping (Teil v. 1/2)** | 💾 **Gespeichert, Nische** | Wertvoll, aber Nischenmarkt. Als Baustein in Feature 1 mitgeführt, kein eigenständiges Produkt. |

**Nächster Schritt:** Feature 1 ist in `roadmap.md` als Task-Track `KG.1`–`KG.7` ausgearbeitet.

## Quellen
- [Course Creator AI (local_coursegen)](https://moodle.org/plugins/local_coursegen)
- [Moodle CourseAI](https://moodle.com/news/moodle-plugin-courseai/)
- [Datacurso Course Creator AI](https://datacurso.com/)
- [Edwiser Smart AI Course Creator](https://edwiser.org/smart-ai-course-creator-for-moodle/)
- [Lumination AI](https://moodle.org/plugins/local_lumination)
- [Dixeo Course Generator](https://moodle.org/plugins/block_dixeo_coursegen)
- [Terus RAG](https://moodle.org/plugins/block_terusrag)
- [Chatbot (uteluq)](https://moodle.org/plugins/block_uteluqchatbot)
- [AI Chat (block_ai_chat)](https://moodle.org/plugins/block_ai_chat)
- [moodle-rag (GitHub)](https://github.com/pascalhuerten/moodle-rag)
- [Best AI Chatbot for Moodle 2026 (Asyntai)](https://asyntai.com/blog/best-ai-chatbot-for-moodle/)
- [IADLearning](https://moodle.org/plugins/mod_iadlearning)
- [Personalised Study Guide format (format_psg)](https://moodle.org/plugins/format_psg)
- [adaptive-learning-path (GitHub)](https://github.com/education-ai/adaptive-learning-path)
- [Moodle Kompetenzrahmen (Doku)](https://docs.moodle.org/502/de/Kompetenzrahmen)
- [KIULY](https://kiuly.de/)
