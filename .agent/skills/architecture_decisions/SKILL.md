---
name: load-blueprint-architecture-decisions
description: KRITISCH! Erzwingt Architecture Decision Records (ADRs) vor jeder Implementierung. Laden bei Projektstart, neuen Modulen, neuen Abhängigkeiten, DB-Schema-Änderungen, Auth-Strategien, API-Versionierung und Shopware-Entity-Entscheidungen.
---

# PLANNING BLUEPRINT: Architecture Decision Records (Enterprise Standard 2026)

**SCOPE:** Architektur-Entscheidungen dokumentieren & genehmigen | **PARADIGMA:** Decision-First — kein Code ohne ADR

## 1. Wann ist ein ADR PFLICHT?

Ein ADR MUSS zwingend erstellt werden bei: **Neues Modul/Package** (inkl. Bundle, Plugin, App), **Neue Dependency** (Composer/NPM, jede externe Lib), **DB-Schema-Änderung** (Migration, neue Tabelle, Spaltenumbau), **Auth-Strategie** (OAuth, JWT, Session-Wechsel), **API-Versionierung** (neue Route, Breaking Change), **Shopware-spezifisch** (Entity vs Custom Table vs Custom Field, DAL vs Custom Query, Store-API vs Admin-API, Plugin vs App). Ohne genehmigten ADR ist Implementierung **STRIKT VERBOTEN**.

## 2. ADR-Template

Jeder ADR MUSS exakt diesem Format folgen:

```markdown
# ADR-NNN: {Titel der Entscheidung}

**Status:** VORGESCHLAGEN | ENTSCHIEDEN | SUPERSEDED durch ADR-XXX
**Datum:** YYYY-MM-DD
**Autor:** {Name/Agent}

## Kontext
Warum steht diese Entscheidung an? Welches Problem wird gelöst?

## Entscheidung
Konkrete, unmissverständliche Aussage: „Wir verwenden X für Y."

## Begründung
Technische und fachliche Argumente. Messbare Kriterien (Performance, Wartbarkeit, Kosten).

## Abgelehnte Alternativen
| Alternative | Grund der Ablehnung |
|---|---|
| {Option A} | {Konkreter Nachteil} |
| {Option B} | {Konkreter Nachteil} |
```

## 3. Ablage-Strategie

- **ADR-Dateien:** Individuelle Markdown-Dateien unter `docs/adr/ADR-NNN-titel.md` (z. B. `docs/adr/ADR-001-entity-design.md`)
- **Index:** `architecture.md` im Projekt-Root dient als **Inhaltsverzeichnis** — enthält nur eine Tabelle mit Verweisen auf die einzelnen ADR-Dateien, KEINEN ADR-Inhalt direkt
- **Nummerierung:** Fortlaufend dreistellig (001, 002, …), Lücken sind untersagt

### architecture.md Format (Index)

```markdown
# Architektur-Entscheidungen

| ADR | Titel | Status | Datum |
|-----|-------|--------|-------|
| [ADR-001](docs/adr/ADR-001-entity-design.md) | Entity-Design: Custom Table | ENTSCHIEDEN | 2026-09-01 |
| [ADR-002](docs/adr/ADR-002-api-strategie.md) | API-Strategie: Admin-API | VORGESCHLAGEN | 2026-09-01 |
```

## 4. Immutabilität & Lebenszyklus

- Ein ADR mit Status **ENTSCHIEDEN** wird **NIEMALS gelöscht oder inhaltlich geändert**
- Revision erfolgt ausschließlich durch neuen ADR mit Status `SUPERSEDED durch ADR-XXX` im alten Eintrag
- Statusänderungen (VORGESCHLAGEN → ENTSCHIEDEN) sind die einzige erlaubte Mutation

## 5. Review-Gate

Der Agent MUSS jeden ADR dem Benutzer **vollständig präsentieren**, bevor Implementierung beginnt. Workflow: ADR erstellen → Benutzer zur Freigabe auffordern → Erst nach expliziter Zustimmung implementieren. Eigenständiges Genehmigen durch den Agent ist **STRIKT VERBOTEN**.

## 6. Shopware-spezifische Entscheidungstrigger

| Entscheidung | ADR dokumentiert | Typische Alternativen |
|---|---|---|
| **DAL vs Custom Query** | Performance-Begründung PFLICHT | DAL (Standard) ↔ DBAL QueryBuilder ↔ Raw SQL |
| **Store-API vs Admin-API** | Sicherheitskontext dokumentieren | Store-API (Storefront) ↔ Admin-API (Backend) |
| **Plugin vs App** | Deployment-Strategie begründen | Plugin (Server) ↔ App (Cloud-kompatibel) |
| **Entity vs Custom Field vs Custom Table** | Datenmodell-Begründung PFLICHT | Entity-Extension ↔ CustomFieldSet ↔ Eigene Tabelle |

## 7. Kill-Kriterien

| Verstoß | Konsequenz |
|---|---|
| Implementierung ohne ADR | Code wird vollständig zurückgerollt — Code-Freeze bis ADR erstellt und genehmigt |
| ADR ohne „Abgelehnte Alternativen" | ADR ist ungültig, Nachbesserung PFLICHT |
| Entschiedenen ADR gelöscht/überschrieben statt Superseded | Verstoß gegen §4 Immutabilität — Rollback + sofortiger Stopp |
| Agent genehmigt ADR eigenständig | Review-Gate-Verletzung — Implementierung ungültig |
| Shopware-Entity-Entscheidung ohne DAL-Begründung | ADR unvollständig, Nachbesserung vor Implementierung |
| ADR ohne User-Review vor Implementierung | Merge blockiert — verstößt gegen §5 Review-Gate |
