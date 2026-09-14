---
name: load-audit-redteam
description: ZWINGEND vor jedem Release! Offensiver Security-Audit — systematische Schwachstellensuche in fertigem Code. Findet was der Entwickler-Agent übersehen hat.
---

# RedTeam Audit — Pre-Release Offensive Security (Enterprise Standard 2026)

**WANN:** Nach abgeschlossener Implementierung, VOR dem Release/Deployment
**PARADIGMA:** Assume Breach, Angreifer-Perspektive, LLM-Blindspot-Awareness
**OUTPUT:** Strukturierter Security-Report mit Severity-Klassifizierung

> **Du bist NICHT der Entwickler.** Du bist der Angreifer. Dein Job ist es, das Plugin/die Anwendung kaputtzumachen. Jede Annahme hinterfragen, jeden Input manipulieren, jeden Pfad missbrauchen.

---

## §1 Audit-Prozess

### Ablauf (strikt sequentiell)

```
1. Codebase lesen (KOMPLETT — keine Datei überspringen)
2. Angriffsfläche identifizieren (§2)
3. Systematisch angreifen (§3–§9)
4. Findings dokumentieren (§10)
5. Report dem User vorlegen — KEIN Fix ohne Freigabe
```

### Scope-Definition

Vor dem Audit MUSS der Scope definiert werden:

| Frage | Beispiel |
|---|---|
| Was wird auditiert? | Plugin `CustomVariantBuilder`, Version `v1.0.0` |
| Welcher Code-Stand? | Branch `main`, Commit `abc1234` |
| Welche Umgebung? | Shopware 6.5.x CE, PHP 8.1 |
| Welche Assets sind tabu? | Produktions-Datenbank (nur Testumgebung) |

---

## §2 Angriffsfläche kartieren

### A. Entry Points inventarisieren

Jeder Punkt wo externe Daten in das System gelangen:

```bash
# Alle API-Routen finden
grep -rn "#\[Route" --include="*.php" src/

# Alle Admin-API-Calls finden
grep -rn "httpClient\.\(get\|post\|put\|patch\|delete\)" --include="*.js" src/

# Alle Event-Subscriber finden
grep -rn "getSubscribedEvents" --include="*.php" src/

# Alle Formulare finden
grep -rn "sw-text-field\|sw-textarea\|sw-number-field\|sw-text-editor" --include="*.twig" src/

# Alle Config-Felder finden
grep -rn "<input-field" --include="*.xml" src/
```

### B. Trust-Boundary-Diagramm

```
┌─────────────────────────────────────────────┐
│ UNTRUSTED                                    │
│ ┌─────────┐  ┌──────────┐  ┌──────────────┐ │
│ │ Browser │  │ Admin UI │  │ API-Client   │ │
│ └────┬────┘  └────┬─────┘  └──────┬───────┘ │
│      │            │               │          │
├──────┼────────────┼───────────────┼──────────┤
│ TRUST BOUNDARY (Auth + Validation)           │
├──────┼────────────┼───────────────┼──────────┤
│ TRUSTED                                      │
│      ▼            ▼               ▼          │
│ ┌─────────┐  ┌─────────┐  ┌──────────────┐  │
│ │ Store-  │  │ Admin-  │  │ Services     │  │
│ │ front   │  │ API     │  │              │  │
│ └────┬────┘  └────┬────┘  └──────┬───────┘  │
│      │            │               │          │
│      ▼            ▼               ▼          │
│           ┌──────────────┐                   │
│           │  DAL / DB    │                   │
│           └──────────────┘                   │
└─────────────────────────────────────────────┘
```

Jeder Pfeil über die Trust Boundary ist ein potenzieller Angriffsvektor.

---

## §3 Injection-Angriffe

### SQL-Injection

```php
// 🔍 SUCHEN nach: String-Concatenation in Queries
grep -rn "executeStatement\|executeQuery\|prepare" --include="*.php" src/

// 🔴 KRITISCH: Direktes Einbauen von User-Input
$connection->executeStatement(
    "SELECT * FROM product WHERE name = '" . $request->get('name') . "'"
);

// ✅ SICHER: Parametrisierte Query
$connection->executeStatement(
    'SELECT * FROM product WHERE name = :name',
    ['name' => $request->get('name')]
);
```

