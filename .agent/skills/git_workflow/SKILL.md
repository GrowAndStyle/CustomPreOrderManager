---
name: load-blueprint-git-workflow
description: ZWINGEND für Git-Operationen, Branching-Strategien, Conventional Commits und automatische Rollbacks.
---

# TECH BLUEPRINT: Git & Version Control (Enterprise Standard 2026)

**PARADIGMA:** Trunk-Based Development, Conventional Commits, Audit-Trail

## 1. Branching-Strategie (Protected Flow)
- **Main/Master Branch:** Dieser Branch ist geschützt (`Protected`). Direkte Pushes sind strikt verboten. Code gelangt ausschließlich über verifizierte Merge-Requests (MR) oder Pull-Requests (PR) in den Main-Branch.
- **Feature Branches:** Jede Änderung erfolgt in einem kurzlebigen Feature-Branch. Naming-Konvention:

| Prefix | Verwendung | Beispiel |
|--------|-----------|---------|
| `feat/` | Neue Features (Standard) | `feat/store-api`, `feat/admin-module` |
| `fix/` | Bugfixes, Security-Patches | `fix/security-hardening-v1`, `fix/xss-sanitize` |
| `refactor/` | Strukturelle Verbesserungen ohne Feature-Änderung | `refactor/plugin-codesplitting` |
| `docs/` | Nur Dokumentation | `docs/architecture-update` |
| `chore/` | Build, Tooling, Dependencies | `chore/upgrade-shopware-6.6` |

- **Regel:** Ausschließlich `feat/` verwenden (nicht `feature/`). Branch-Namen in `kebab-case`, kurz und beschreibend.
- **Merge-Kriterien:** Ein Merge darf nur erfolgen, wenn:
  1. Alle automatisierten Tests (CI) bestanden sind.
  2. Der Linter keine Warnungen ausgibt.
  3. Mindestens ein Reviewer den Code freigegeben hat.

## 2. Commit-Standard (Conventional Commits)
- Commits MÜSSEN dem Schema `<Typ>(<Scope>): <Beschreibung>` folgen. Die **Beschreibung ist auf Deutsch** (z.B. `feat(auth): MFA-Support hinzugefügt`).
- **Typen:** `feat`, `fix`, `refactor`, `docs`, `chore`, `test`, `style`, `perf`.

> **Vollständiges Commit-Beispiel (mit Body):**
> ```
> feat(warenkorb): Rabattcode-Validierung mit HMAC-Signatur
>
> Validiert Rabattcodes serverseitig per HMAC-SHA256 bevor sie
> auf den Warenkorb angewendet werden. Verhindert Client-Side-Manipulation.
> ```
- **Scope:** Modul oder Bereich in Klammern (z.B. `store-api`, `admin`, `storefront`, `cart`, `auth`).
- **Zuweisung:** Jeder Commit sollte idealerweise eine Referenz auf den aktuellen Task in der `task.md` enthalten.
- **Atomare Commits:** Änderungen sind in kleinen, logisch abgeschlossenen Einheiten zu committen. „Riesen-Commits" mit 50 geänderten Dateien sind zu vermeiden.
- **Tagging:** Releases folgen Semantic Versioning mit `v`-Prefix (`v1.2.0`). Tags werden nur auf dem Main-Branch gesetzt.

## 3. Dokumentations-Synchronität
- Ein Code-Merge ist erst dann vollständig, wenn die entsprechenden Änderungen auch in der `walkthrough.md` reflektiert wurden. Code und Dokumentation bilden eine untrennbare Einheit.

## 4. Agent Undo-Pflicht & Safe-Rollbacks
- **Micro-Commits vor Refactorings:** Bevor der KI-Agent eine komplexe Änderung über mehrere Dateien hinweg durchführt, MUSS er den letzten funktionierenden Stand zwingend lokal sichern (`git commit` oder `git stash`).
- **Fail Gracefully & Rollback:** Verheddert sich der Agent in einer Fehlerschleife (z.B. durch Linter- oder Compiler-Fehler) und kann diese nach 3 Versuchen nicht beheben, MUSS er selbstständig ein Rollback durchführen, anstatt das Projekt in einem defekten (gebrickten) Zustand zu hinterlassen. **Safeguard:** Vor jedem `git reset --hard` MUSS zwingend `git stash` ausgeführt werden, um ungesicherte Änderungen zu bewahren. Rollback-Sequenz: `git stash && git reset --hard <letzter-sauberer-commit>`. Ein `git reset --hard` ohne vorheriges `git stash` ist **VERBOTEN**.

## 5. Multi-Agent Parallel-Arbeit (Branch-per-Agent)

