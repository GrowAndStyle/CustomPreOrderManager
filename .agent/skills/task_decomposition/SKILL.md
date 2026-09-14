---
name: load-blueprint-task-decomposition
description: KRITISCH! Definiert wie ein vages Ziel systematisch in priorisierte, ausführbare Tasks zerlegt wird. Laden bei Projektstart, neuen Features oder wenn ein Ziel > 2h geschätzte Arbeit umfasst.
---

# PLANNING BLUEPRINT: Systematische Aufgabenzerlegung (Enterprise Standard 2026)

**SCOPE:** Projektziele → Meilensteine → Epics → Tasks | **PARADIGMA:** Vertical-Slice-Dekomposition

## 1. Zielhierarchie

- **Projektziel:** Ein Satz, messbar, mit Erfolgskriterium — z.B. *"MVP des Webshops mit Checkout live auf Staging bis KW 28"*.
- **Meilenstein:** Lieferbares Zwischenergebnis mit **Milestone-Gate** (= Abnahmekriterien). Max. 3–5 pro Projekt.
- **Epic:** Fachliche Funktionseinheit, die genau einem Meilenstein zugeordnet ist. Benennung: `EPIC-{NR}: {Domäne}`.
- **Task:** Atomar ausführbare Einheit. Jeder Task MUSS einem Epic zugeordnet sein. Siehe §5 für Template.

## 2. Feature-Slicing (Vertical Slices)

- **PFLICHT:** Jedes Feature wird **end-to-end** in einem Slice umgesetzt: `Entity → Migration → Controller → Admin-UI → Storefront → Test`.
- **STRIKT VERBOTEN:** Horizontales Layer-by-Layer-Vorgehen (*"erst alle Entities, dann alle Controller, dann alle Templates"*) — dies erzeugt unintegrierbare Zwischenstände und blockiert Feedback.
- **Slice-Regel:** Ein Slice MUSS nach Abschluss einen **sichtbaren, testbaren Mehrwert** liefern. Kein Slice ohne UI- oder API-Endpunkt.
- **Reihenfolge:** Slices nach **Business-Value absteigend** priorisieren (P0 zuerst). Bei gleichem Value: Risiko-Slice zuerst.

## 3. Task-Sizing

- **Max. 2h geschätzter Human-Effort** pro Task. Überschreitung → Task MUSS weiter zerlegt werden.
- **Mindestgranularität:** Ein Task darf nicht kleiner als eine sinnvolle Commit-Einheit sein (kein *"Variable umbenennen"*).
- **Schätzformat:** `~30min | ~1h | ~2h` (Range-Schätzungen, explizit erlaubt). Keine falschen Präzisions-Schätzungen (z.B. "4h 23min"), keine Story-Points.

## 4. Dependency-Graph & Critical Path

- Jeder Task deklariert seine **Abhängigkeiten** via `depends_on: [TASK-ID, ...]`.
- **Critical Path** MUSS vor Umsetzungsbeginn identifiziert werden — Tasks auf dem kritischen Pfad erhalten automatisch **P0**.
- **Zirkuläre Abhängigkeiten** sind STRIKT VERBOTEN. Bei Erkennung: sofort refactoren.
- Parallelisierbare Tasks MÜSSEN als solche markiert werden (`parallel: true`).

## 5. Enterprise Task-Template (`task.md`)

Jeder Task in `task.md` (vgl. bestehende Konvention) MUSS folgendes Format einhalten:

```markdown
## TASK-{NR}: {Titel}
- **Epic:** EPIC-{NR}
- **Priority:** P0 | P1 | P2 | P3
- **Estimate:** ~1h
- **depends_on:** [TASK-003, TASK-007]
- **parallel:** false
- **DoD (Definition of Done):**
  - [ ] Feature end-to-end implementiert (Entity bis Test)
  - [ ] Tests grün (Unit + Integration)
  - [ ] Code-Review abgeschlossen
- **Status:** TODO | IN_PROGRESS | DONE | BLOCKED
```

- **P0:** Blocker / Critical Path. **P1:** Must-have für Meilenstein. **P2:** Should-have. **P3:** Nice-to-have.
- Fortschritt wird zusätzlich in `walkthrough.md` dokumentiert (Querverweis auf bestehende Konvention).

## 6. Milestone-Gates

| Gate | Kriterium | Prüfung |
|------|-----------|---------|
| **Planning-Gate** | Alle Tasks geschätzt, Dependencies aufgelöst, Critical Path markiert | Vor Sprint-/Iterationsstart |
| **Integration-Gate** | Slice end-to-end lauffähig, Tests grün | Nach jedem abgeschlossenen Slice |
| **Milestone-Gate** | Alle Epics des Meilensteins DONE, DoD erfüllt, `walkthrough.md` aktuell | Vor Meilenstein-Abnahme |

## 7. Kill-Kriterien

| Verstoß | Konsequenz |
|---------|------------|
| Task > 2h ohne Zerlegung | Task wird **abgelehnt**, Rückgabe zur Dekomposition |
| Horizontales Slicing (Layer-by-Layer) | Planung wird **verworfen**, Vertical Slices PFLICHT |
| Fehlende DoD pro Task | Task gilt als **nicht planbar**, wird blockiert |
| Zirkuläre Abhängigkeit im Graph | Sofortiger **Planungsstopp**, Refactoring der Abhängigkeiten |
| Slice ohne testbaren Endpunkt | Slice wird **nicht akzeptiert** |
