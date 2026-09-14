---
name: load-blueprint-project-scaffolding
description: KRITISCH bei Projektstart & neuen Features! Erzwingt eine standardisierte Verzeichnisstruktur, Pflicht-Artefakte und Mobile-First-Grundlagen VOR dem ersten Code-Commit. Ohne Scaffold kein Code.
---

# PLANNING BLUEPRINT: Project Scaffolding & Struktur (Enterprise Standard 2026)

**SCOPE:** Verzeichnisstruktur, Pflicht-Artefakte, Archetyp-Templates, Naming-Konventionen
**PARADIGMA:** Structure-First, Mobile-First, Domain-Driven Separation

## 1. Betriebsmodi (Dual-Trigger)
- **Projektstart-Modus:** Wird bei einem neuen Projekt getriggert. Der Agent MUSS die komplette Verzeichnisstruktur nach dem passenden Archetyp-Template (§4–§6) aufbauen, bevor eine einzige Zeile produktiver Code geschrieben wird.
- **Feature-Modus:** Wird bei einem neuen Feature in einem laufenden Projekt getriggert. Der Agent MUSS prüfen, ob das Feature in die bestehende Struktur passt, oder ob neue Module/Ordner nötig sind. Strukturelle Erweiterungen werden in der `walkthrough.md` dokumentiert.

### Repository-Isolation (PFLICHT)

> **KERNREGEL: Jedes eigenständige Projekt, Plugin oder Service bekommt sein EIGENES Git-Repository.** Ein Plugin als Unterordner in einem bestehenden Repo zu hosten ist **VERBOTEN**.

| Szenario | Richtig | Falsch |
|----------|---------|--------|
| Neues Shopware-Plugin | Eigenes Repo: `GrowAndStyle/GsRedirectManager` | Unterordner: `shopware-content-hub/GsRedirectManager/` |
| Neues Backend-Service | Eigenes Repo: `GrowAndStyle/SetGenerator` | Unterordner: `KassenSystem/set-generator/` |
| Scripts + Daten für ein bestehendes Projekt | Im bestehenden Repo OK (kein eigenständiges Projekt) | — |

**Begründung:**
- Git-History bleibt sauber (keine vermischten Commits)
- `composer require` / `npm install` funktioniert direkt
- Branch-per-Agent (§5 `git_workflow`) ohne Seiteneffekte auf andere Projekte
- `.agent/manifest.md` liegt im Repo-Root und routet korrekt
- CI/CD-Pipelines können isoliert laufen

## 2. Pflicht-Artefakte (Gate-Keeper)
Bevor produktiver Code geschrieben wird, MÜSSEN folgende Dateien im Projekt-Root existieren:

| Artefakt | Zweck | Erstellt von |
|----------|-------|-------------|
| `.agent/manifest.md` | Kaskaden-Router für Skills | Agent (nach Template) |
| `task.md` | Aktives Task-Board mit DoD | Agent |
| `walkthrough.md` | Architektur-Entscheidungen & Fortschritt | Agent |
| `.env.example` | ENV-Contract mit DUMMY-Werten | Agent |
| `README.md` | Projektbeschreibung, Setup-Anleitung, Stack-Übersicht | Agent |
| `.gitignore` | Ausschluss von `.env`, `node_modules/`, `__pycache__/`, `vendor/` etc. | Agent |

**KILL-KRITERIUM:** Code-Commits ohne diese Artefakte gelten als **defekt**.
**KILL-KRITERIUM:** Ein eigenständiges Projekt/Plugin als Unterordner in einem fremden Repository ist **strukturell defekt** — eigenes Repo anlegen.

## 3. Naming-Konventionen (Universell)

