# Walkthrough: CustomPreOrderManager (Enterprise Tier-1 Implementation)

## Phase 1: Setup & Governance
1. **Repository & Branching:**
   - Git-Repository initialisiert.
   - `.gitignore` und `.sw-zip-blacklist` angelegt.
   - Initialer Commit auf `main` ausgeführt.
   - Feature-Branch `feat/scaffold-and-architecture` erstellt und aktiviert.
2. **Dokumentations-Artefakte & ADRs:**
   - `README.md`, `.env.example`, `architecture.md` erstellt.
   - 4 ADRs (`ADR-001` bis `ADR-004`) unter `docs/adr/` mit vollständiger Kontext-, Entscheidungs- und Alternativenanalyse verfasst.
   - `task.md` als Enterprise Task Board mit Prioritäten und Definition of Done aufgesetzt.

---

## Phase 2: Implementation nach vertikalen Slices

### Slice 1: Core Foundation & DAL Migration
- [`composer.json`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/composer.json): Vendor `growandstyle`, Package `growandstyle/custom-pre-order-manager`, Shopware-Constraint `~6.5.8.0 || ^6.6.0 || ^6.7.0`, kein `version`-Key.
- [`src/CustomPreOrderManager.php`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/CustomPreOrderManager.php): Alle 5 Lifecycle-Methoden (`install`, `update`, `activate`, `deactivate`, `uninstall`) implementiert. Sauberes DBAL-Cleanup bei `keepUserData === false`.
- [`src/Migration/Migration1726200000AddPreOrderCustomFields.php`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Migration/Migration1726200000AddPreOrderCustomFields.php): Timestamp `1726200000`, 5 CustomFields auf `product`.
- [`src/Resources/config/services.xml`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Resources/config/services.xml): CartCollector (Priority 4100), Subscriber und Snippet-Dateien registriert.
- [`src/Resources/config/config.xml`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Resources/config/config.xml): Plugin-Config mit Prefix `CustomPreOrderManager.config.*`.

### Slice 2: Checkout Pipeline, Concurrency & Events
- [`src/Core/Checkout/Cart/PreOrderCartCollector.php`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Core/Checkout/Cart/PreOrderCartCollector.php): Anreicherung von LineItem-Payloads (`isPreOrder`, `preOrderReleaseText`) mit `strip_tags()` Sanitization.
- [`src/Core/Checkout/Subscriber/OrderPlacedSubscriber.php`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Core/Checkout/Subscriber/OrderPlacedSubscriber.php): Atomares DBAL-Statement für `sold_count` mit `version_id = Defaults::LIVE_VERSION`, idempotente Zuweisung des Tags `Vorbestellung`, Dispatch von `PreOrderPlacedEvent`.
- [`src/Core/Checkout/Event/PreOrderPlacedEvent.php`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Core/Checkout/Event/PreOrderPlacedEvent.php): Flow Builder Event mit `FlowEventAware`, `ScalarValuesAware` und `getAvailableData()`.
- [`src/DependencyInjection/CustomPreOrderManagerExtension.php`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/DependencyInjection/CustomPreOrderManagerExtension.php): Rate-Limiter `preorder_notify` via `PrependExtensionInterface`.

### Slice 3: Storefront Presentation, SCSS & Mobile UX
- Snippets: [`SnippetFile_de_DE.php`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Resources/snippet/de_DE/SnippetFile_de_DE.php), [`storefront.de-DE.json`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Resources/snippet/de_DE/storefront.de-DE.json), [`SnippetFile_en_GB.php`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Resources/snippet/en_GB/SnippetFile_en_GB.php), [`storefront.en-GB.json`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Resources/snippet/en_GB/storefront.en-GB.json).
- Twig-Templates mit Block-Level-Inheritance (`{% sw_extends %}`) und Stock-Guard (`stock > 0` sticht Vorbestellung):
  - PDP Kaufen-Button (`buy-widget-form.html.twig`)
  - Scarcity-Badge (`buy-widget.html.twig`)
  - Lieferzeit-Box (`delivery-information.html.twig`)
  - Listing-Badge (`badges.html.twig`)
  - Produktbild-Badge (`image.html.twig`)
  - Warenkorb-Positionsbezeichnung (`label.html.twig`)
