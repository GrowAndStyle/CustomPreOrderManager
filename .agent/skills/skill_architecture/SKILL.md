---
name: load-skill-architecture
description: ZWINGEND! Lade diese Datei für alle Skill-Design-, Audit- und Qualitätssicherungs-Aufgaben im AgentSkill-Repository.
---

# Skill-Architektur & Audit-Methodik (Antigravity Enterprise)

**PARADIGMA:** Code-First-Verifikation, Cross-Referenz-Integrität, Zero-Drift

## 1. Skill-Anatomie (Pflichtstruktur)

Jede `SKILL.md` MUSS folgende Struktur einhalten:

```
---
name: load-{skill-name}
description: {Wann und warum laden. KRITISCH/ZWINGEND als Prefix wenn essenziell.}
---

# {Titel} ({Projekt/Kontext})

**PARADIGMA/STACK:** {Kernprinzipien in einem Satz}

## 1-N. Fachliche Sektionen
- Regeln, Patterns, Code-Beispiele

## N+1. Kill-Kriterien (Pflicht für Enterprise-Skills)
- ❌ {Was verboten ist — mit Begründung}
```

**Regeln:**
- Frontmatter (`name`, `description`) ist Pflicht — ohne Frontmatter wird der Skill nicht korrekt injiziert.
- Kill-Kriterien sind **harte Regeln**, nicht Empfehlungen. Ein Agent der dagegen verstößt, produziert fehlerhaften Output.
- Jede Sektion nummeriert (§1, §2...) — für Cross-Referenzen aus anderen Skills.

## 2. Skill-Typen & Abstraktionsebenen

| Typ | Lebt in | Geteilt? | Beispiel |
|-----|---------|:---:|---------|
| **Shared Skill** | `skills/` | Ja, alle Projekte | `global`, `communication`, `git_workflow` |
| **Planning Blueprint** | `skills/` | Ja, alle Projekte | `project_scaffolding`, `task_decomposition` |
| **Tech Blueprint** | `skills/` | Ja, relevante Projekte | `python_fastapi`, `react_modern`, `design_system` |
| **Projekt-Skill** | `projects/{Name}/` | Nein, nur 1 Projekt | `rules_tagging`, `rules_modal_logic` |

**Routing-Regel (A→B→C):**
- **A. Globales Mindset** — Shared Skills, immer aktiv
- **B. Planungs-Blueprints** — bei Projektstart/neuen Features
- **C. Projekt-Logik** — fachspezifisch, nur im jeweiligen Projekt

## 3. Cross-Referenz-Regeln

### Erlaubt
- Shared Skill → Shared Skill: ✅ (z.B. `dependency_governance` → `dependency_planning`)
- Projekt-Skill → Shared Skill: ✅ (z.B. `rules_shopware_plugin` → `dependency_planning §4`)
- Projekt-Skill → Projekt-Skill: ✅ (z.B. `rules_modal_logic` → `rules_shopware_plugin §4B`)

### Verboten
- Shared Skill → Projekt-Skill: ❌ NIEMALS (z.B. `communication` → `rules_shopware_plugin` — existiert nicht in allen Projekten)
- Shared Skill → Tech-Skill der nicht überall deployed ist: ⚠️ NUR wenn die Referenz als optional gekennzeichnet ist

### Ausnahme: Deployment-Skills
- `deployment_sync` DARF Projektnamen und Pfade referenzieren — die Projekt-Registry ist seine Kernfunktion. Diese Ausnahme gilt **nur** für Skills deren Zweck das Deployment/Sync zwischen Projekten ist.

### Cross-Referenz-Format
```
> → Siehe auch: `{skill_name}` §{Sektion} für {was}.
```

## 4. Audit-Methodik (Code-First)

Ein Skill-Audit prüft **immer gegen den tatsächlichen Code**, nicht gegen andere Dokumentation:

### Prüfschritte
1. **Phantom-Werte:** Listet der Skill Tags, Pakete, Klassen oder Konfigurationen die im Code nicht existieren?
2. **Fehlende Abdeckung:** Gibt es Code-Konstrukte (Services, Engines, Security-Patterns) die keine Skill-Dokumentation haben?
3. **Widersprüche:** Fordert Skill A etwas, das Skill B verbietet?
4. **Tote Cross-Referenzen:** Verweist der Skill auf Skills die nicht im Projekt deployed sind?
5. **Drift:** Hat sich der Code seit der Skill-Erstellung verändert (z.B. Refactoring, neue Architektur)?

### Schweregrade
| Grad | Bedeutung | Beispiel |
|:---:|-----------|---------|
| 🔴 P0 | Agent produziert **falsche Ergebnisse** | Phantom-Tags in Tagging-Skill |
| 🟡 P1 | Agent arbeitet **unvollständig** | HMAC-Signierung nicht dokumentiert |
| 🟢 P2 | Verbesserung, kein Risiko | Skill ist dünn aber korrekt |

## 5. Skill-Design-Prinzipien

- **Spezifisch > Generisch:** Ein Skill der "Code sauber schreiben" sagt, ist wertlos. Ein Skill der sagt "Shopware DAL: `addAssociation()` statt Criteria-Join, `TranslatedField` für mehrsprachige Felder" ist wertvoll.
- **Code-Beispiele sind Pflicht:** Jede Regel die ein Pattern beschreibt, MUSS ein ✅/❌ Beispiel haben.
- **Warum > Was:** Nicht nur "Mach X", sondern "Mach X **weil** Y passiert wenn du es nicht tust".
- **Keine Wunschlisten:** Ein Skill dokumentiert den **Ist-Zustand** und **verbindliche Regeln**. Zukunftspläne gehören in die ROADMAP.
- **Messbar:** Kill-Kriterien müssen überprüfbar sein. "Schreibe guten Code" ist kein Kill-Kriterium. "`$this->createMock(EntityRepository::class)` ist verboten" ist eines.

## 6. Kill-Kriterien
- ❌ Shared Skill mit Cross-Referenz auf Projekt-spezifischen Skill
- ❌ Skill ohne Frontmatter (`name`, `description`)
- ❌ Skill mit Phantom-Werten (Tags/Pakete/Klassen die nicht im Code existieren)
- ❌ Skill der den falschen Tech-Stack beschreibt (z.B. ShadcnUI in einem Svelte-Projekt)
- ❌ Skill ohne Kill-Kriterien (bei Enterprise-Skills)
- ❌ Zwei Skills die sich gegenseitig widersprechen (z.B. Assert\Url vs. parse_url)