| Kontext | Konvention | Beispiel |
|---------|-----------|---------|
| **Verzeichnisse** | `kebab-case` oder `snake_case` (konsistent pro Projekt) | `order-management/`, `order_management/` |
| **PHP-Klassen** | `PascalCase`, PSR-4 Autoloading | `CustomModalDefinition.php` |
| **JS/TS-Komponenten** | `PascalCase` (Datei + Export) | `CheckoutSidebar.tsx`, `CartStore.svelte` |
| **JS/TS-Utilities** | `kebab-case` (Datei), `camelCase` (Export) | `format-currency.ts` → `formatCurrency()` |
| **Python-Module** | `snake_case` | `scoring_engine.py` |
| **Tests** | `test_*.py` (Python), `*.test.tsx` / `*.spec.ts` (JS/TS) | `test_checkout.py`, `CartStore.test.tsx` |
| **CSS/Styles** | Semantische Klassen, keine Utility-Duplikate | `bg-primary`, nicht `bg-blue-500` |
| **Datenbank-Tabellen** | `snake_case`, Singular, Plugin-Prefix bei Shopware | `custom_modal`, `custom_modal_inquiry` |
| **ENV-Variablen** | `SCREAMING_SNAKE_CASE` | `DATABASE_URL`, `MOCK_MODE` |

## 4. Archetyp A: Shopware 6 Plugin

**Anwendung:** Native Shopware-Module (Admin-Erweiterung, Storefront-Feature, Store-API-Endpoint).

```text
PluginName/
├── src/
│   ├── PluginName.php                          # Plugin-Hauptklasse (Boot, Lifecycle, Uninstall)
│   ├── Entity/
│   │   └── EntityName/
│   │       ├── EntityNameDefinition.php         # DAL Entity-Definition
│   │       ├── EntityNameEntity.php             # Entity-Klasse
│   │       └── EntityNameCollection.php         # Collection-Klasse
│   ├── Migration/
│   │   └── Migration1234567890CreateEntityName.php
│   ├── Controller/
│   │   └── Api/                                # Store-API / Admin-API Controller
│   ├── Service/                                # Business-Logik (Service-Layer)
│   ├── Subscriber/                             # Event-Subscriber
│   ├── Resources/
│   │   ├── config/
│   │   │   ├── services.xml                    # DI-Container
│   │   │   └── routes.xml                      # Route-Definitionen
│   │   ├── app/
│   │   │   └── administration/
│   │   │       └── src/
│   │   │           ├── module/                 # Vue.js 3 Admin-Module
│   │   │           └── main.js                 # Admin Entry-Point
│   │   ├── views/
│   │   │   └── storefront/                     # Twig-Templates
│   │   └── public/
│   │       └── storefront/
│   │           └── js/                         # Storefront JS-Plugins
│   └── Test/                                   # PHPUnit Tests
├── .agent/
│   ├── manifest.md                             # AI Kaskaden-Router
│   └── skills/                                 # Symlink oder Kopie der Blueprints
├── composer.json
├── .env.example
├── README.md
├── task.md
├── walkthrough.md
└── .gitignore
```

**KRITISCH:**
- `Resources/` liegt INNERHALB von `src/`, NICHT auf Root-Level. Shopwares `Bundle.php` sucht unter `getPath()/Resources/`.
- **Plugin-Lifecycle PFLICHT:** `PluginName.php` MUSS die Methoden `install()`, `update()`, `activate()`, `deactivate()` und `uninstall()` implementieren. Die `uninstall()`-Methode MUSS `$context->keepUserData()` abfragen. Bei `keepUserData() === false`: Custom Tables droppen, Custom Fields entfernen, Plugin-Config löschen. **NIEMALS** löschen: Bestellungen, Dokumente (Rechnungen, Widerrufsformulare), Kundendaten, Media-Uploads des Users.

## 5. Archetyp B: Headless Companion (FastAPI + Svelte/React)

**Anwendung:** Standalone-Widget oder Micro-Frontend, das in Shopware Erlebniswelten injiziert wird und über ein eigenes Backend kommuniziert.

