---
name: load-core-communication
description: Lade diese Datei, um den Universal Communication Standard (UCS) zu befolgen, Dokumentationspflichten (walkthrough.md) zu verstehen und Transparenz zu gewährleisten.
---

# UNIVERSAL COMMUNICATION STANDARD (UCS)

**PARADIGMA:** Klartext-Kommunikation, Deutsch-First, Dokumentationspflicht

## 1. Sprach-Direktive
- **Primärsprache:** Deutsch — für Code-Kommentare, Docstrings, UI-Strings, Commit-Messages und Agenten-Kommunikation.
- **Ausnahme:** Code-Bezeichner (Variablen, Funktionen, Klassen) werden in Englisch geschrieben.
- **Technisches Vokabular:** Präzise, fachlich korrekt, professioneller Tonfall. Keine Umgangssprache, kein Marketing-Deutsch.
> → Siehe auch: `humanizer` für die vollständigen Anti-KI-Sprech-Regeln, Blacklists und Kontext-Register.

## 2. Dokumentations-Hierarchie (Source of Truth)
Jedes Projekt hat eine klare Hierarchie der Dokumentation. Bei Widersprüchen gilt die höherrangige Quelle:

| Rang | Dokument | Inhalt | Wer pflegt |
|:---:|----------|--------|-----------|
| 1 | `manifest.md` | Persona, Routing, System-Konstanten | Agent (nach Template) |
| 2 | `LOGIC.md` / `ARCHITECTURE.md` | Fachliche Regeln, Datenmodell, Architektur-Entscheidungen | Agent + User |
| 3 | `task.md` / `ROADMAP.md` | Aktuelle Tasks, Phasen, Checklisten | Agent (laufend) |
| 4 | `walkthrough.md` | Schritt-für-Schritt-Vorgehen, Entscheidungsprotokolle | Agent (nach jeder Phase) |
| 5 | `README.md` | Projektbeschreibung, Setup, Onboarding | Agent |

**Regel:** `task.md` und `walkthrough.md` sind die Single Source of Truth für den **operativen** Projektstatus. `LOGIC.md` und `ARCHITECTURE.md` sind die Source of Truth für **fachliche und technische** Entscheidungen.

## 3. Code-Kommentare
- **Wann Pflicht:** Bei nicht-offensichtlicher Logik, Workarounds, Edge-Cases, Sicherheitsrelevanz und TODO-Markierungen.
- **Wann verboten:** Triviale Kommentare, die nur den Code umformulieren (`// Setze x auf 5`).
- **Format:** Kurz, auf Deutsch, erklärt das **Warum** nicht das **Was**.
- **TODO-Markierungen:** `// TODO(Phase XX):` mit Phasen-Referenz für Nachverfolgbarkeit.

> **Beispiele:**
> ✅ `# Shopware erwartet UTC — lokale Zeitzonen werden vom Frontend gehandelt`
> ❌ `# Datum konvertieren`

## 4. Transparenz & Fehlerkommunikation
- **Keine "Magic Fixes".** Der Agent erklärt komplexe Architektur-Entscheidungen im Walkthrough.
- **Fehlermeldungen** sind klar strukturiert: **Fehler** → **Ursache** → **Lösungsweg**.
- **Unsicherheit benennen:** Wenn der Agent eine Vermutung hat, MUSS er das kennzeichnen ("Vermutlich...", "Muss verifiziert werden:"). Falsche Sicherheit ist schlimmer als ehrliche Unsicherheit.
- **Scope-Transparenz:** Wenn eine Aufgabe den geplanten Scope überschreitet, meldet der Agent das **proaktiv** bevor er weitermacht.

## 5. UI-Texte & Admin-Oberflächen
- **Zielgruppe:** Endnutzer und Betreiber, nicht Entwickler. Fachbegriffe nur mit Erklärung.
- **Tooltips/Hilfetexte:** Kurz, auf Deutsch, erklärt **WAS** das Feld tut und **WANN** es relevant ist.
- **Fehlermeldungen im Frontend:** Handlungsanweisend formulieren ("Bitte geben Sie eine gültige E-Mail ein"), nicht technisch ("Validation Error: email field invalid").

## 6. Kill-Kriterien

| Verstoß | Konsequenz |
|---|---|
| Englischer Code-Kommentar | Auf Deutsch umschreiben — verstößt gegen §1 Sprach-Direktive |
| Trivialer Kommentar (erklärt das Was statt das Warum) | Entfernen — verstößt gegen §3 |
| Fehler verschwiegen (kein Hinweis in Walkthrough/Response) | Sofort korrigieren — verstößt gegen §4 Transparenz |
| UI-Text mit Fachbegriff ohne Erklärung | Umformulieren — verstößt gegen §5 Zielgruppen-Regel |