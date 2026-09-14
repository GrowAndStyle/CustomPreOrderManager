---
name: load-audit-blueteam
description: ZWINGEND vor jedem Release! Defensiver Quality-Audit — systematische Compliance-, Vollständigkeits- und Konsistenzprüfung. Verifiziert dass der Code alle Skills, ADRs und Standards einhält.
---

# BlueTeam Audit — Pre-Release Defensive Quality (Enterprise Standard 2026)

**WANN:** Nach abgeschlossener Implementierung, VOR dem Release/Deployment (parallel zum RedTeam-Audit)
**PARADIGMA:** Trust-but-Verify, Checklisten-Driven, Zero-Tolerance auf Kill-Criteria
**OUTPUT:** Strukturierter Compliance-Report mit Freigabe-Empfehlung

> **Du bist NICHT der Entwickler.** Du bist der QA-Prüfer. Dein Job ist es, JEDE Behauptung des Entwickler-Agents zu verifizieren. „Funktioniert" heißt nichts — zeig mir den Beweis.

---

## §1 Audit-Prozess

### Ablauf (strikt sequentiell)

```
1. Manifest lesen → Welche Skills gelten für dieses Projekt?
2. ALLE geladenen Skills identifizieren → Kill-Criteria sammeln
3. Codebase KOMPLETT lesen (keine Datei überspringen)
4. Systematisch prüfen (§2–§9)
5. Findings dokumentieren (§10)
6. Report dem User vorlegen — KEIN Fix ohne Freigabe
```

### Scope-Definition

| Frage | Beispiel |
|---|---|
| Was wird auditiert? | Plugin `CustomVariantBuilder`, Version `v1.0.0` |
| Welcher Code-Stand? | Branch `main`, Commit `abc1234` |
| Welches Manifest? | `.agent/manifest.md` |
| Welche Skills gelten? | Alle in `.agent/skills/` gelisteten |

---

## §2 Kill-Criteria Compliance (KRITISCHSTER PRÜFPUNKT)

### Prozess

1. **Sammle ALLE Kill-Criteria** aus allen geladenen Skills
2. **Für jedes Kill-Kriterium:** Durchsuche die GESAMTE Codebase nach Verstößen
3. **Dokumentiere jeden Verstoß** mit Datei, Zeile und konkretem Code-Snippet

### Systematische Suche

```bash
# Schritt 1: Alle Skills auflisten
ls .agent/skills/

# Schritt 2: Kill-Criteria aus jedem Skill extrahieren
# Für JEDEN Skill: Sektion "Kill-Criteria" lesen und in Prüfliste aufnehmen
```

### Prüf-Matrix (Beispiel für CustomVariantBuilder)

| Skill | Kill-Kriterium | Prüfmethode | Status |
|---|---|---|---|
| `rules_variant_logic` #1 | Neue UUID für bestehenden Artikel | `grep -rn "Uuid::randomHex" --include="*.php" src/` — jeden Treffer auf Kontext prüfen | ⬜ |
| `rules_variant_logic` #2 | Child ohne parent_id/optionIds | Child-Verknüpfungs-Code prüfen — werden beide Felder gesetzt? | ⬜ |
| `rules_variant_logic` #3 | Description bei Children nicht genullt | `grep -rn "description" --include="*.php" src/Core/Service/` — wird `''` gesetzt? | ⬜ |
| `rules_variant_logic` #8 | DAL-Bypass | `grep -rn "executeStatement\|executeQuery" --include="*.php" src/` — nur in Migration erlaubt | ⬜ |
| `rules_variant_logic` #10 | PHP/Composer lokal ausgeführt | Prüfe ob Sandbox-Befehle mit php/composer im Transcript stehen | ⬜ |
| `rules_admin_wizard` #3 | export default statt Component.register | `grep -rn "export default" --include="*.js" src/` | ⬜ |
| `rules_admin_wizard` #5 | httpClient per inject | `grep -rn "inject.*httpClient\|'httpClient'" --include="*.js" src/` | ⬜ |
| `rules_admin_wizard` #6 | v-model:value statt v-model | `grep -rn "v-model:value" --include="*.twig" src/` | ⬜ |
| `shopware_plugin` #0 | Code ohne Doku-Abgleich | Stichprobenartig — hat der Agent `search_web` für Shopware-Patterns genutzt? | ⬜ |
| `git_workflow` #1 | Push direkt auf main | Git-History prüfen — alle Commits über Feature-Branches? | ⬜ |

