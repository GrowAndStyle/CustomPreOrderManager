---
name: load-deployment-sync
description: ZWINGEND! Lade diese Datei für alle Deployment-, Sync- und Rollout-Operationen zwischen dem AgentSkill-Repository und den Projektordnern.
---

# Deployment Sync (Enterprise Standard 2026)

**SCOPE:** Synchronisation von Skills, Manifests und Konfiguration zwischen dem zentralen AgentSkill-Repository und den Projekt-Repositories
**PARADIGMA:** Single Source of Truth — AgentSkill-Repo ist IMMER die Quelle

---

## 1. Grundprinzip: Was gehört wohin?

> **KERNREGEL:** Die `.agent/`-Verzeichnisse in Projekten enthalten IMMER Kopien. Das Original lebt im AgentSkill-Repo. Kopien werden NIEMALS auf GitHub gepusht.

### A. Quellen (Single Source of Truth)

| Was | Wo lebt das Original | Beispiel |
|-----|----------------------|---------|
| **Shared Skills** | `AgentSkill/.agent/skills/{name}/SKILL.md` | `.agent/skills/global/SKILL.md` |
| **Projekt-Manifest** | `AgentSkill/projects/{name}/manifest.md` | `projects/CustomRedirectManager/manifest.md` |
| **Projekt-spezifische Skills** | `AgentSkill/projects/{name}/{skill}/SKILL.md` | `projects/CustomVariantBuilder/rules_admin_wizard/SKILL.md` |

### B. Ziele (Deployed Copies)

| Was | Wohin deployed | Auf GitHub? |
|-----|---------------|:-----------:|
| Shared Skills | `{Projekt}/.agent/skills/{name}/SKILL.md` | ❌ NEIN |
| Manifest | `{Projekt}/.agent/manifest.md` | ❌ NEIN |
| Projekt-spezifische Skills | `{Projekt}/.agent/skills/{name}/SKILL.md` | ❌ NEIN |
| Build-Scripts | `{Projekt}/.agent/scripts/` | ✅ JA |
| Walkthrough-Archive | `{Projekt}/.agent/archive/` | ✅ JA |

### C. `.gitignore` Regel für ALLE Projekt-Repos (PFLICHT)

```gitignore
# AI Agent Skills — deployed vom zentralen AgentSkill-Repo
# Single Source of Truth: github.com/GrowAndStyle/AgentSkills
.agent/skills/
.agent/manifest.md
```

**Was NICHT gitignored wird:**
- `.agent/scripts/` — Projekt-spezifische Build-Automation (gehört zum Projekt)
- `.agent/archive/` — Walkthrough-History (gehört zum Projekt)

---

## 2. Deployment-Workflow

### A. Manueller Deploy (SkillAgent → Projekt)

```bash
# Einzelnes Projekt deployen
cd /Users/nicoschultz/Desktop/AgentSkill

TARGET="/Users/nicoschultz/Documents/{ProjektName}/.agent"

# Manifest
cp projects/{ProjektName}/manifest.md "$TARGET/manifest.md"

# Shared Skills (alle aus .agent/skills/)
for skill_dir in .agent/skills/*/; do
    skill_name=$(basename "$skill_dir")
    mkdir -p "$TARGET/skills/$skill_name"
    cp "$skill_dir/SKILL.md" "$TARGET/skills/$skill_name/SKILL.md"
done

# Projekt-spezifische Skills
for skill_dir in projects/{ProjektName}/*/; do
    skill_name=$(basename "$skill_dir")
    [ "$skill_name" = "manifest.md" ] && continue
    mkdir -p "$TARGET/skills/$skill_name"
    cp "$skill_dir/SKILL.md" "$TARGET/skills/$skill_name/SKILL.md"
done
```

### B. Bulk-Deploy (Alle Projekte)

```bash
cd /Users/nicoschultz/Desktop/AgentSkill

for proj_dir in projects/*/; do
    proj_name=$(basename "$proj_dir")
    # Mapping: Projektname → Lokaler Ordner
    case "$proj_name" in
        POS)                       target="/Users/nicoschultz/Documents/KassenSystem" ;;
        SetGenerator)              target="/Users/nicoschultz/Documents/SetGenerator" ;;
        ModalManager)              target="/Users/nicoschultz/Documents/ShopwareModalManager" ;;
        ProductRefactoring)        target="/Users/nicoschultz/Documents/ShopwareProductRefactoring" ;;
        CustomRedirectManager)     target="/Users/nicoschultz/Documents/CustomRedirectManager" ;;
        CustomVariantBuilder)      target="/Users/nicoschultz/Documents/CustomVariantBuilder" ;;
        GrowAndStyleTheme)         target="/Users/nicoschultz/Documents/GrowAndStyleTheme" ;;
        CustomPreOrderManager)     target="/Users/nicoschultz/Documents/CustomPreOrderManager" ;;
        CustomerInteractionSuite)  target="/Users/nicoschultz/Documents/CustomerInteractionSuite" ;;
        *) echo "⚠️ Unbekanntes Projekt: $proj_name"; continue ;;
    esac

    [ ! -d "$target" ] && echo "⚠️ $target nicht gefunden" && continue

    # Manifest
    mkdir -p "$target/.agent/skills"
    cp "projects/$proj_name/manifest.md" "$target/.agent/manifest.md"

    # Shared Skills (alle aus .agent/skills/)
    for skill_dir in .agent/skills/*/; do
        skill_name=$(basename "$skill_dir")
        mkdir -p "$target/.agent/skills/$skill_name"
        cp "$skill_dir/SKILL.md" "$target/.agent/skills/$skill_name/SKILL.md"
    done

    # Projekt-spezifische Skills
    for skill_dir in "projects/$proj_name"/*/; do
        [ ! -d "$skill_dir" ] && continue
        skill_name=$(basename "$skill_dir")
        mkdir -p "$target/.agent/skills/$skill_name"
        cp "$skill_dir/SKILL.md" "$target/.agent/skills/$skill_name/SKILL.md"
    done

    echo "✅ $proj_name → $target"
done
```

