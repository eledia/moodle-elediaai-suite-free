# Was das KI-Audit heute sieht — und was nicht

**Stand:** 05.09.2026 · Erhoben an `52` · Autor: Bestandsaufnahme vor einer
Entscheidung, nicht Entwurf einer Lösung.

Der Betreiber hat am 05.09.2026 entschieden: **erst aufnehmen, was heute wo
protokolliert wird, dann entscheiden.** Dieses Dokument ist die Aufnahme.

---

## Kurzfassung

Das Audit zeigt genau die KI-Aktionen, die durch Moodles `core_ai` gelaufen
sind. Das sind zwölf von fünfzehn Funktionen der Suite. Drei Wege daran vorbei
sind **unsichtbar**, obwohl auf ihnen die mit Abstand meisten Aufrufe liegen —
darunter jede einzelne Chatnachricht an den Tutor.

Die Einstiegsseite verspricht mehr, als das Audit hält.

---

## Was gemessen wurde

Der Bericht setzt auf Moodles eigener Tabelle auf:

```php
// classes/reportbuilder/local/systemreports/audit.php
$this->set_main_table('ai_action_register', $entitymainalias);
```

Alles, was nicht in `ai_action_register` steht, kann er nicht zeigen. Die Frage
ist also allein: **wer schreibt dort hinein?**

---

## Drei Kategorien

### 1. Im Audit sichtbar — der Weg über `core_ai`

Diese Funktionen rufen `quota_aware_ai_manager::process_action()`, das
intern `\core_ai\manager::process_action()` bedient. Moodle schreibt dabei
selbst nach `ai_action_register`.

`local_elediaai_questiongen` · `local_elediaai_coursegen` ·
`local_elediaai_h5pauthor` · `local_elediaai_selfstudy` ·
`local_elediaai_strategy` · `local_elediaai_tactics` ·
`local_elediaai_teachertools` · `mod_aifeedback` · `qtype_aitext` ·
`local_activityfilter` · `block_elediaai_path` · `webservice_elediamcp`

**Diese Hälfte funktioniert wie beworben.**

> **Korrektur vom 05.09.2026, nach der ersten Fassung.** „Die Chat-Turns sind
> unsichtbar" war zu grob. Sie fehlen im Audit, sind aber seit jeher in einem
> **zweiten Register** verzeichnet: `local_aitransparency_rec`. Das speichert
> bewusst nur einen `contenthash`, nie den Text, und beantwortet eine andere
> Frage (Art. 50: wurde diese eine Ausgabe von einer KI erzeugt?). Vier Stellen
> schreiben dorthin — Chat-Engine, LiteRAG, `qtype_aitext` und
> `aiprovider_eledia`.
>
> Wichtig für jede künftige Zusammenführung: `aiprovider_eledia` schreibt in
> **beide** Register, und das Feld `registerid`, das die Zuordnung tragen soll,
> **setzt niemand**. Eine naive Vereinigung beider Quellen zählt dieselbe
> Aktion doppelt.

### 2. In der Quota, aber nicht im Audit — `local_elediaai_chatengine`

Die Chat-Engine bucht ihre Turns über den Kompatibilitäts-Einstieg
`quota_aware_ai_manager::process_callback()`. Der reserviert, verbucht und gibt
frei — und schreibt **nichts** nach `ai_action_register`:

```php
// classes/quota_aware_ai_manager.php, process_callback()
$reservation = quota_manager::reserve(...);
$result = $callback();          // eigener Transport, kein core_ai
quota_manager::commit(...);     // nur das Guthaben-Hauptbuch
```

**Folge:** Jede Nachricht an den Tutor, an `mod_aichat` und an `mod_elli` zählt
gegen das Tagesbudget, taucht im Audit aber nicht auf. Wer fragt „welche
KI-Funktion wird eigentlich benutzt?", bekommt eine Antwort, in der die
meistgenutzte fehlt.

Sie ist der **einzige** Aufrufer dieses Einstiegs.

### 3. Weder im Audit noch in der Quota

| Plugin | Weg | Was dort passiert |
|---|---|---|
| `filter_eledia_translate` | `deepl_endpoint.php` direkt zu DeepL | Übersetzungen, kein `core_ai`, keine Quota-Buchung |
| `local_elediaai_sources` | `api_client.php` zum Ingest-Dienst | Indexierung von Kursmaterial; die Einbettungen entstehen serverseitig |
| `local_literag` | eigener Transport zum RAG-Backend | Retrieval und Antwortgenerierung |

> **Nachtrag 06.09.2026 — diese drei sind keine Lücke, sondern Absicht.**
> Vom Betreiber richtiggestellt, und was nachprüfbar war, ist nachgeprüft:
>
> - **`local_elediaai_sources` braucht kein LLM.** Es wählt Material aus und
>   schickt es zur Indexierung; die Einbettungen entstehen beim Backend. Im
>   Quelltext bestätigt: kein `core_ai`-Aufruf im ganzen Plugin. Ohne
>   LLM-Aufruf gibt es nichts, was gegen ein Token-Guthaben zählen könnte.
> - **`local_literag` wird über Chat-Engine und Tutor gemessen.** Das Retrieval
>   passiert innerhalb eines Turns, der dort bereits gebucht und — seit !59 —
>   anonym protokolliert wird. Es getrennt zu zählen hieße, denselben Vorgang
>   zweimal zu buchen.
> - **DeepL ist ein eigener Guthabentopf.** Der Übersetzungsfilter rechnet
>   nicht in Tokens ab und gehört nicht in die Token-Quota der Suite.
>
> Der ursprüngliche Absatz stand hier, weil ich von „geht an `core_ai` vorbei"
> auf „wird nicht erfasst" geschlossen habe. Das eine folgt nicht aus dem
> anderen.