> **JEDES Kill-Kriterium aus JEDEM geladenen Skill MUSS geprüft werden.** Kein Überspringen, kein „das ist bestimmt OK".

---

## §3 ADR-Compliance

### Prüfpunkte

| Prüfpunkt | Wie prüfen |
|---|---|
| **Existieren ADRs?** | `ls docs/adr/` — Sind alle laut ROADMAP erwarteten ADRs vorhanden? |
| **ADR-Index aktuell?** | `architecture.md` lesen — stimmen die Verweise mit den Dateien in `docs/adr/` überein? |
| **Implementierung entspricht ADR?** | Für jeden ADR mit Status `ENTSCHIEDEN`: Prüfen ob der Code die Entscheidung widerspiegelt |
| **Abgelehnte Alternativen dokumentiert?** | Jeder ADR MUSS eine Tabelle mit abgelehnten Alternativen haben |
| **Status korrekt?** | Kein ADR mit Status `VORGESCHLAGEN` der bereits implementiert wurde |

```bash
# 🔍 ADR-Dateien auflisten
find docs/adr -name "*.md" | sort

# 🔍 ADR-Verweise in architecture.md prüfen
grep -n "ADR-" architecture.md
```

---

## §4 Plugin-Lifecycle & Struktur

### Verzeichnisstruktur-Audit

```bash
# 🔍 Erwartete Struktur gegen tatsächliche prüfen
# Manifest §1 definiert die Struktur — jede Abweichung ist ein Finding

# Pflichtdateien prüfen:
test -f composer.json        && echo "✅ composer.json" || echo "🔴 FEHLT: composer.json"
test -f phpunit.xml.dist     && echo "✅ phpunit.xml.dist" || echo "🔴 FEHLT: phpunit.xml.dist"
test -f .gitignore           && echo "✅ .gitignore" || echo "🔴 FEHLT: .gitignore"
test -f architecture.md      && echo "✅ architecture.md" || echo "🔴 FEHLT: architecture.md"
test -d docs/adr             && echo "✅ docs/adr/" || echo "🔴 FEHLT: docs/adr/"
test -d tests                && echo "✅ tests/" || echo "🔴 FEHLT: tests/"
test -f tests/TestBootstrap.php && echo "✅ TestBootstrap.php" || echo "🔴 FEHLT: TestBootstrap.php"
```

### Lifecycle-Vollständigkeit

```bash
# 🔍 Alle 5 Lifecycle-Methoden vorhanden?
grep -n "function install\|function update\|function activate\|function deactivate\|function uninstall" src/*.php
```

| Prüfpunkt | grep/Suche | Erwartet |
|---|---|---|
| `install()` vorhanden? | `function install(InstallContext` | ✅ |
| `update()` vorhanden? | `function update(UpdateContext` | ✅ |
| `activate()` vorhanden? | `function activate(ActivateContext` | ✅ |
| `deactivate()` vorhanden? | `function deactivate(DeactivateContext` | ✅ |
| `uninstall()` vorhanden? | `function uninstall(UninstallContext` | ✅ |
| `keepUserData()` Check in Uninstall? | `keepUserData()` in uninstall-Methode | ✅ |
| `parent::uninstall()` aufgerufen? | `parent::uninstall($context)` | ✅ |
| Custom Tables in Uninstall gedroppt? | `DROP TABLE IF EXISTS` | ✅ |
| System-Config in Uninstall gelöscht? | `DELETE FROM system_config WHERE` | ✅ |

### composer.json Audit

| Prüfpunkt | Erwartet |
|---|---|
| `name` = `growandstyle/{plugin-name}` | Vendor korrekt? |
| `type` = `shopware-platform-plugin` | Shopware-Typ korrekt? |
| KEIN `version`-Feld | Version kommt vom Git-Tag |
| `shopware-plugin-class` korrekt? | Zeigt auf die richtige Hauptklasse? |
| `require` shopware/core Constraint korrekt? | `~6.5.8.0 \|\| ^6.6.0` |
| Labels in DE + EN vorhanden? | Zweisprachig? |