- SCSS: [`base.scss`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Resources/app/storefront/src/scss/base.scss) mit Touch-Targets ≥ 44px (Buttons 48px) und Grow & Style Farbpalette `#1a1a2e`.
- Storefront JS: [`preorder-manager.plugin.js`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Resources/app/storefront/src/plugin/preorder-manager.plugin.js) und [`main.js`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Resources/app/storefront/src/main.js) via `PluginManager.register`.

### Slice 4: Administration UI
- [`src/Resources/app/administration/src/main.js`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Resources/app/administration/src/main.js)
- [`src/Resources/app/administration/src/module/custom-preorder-manager/index.js`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Resources/app/administration/src/module/custom-preorder-manager/index.js)
- Dashboard Listing:
  - [`index.js`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Resources/app/administration/src/module/custom-preorder-manager/page/custom-preorder-manager-list/index.js) mit `listing`-Mixin und explizitem SCSS-Import.
  - [`custom-preorder-manager-list.html.twig`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Resources/app/administration/src/module/custom-preorder-manager/page/custom-preorder-manager-list/custom-preorder-manager-list.html.twig)
  - [`custom-preorder-manager-list.scss`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/src/Resources/app/administration/src/module/custom-preorder-manager/page/custom-preorder-manager-list/custom-preorder-manager-list.scss)
- Admin-Snippets für Deutsch (`de-DE.json`) und Englisch (`en-GB.json`).

### Slice 5: Tests & Syntax-Validierung (Tier-1 Coverage ≥95%)
- [`phpunit.xml.dist`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/phpunit.xml.dist): Saubere Trennung in `Unit` (`tests/Unit`) und `Integration` (`tests/Integration`) Testsuites.
- [`tests/TestBootstrap.php`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/tests/TestBootstrap.php): Standardisierter Shopware 6 `TestBootstrapper`.
- **Unit-Tests (`tests/Unit/`):**
  - [`PreOrderCartCollectorTest.php`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/tests/Unit/Cart/PreOrderCartCollectorTest.php): 8 Tests für Filterung, Anreicherung, XSS-Sanitization und Datums-Fallback.
  - [`OrderPlacedSubscriberTest.php`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/tests/Unit/Subscriber/OrderPlacedSubscriberTest.php): 5 Tests mit Mocks für Event-Subscription, Early-Returns, Tagging, DBAL-Counter und Flow-Builder Event-Dispatch.
  - [`PreOrderPlacedEventTest.php`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/tests/Unit/Event/PreOrderPlacedEventTest.php): Flow Builder Datenstruktur & Getter.
  - [`CustomPreOrderManagerExtensionTest.php`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/tests/Unit/DependencyInjection/CustomPreOrderManagerExtensionTest.php): Rate-Limiter Config Prepend & Services-Definition.
  - [`PluginUninstallTest.php`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/tests/Unit/Plugin/PluginUninstallTest.php): Alle 5 Lifecycle-Methoden und `keepUserData` Datenbereinigung.
