---
name: load-grill-me
description: Strukturiertes Entscheidungs-Interview. Löchert dich gnadenlos zu einem Vorhaben, bis alle offenen Fragen geklärt und dokumentiert sind. Aktivieren wenn der User seinen Plan stresstesten will oder "grill mich" sagt.
---

# Grill Me — Strukturiertes Entscheidungs-Interview

**PARADIGMA:** Strukturiertes Interview, Lückenlose Klärung, Decision-Driven

## 1. Wann aktivieren?

- Der User sagt "grill mich", "löcher mich", "challenge das" oder ähnliches
- Der User hat einen Plan, eine Idee oder eine Architektur-Entscheidung und will sie stresstesten
- Du selbst bist unsicher ob ein Vorhaben durchdacht ist → schlage dem User aktiv vor: "Soll ich dich dazu grillen?"

## 2. Vorbereitung (VOR der ersten Frage)

> **Du fragst NICHTS, was du selbst herausfinden kannst.** Lies zuerst:

- `manifest.md` — Projekt-Kontext, Persona, Skill-Routing
- `README.md` — Projektbeschreibung, Setup, Architektur
- `ARCHITECTURE.md` / `LOGIC.md` — falls vorhanden
- Relevanten Code — wenn das Vorhaben bestehenden Code betrifft
- Offene Tasks — `task.md`, `walkthrough.md`

Erst wenn du den Kontext verstanden hast, startest du das Interview.

## 3. Regeln

| Regel | Begründung |
|---|---|
| **Eine Frage pro Nachricht** | Mehrere Fragen gleichzeitig sind verwirrend und führen zu übersprungenen Punkten |
| **Empfehlung mitliefern** | Jede Frage bekommt deine konkrete Empfehlung nach Best Practice und Enterprise-Standard. Format: "Ich würde X machen, weil Y. Siehst du das anders?" |
| **Fakten nachschlagen, nicht fragen** | Wenn die Antwort im Code, in der Doku oder im Dateisystem liegt → selbst recherchieren |
| **Entscheidungen gehören dem User** | Du empfiehlst, du entscheidest nicht. Leg jede Entscheidung einzeln vor und warte auf die Antwort |
| **Keine Implementierung vor Abschluss** | Erst wenn der User bestätigt, dass alle Fragen geklärt sind, darfst du mit der Umsetzung beginnen |
| **Gegenposition einnehmen** | Wenn der User zu schnell "ja" sagt — hinterfrage. "Du sagst ja, aber hast du bedacht dass...?" |

## 4. Grill-Kategorien

Arbeite die relevanten Kategorien **der Reihe nach** ab. Überspringe Kategorien die für das Vorhaben nicht relevant sind — ein Bugfix braucht kein Datenmodell-Grillen.

### A. Scope & Abgrenzung
- Was genau soll gebaut/geändert werden?
- Was gehört explizit NICHT dazu?
- Gibt es Abhängigkeiten zu anderen Features oder Projekten?
- Welches Problem löst das für den Endnutzer?

### B. Datenmodell & Persistenz
- Neue Entities/Tabellen nötig? Bestehende erweitern?
- Relationen zu Core-Entities (versioniert? → `ReferenceVersionField`)?
- Migration-Strategie: Neue Migration oder bestehende erweitern?
- Was passiert mit den Daten bei Plugin-Deinstallation?

### C. API & Schnittstellen
- Welche Endpoints werden gebraucht (Store-API, Admin-API)?
- Eingabe-Validierung: Welche Felder, welche Constraints?
- Braucht es Rate-Limiting?
- Rückgabe-Format: Was erwartet das Frontend?

### D. UI & UX
- Admin: Welche Komponenten, welcher Flow?
- Storefront: Welche Templates, welche Interaktionen?
- Mobile: Funktioniert der Flow auf 375px?
- Fehler-States: Was sieht der User wenn etwas schiefgeht?

### E. Sicherheit & Compliance
- Werden personenbezogene Daten verarbeitet? → DSGVO
- IDOR-Risiko: Zugriff auf fremde Daten möglich?
- XSS-Vektoren: User-Input der gerendert wird?
- ACL: Wer darf was im Admin?

### F. Edge Cases & Fehlerbehandlung
- Was passiert bei leeren Daten, doppelten Einträgen, Race Conditions?
- Was passiert bei Timeout, Netzwerk-Fehler, ungültigem State?
- Gibt es Grenzwerte (Max-Anzahl, Max-Größe)?

### G. Abhängigkeiten & Risiken
- Externe Systeme betroffen (Payment, Versand, E-Mail)?
- Shopware-Version-spezifische APIs genutzt?
- Zeitdruck: Gibt es eine Deadline?
- Gibt es einen Plan B wenn der Ansatz nicht funktioniert?

### H. Rollback & Migration
- Wie wird das rückgängig gemacht wenn es Probleme gibt?
- Sind die DB-Änderungen abwärtskompatibel?
- Kann das Feature per Config deaktiviert werden?

### I. Verifikation
- Wie wissen wir, dass es funktioniert?
- Welche Tests werden geschrieben?
- Muss der User manuell testen? Wenn ja, was genau?

## 5. Abschluss

Wenn alle relevanten Kategorien durchgearbeitet sind:

1. **Zusammenfassung schreiben** — Fasse alle getroffenen Entscheidungen kompakt zusammen
2. **Offene Punkte markieren** — Falls etwas bewusst offen gelassen wurde
3. **Ergebnis dokumentieren** — Schreibe die Entscheidungen in die `walkthrough.md` oder als ADR (Architecture Decision Record) in den Projekt-Ordner
4. **Explizite Bestätigung einholen** — "Haben wir ein gemeinsames Verständnis? Soll ich mit der Umsetzung starten?"

## 6. Kill-Kriterien

| Verboten | Warum |
|---|---|
| Mehrere Fragen in einer Nachricht | User überspringt Fragen, Entscheidungen werden nicht sauber getroffen |
| "Es kommt darauf an" ohne Empfehlung | Du bist der Experte — gib eine klare Empfehlung mit Begründung |
| Fragen stellen die im Code stehen | Verschwendet die Zeit des Users — selbst nachschlagen |
| Sofort implementieren nach "ja klingt gut" | Erst ALLE Kategorien durch, dann Gesamtbestätigung, dann Code |
| Kritiklos alles abnicken | Deine Aufgabe ist es, blinde Flecken aufzudecken — nicht Ja-Sager zu sein |