---

## §5 DI & Routing Konsistenz

### services.xml vs. PHP

```bash
# 🔍 Alle Service-IDs in services.xml
grep -n 'service id=' src/Resources/config/services.xml

# 🔍 Alle PHP-Klassen die als Service erwartet werden
find src -name "*.php" | grep -v "Entity\|Collection\|Exception\|Migration" | sort

# Vergleich: Jede Service-Klasse muss in services.xml registriert sein
```

| Prüfpunkt | Wie prüfen |
|---|---|
| Jeder Service in services.xml registriert? | Vergleich: PHP-Klassen vs. Service-IDs |
| Entity-Definition hat `shopware.entity.definition` Tag? | `grep "entity.definition" services.xml` |
| Controller ist `public="true"`? | Controller-Service prüfen |
| Constructor-Arguments stimmen mit services.xml überein? | PHP-Constructor vs. XML-Arguments vergleichen |
| Keine toten Registrierungen? | Service registriert aber Klasse existiert nicht |

### routes.xml vs. Controller

```bash
# 🔍 Registrierte Routes
cat src/Resources/config/routes.xml

# 🔍 Controller-Methoden mit #[Route]
grep -n "#\[Route" --include="*.php" src/Core/Api/
```

| Prüfpunkt | Wie prüfen |
|---|---|
| Alle Controller in routes.xml importiert? | `<import resource>` Pfade stimmen? |
| Import-Type ist `attribute`? | `type="attribute"` (nicht `annotation`) |
| Relative Pfade korrekt? | `../../Core/Api/` — stimmt die Verzeichnistiefe? |

---

## §6 Entity & Migration Konsistenz

```bash
# 🔍 Entity-Definition Felder
grep -n "new.*Field\|addFlags" src/Core/Content/*/Definition.php

# 🔍 Migration Schema
grep -n "CREATE TABLE\|ADD COLUMN\|ALTER TABLE" src/Migration/*.php
```

| Prüfpunkt | Wie prüfen |
|---|---|
| Jedes Feld in Entity hat Spalte in Migration? | Definition-Felder vs. CREATE TABLE Spalten vergleichen |
| Feld-Typen stimmen überein? | `StringField` → `VARCHAR`, `JsonField` → `JSON`, `IdField` → `BINARY(16)` |
| PrimaryKey korrekt? | `IdField` + `Required` + `PrimaryKey` Flags |
| ForeignKeys haben zugehörige Association? | `FkField` immer mit `ManyToOneAssociationField` |
| Versionierte FK haben `ReferenceVersionField`? | FK auf `product`, `order` etc. brauchen Version-Spalte |
| Migration hat `IF NOT EXISTS`? | Crash-Schutz bei Reinstall |
| Entity-Name in Definition stimmt mit Tabellen-Prefix? | `custom_variant_migration_log` |

---

## §7 Admin-UI Konsistenz

### Snippet-Vollständigkeit

```bash
# 🔍 Alle referenzierten Snippet-Keys in Templates
grep -rn "\$tc('\|this\.\$tc('\|tc('" --include="*.twig" --include="*.js" src/ | \
    sed "s/.*\$tc('//" | sed "s/'.*//" | sort -u > /tmp/used_keys.txt

# 🔍 Alle definierten Snippet-Keys
cat src/Resources/app/administration/src/module/*/snippet/de-DE.json | \
    python3 -c "import sys,json; [print(k) for k in json.loads(sys.stdin.read())]" | sort -u > /tmp/defined_keys.txt

# 🔍 Fehlende Keys (definiert aber nicht genutzt, genutzt aber nicht definiert)
diff /tmp/used_keys.txt /tmp/defined_keys.txt
```

| Prüfpunkt | Wie prüfen |
|---|---|
| Jeder `$tc()` Key existiert in DE JSON? | Keys vergleichen |
| Jeder `$tc()` Key existiert in EN JSON? | Gleicher Check für en-GB |
| Keine verwaisten Snippet-Keys? | Definiert aber nirgends genutzt |
| Alle Plural-Forms korrekt? | `{count}` Platzhalter in Snippets |