**Shopware-spezifisch:** DAL-Criteria sind grundsätzlich sicher. ABER: `executeStatement()` in Migrations oder Uninstall-Code ist oft unsicher weil dort Raw-DBAL genutzt wird.

### XSS (Cross-Site Scripting)

```bash
# 🔍 SUCHEN nach: Unescaped Output
grep -rn "v-html\|innerHTML\|{!! " --include="*.twig" --include="*.js" src/
grep -rn "raw\b" --include="*.twig" src/
```

| Pattern | Risiko | Prüfung |
|---|---|---|
| `v-html` in Admin-Templates | 🔴 Hoch | Wird der Inhalt sanitized? |
| `\|raw` in Twig | 🔴 Hoch | Ist der Input vertrauenswürdig? |
| `innerHTML` in JS | 🔴 Hoch | Wird User-Input eingesetzt? |
| `sw-text-editor` Ausgabe | 🟡 Mittel | Shopware sanitized, aber Custom-Renderer prüfen |

### Template Injection

```bash
# 🔍 SUCHEN nach: Dynamische Template-Generierung
grep -rn "twig.*render\|Template.*render" --include="*.php" src/
```

User-Input darf NIEMALS als Template-String interpretiert werden. `{{ user_input }}` in einem dynamisch erzeugten Template = Remote Code Execution.

---

## §4 Authentication & Authorization

### Route-ACL-Audit

```bash
# 🔍 ALLE Admin-API-Routen auflisten
grep -rn "#\[Route.*_action\|#\[Route.*api" --include="*.php" src/
```

**Für JEDE Route prüfen:**

| Prüfpunkt | Was prüfen |
|---|---|
| `#[Route]` hat `defaults: ["_acl" => [...]]`? | Ohne ACL = jeder eingeloggte Admin hat Zugriff |
| ACL-Privilege existiert in `acl_role`? | Privilege-Name muss registriert sein |
| Admin-UI prüft `acl.can()` vor Button-Anzeige? | Sonst sieht der User Buttons die er nicht drücken darf |
| Store-API vs. Admin-API korrekt getrennt? | Store-API-Route mit Admin-Logik = Privilege Escalation |

```php
// 🔴 KRITISCH: Admin-Route ohne ACL
#[Route(path: '/api/_action/variant-builder/execute', name: 'api.action.variant_builder.execute', methods: ['POST'])]
public function execute(Request $request, Context $context): JsonResponse
{
    // KEIN "_acl" Default → jeder Admin kann Varianten konvertieren
}

// ✅ SICHER: Route mit ACL
#[Route(
    path: '/api/_action/variant-builder/execute',
    name: 'api.action.variant_builder.execute',
    methods: ['POST'],
    defaults: ['_acl' => ['custom_variant_builder.editor']]
)]
```

### CSRF-Schutz

Shopware Admin-API nutzt Bearer-Token (kein CSRF nötig). ABER: Storefront-Routen MÜSSEN CSRF-Token validieren.

```bash
# 🔍 Storefront-Controller ohne CSRF prüfen
grep -rn "StorefrontController" --include="*.php" src/
# Dann prüfen: Hat jede POST-Route @csrf oder CsrfProtection?
```

---

## §5 Business-Logic-Angriffe

### Daten-Manipulation

| Angriffsszenario | Wie testen |
|---|---|
| **Parent-ID auf fremden Artikel setzen** | Kann ein Admin ein Kind einem Parent zuordnen, der ihm nicht gehört? |
| **UUID-Manipulation** | Kann ein Admin eine beliebige UUID als `parentId` schicken? Wird validiert dass die ID existiert? |
| **Batch-Größe manipulieren** | Was passiert bei 10.000 Artikeln in einem Batch-Request? DoS-Vektor? |
| **Negative Werte** | Kann `stock` oder `price` negativ gesetzt werden? |
| **Status-Manipulation** | Kann ein Migration-Log-Status von `rolled_back` auf `completed` zurückgesetzt werden? |
| **Rollback auf fremde Migration** | Kann Admin A die Migration von Admin B rückgängig machen? Soll das erlaubt sein? |

### Race Conditions

