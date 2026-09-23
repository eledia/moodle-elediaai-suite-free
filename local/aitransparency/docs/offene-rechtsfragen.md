# Offene Rechtsfragen — `local_aitransparency`

Diese Datei sammelt Einstufungs- und Rechtsfragen, die aus dem Handover
(SUI-568, Art. 50 EU AI Act) nicht abschließend beantwortet werden können.
Rechtsprüfung ist **nicht** Aufgabe des Umsetzungs-Agenten (Handover Abschnitt 1);
offene Punkte werden hier dokumentiert, damit die Umsetzung nicht blockiert.

Status je Frage: `offen` · `beantwortet` · `verworfen`.

## R-01 — Reicht der dreistufige Textmarkierungs-Ansatz für Art. 50 Abs. 2? `offen`

Handover 5.4: HTML-Attribute (IPTC-Vokabel `trainedAlgorithmicMedia`) +
persistenter Provenance-Record + auflösbare UUID. Es gibt keinen normierten
Standard für maschinenlesbare **Text**markierung. Der Ansatz ist eine
verteidigbare Auslegung, aber nicht rechtssicher bestätigt.
- **Braucht:** juristische Bestätigung, dass das die Pflicht aus Abs. 2 für Text erfüllt.
- **Auswirkung offen:** keine — Umsetzung folgt dem Handover, Begründung in `03-dev-doc.md`.

## R-02 — `block_elediaai_tutor`: harte Dependency oder Guard? `beantwortet`

**Entscheidung Johannes (2026-08-03):** `class_exists()`-Guard **plus**
Admin-Warnung im Statusbericht bei fehlendem `local_aitransparency` — keine harte
Dependency. Umsetzung in AP3.


Handover 4.2: Das Plugin ist bewusst als „installs and runs standalone"
dokumentiert (Kommentar in `version.php`). Eine harte Dependency auf
`local_aitransparency` unterläuft diese Produktentscheidung.
- **Entscheidung gehört:** Johannes / Produkt.
- **Braucht:** Freigabe für harte Dependency, sonst `class_exists()`-Guard **plus**
  Admin-Check-Warnung im Statusbericht (stillschweigend unmarkiert ist keine Option).
- Wird in AP3 abgestimmt, nicht vorher gesetzt.

## R-03 — Hochrisiko-Abgrenzung (Anhang III Nr. 3, Frist 2027) `beantwortet`

Handover 1 + 9: Ist **separates Projekt**. Hier nicht implementieren, nur das
Provenance-Schema sauber halten, damit die Hochrisiko-Doku 2027 darauf aufsetzt.

## R-04 — Retention/Anonymisierung der `userid` im Provenance-Record `offen`

Handover 5.2: Records sind Compliance-Nachweise (dürfen nicht mit dem Inhalt
gelöscht werden), aber `userid` ist personenbezogen. Vorgabe: Aufbewahrungsdauer
als Setting, Scheduled Task anonymisiert **nur** die `userid` (auf 0), Record bleibt.
- **Braucht:** Bestätigung der konkreten Default-Aufbewahrungsfrist durch Datenschutz.
- **Auswirkung offen:** Default-Wert des Settings; Mechanik ist klar.

## R-05 — Signing-Dienstleister: Datenschutz/AVV `offen`

Handover 5.7: Nur hash-basiertes Remote-Signing (Ausschlusskriterium), AVV nach
DSGVO, Verarbeitung in der EU. Beschaffung/Vertragsschluss liegt außerhalb der
Agenten-Rolle.
- **Entscheidung/Vertrag gehört:** Johannes / Einkauf.
- Betrifft AP5b, nicht den selbstsignierten Pfad AP5a.
