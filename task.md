# Task Board: CustomPreOrderManager

## EPIC-1: Foundation, Scaffolding & DAL

### TASK-001: Verzeichnis-Scaffolding & Basis-Artefakte
- **Epic:** EPIC-1
- **Priority:** P0
- **Estimate:** ~30min
- **depends_on:** []
- **parallel:** false
- **DoD (Definition of Done):**
  - [x] Git initialisiert auf Feature-Branch `feat/scaffold-and-architecture`
  - [x] Basis-Dateien `.gitignore`, `.sw-zip-blacklist`, `.env.example`, `README.md`, `architecture.md` und ADRs angelegt
  - [x] Verzeichnisstruktur gemäß §1 Backend-Skill vollständig erstellt
- **Status:** DONE

### TASK-002: Plugin-Hauptklasse & Lifecycle
- **Epic:** EPIC-1
- **Priority:** P0
- **Estimate:** ~30min
- **depends_on:** [TASK-001]
- **parallel:** false
- **DoD (Definition of Done):**
  - [x] `composer.json` mit Namespace `CustomPreOrderManager\\`, Vendor `growandstyle` und ohne `version`-Feld
  - [x] `src/CustomPreOrderManager.php` mit allen 5 Lifecycle-Methoden implementiert
  - [x] `uninstall()` mit `keepUserData()`-Prüfung und DBAL-Cleanup für CustomFields und Config
- **Status:** DONE

### TASK-003: Migration CustomField-Set
- **Epic:** EPIC-1
- **Priority:** P0
- **Estimate:** ~30min
- **depends_on:** [TASK-002]
- **parallel:** false
- **DoD (Definition of Done):**
  - [x] `Migration1726200000AddPreOrderCustomFields.php` angelegt mit Timestamp `1726200000`
  - [x] 5 CustomFields (`active`, `release_date`, `release_text`, `inbound_stock`, `sold_count`) auf `product`
  - [x] Idempotente Ausführung mit Existence-Checks
- **Status:** DONE

### TASK-004: Service- & Plugin-Konfiguration
- **Epic:** EPIC-1
- **Priority:** P0
- **Estimate:** ~30min
- **depends_on:** [TASK-002]
- **parallel:** true
- **DoD (Definition of Done):**
  - [x] `src/Resources/config/services.xml` mit Services und Snippet-Tags definiert
  - [x] `src/Resources/config/config.xml` mit Prefix `CustomPreOrderManager.config.*`
- **Status:** DONE

---

## EPIC-2: Checkout Pipeline & Business Events

### TASK-005: PreOrderCartCollector
- **Epic:** EPIC-2
- **Priority:** P0
- **Estimate:** ~30min
- **depends_on:** [TASK-003, TASK-004]
- **parallel:** false
- **DoD (Definition of Done):**
  - [x] `src/Core/Checkout/Cart/PreOrderCartCollector.php` implementiert `CartDataCollectorInterface`
  - [x] Priorität `4100` in `services.xml`
  - [x] Payload-Enrichment (`isPreOrder`, `preOrderReleaseText` mit `strip_tags()`)
- **Status:** DONE

### TASK-006: OrderPlacedSubscriber & Atomic Counter
- **Epic:** EPIC-2
- **Priority:** P0
- **Estimate:** ~45min
- **depends_on:** [TASK-005]
- **parallel:** false
- **DoD (Definition of Done):**
  - [x] `src/Core/Checkout/Subscriber/OrderPlacedSubscriber.php` lauscht auf `CheckoutOrderPlacedEvent`
  - [x] Atomares DBAL-Update für `sold_count` mit `version_id = Defaults::LIVE_VERSION`
  - [x] Idempotente Zuweisung des Tags `Vorbestellung` an die Order
- **Status:** DONE

### TASK-007: PreOrderPlacedEvent & Flow Builder
- **Epic:** EPIC-2
- **Priority:** P1
- **Estimate:** ~30min
- **depends_on:** [TASK-006]
- **parallel:** true
- **DoD (Definition of Done):**
  - [x] `src/Core/Checkout/Event/PreOrderPlacedEvent.php` implementiert `FlowEventAware`, `ScalarValuesAware`
  - [x] `getAvailableData()` und `getValues()` korrekt definiert
- **Status:** DONE

### TASK-008: Rate-Limiter DI Extension
- **Epic:** EPIC-2
- **Priority:** P1
- **Estimate:** ~30min
- **depends_on:** [TASK-004]
- **parallel:** true
- **DoD (Definition of Done):**
  - [x] `src/DependencyInjection/CustomPreOrderManagerExtension.php` implementiert `PrependExtensionInterface`
  - [x] Rate-Limiter `preorder_notify` für Framework konfiguriert