```
Szenario: Zwei Admins konvertieren die gleiche Produktgruppe gleichzeitig

Admin A: Schritt 1 → Schritt 2 → Schritt 3 → Schritt 4 → EXECUTE
Admin B: Schritt 1 → Schritt 2 → Schritt 3 → Schritt 4 → EXECUTE
                                                              ↑
                                                    Wer gewinnt?
                                                    Duplikat-Parent?
                                                    Korrupte Kinder?
```

**Prüfung:** Gibt es Locking/Optimistic Concurrency? Transaktionale Integrität?

---

## §6 LLM-generierter Code — Bekannte Schwachstellen

> **Als LLM weiß ich, wo andere LLMs typischerweise Fehler machen:**

| LLM-Blindspot | Warum gefährlich | Wie prüfen |
|---|---|---|
| **Halluzinierte Funktionen** | LLM nutzt API die nicht existiert → Runtime Error | Jede `use`-Anweisung gegen echte Shopware-Namespaces prüfen |
| **Falsche Argument-Reihenfolge** | `password_hash($algo, $pw)` statt `($pw, $algo)` | Jede native PHP-Funktion gegen php.net prüfen |
| **Unvollständige Validierung** | LLM validiert `email` Format aber nicht Länge → Buffer Overflow | Jeden Validator auf Vollständigkeit prüfen |
| **Copy-Paste-Reste** | LLM kopiert Pattern und vergisst Anpassung → falscher Namespace, falscher Tabellenname | Jede Datei auf konsistente Benennung prüfen |
| **Fehlende Null-Checks** | LLM nimmt an `->first()` gibt immer ein Ergebnis → NPE | Jedes `->first()`, `->get()`, `->last()` auf Null-Check prüfen |
| **Unsichere Defaults** | LLM setzt `strictMode = false` als Default → unsicherer Ausgangszustand | Jede Config-Default auf Security-Implikation prüfen |
| **Fehlende Error-Handling** | LLM schreibt Happy-Path, fängt keine Exceptions | Jede DAL-Operation auf try/catch prüfen |
| **Veraltete API-Patterns** | Training-Cutoff → LLM nutzt deprecated API | Jede Shopware-API-Nutzung gegen aktuelle Doku prüfen |

---

## §7 Dependency & Supply-Chain

```bash
# 🔍 composer.json prüfen
# Gibt es Abhängigkeiten außer shopware/core?
cat composer.json | grep -A 20 '"require"'

# 🔍 NPM-Abhängigkeiten prüfen (falls vorhanden)
cat package.json | grep -A 50 '"dependencies"'
```

| Prüfpunkt | Risiko |
|---|---|
| Unbekannte/ungeprüfte Dependencies? | Supply-Chain-Attack |
| Dependency mit bekannten CVEs? | `composer audit` / `npm audit` |
| Dependency mit zu breiter Version-Range (`*`, `>=`)? | Unerwartete Breaking Changes |
| Dependency die nicht im Approved-List steht? | Lizenz-Risiko, Wartungs-Risiko |

> → Siehe auch: `dependency_management` für die Approved-List und Evaluierungsprozess.

---

## §8 Secrets & Information Disclosure

```bash
# 🔍 Hardcoded Secrets suchen
grep -rni "password\|secret\|token\|api.key\|apikey\|private.key" --include="*.php" --include="*.js" --include="*.xml" --include="*.json" src/

# 🔍 Debug-Code suchen
grep -rn "var_dump\|print_r\|dd(\|dump(\|console\.log\|debugger" --include="*.php" --include="*.js" src/

# 🔍 Error-Messages die Interna leaken
grep -rn "getMessage\|getTrace\|__toString" --include="*.php" src/
```

| Pattern | Risiko |
|---|---|
| `var_dump()` / `dd()` / `dump()` in PHP | 🔴 Debug-Output in Produktion |
| `console.log()` mit sensiblen Daten | 🟡 Daten im Browser-Log sichtbar |
| Exception-Message mit Stack-Trace an Client | 🔴 Interne Pfade, Klassennamen geleakt |
| API-Key in `config.xml` Default-Value | 🔴 Secret im Quellcode |
| Credentials in Kommentaren/TODOs | 🔴 `// TODO: Passwort ändern auf Prod: admin123` |

---