### Komponenten-Registrierung

```bash
# 🔍 Alle Component.register() Aufrufe
grep -rn "Component.register" --include="*.js" src/

# 🔍 Alle Komponenten-Referenzen in Templates
grep -rn "component:" --include="*.js" src/
```

| Prüfpunkt | Wie prüfen |
|---|---|
| Jede Komponente hat `Component.register()`? | Kein `export default` |
| SCSS-Import in jeder `index.js`? | `import './component-name.scss'` |
| Template-Import korrekt? | `import template from './component.html.twig'` |
| `inject` enthält nur erlaubte Services? | Kein `httpClient` in inject |
| `v-model` statt `v-model:value`? | `grep "v-model:value"` in Templates |
| Jedes Eingabefeld hat `helpText`? | `grep "helpText"` in Templates |

---

## §8 Test-Vollständigkeit

```bash
# 🔍 Test-Dateien auflisten
find tests -name "*Test.php" | sort

# 🔍 Test-Methoden zählen
grep -rn "function test\|@test" --include="*.php" tests/
```

| Prüfpunkt | Erwartet |
|---|---|
| `TestBootstrap.php` vorhanden? | ✅ |
| `phpunit.xml.dist` vorhanden? | ✅ |
| Integration-Tests nutzen `IntegrationTestBehaviour`? | `grep "IntegrationTestBehaviour" tests/` |
| KEIN `createMock(EntityRepository::class)`? | `grep "createMock.*Repository" tests/` |
| Unit-Tests für reine Logik (Parser, Matcher)? | Tests in `tests/Unit/` |
| Integration-Tests für DAL-Operationen? | Tests in `tests/Integration/` |
| DataProvider für Regex-Patterns? | `grep "@dataProvider" tests/` |
| `static::assertSame` statt `assertEquals`? | `grep "assertEquals" tests/` — sollte 0 Treffer haben |
| Testdaten über DAL, nicht SQL? | Kein `executeStatement` in Tests |

### Kritische Pfade die getestet sein MÜSSEN

| Feature | Mindest-Tests |
|---|---|
| Basisname-Parsing | Alle Einheiten (ml, L, Liter, kg, g, cm) + Edge Cases |
| Größen-Normalisierung | Umrechnung (L→ml, kg→g) + Ohne-Größe-Fallback |
| Gruppierung | 2+ Artikel, 1 Artikel (Skip), CloneBox-Filter |
| Parent-Erzeugung | Pflichtfelder, Hauptvariante, configuratorSettings |
| Child-Verknüpfung | parentId, optionIds, Description-Nulling |
| Rollback | Restore previous_state, Parent-Löschung |
| Property-Matching | Match, kein Match, mehrere Matches |

---

## §9 Git & Dokumentation

### Git-Hygiene

```bash
# 🔍 Commit-Messages prüfen (Conventional Commits)
git log --oneline -20

# 🔍 Direkte Pushes auf main?
git log --first-parent main --oneline -20
# Alle Commits sollten Merge-Commits sein (--no-ff)

# 🔍 Debug-Code in History?
git log --all --diff-filter=A -p | grep -n "var_dump\|console\.log\|dd(\|dump("
```

| Prüfpunkt | Erwartet |
|---|---|
| Alle Commits folgen Conventional Commits? | `feat/fix/docs/chore(scope): Beschreibung` |
| Beschreibung auf Deutsch? | Commit-Messages prüfen |
| Keine direkten Pushes auf main? | Nur Merge-Commits auf main |
| Kein Debug-Code in History? | 0 Treffer |
| `.gitignore` enthält `.agent/skills/` und `.agent/manifest.md`? | Skill-Dateien nicht im Repo |

### Dokumentation

| Prüfpunkt | Vorhanden? |
|---|---|
| `README.md` mit Plugin-Beschreibung (DE+EN)? | ⬜ |
| `architecture.md` mit ADR-Index? | ⬜ |
| `docs/adr/` mit ADR-Dateien? | ⬜ |
| Inline-Kommentare auf Deutsch? | ⬜ |
| Docstrings auf allen öffentlichen Methoden? | ⬜ |
| `ROADMAP.md` aktuell? | ⬜ (in `.agent/`) |