```text
project-name/
├── backend/
│   ├── app/
│   │   ├── main.py                     # FastAPI Entry-Point
│   │   ├── config.py                   # Settings (Pydantic BaseSettings)
│   │   ├── models/                     # SQLModel / Pydantic Schemas
│   │   ├── routers/                    # Route-Definitionen (kein Business-Logic!)
│   │   ├── services/                   # Business-Logik (Service-Layer)
│   │   ├── dependencies/               # FastAPI Dependency Injection
│   │   └── utils/                      # Reine Utility-Funktionen (Formatter, Encoder)
│   ├── tests/
│   │   ├── conftest.py                 # Fixtures, Test-DB
│   │   └── test_*.py
│   ├── mock_data.py                    # MOCK_MODE Dummy-Daten
│   ├── pyproject.toml
│   └── Dockerfile
├── frontend/
│   ├── src/
│   │   ├── lib/
│   │   │   ├── components/             # UI-Komponenten
│   │   │   ├── stores/                 # State-Management (Zustand / Svelte Context)
│   │   │   ├── api/                    # API-Client (Fetch-Wrapper, Zod-Validierung)
│   │   │   ├── types/                  # Shared TypeScript-Types
│   │   │   └── utils/                  # Reine Utility-Funktionen
│   │   ├── App.svelte / App.tsx        # Root-Komponente
│   │   └── main.ts                     # Entry-Point (Web Component Registration)
│   ├── package.json
│   └── vite.config.ts
├── shared/                             # Stack-übergreifende Schemas (optional)
│   └── schemas/
├── docker-compose.yml
├── .agent/
│   ├── manifest.md
│   └── skills/
├── .env.example
├── README.md
├── task.md
├── walkthrough.md
└── .gitignore
```

**KRITISCH:**
- Frontend und Backend sind **strikte Isolation** — kein Shared Code außer über `shared/schemas/`.
- Frontend spricht **NIEMALS** direkt mit Shopware. Backend ist der zensierende Proxy (Zero-Trust).

## 6. Archetyp C: Data Pipeline / Migration

**Anwendung:** CSV-Migration, Daten-Transformation, Batch-Prozesse (z.B. Shopware Produktmigration).

```text
project-name/
├── scripts/
│   ├── migrate_*.py                    # Migrations-Skripte
│   └── validate_*.py                   # Validierungs-Skripte
├── data/
│   ├── export/                         # Shopware-Exporte (CSV)
│   ├── import/                         # Aufbereitete Import-Dateien
│   └── mappings/                       # ID-Mappings, Lookup-Tables
├── docs/
│   └── migration-plan.md              # Phasen-Dokumentation
├── tests/
│   └── test_*.py                       # Pytest mit Dummy-CSVs
├── .agent/
│   ├── manifest.md
│   └── skills/
├── .env.example
├── README.md
├── task.md
├── walkthrough.md
└── .gitignore
```

## 7. Mobile-First Grundlagen (PFLICHT — nicht verhandelbar)
- **Viewport-Meta:** Jedes HTML-Dokument MUSS `<meta name="viewport" content="width=device-width, initial-scale=1">` enthalten.
- **Touch-Targets:** Alle interaktiven Elemente MÜSSEN mindestens `48x48px` groß sein (WCAG 2.5.8). **Shopware-Storefront:** Mindestens `44x44px` (Apple HIG), da Shopware-Kern-Komponenten diesen Standard nutzen.
- **Responsive Breakpoints:** Es gelten IMMER die Breakpoints der **aktuellen stabilen Version** des jeweiligen Plattform-Frameworks:
  - **E-Commerce / Public Websites / Shopware (Bootstrap):** Die von der Plattform definierten Bootstrap-Breakpoints (aktuell: sm ≥ 576px, md ≥ 768px, lg ≥ 992px, xl ≥ 1200px).
  - **Standalone-Projekte ohne Plattform-Bindung (Tailwind CSS):** Die von Tailwind definierten Breakpoints (aktuell: sm ≥ 640px, md ≥ 768px, lg ≥ 1024px, xl ≥ 1280px).
  - Design wird von Mobile aufwärts gebaut (Mobile-First), NICHT von Desktop herunter.