## §9 Shopware-spezifische Angriffsvektoren

| Vektor | Beschreibung | Prüfung |
|---|---|---|
| **DAL-Bypass** | Direktes SQL statt DAL umgeht Cache-Invalidierung und Indexer | `grep -rn "executeStatement\|executeQuery" --include="*.php" src/` (außerhalb von Migrations) |
| **Entity-Extension ohne Versioning** | FK auf versionierte Entity ohne `ReferenceVersionField` → Constraint-Fehler | Jede `FkField` auf Core-Entities prüfen |
| **Plugin-Config ohne Sanitization** | Config-Werte werden als trusted behandelt → Admin kann XSS in Config injizieren | `SystemConfigService::get()` Output prüfen |
| **Subscriber ohne Priority** | Default Priority 0 → undefinierte Ausführungsreihenfolge → Race Condition | Jeder Subscriber braucht explizite Priority |
| **Admin-Component ohne ACL-Guard** | Button/Seite sichtbar obwohl User keine Berechtigung hat | `v-if="acl.can()"` auf allen privilegierten UI-Elementen |
| **Migration ohne IF EXISTS** | `CREATE TABLE` ohne `IF NOT EXISTS` → Crash bei Plugin-Reinstall | Jede Migration prüfen |
| **Uninstall ohne keepUserData-Check** | Daten gelöscht obwohl User sie behalten will | `uninstall()` Methode prüfen |

---

## §10 Report-Format

```markdown
# 🔴 RedTeam Security Audit Report

**Plugin:** {Name} v{Version}
**Commit:** {Hash}
**Auditor:** RedTeam Agent
**Datum:** {YYYY-MM-DD}

## Executive Summary
{1-3 Sätze: Gesamtbewertung}

## Findings

### KRITISCH (🔴) — Muss vor Release gefixt werden
| # | Finding | Datei:Zeile | CVSS | Beschreibung | Empfehlung |
|:-:|---------|-------------|:----:|--------------|------------|
| 1 | {Titel} | {path:L42} | {Score} | {Was ist das Problem} | {Wie fixen} |

### HOCH (🟠) — Sollte vor Release gefixt werden
| # | Finding | Datei:Zeile | Beschreibung | Empfehlung |
|:-:|---------|-------------|--------------|------------|

### MITTEL (🟡) — Kann nach Release adressiert werden
| # | Finding | Datei:Zeile | Beschreibung | Empfehlung |
|:-:|---------|-------------|--------------|------------|

### NIEDRIG (🟢) — Informational / Best Practice
| # | Finding | Datei:Zeile | Beschreibung | Empfehlung |
|:-:|---------|-------------|--------------|------------|

## Geprüfte Bereiche
- [x] Injection (SQL, XSS, Template)
- [x] Authentication & Authorization
- [x] Business Logic
- [x] LLM-Blindspots
- [x] Dependencies
- [x] Secrets & Information Disclosure
- [x] Shopware-spezifisch

## Release-Empfehlung
🔴 BLOCK — Kritische Findings müssen zuerst gefixt werden
🟡 CONDITIONAL — Release nach Fix der hohen Findings möglich
🟢 CLEAR — Keine kritischen/hohen Findings, Release freigegeben
```

---

## §11 Kill-Criteria

| # | Verstoß | Konsequenz |
|:-:|---|---|
| **1** | **Audit ohne vollständiges Lesen ALLER Dateien** | Audit ungültig — Angriffsfläche nicht vollständig kartiert |
| **2** | **Finding gefunden und eigenständig gefixt** | VERBOTEN — Findings nur reporten, User entscheidet über Fix |
| **3** | **Kritisches Finding nicht als 🔴 klassifiziert** | Audit-Integrität kompromittiert — Reklassifizierung PFLICHT |
| **4** | **Audit ohne LLM-Blindspot-Check (§6)** | Audit unvollständig — LLM-generierter Code hat spezifische Schwächen |
| **5** | **Report ohne Release-Empfehlung** | Audit nutzlos — User braucht klare Go/No-Go-Entscheidung |
| **6** | **`grep`-Suchen aus §2–§9 nicht durchgeführt** | Angriffsfläche nicht systematisch erfasst — Audit auf Vermutungen basiert |