---

## Der Text verspricht zu viel

`audit_intro` ist **ehrlich** und nennt die Quelle beim Namen:

> Source is Moodle core, which logs every `core_ai` action to
> `ai_action_register` …

Die beiden Texte auf der Kachel und der Detailseite sind es nicht:

> `feature_audit_desc`: „Review AI usage: users, actions, providers, token
> usage and errors **at a glance**."
>
> `feature_audit_detail`: „Decision makers can see **which AI features are
> used** … Strategically, it supports **governance, privacy review and cost
> control**."

Genau das kann ein Bericht nicht leisten, dem die Chat-Nutzung fehlt. Für
Datenschutzprüfung wiegt das am schwersten: die Prompts, die am ehesten
personenbezogene Daten enthalten, sind die aus dem Chat — und die sind nicht
darin.

---

## Drei Wege, mit Aufwand und Preis

### A — Nur die Texte richtigstellen

Die beiden Werbetexte sagen, was das Audit ist: eine Sicht auf die
`core_ai`-Aktionen. Kein Code, eine Stunde.

*Dafür:* Die Aussage stimmt sofort, und niemand trifft eine Governance-
Entscheidung auf falscher Grundlage.
*Dagegen:* Die Lücke bleibt. Wer die Chat-Nutzung sehen will, kann es nicht.

### B — Die Chat-Engine in `ai_action_register` schreiben lassen

Ein Aufrufer, ein Einstiegspunkt. `process_callback()` bekommt dieselbe
Registerschreibung wie `process_action()`.

*Dafür:* Schließt die größte Lücke an der schmalsten Stelle. Die Turns tragen
bereits Nutzer, Komponente, Tokenzahlen und Erfolg — alles, was das Register
braucht.
*Dagegen:* Zu klären, ob wir in Moodles Tabelle schreiben dürfen, ohne durch
`core_ai` zu gehen. Die Detailtabellen (`ai_action_generate_text`) gehören zu
den Kern-Aktionstypen; ein Chat-Turn ist keiner davon. Und: **die
Prompt-Texte des Chats würden im Audit sichtbar** — das ist eine
Datenschutzentscheidung, keine technische.

### C — Ein eigenes Register für die ganze Suite

Alles schreibt in eine eigene Tabelle, das Audit liest beides.

*Dafür:* Das einzige, was auch Ingest, Retrieval und Übersetzung erfasst.
*Dagegen:* Die größte der drei Aufgaben — eigene Tabelle, Aufbewahrungsfrist,
Privacy-Provider, Migration und die Frage, wie zwei Quellen in einem Bericht
zusammenkommen, ohne doppelt zu zählen.

---

## Was daraus geworden ist

**A ist umgesetzt** — die beiden Werbetexte sagen jetzt, was der Bericht zeigt.

**B ist umgesetzt, in der vom Betreiber am 05.09.2026 gewählten Form:**
„Prompt und Antwort und Ort/Kurs sollen erfasst werden, aber nicht die
Nutzer-Identifikation."

Die Chat-Engine schreibt ihre Turns über
`local_elediaai_core\local\audit_recorder` nach `ai_action_register` und in die
Detailtabelle — **mit** Prompt, Antwort, Ort und Tokens, **ohne** Person.
`userid` ist 0, gesetzt beim Schreiben, nicht verborgen beim Anzeigen: die
Identität erreicht die Tabelle nie, also kann keine spätere Einstellung, kein
Export und keine Abfrage sie zurückholen.

Das Transparenz-Register kam dafür nicht in Frage — es hält von Bauart keinen
Text. Beide bleiben nebeneinander bestehen und sind Spiegelbilder: das eine
behält die Person und hasht den Inhalt, das andere behält den Inhalt und lässt
die Person weg.

**C bleibt offen** und braucht vorher das gesetzte `registerid`.

---

## Ursprüngliche Empfehlung (Stand vor der Entscheidung)

**A sofort, dann B.** Die Texte kosten nichts und beenden ein Versprechen, das
heute nicht gehalten wird. B schließt danach die größte Lücke an der Stelle
mit dem geringsten Eingriff — vorbehaltlich der Datenschutzfrage, wer die
Chat-Prompts sehen darf.

C erst, wenn jemand danach fragt: Ingest und Retrieval sind Maschinenlast, die
kein Mensch ausgelöst hat, und gehören eher in eine Betriebsüberwachung als in
ein Audit, das Fragen über *Personen* beantwortet.

---

## Was hier nicht drinsteht

- Ob die Kern-Detailtabellen einen Chat-Turn überhaupt aufnehmen können. Zu
  prüfen, bevor B beschlossen wird.
- Wie viele Aufrufe tatsächlich auf welchem Weg laufen. Auf einer
  Entwicklungsinstanz nicht messbar; auf einer echten Installation wäre die
  Zahl das stärkste Argument für oder gegen C.