---

## §10 Report-Format

```markdown
# 🔵 BlueTeam Quality Audit Report

**Plugin:** {Name} v{Version}
**Commit:** {Hash}
**Auditor:** BlueTeam Agent
**Datum:** {YYYY-MM-DD}
**Manifest:** .agent/manifest.md
**Geprüfte Skills:** {Auflistung aller geladenen Skills}

## Executive Summary
{1-3 Sätze: Gesamtbewertung}

## Kill-Criteria Compliance
| Skill | Kriterium # | Beschreibung | Status | Fundstelle |
|-------|:-----------:|--------------|:------:|------------|
| {skill} | {#} | {Beschreibung} | ✅/🔴 | {Datei:Zeile oder —} |

**Ergebnis:** {X}/{Y} Kill-Criteria bestanden

## ADR-Compliance
| ADR | Titel | Status | Implementierung korrekt? |
|-----|-------|--------|:------------------------:|
| ADR-001 | {Titel} | ENTSCHIEDEN | ✅/🔴 |

## Struktur & Lifecycle
| Prüfpunkt | Status | Finding |
|-----------|:------:|---------|
| Verzeichnisstruktur | ✅/🔴 | {Details} |
| Lifecycle-Methoden | ✅/🔴 | {Details} |
| composer.json | ✅/🔴 | {Details} |

## DI & Routing
| Prüfpunkt | Status | Finding |
|-----------|:------:|---------|
| services.xml Konsistenz | ✅/🔴 | {Details} |
| routes.xml Konsistenz | ✅/🔴 | {Details} |

## Entity & Migration
| Prüfpunkt | Status | Finding |
|-----------|:------:|---------|
| Definition/Migration Match | ✅/🔴 | {Details} |

## Admin-UI
| Prüfpunkt | Status | Finding |
|-----------|:------:|---------|
| Snippet-Vollständigkeit | ✅/🔴 | {Details} |
| Komponenten-Registrierung | ✅/🔴 | {Details} |

## Test-Vollständigkeit
| Prüfpunkt | Status | Finding |
|-----------|:------:|---------|
| Kritische Pfade abgedeckt? | ✅/🔴 | {Details} |
| Test-Patterns korrekt? | ✅/🔴 | {Details} |

## Git & Dokumentation
| Prüfpunkt | Status | Finding |
|-----------|:------:|---------|
| Conventional Commits | ✅/🔴 | {Details} |
| Dokumentation vollständig | ✅/🔴 | {Details} |

## Release-Empfehlung
🔴 BLOCK — Kill-Criteria-Verstöße oder kritische Lücken
🟡 CONDITIONAL — Kleinere Findings, Release nach Nachbesserung möglich
🟢 CLEAR — Alle Prüfpunkte bestanden, Release freigegeben
```

---

## §11 Kill-Criteria

| # | Verstoß | Konsequenz |
|:-:|---|---|
| **1** | **Nicht ALLE Kill-Criteria aus ALLEN Skills geprüft** | Audit ungültig — unvollständige Compliance-Prüfung |
| **2** | **Finding gefunden und eigenständig gefixt** | VERBOTEN — Findings nur reporten, User entscheidet über Fix |
| **3** | **Datei übersprungen beim Code-Review** | Audit unvollständig — muss nachgeholt werden |
| **4** | **Snippet-Vollständigkeit nicht geprüft** | Fehlende Snippets crashen die Admin-UI — MUSS geprüft werden |
| **5** | **services.xml nicht gegen PHP-Klassen abgeglichen** | Fehlende Registrierung → Service nicht verfügbar zur Laufzeit |
| **6** | **Entity-Definition nicht gegen Migration geprüft** | Schema-Mismatch → DAL-Fehler zur Laufzeit |
| **7** | **Report ohne Release-Empfehlung** | Audit nutzlos — User braucht klare Go/No-Go-Entscheidung |
| **8** | **Test-Patterns nicht gegen §8 geprüft** | createMock-Verstöße bleiben unentdeckt |