- **Bottom-Navigation:** Primäre Aktionen ("Weiter", "In den Warenkorb") werden im Daumen-Bereich fixiert (Sticky Bottom).
- **Kein Hover-Only:** Jede Hover-Interaktion MUSS eine Touch-Alternative haben (Long-Press, Tap-to-Toggle).

### 7a. Versionsprinzip (Aktuelle Stabile Version — Universell)
- **Plattform-Bindung:** Arbeite IMMER auf der **aktuellen stabilen Version** der jeweiligen Plattform und deren Abhängigkeiten. Keine veralteten Versionen, keine Beta/RC-Versionen in Produktion.
- **Shopware:** Die aktuelle CE-Version bestimmt die PHP-Version, das Bootstrap-Release, die Admin-SDK-Version und die DAL-API. `composer.json` Constraints MÜSSEN die aktuelle + nächste Major-Version abdecken.
- **Frontend-Frameworks:** Aktuelle stabile Version von Svelte, React, Tailwind CSS, etc. LTS-Versionen bevorzugen.
- **Laufzeitumgebungen:** Aktuelle LTS-Version von Node.js, Python, PHP.
- **Regel:** Wenn ein Skill eine spezifische Versionsnummer nennt (z.B. „Bootstrap 5"), ist damit IMMER „die zum Zeitpunkt der Entwicklung aktuelle stabile Version" gemeint. Bei Version-Updates werden die Skills zentral aktualisiert.

## 8. Anti-Patterns (STRIKT VERBOTEN)

| Anti-Pattern | Warum verboten | Korrekte Alternative |
|-------------|----------------|---------------------|
| Alles in `/src` ohne Unterordner | Unwartbar ab 10+ Dateien | Domain-Driven Ordnerstruktur nach Archetyp |
| `utils.ts` / `helpers.py` als Sammelbecken | Wird zur Müllhalde, niemand findet etwas | Spezifische Module: `format-currency.ts`, `date-utils.ts` |
| Geschäftslogik in Komponenten | Untestbar, nicht wiederverwendbar | Service-Layer (Backend) / Store (Frontend) |
| `Resources/` auf Root-Level (Shopware) | Shopware findet die Config nicht | `src/Resources/` — immer innerhalb von `src/` |
| Flat-File-Architektur ohne Separation | Kein Scaling möglich | Feature-Ordner oder Domain-Ordner |
| Tests im gleichen Ordner wie Source | Unübersichtlich, Build-Konflikte | Separater `tests/` / `Test/` Ordner |

## 9. Scaffold-Checkliste (Gate vor Code-Beginn)
Bevor der Agent produktiven Code schreibt, MUSS er in der `walkthrough.md` folgende Punkte abhaken:

- [ ] Archetyp identifiziert (A / B / C)
- [ ] Verzeichnisstruktur nach Template angelegt
- [ ] Alle Pflicht-Artefakte erstellt (§2)
- [ ] `manifest.md` mit korrektem Skill-Routing konfiguriert
- [ ] `.env.example` mit allen benötigten Variablen (DUMMY-Werte)
- [ ] `.gitignore` konfiguriert
- [ ] Mobile-First Viewport-Meta gesetzt (falls HTML vorhanden)
- [ ] README.md mit Projektbeschreibung und Setup-Anleitung

## 10. Kill-Kriterien

- ❌ Code-Commit ohne vorheriges Scaffold (verstößt gegen §9 Gate)
- ❌ Produktiver Code ohne `manifest.md` und Skill-Routing
- ❌ Fehlender Archetyp — jedes Projekt MUSS einem der drei Archetypen (A/B/C) zugeordnet sein
- ❌ Monorepo statt Repository-Isolation für Plugins (verstößt gegen §4)
- ❌ Desktop-First CSS ohne Mobile-First Basis (verstößt gegen §7)