- **Status:** DONE

---

## EPIC-3: Storefront Presentation & Scarcity Engine

### TASK-009: Storefront Snippets (de-DE, en-GB)
- **Epic:** EPIC-3
- **Priority:** P1
- **Estimate:** ~30min
- **depends_on:** [TASK-004]
- **parallel:** true
- **DoD (Definition of Done):**
  - [x] `SnippetFile_de_DE.php` und `storefront.de-DE.json`
  - [x] `SnippetFile_en_GB.php` und `storefront.en-GB.json`
  - [x] Validierung aller Keys gegen Schema
- **Status:** DONE

### TASK-010: Twig Template Extensions
- **Epic:** EPIC-3
- **Priority:** P0
- **Estimate:** ~45min
- **depends_on:** [TASK-009]
- **parallel:** false
- **DoD (Definition of Done):**
  - [x] Alle 5 Twig-Dateien nutzen `{% sw_extends %}`
  - [x] Stock-Guard `{% if isPreOrder and not hasStock %}` in allen relevanten Views
  - [x] Scarcity-Badge auf PDP per Config gesteuert
  - [x] Kaufen-Button deaktiviert bei Kontingenterschöpfung
- **Status:** DONE

### TASK-011: Storefront SCSS & Mobile-First
- **Epic:** EPIC-3
- **Priority:** P1
- **Estimate:** ~30min
- **depends_on:** [TASK-010]
- **parallel:** true
- **DoD (Definition of Done):**
  - [x] `src/Resources/app/storefront/src/scss/base.scss` angelegt
  - [x] Touch-Targets ≥ 44px (Buttons 48px), Farbpalette `#1a1a2e`
  - [x] Mobile-First Breakpoints
- **Status:** DONE

### TASK-012: Storefront JS-Plugin
- **Epic:** EPIC-3
- **Priority:** P1
- **Estimate:** ~30min
- **depends_on:** [TASK-010]
- **parallel:** true
- **DoD (Definition of Done):**
  - [x] `src/Resources/app/storefront/src/plugin/preorder-manager.plugin.js`
  - [x] `src/Resources/app/storefront/src/main.js` mit `PluginManager.register`
- **Status:** DONE

---

## EPIC-4: Administration UI

### TASK-013: Admin Module & Snippets
- **Epic:** EPIC-4
- **Priority:** P2
- **Estimate:** ~30min
- **depends_on:** [TASK-004]
- **parallel:** true
- **DoD (Definition of Done):**
  - [x] `src/Resources/app/administration/src/main.js`
  - [x] `src/Resources/app/administration/src/module/custom-preorder-manager/index.js`
  - [x] Zweisprachige Snippets `de-DE.json` und `en-GB.json`
- **Status:** DONE

### TASK-014: Admin Dashboard Listing
- **Epic:** EPIC-4
- **Priority:** P2
- **Estimate:** ~45min
- **depends_on:** [TASK-013]
- **parallel:** false
- **DoD (Definition of Done):**
  - [x] `Component.register('custom-preorder-manager-list', ...)`
  - [x] `listing`-Mixin eingebunden, Criteria-Setter genutzt
  - [x] SCSS explizit in `index.js` importiert
- **Status:** DONE

---

## EPIC-5: Verification, Tests & Release Gate

### TASK-015: PHPUnit Test Integration
- **Epic:** EPIC-5
- **Priority:** P1
- **Estimate:** ~45min
- **depends_on:** [TASK-006]
- **parallel:** true
- **DoD (Definition of Done):**
  - [x] `tests/TestBootstrap.php`
  - [x] `tests/Integration/Cart/PreOrderCartCollectorTest.php`
  - [x] `tests/Integration/Subscriber/OrderPlacedSubscriberTest.php`
- **Status:** DONE

### TASK-016: Syntax-Validierung & Audit-Gate
- **Epic:** EPIC-5
- **Priority:** P0
- **Estimate:** ~30min
- **depends_on:** [TASK-001, TASK-015]
- **parallel:** false
- **DoD (Definition of Done):**
  - [x] XML-Validierung mit `xmllint` fehlerfrei
  - [x] JSON-Validierung mit `python3 -m json.tool` fehlerfrei
  - [x] Prüfung aller Kill-Kriterien
  - [x] `walkthrough.md` und `task.md` finalisiert
- **Status:** DONE