### A. Grundregel

Wenn ein Agent einen **eigenständigen Task** beginnt, erstellt er einen eigenen Feature-Branch. Das isoliert seine Änderungen und ermöglicht parallele Arbeit mit anderen Agents.

```
Agent startet Task → git checkout -b feat/{task-name} → arbeitet → merged
```

### B. Branch-Naming für Agents

| Muster | Beispiel | Verwendung |
|--------|---------|-----------|
| `feat/{task-name}` | `feat/redirect-subscriber` | Standard für Feature-Arbeit |
| `feat/{slice-id}-{name}` | `feat/s2-redirect-subscriber` | Wenn Slices aus einem Masterplan abgearbeitet werden |
| `fix/{issue}` | `fix/normalizer-priority` | Bugfixes während der Implementierung |

### C. Parallelisierungs-Regeln

> **KERNREGEL:** Zwei Agents dürfen NUR parallel arbeiten wenn sie **verschiedene Dateien** bearbeiten. Gleiche Dateien = sequentiell.

| Szenario | Parallel? | Begründung |
|----------|:---------:|-----------|
| Agent A: Admin-Modul (Vue.js), Agent B: Storefront Subscriber (PHP) | ✅ | Komplett verschiedene Dateien |
| Agent A: Plugin Phase 0, Agent B: Content-Texte Phase 2 | ✅ | Verschiedene Bereiche, kein File-Overlap |
| Agent A: Slice 2 (RedirectSubscriber), Agent B: Slice 3 (SeoUrlChangeSubscriber) | ✅ | Verschiedene Service/Subscriber-Dateien, gleiche Entity nur gelesen |
| Agent A: CSS/SCSS, Agent B: JS/Logik im gleichen Feature | ❌ | Beide ändern wahrscheinlich die gleichen Templates |
| Agent A: Entity-Definition, Agent B: Admin-UI für die gleiche Entity | ❌ | Agent B braucht Agent A's Entity zuerst |
| Agent A: Migration, Agent B: Code der die Migration braucht | ❌ | Strikte Abhängigkeit |

### D. Merge-Reihenfolge

```
1. Agent A ist fertig → merged seinen Branch in main
2. Agent B ist fertig → MUSS erst rebasen:
   git fetch origin
   git rebase origin/main
   → Konflikte lösen (falls vorhanden)
   → Dann mergen
```

**Wer zuerst fertig ist, merged zuerst.** Der zweite Agent muss seinen Branch auf den aktuellen `main` rebasen bevor er merged. Das stellt sicher dass:
- Kein Code verloren geht
- Merge-Konflikte vom Agent gelöst werden der den Kontext hat
- `main` immer in einem lauffähigen Zustand bleibt

### E. Vor dem Branch-Start (Pflicht)

```
□ Habe ich geprüft welche Dateien mein Task berührt?
□ Gibt es einen anderen Agent der aktuell auf den gleichen Dateien arbeitet?
  → Wenn JA: Sequentiell arbeiten, nicht parallel
  → Wenn NEIN: Branch anlegen, parallel OK
□ Habe ich den neuesten main gepullt bevor ich meinen Branch erstellt habe?
□ Ist mein Branch-Name nach §1 Konvention benannt?
```

### F. Abhängigkeitsregel

Wenn Agent B das **Ergebnis von Agent A braucht** (z.B. eine Entity-Definition die Agent A erstellt):
1. Agent A arbeitet und merged **zuerst**
2. Agent B wartet bis Agent A gemerged hat
3. Agent B erstellt seinen Branch **von dem aktualisierten main**
4. Niemals von einem anderen Feature-Branch branchen — immer von `main`

## 6. Kill-Kriterien

| Verstoß | Konsequenz |
|---|---|
| Push direkt auf `main`/`master` | Revert — verstößt gegen §1 Protected Flow |
| Commit ohne Conventional-Prefix (`feat`, `fix`, etc.) | Amend — Commit-Message korrigieren |
| Branch-Name weicht ab (`feature/` statt `feat/`) | Umbenennen — verstößt gegen §1 Naming-Konvention |
| Refactoring ohne vorherigen Micro-Commit | Rollback — verstößt gegen §4 Micro-Commit-Pflicht |
| Parallele Arbeit auf gleichen Dateien ohne Absprache | Einer der Branches muss warten — Merge-Konflikt-Risiko |
| Merge ohne vorheriges Rebase auf aktuellen `main` | Rebase erzwingen — verstößt gegen §5D |
| Branch von Feature-Branch statt von `main` erstellt | Branch neu von `main` erstellen — verstößt gegen §5F |