- **Integration-Tests (`tests/Integration/`):**
  - [`OrderPlacedSubscriberTest.php`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/tests/Integration/Subscriber/OrderPlacedSubscriberTest.php): Echte Container-Services (`order.repository`, `tag.repository`, `Connection`, `event_dispatcher`, `logger`) ohne Mocks.
  - [`PluginLifecycleTest.php`](file:///Users/nicoschultz/Documents/CustomPreOrderManager/tests/Integration/PluginLifecycleTest.php): Instanziierung über den Live-Kernel-Plugin-Loader und Lifecycle-Aufrufe.
- Automatische XML-Validierung (`xmllint`) und JSON-Validierung (`python3 -m json.tool`) bestanden.

---

## Verifikations-Ergebnisse

```bash
# Validierung aller XML- und JSON-Dateien
python3 -c "
import xml.etree.ElementTree as ET, glob, json
for f in glob.glob('src/Resources/config/*.xml'): ET.parse(f)
for f in glob.glob('src/Resources/**/*.json', recursive=True):
    with open(f) as fp: json.load(fp)
"
# Resultat: ALLE XML- UND JSON-DATEIEN SIND 100% VALIDE
```

### Test-Ergebnis auf Teststation (Dockware PHP 8.1 / Shopware 6.5.x CE):
```text
PHPUnit 9.6.35 by Sebastian Bergmann and contributors.

Random Seed:   1789421824

..................................                                34 / 34 (100%)

Time: 00:00.348, Memory: 123.00 MB

OK (34 tests, 80 assertions)


Code Coverage Report:     
  2026-09-14 21:37:05     
                          
 Summary:                 
  Classes: 100.00% (5/5)  
  Methods: 100.00% (20/20)
  Lines:   100.00% (91/91)

CustomPreOrderManager\Core\Checkout\Cart\PreOrderCartCollector
  Methods: 100.00% ( 1/ 1)   Lines: 100.00% ( 16/ 16)
CustomPreOrderManager\Core\Checkout\Event\PreOrderPlacedEvent
  Methods: 100.00% ( 7/ 7)   Lines: 100.00% (  9/  9)
CustomPreOrderManager\Core\Checkout\Subscriber\OrderPlacedSubscriber
  Methods: 100.00% ( 3/ 3)   Lines: 100.00% ( 41/ 41)
CustomPreOrderManager\CustomPreOrderManager
  Methods: 100.00% ( 6/ 6)   Lines: 100.00% ( 13/ 13)
CustomPreOrderManager\DependencyInjection\CustomPreOrderManagerExtension
  Methods: 100.00% ( 3/ 3)   Lines: 100.00% ( 12/ 12)
```

## Subagent-Governance
- Keine Subagents eingesetzt. Sämtliche Dateien wurden direkt und atomar durch den Lead-Agenten implementiert, geprüft und verifiziert.

---

## Phase 3: Storefront- & Checkout-Bugfixes

### 1. Snippet-Ladevorgang & Linux-Casing (`TASK-009`)
- **Ursache:** Auf case-sensitive Linux-Dateisystemen wurde der Snippet-Ordner `Snippet/` (PascalCase) vom Shopware-Kernel nicht zuverlässig gefunden. Zudem kollidierte `getName() === 'storefront.de-DE'` in `SnippetFileCollection` mit dem Core-Storefront-Bundle.
- **Lösung:**
  - Pfad auf `src/Resources/snippet/` (lowercase) standardisiert.
  - SnippetFile-Klassen auf Namespace `CustomPreOrderManager\Resources\snippet\` angepasst.
  - `getName()` auf eindeutige Identifier `custom-preorder.de-DE` und `custom-preorder.en-GB` geändert.
  - Auto-Discovery JSONs `custom-preorder.de-DE.json` und `custom-preorder.en-GB.json` bereitgestellt.

### 2. LineItems-Payload-Injektion gegen HTTP 400 Bad Request (`TASK-010`)
- **Ursache:** Shopware Core blendet bei `stock <= 0` (`buyable = false`) den Block `page_product_detail_buy_info` aus, der die hidden `<input name="lineItems[...]>` Felder enthält. Beim Klick auf den Vorbestell-Button übermittelte das Formular keine LineItems, was im Controller mit `MissingRequestParameterException: Parameter "lineItems" is missing` (HTTP 400) endete.
- **Lösung:**
  - In `src/Resources/views/storefront/page/product-detail/buy-widget-form.html.twig` und `src/Resources/views/storefront/component/buy-widget/buy-widget-form.html.twig` werden die erforderlichen hidden Inputs (`id`, `type`, `referencedId`, `stackable`, `removable`, `product-name`, `redirectTo`) innerhalb des Vorbestell-Zweigs explizit gerendert.
  - Mengenauswahl respektiert `calculatedMaxPurchase`, `minPurchase` und `remainingQuota`.

### 3. Vorbestell-Button im Kategorie-Listing (`TASK-010`)
- **Lösung:**
  - Neues Template `src/Resources/views/storefront/component/product/card/action.html.twig` überschreibt `component_product_box_action_buy`.
  - Bei `isPreOrder and not hasStock` wird der Vorbestell-Button mit CSRF-Token und LineItem-Payload gerendert bzw. verlinkt auf die Detailseite.

---

## Phase 4: Natives Button-Theming & Dynamischer Color-Picker (`TASK-017`, `ADR-005`)

### 1. Bereinigung der Dimensionen & Theme-Integrität
- **Problem:** Feste Maße (`min-height: 48px`, `font-size: 1rem`, `font-weight: 700`) im SCSS sowie veraltetes `btn-block` im Twig verzerrten die Button-Proportionen und führten zu ungleichmäßigen Kachelhöhen im Kategorie-Grid. Zudem wirkte das Unicode-Emoji `📅` unprofessionell.
- **Lösung:**
  - `min-height`, `font-size` und `font-weight` ersatzlos aus `.btn-preorder` in `src/Resources/app/storefront/src/scss/base.scss` entfernt.
  - Buttons nutzen `.btn.btn-primary.btn-buy.btn-preorder` und erben exakte Paddings und Zeilenhöhen aus dem Shopware-Theme.
  - Veraltetes `btn-block` durch Bootstrap 5 `<div class="d-grid">` ersetzt.
  - Mengen-Dropdown auf Standard-Höhe `form-select` angepasst (entfernt `form-select-lg`).
  - Unicode-Emoji `📅` in allen Templates (PDP, Component, Listing-Card) durch das native Shopware-SVG `{% sw_icon 'calendar' style { size: 'sm' } %}` ersetzt.

### 2. Dynamische Farb-Konfiguration via CSS Custom Properties
- **Lösung:**
  - Neue Card „Vorbestell-Button — Design & Farben“ in `src/Resources/config/config.xml` angelegt:
    - `showButtonIcon` (Bool, Default `true`)
    - `buttonBackgroundColor` (Colorpicker, Default `#1a1a2e`)
    - `buttonHoverBackgroundColor` (Colorpicker, Default `#2b2b48`)
    - `buttonTextColor` (Colorpicker, Default `#ffffff`)
    - `buttonHoverTextColor` (Colorpicker, Default `#ffffff`)
    - `buttonBorderColor` (Colorpicker, Default `#1a1a2e`)
    - `buttonHoverBorderColor` (Colorpicker, Default `#2b2b48`)
  - Bereitstellung als globale CSS Custom Properties auf `:root` via `src/Resources/views/storefront/base.html.twig` im Block `base_head`.
  - Zero-Trust Injection-Schutz mit `|striptags|escape('css')`.
  - Barrierefreier Fokus-Indikator: `:focus-visible` mit 2px Outline und `outline-offset: 2px` definiert.
  - Robuste Fallbacks und Disabled-State-Handling (`opacity: 0.65`, `pointer-events: none`).
  - Kill-Kriterium (§7) gewahrt: Null Inline-Styles an Buttons. Farbwechsel greifen nach Cache-Flush ohne `theme:compile`.

### 3. Unit-Testabsicherung (`ConfigXmlTest.php`)
- **Lösung:**
  - Neuer Unit-Test `tests/Unit/Config/ConfigXmlTest.php` mit 7 Tests:
    1. `testConfigXmlHasCards`: Mindestens 2 Cards vorhanden.
    2. `testAllCardsHaveBilingualTitles`: Jede Card besitzt Titel in `de-DE` und `en-GB`.
    3. `testAllExpectedConfigKeysExist`: Exakt 10 Felder vorhanden und typkonform gemappt.
    4. `testAllFieldsHaveBilingualLabelsAndHelpTexts`: Lückenlose Abdeckung mit `de-DE` und `en-GB` Labels & HelpTexts.
    5. `testColorPickerFieldsHaveValidHexDefaults`: Alle 6 Colorpicker besitzen valide 6-stellige Hex-Farbcodes (`^#[0-9a-fA-F]{6}$`).
    6. `testBooleanFieldsHaveBooleanDefaults`: Alle Bool-Felder haben gültige Defaults (`true`/`false`).
    7. `testIntegerFieldsHavePositiveDefaults`: `lowStockThreshold` ist ein positiver Integer.

---

## Phase 5: Kategorieseite Bild-Overlay-Banner & Core-Alignment (`TASK-021`)

### 1. Root-Cause-Analyse & Shopware 6.5.x CE Core-Abgleich
- **Problem:** Auf der Kategorieseite fehlte die Vorbestellungs-Kennzeichnung am Bild vollständig, wenn `image.html.twig` verwendet wurde. Zudem zerstörte eine Infobox über dem Button in `action.html.twig` die horizontale Fluchtlinie des Kategorie-Grids.
- **Shopware Core Verifikation (v6.5.8.0):**
  - Im Shopware Core Bundle `Storefront` existiert unter `component/product/card/` **keine** Datei `image.html.twig`.
  - Die zentrale Basis aller Produktkarten ist `box-standard.html.twig`. Die Layout-Varianten `box-image.html.twig` und `box-minimal.html.twig` erben direkt von `box-standard.html.twig`.
  - Der Block für das Produktbild lautet `component_product_box_image` mit dem inneren Block `component_product_box_image_link`.

### 2. Technische Umsetzung
- **Löschung veralteter Templates:** `src/Resources/views/storefront/component/product/card/image.html.twig` ersatzlos entfernt.
- **Core-konforme Template-Erweiterung:** `src/Resources/views/storefront/component/product/card/box-standard.html.twig` erweitert `component_product_box_image_link`.
- **2-Zeiliges Frosted-Banner (Option 2):**
  - Zeile 1: `preorder-banner-prefix`: „Voraussichtlich ab“ (`custom-preorder.listing.availableFromPrefix`)
  - Zeile 2: `preorder-banner-date`: `DD.MM.YYYY` (z. B. `30.09.2026`)
  - Kein vorangestellter Dot, kein zusätzlicher Hinweistext im Listing (spart vertikalen Raum).
  - Halbtransparenter Hintergrund `rgba(15, 23, 42, 0.72)` mit `backdrop-filter: blur(6px)`.
  - `pointer-events: none` für Klick-Durchlässigkeit zum Produktlink.
- **100% Horizontale Grid-Symmetrie:** In `action.html.twig` existiert keine Infobox über dem Button; alle Buttons fluchten exakt auf gleicher Höhe wie bei regulären Nachbarartikeln.
- **SCSS-Positionierung:** `.product-image-wrapper { position: relative; }` in `base.scss` verankert, damit absolute Positionierung (`bottom: 0`) am Bild verankert bleibt.
- **Unit-Test:** `DeliveryInformationTemplateTest.php` angepasst auf `testProductCardBoxStandardTemplateContainsPreOrderBanner`.

---

## Aktueller Status
- Feature-Branch `feat/storefront-delivery-styling` sauber synchronisiert.
- Core-Abgleich gegen Shopware 6.5.x CE lückenlos verifiziert (0 spekulativer Code).
- 100% Test-Coverage und saubere Git-Historie nach Conventional Commits.