---

## 3. Projekt-Registry

| Projekt-Key | GitHub Repo | Lokaler Pfad | Manifest |
|------------|------------|-------------|----------|
| `POS` | `GrowAndStyle/KassenSystem` | `/Documents/KassenSystem` | ✅ |
| `SetGenerator` | `GrowAndStyle/SetGenerator` | `/Documents/SetGenerator` | ✅ |
| `ModalManager` | `GrowAndStyle/shopwareModalManager` | `/Documents/ShopwareModalManager` | ✅ |
| `ProductRefactoring` | `GrowAndStyle/shopware-content-hub` | `/Documents/ShopwareProductRefactoring` | ✅ |
| `CustomRedirectManager` | `GrowAndStyle/CustomRedirectManager` | `/Documents/CustomRedirectManager` | ✅ |
| `CustomVariantBuilder` | `GrowAndStyle/CustomVariantBuilder` | `/Documents/CustomVariantBuilder` | ✅ |
| `GrowAndStyleTheme` | `GrowAndStyle/GrowAndStyleTheme` | `/Documents/GrowAndStyleTheme` | ✅ |
| `CustomPreOrderManager` | `GrowAndStyle/CustomPreOrderManager` | `/Documents/CustomPreOrderManager` | ✅ |
| `CustomerInteractionSuite` | `GrowAndStyle/CustomerInteractionSuite` | `/Documents/CustomerInteractionSuite` | ✅ |

---

## 4. Wann deployen?

| Trigger | Aktion |
|---------|--------|
| Shared Skill geändert | Bulk-Deploy an ALLE Projekte |
| Projekt-Manifest geändert | Deploy an das betroffene Projekt |
| Projekt-spezifischer Skill geändert | Deploy an das betroffene Projekt |
| Neues Projekt angelegt | Projekt-Registry erweitern + Initial-Deploy |
| Skill gelöscht/umbenannt | Alte Kopien in ALLEN Projekten löschen |

---

## 5. Migrations-Checkliste (Bestehende Projekte bereinigen)

Für Projekte die aktuell Skills auf GitHub committed haben:

```bash
# 1. Skills aus Git-Tracking entfernen (OHNE lokal zu löschen)
cd /Users/nicoschultz/Documents/{Projekt}
git rm -r --cached .agent/skills/
git rm --cached .agent/manifest.md

# 2. .gitignore aktualisieren
echo -e "\n# AI Agent Skills — Single Source of Truth: AgentSkill-Repo\n.agent/skills/\n.agent/manifest.md" >> .gitignore

# 3. Commit
git add .gitignore
git commit -m "chore: Agent-Skills aus Git entfernt — Single Source of Truth ist AgentSkill-Repo

Skills und Manifest werden lokal per Deployment-Sync bereitgestellt.
Originals: github.com/GrowAndStyle/AgentSkills"

# 4. Push
git push
```

> [!CAUTION]
> `git rm --cached` entfernt Dateien NUR aus dem Git-Index (GitHub), NICHT vom lokalen Dateisystem. Die Skills bleiben lokal verfügbar.

---

## 6. Kill-Kriterien

| Verstoß | Konsequenz |
|---|---|
| Skill-Dateien in Projekt-Repo auf GitHub gepusht | `git rm --cached` + `.gitignore` Fix |
| Skill direkt im Projekt bearbeitet statt im AgentSkill-Repo | Änderung ins AgentSkill-Repo übertragen, Projekt-Kopie überschreiben |
| Manifest im Projekt bearbeitet statt in `projects/{name}/manifest.md` | Änderung ins AgentSkill-Repo übertragen |
| Neues Projekt ohne Registry-Eintrag (§3) | Eintrag ergänzen bevor deployed wird |
| Deploy ohne vorherigen Commit im AgentSkill-Repo | Erst committen, dann deployen |
