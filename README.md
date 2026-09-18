<p align="center">
  <img src="src/Resources/config/plugin.png" alt="CustomPreOrderManager Logo" width="128">
</p>

<h1 align="center">CustomPreOrderManager</h1>

<p align="center">
  <strong>Autarkes High-Conversion Vorbestellungs-System mit Scarcity-Engine für Shopware 6</strong><br>
  <em>Self-contained high-conversion pre-order system with scarcity engine for Shopware 6</em>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Shopware-6.5%20|%206.6%20|%206.7-189eff?style=flat-square&logo=shopware&logoColor=white" alt="Shopware">
  <img src="https://img.shields.io/badge/Version-v1.0.0-0f62fe?style=flat-square" alt="Version">
  <img src="https://img.shields.io/badge/PHP-8.1+-777bb4?style=flat-square&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/Tests-42%20passed-2ea44f?style=flat-square&logo=githubactions&logoColor=white" alt="Tests">
  <img src="https://img.shields.io/badge/Coverage-100%25-2ea44f?style=flat-square" alt="Coverage">
  <img src="https://img.shields.io/badge/Architecture-Enterprise%20Tier--1-blueviolet?style=flat-square" alt="Architecture">
  <img src="https://img.shields.io/badge/Security-Zero--Trust%20%7C%20XSS--Proof-success?style=flat-square" alt="Security">
  <img src="https://img.shields.io/badge/DSGVO%2FGDPR-100%25%20Compliant-brightgreen?style=flat-square" alt="DSGVO">
  <img src="https://img.shields.io/badge/Lizenz-Propriet%C3%A4r-red?style=flat-square" alt="Lizenz">
</p>

---

## 📑 Inhaltsverzeichnis / Table of Contents

- [Übersicht / Overview](#-übersicht--overview)
  - [Deutsch](#deutsch)
  - [English](#english)
- [Problem & Lösung (Problem vs. Solution Matrix)](#-problem--lösung-problem-vs-solution-matrix)
- [Zielgruppen-Mehrwert (Value Proposition)](#-zielgruppen-mehrwert-value-proposition)
- [Feature-Matrix & Highlights](#-feature-matrix--highlights)
  - [Storefront & Conversion UX](#-storefront--conversion-ux)
  - [High-Concurrency Backend & Automation](#-high-concurrency-backend--automation)
  - [Administration & Dashboard](#-administration--dashboard)
- [Vorbestellungs-Lifecycle & State Machine](#-vorbestellungs-lifecycle--state-machine)
- [Datenmodell & CustomFields](#-datenmodell--customfields)
- [System-Konfiguration](#️-system-konfiguration-configuration-reference)
- [Flow Builder Integration & Business Events](#-flow-builder-integration--business-events)
- [Architektur & Verzeichnisstruktur](#️-architektur--verzeichnisstruktur)
- [Installation & Build-Pipeline](#-installation--build-pipeline)
  - [Systemvoraussetzungen](#systemvoraussetzungen)
  - [Release-Build mit shopware-cli](#release-build-mit-shopware-cli)
  - [Manuelle Installation im Shop](#manuelle-installation-im-shop)
- [Testing & Qualitätssicherung (100% Coverage)](#-testing--qualitätssicherung-100-coverage)
  - [Test-Architektur & Test-Matrix](#test-architektur--test-matrix)
  - [Offizieller PHPUnit-Prüfbericht (Dockware Teststation)](#offizieller-phpunit-prüfbericht-dockware-teststation)
- [Sicherheit, Concurrency & DSGVO](#-sicherheit-concurrency--dsgvo)
- [Architecture Decision Records (ADR-Index)](#️-architecture-decision-records-adr-index)
- [Lizenz & Support](#-lizenz--support)

---

## 📖 Übersicht / Overview

### Deutsch
Das Plugin **CustomPreOrderManager** ist ein hochperformantes, autarkes Vorbestellungs-System für Shopware 6.5.x Community Edition (vollständig aufwärtskompatibel zu Shopware 6.6 und 6.7). Es ermöglicht Online-Händlern den risikolosen Vorabverkauf von Produkten im Lieferzulauf mit atomarer Echtzeit-Kontingentsicherung, psychologischer Verknappungsanzeige (Scarcity Engine) und nativer Flow-Builder-Anbindung.

Das Plugin folgt strikt dem Prinzip der **Non-Destructive Architecture**: Bestehende Shopware-Core-Strukturen, Standard-Mail-Vorlagen, PDF-Belege und Datenbanktabellen bleiben unberührt. Vorbestellungs-Metadaten werden zur Laufzeit über einen Cart-Collector mit Priorität `4100` direkt an die LineItem-Payload angeheftet und nahtlos durch den Checkout geschleust.

### English
**CustomPreOrderManager** is an enterprise-grade, autonomous pre-order solution for Shopware 6.5.x Community Edition (fully forward-compatible with Shopware 6.6 and 6.7). It empowers online merchants to monetize incoming stock before physical arrival using atomic real-time quota protection, an integrated psychological scarcity engine, and native Flow Builder integration.

Engineered with a **Non-Destructive Architecture**, the plugin enriches line item payloads at runtime (Collector priority `4100`) without modifying core database schemas, mail templates, or PDF documents. Transactions remain fully atomic, eliminating race-conditions during high-volume product drop events.

---

## 🎯 Problem & Lösung (Problem vs. Solution Matrix)

| Herausforderung im Shopware-Standard | Enterprise-Lösung durch CustomPreOrderManager | Technischer Mechanismus |
|---|---|---|
| **Umsatzverlust bei Stockout:** Artikel mit `stock <= 0` können nicht gekauft werden; Kunden brechen ab. | **Nahtloser Vorabverkauf:** Produkte lassen sich sofort mit Zulaufmenge und Erscheinungsdatum anbieten. | Buy-Container-Override hebelt Standard-Sperre bei aktivem Vorbestell-Modus gezielt aus. |
| **Überbuchung bei Traffic-Spitzen:** Parallele Checkouts überbuchen Lieferantenkontingente. | **Atomare DBAL-Inkrementierung:** Zählerstände werden auf Zeilenebene sperrungsfrei und konsistent gezählt. | Direktes `UPDATE product SET ...` mit `JSON_SET` gefiltert auf `Defaults::LIVE_VERSION`. |
| **Zerstörte Mail-Layouts:** Drittanbieter-Plugins überschreiben Standard-Templates und kollidieren. | **LineItem Payload Enrichment:** Vorbestell-Metadaten werden an die Position geheftet; Belege übernehmen sie nativ. | `PreOrderCartCollector` (`Priority 4100`) injiziert Metadaten in `lineItem->payload`. |
| **Manueller Umstellungsaufwand:** Nach Wareneingang müssen Vorbestellungen manuell deaktiviert werden. | **Automatischer Stock-Lifecycle:** Physischer Lagerbestand (`stock > 0`) deaktiviert den Vorbestellmodus automatisch. | Template- & Collector-Guard priorisiert physischen Bestand vor Vorbestell-Status. |
| **DSGVO-Risiken durch Wartelisten:** E-Mail-Wartelisten erfordern DOI-Einwilligungen und Löschroutinen. | **Autarke Kontingentsperre:** Nach Kontingenterschöpfung wird der Kaufbutton gesperrt. Keine Datenspeicherung. | Direkte Sperrung im Storefront-Template; DSGVO-konform ohne PII-Hinterlegung. |

---

## 💎 Zielgruppen-Mehrwert (Value Proposition)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        CUSTOM PRE-ORDER MANAGER                             │
├────────────────────────┬────────────────────────────┬───────────────────────┤
│    FÜR DEN HÄNDLER     │    FÜR DEN TECHNISCHEN     │    FÜR DEN ENDKUNDEN  │
│      (COMMERCE)        │        LEITER (CTO)        │         (UX)          │
├────────────────────────┼────────────────────────────┼───────────────────────┤
│ • Maximaler Cashflow   │ • Zero Schema Locks        │ • Transparente        │
│   vor Wareneingang     │ • 100% Test-Coverage       │   Lieferzeitzusagen   │
│ • Hohe Conversion Dank │ • Atomare DBAL-Updates     │ • Kein Abbruch beim   │
│   Scarcity-Engine      │ • Zero-Trust XSS-Schutz    │   Mischwarenkorb      │
│ • Automatisches Order- │ • Native Flow Builder      │ • Psychologische      │
│   Tagging & Filterung  │   Events ohne Core-Hacks   │   Dringlichkeit       │
└────────────────────────┴────────────────────────────┴───────────────────────┘
```

---

## 💡 Feature-Matrix & Highlights

### 🛍️ Storefront & Conversion UX
* **Dynamischer Kauf-Button:** Ersetzt den Standard-Warenkorbbutton auf der Produktdetailseite durch einen markanten Vorbestell-Button mit dynamischem Icon.
* **Psychologische Scarcity Engine:** Ermittelt die Restquote in Echtzeit. Fällt das Kontingent unter den konfigurierbaren Schwellenwert (`lowStockThreshold`, z. B. 5 Stück), signalisiert ein pulsierendes Badge: *„Fast vergriffen: Nur noch X Stück verfügbar!“*.
* **Präzise Lieferzeit-Information:** Zeigt entweder ein formatiertes Erscheinungsdatum (*„Lieferbar ab DD.MM.YYYY“*) oder einen frei pflegbaren Hinweistext an.
* **Listing- & Suchergebnis-Badges:** Visuelle Auszeichnung von Vorbestellartikeln direkt auf Kategorieseiten und im Such-Listing.
* **Mischwarenkörbe (Seamless Checkout):** Kunden können Vorbestellartikel und sofort lieferbare Lagerartikel in einer gemeinsamen Bestellung kombinieren.
* **Mobile-First UX:** Sämtliche interaktiven Elemente besitzen Touch-Targets von mindestens 48px, optimierte Tap-Abstände und verhindern Layout-Shifts (CLS = 0).

### ⚙️ High-Concurrency Backend & Automation
* **Non-Destructive CustomField-Set:** Verwaltet alle Daten im Set `custom_preorder_set` auf `product` – keine Core-Tabellenänderungen, automatische Variantenvererbung.
* **Atomarer DBAL-Zähler:** Parallele Bestellvorgänge aktualisieren `custom_preorder_sold_count` atomar im SQL-Statement auf Zeilenebene unter `Defaults::LIVE_VERSION`.
* **Automatisches Order-Tagging:** Bestellungen mit Vorbestellpositionen erhalten beim Abschluss automatisch das Tag `Vorbestellung` zur direkten Filterung in der Versandlogistik.
* **Flow Builder Integration:** Dispatched das Business Event `preorder.order.placed` (`PreOrderPlacedEvent`) mit vollständiger `FlowEventAware`- und `ScalarValuesAware`-Unterstützung.
* **Zero-Trust Input-Sanitization:** Sämtliche Freitexteingaben werden vor der Übernahme in die Cart-Payload rekursiv von HTML-Tags gereinigt (`strip_tags()`).
* **Integrierter Rate Limiter:** Registriert Symfony-Rate-Limiter-Konfigurationen via `PrependExtensionInterface` für Storefront-Endpunkte.

### 🖥️ Administration & Dashboard
* **Dediziertes Vorbestellungs-Dashboard:** Eigenständiges Vue.js 3 Modul unter *Bestellungen → Vorbestellungen* mit Statusspalten, Quotenberechnung und Pagination.
* **Produkt-Detail Tab:** Intuitive Pflege aller 5 Vorbestellungs-Attribute inklusive Anzeige des aktuellen Restkontingents.
* **100 % Zweisprachig:** Vollständige Snippet-Abdeckung in Deutsch (`de-DE`) und Englisch (`en-GB`).

---

## 🔄 Vorbestellungs-Lifecycle & State Machine

Das Plugin steuert den Produktstatus vollautomatisch anhand von physischem Lagerbestand und Vorbestellkontingent:

```
                      ┌─────────────────────────┐
                      │  Neues Produkt erfasst  │
                      └────────────┬────────────┘
                                   │
                                   ▼
                     /───────────────────────────\
                    <   Physischer Stock > 0?     >─── JA ───► Standard-Verkauf (Sofort lieferbar)
                     \───────────────────────────/
                                   │ NEIN
                                   ▼
                     /───────────────────────────\
                    <   Vorbestellung aktiv?      >─── NEIN ──► Ausverkauft (Standard Shopware)
                     \───────────────────────────/
                                   │ JA
                                   ▼
                     /───────────────────────────\
                    <  Restkontingent verfügbar?  >─── NEIN ──► Button gesperrt ("Nicht vorbestellbar")
                     \───────────────────────────/
                                   │ JA
                                   ▼
          ┌─────────────────────────────────────────────────┐
          │         VORBESTELLUNG AKTIV (Storefront)        │
          │  • Restmenge > Threshold  → Normales Badge      │
          │  • Restmenge <= Threshold → Pulsierendes Badge  │
          └────────────────────────┬────────────────────────┘
                                   │
                          Kunde bestellt Artikel
                                   │
                                   ▼
          ┌─────────────────────────────────────────────────┐
          │             CHECKOUT & ORDER PLACED             │
          │  1. LineItem Payload enthält Vorbestell-Flags   │
          │  2. Atomarer DBAL Counter: sold_count += Qty    │
          │  3. Tag "Vorbestellung" an Bestellung geheftet  │
          │  4. Business Event 'preorder.order.placed' feuert│
          └─────────────────────────────────────────────────┘
```

---

## 📋 Datenmodell & CustomFields

Das Plugin erweitert Shopware-Produkte über das CustomField-Set `custom_preorder_set` auf der Entity `product`:

| Technischer Feldname | Datentyp | Admin UI Komponente | Standard | Beschreibung |
|---|:---:|---|:---:|---|
| `custom_preorder_active` | `BOOL` | `sw-switch-field` | `false` | Aktiviert den Vorbestellmodus für das Produkt (greift wenn `stock <= 0`). |
| `custom_preorder_release_date` | `DATETIME` | `sw-datepicker` | `null` | Geplantes Erscheinungsdatum (steuert Datumsanzeige im Frontend). |
| `custom_preorder_release_text` | `TEXT` | `sw-field (text)` | `null` | Individueller Hinweistext in der Storefront (z. B. *„Lieferbar ab Mitte Oktober“*). |
| `custom_preorder_inbound_stock` | `INT` | `sw-field (number)` | `0` | Maximales Vorbestellungs-Kontingent (Lieferanten-Zulaufmenge). |
| `custom_preorder_sold_count` | `INT` | `sw-field (disabled)` | `0` | Bisher verkaufte Vorbestellungsmenge (wird atomar per DBAL inkrementiert). |

### Kontingent-Berechnungsformel

$$\text{Verbleibendes Kontingent} = \max\left(0, \; \text{custom\_preorder\_inbound\_stock} - \text{custom\_preorder\_sold\_count}\right)$$

* Ist $\text{Verbleibendes Kontingent} \le \text{lowStockThreshold}$, wird die Dringlichkeitsstufe (Scarcity Badge) aktiv.
* Ist $\text{Verbleibendes Kontingent} = 0$, wird der Kauf-Button gesperrt und der Status *„Aktuell nicht vorbestellbar“* angezeigt.

---

## ⚙️ System-Konfiguration (Configuration Reference)

Pflegbar im Shopware Administration Panel unter *Einstellungen → Erweiterungen → PreOrderManager*:

| Konfigurations-Schlüssel | Typ | Standard | Beschreibung (DE) | Description (EN) |
|---|:---:|:---:|---|---|
| `CustomPreOrderManager.config.enableListingBadge` | `bool` | `true` | Zeigt das Vorbestellungs-Badge auf Kategorieseiten und in der Suche an. | Display pre-order badge on category and search listing cards. |
| `CustomPreOrderManager.config.enableScarcityCounter` | `bool` | `true` | Aktiviert den Restmengen-Zähler auf der Produktdetailseite. | Enable remaining quota counter on the product detail page. |
| `CustomPreOrderManager.config.lowStockThreshold` | `int` | `5` | Schwellenwert für das Dringlichkeits-Badge (*„Fast vergriffen“*). | Remaining stock threshold to trigger urgency badge. |
| `CustomPreOrderManager.config.buttonBackgroundColor` | `colorpicker` | `#1a1a2e` | Button Hintergrundfarbe (Normalzustand). | Button background color (default state). |
| `CustomPreOrderManager.config.buttonHoverBackgroundColor` | `colorpicker` | `#2b2b48` | Button Hintergrundfarbe (Hover / Fokus). | Button background color (hover / focus). |
| `CustomPreOrderManager.config.buttonTextColor` | `colorpicker` | `#ffffff` | Button Text- und Icon-Farbe (Normalzustand). | Button text and icon color (default state). |
| `CustomPreOrderManager.config.buttonHoverTextColor` | `colorpicker` | `#ffffff` | Button Text- und Icon-Farbe (Hover / Fokus). | Button text and icon color (hover / focus). |
| `CustomPreOrderManager.config.buttonBorderColor` | `colorpicker` | `#1a1a2e` | Button Rahmenfarbe (Normalzustand). | Button border color (default state). |
| `CustomPreOrderManager.config.buttonHoverBorderColor` | `colorpicker` | `#2b2b48` | Button Rahmenfarbe (Hover / Fokus). | Button border color (hover / focus). |
| `CustomPreOrderManager.config.cardBorderRadius` | `int` | `12` | Eckenrundung der Card in Pixeln (Squircle). | Corner radius of the card in pixels (squircle). |
| `CustomPreOrderManager.config.cardBackgroundColor` | `colorpicker` | `#f8fafc` | Card Hintergrundfarbe (Fläche / Gradient). | Card background color (surface / gradient). |
| `CustomPreOrderManager.config.cardBorderColor` | `colorpicker` | `#e2e8f0` | Card Rahmenfarbe (Micro-Border). | Card border color (micro-border). |
| `CustomPreOrderManager.config.cardAccentColor` | `colorpicker` | `#e67e22` | Card Akzentfarbe (Icon-Badge, Pill-Tag, Puls-Dot). | Card accent color (icon badge, pill tag, pulse dot). |
| `CustomPreOrderManager.config.cardTextColor` | `colorpicker` | `#0f172a` | Card Headline-Textfarbe. | Card headline text color. |
| `CustomPreOrderManager.config.cardEnableShadow` | `bool` | `true` | Soft Elevation (Schatten) aktivieren. | Enable soft elevation (layered shadow). |
| `CustomPreOrderManager.config.cardEnableGradient` | `bool` | `true` | Sanften Hintergrund-Farbverlauf aktivieren. | Enable subtle background gradient. |
| `CustomPreOrderManager.config.enableMixedCartNotice` | `bool` | `true` | Mischwarenkorb-Hinweis im Offcanvas & Checkout aktivieren. | Enable mixed cart notice in offcanvas & checkout. |
| `CustomPreOrderManager.config.mixedCartNoticeMode` | `select` | `flexible` | Fulfillment-Modus für Mischwarenkörbe (`flexible`, `split`, `consolidated`, `custom`). | Fulfillment mode for mixed carts (`flexible`, `split`, `consolidated`, `custom`). |
| `CustomPreOrderManager.config.mixedCartCustomNoticeText` | `text` | `""` | Individueller Hinweistext bei Modus `custom`. | Custom notice text when mode is set to `custom`. |

---

## 📧 Flow Builder Integration & Business Events

Das Plugin registriert das native Shopware Business Event `preorder.order.placed`, um automatisierte Workflows ohne Template-Eingriffe zu ermöglichen:

### Event-Spezifikation

```yaml
Event Name: preorder.order.placed
Event Klasse: CustomPreOrderManager\Core\Checkout\Event\PreOrderPlacedEvent
Interfaces:
  - Shopware\Core\Framework\Event\FlowEventAware
  - Shopware\Core\Framework\Event\OrderAware
  - Shopware\Core\Framework\Event\CustomerAware
  - Shopware\Core\Framework\Event\MailAware
  - Shopware\Core\Framework\Event\ScalarValuesAware
Available Payload:
  - order: Shopware\Core\Checkout\Order\OrderDefinition (Entity)
  - preOrderItemCount: int (ScalarValue)
  - context: Shopware\Core\Framework\Context
```

### Anwendungsbeispiele im Flow Builder:
1. **Kundenspezifische Vorbestell-Infomail:** Sobald `preOrderItemCount >= 1`, versendet der Flow Builder eine gesonderte E-Mail mit Lieferzeit-Informationen an den Kunden.
2. **ERP- & WWS-Routing:** Übertragung von Vorbestellungs-Aufträgen in einen separaten Clearing-Status im ERP-System.
3. **VIP-Kundenbetreuung:** Benachrichtigung des Kundenservice bei Großbestellungen von limitierten Vorbestellartikeln.

---

## 🏗️ Architektur & Verzeichnisstruktur

```text
CustomPreOrderManager/
├── .agent/                                      # Agent Governance & Quality Skill Blueprints
├── docs/
│   └── adr/                                     # Architecture Decision Records (ADR-001 bis ADR-006)
├── src/
│   ├── CustomPreOrderManager.php                # Plugin-Hauptklasse (Lifecycle & Container-Extension)
│   ├── Core/Checkout/
│   │   ├── Cart/
│   │   │   └── PreOrderCartCollector.php        # LineItem Payload Enrichment (Priority 4100)
│   │   ├── Subscriber/
│   │   │   └── OrderPlacedSubscriber.php        # Auto-Tagging & atomarer DBAL Counter
│   │   └── Event/
│   │       └── PreOrderPlacedEvent.php           # Flow Builder Business Event
│   ├── DependencyInjection/
│   │   └── CustomPreOrderManagerExtension.php   # DI Extension & Rate Limiter Prepend
│   ├── Migration/
│   │   └── Migration1726200000AddPreOrderCustomFields.php # CustomFields auf Entity product
│   └── Resources/
│       ├── config/
│       │   ├── config.xml                       # System-Konfiguration
│       │   ├── services.xml                     # Symfony DI Service-Definitionen
│       │   └── plugin.png                       # Plugin-Icon (128x128)
│       ├── Snippet/
│       │   ├── de_DE/custom_pre_order_manager.de-DE.json # Deutsche Textbausteine
│       │   └── en_GB/custom_pre_order_manager.en-GB.json # Englische Textbausteine
│       ├── views/storefront/                    # Twig-Template-Erweiterungen mit Stock-Guards
│       │   ├── component/buy-widget/buy-widget-form.html.twig
│       │   ├── page/product-detail/buy-widget-form.html.twig
│       │   └── component/product/card/badges.html.twig
│       └── app/
│           ├── storefront/                      # Vanilla JS Plugin & Mobile-First SCSS
│           └── administration/                  # Vue.js 3 Admin-Modul (Dashboard & Order-Tabs)
├── tests/
│   ├── TestBootstrap.php                        # Shopware 6 Kernel Test-Bootstrapper
│   ├── Unit/                                    # Isolierte Unit-Tests mit Mocks (28 Tests)
│   │   ├── Cart/PreOrderCartCollectorTest.php
│   │   ├── Subscriber/OrderPlacedSubscriberTest.php
│   │   ├── Event/PreOrderPlacedEventTest.php
│   │   ├── DependencyInjection/CustomPreOrderManagerExtensionTest.php
│   │   └── Plugin/PluginUninstallTest.php
│   └── Integration/                             # Integration-Tests mit echtem Container (6 Tests)
│       ├── Subscriber/OrderPlacedSubscriberTest.php
│       └── PluginLifecycleTest.php
├── composer.json                                # Plugin-Metadaten & Namespace-Autoloading
├── phpunit.xml.dist                             # PHPUnit-Konfiguration & Coverage-Filter
└── BUILD.md                                     # Enterprise Release-Build Dokumentation (lokal)
```

---

## 🚀 Installation & Build-Pipeline

### Systemvoraussetzungen

| Anforderung | Unterstützte Versionen |
|---|---|
| **Shopware Plattform** | `~6.5.8.0` · `^6.6.0` · `^6.7.0` (Community, Professional, Enterprise) |
| **PHP Runtime** | `8.1` · `8.2` · `8.3` |
| **Datenbank** | MySQL 8.0+ / MariaDB 10.5+ |
| **Externe Abhängigkeiten**| **Keine** (100 % autark, Zero Dependency Bloat) |

### Release-Build mit shopware-cli

Releases werden nach Enterprise Tier-1 Standard vollautomatisch über die offizielle `shopware-cli` gebaut:

```bash
# 1. Repository auf Release-Stand bringen
git checkout feat/scaffold-and-architecture
git pull

# 2. Versions-Tag vergeben
git tag -a v1.0.0 -m "Release v1.0.0: Enterprise Tier-1 Pre-Order Manager"

# 3. Distributions-ZIP erstellen (kompiliert Assets & exkludiert Tests/Doku)
shopware-cli extension zip . --use-git-tag-as-version --output-directory dist/
```

Das erzeugte Archiv `dist/CustomPreOrderManager-v1.0.0.zip` ist sofort distributions- und marktplatzfähig.

### Manuelle Installation im Shop

```bash
# Plugin in Shopware-Verzeichnis kopieren
bin/console plugin:refresh

# Plugin installieren und aktivieren
bin/console plugin:install --activate CustomPreOrderManager

# Caches leeren und Assets kompilieren
bin/console cache:clear
bin/console theme:compile
```

---

## 🧪 Testing & Qualitätssicherung (100% Coverage)

Die Qualitätssicherung erfolgt nach striktem Enterprise Tier-1 Standard mit vollständiger Trennung von isolierten Unit- und zustandsbehafteten Integration-Testsuites.

### Test-Architektur & Test-Matrix

```
tests/
├── Unit/ (35 Tests, 100+ Assertions)
│   ├── Cart/PreOrderCartCollectorTest.php               # 8 Tests: Payload, Sanitizing, Fallbacks, Stock-Priorisierung
│   ├── Subscriber/OrderPlacedSubscriberTest.php          # 5 Tests: Event-Handling, atomarer Zähler, Auto-Tagging
│   ├── Event/PreOrderPlacedEventTest.php                # 1 Test:  Flow Builder Datenstrukturen & ScalarValues
│   ├── DependencyInjection/CustomPreOrderManagerExtTest # 3 Tests: DI Prepend & Symfony Rate-Limiter Config
│   ├── Plugin/PluginUninstallTest.php                   # 7 Tests: Alle 5 Lifecycle-Hooks & Deinstallations-Cleanup
│   └── Config/ConfigXmlTest.php                         # 7 Tests: Wohlgeformtheit, bilinguale Labels/HelpTexts & Hex-Defaults
└── Integration/ (7 Tests, 19 Assertions)
    ├── Subscriber/OrderPlacedSubscriberTest.php         # Echte Repositories, DBAL & Test-Container ohne Mocks
    └── PluginLifecycleTest.php                          # Kernel Plugin-Loader, DI Boot & vollständiger Lifecycle-Cleanup
```

### Offizieller PHPUnit-Prüfbericht (Dockware Teststation)

Verifiziert auf Dockware Teststation (PHP 8.1.33 / Shopware 6.5.8.12):

```text
PHPUnit 9.6.35 by Sebastian Bergmann and contributors.

...................................                               35 / 35 (100%)

Time: 00:00.362, Memory: 124.00 MB

OK (35 tests, 83 assertions)

Code Coverage Report:     
  2026-09-15 10:17:04     
                          
 Summary:                 
  Classes: 100.00% (5/5)  
  Methods: 100.00% (20/20)
  Lines:   100.00% (102/102)

CustomPreOrderManager\Core\Checkout\Cart\PreOrderCartCollector
  Methods: 100.00% ( 1/ 1)   Lines: 100.00% ( 16/ 16)
CustomPreOrderManager\Core\Checkout\Event\PreOrderPlacedEvent
  Methods: 100.00% ( 7/ 7)   Lines: 100.00% (  9/  9)
CustomPreOrderManager\Core\Checkout\Subscriber\OrderPlacedSubscriber
  Methods: 100.00% ( 3/ 3)   Lines: 100.00% ( 41/ 41)
CustomPreOrderManager\CustomPreOrderManager
  Methods: 100.00% ( 6/ 6)   Lines: 100.00% ( 24/ 24)
CustomPreOrderManager\DependencyInjection\CustomPreOrderManagerExtension
  Methods: 100.00% ( 3/ 3)   Lines: 100.00% ( 12/ 12)
```

---

## 🔒 Sicherheit, Concurrency & DSGVO

| Sicherheitsdomäne | Implementierter Schutzmechanismus | Standard / Regelwerk |
|---|---|---|
| **Race-Condition-Schutz** | Atomares DBAL SQL-Inkrement mit `JSON_SET` gefiltert auf `Defaults::LIVE_VERSION`. Verhindert Überbuchungen bei gleichzeitigen Checkouts. | Concurrency Standard (§4) |
| **XSS-Prävention** | Sämtliche Freitexteingaben (`release_text`) durchlaufen rekursiv `strip_tags()` vor der Payload-Übernahme. Storefront-Ausgabe nutzt Twig `|striptags` und Auto-Escaping. | OWASP Top 10 A03:2021 |
| **SQL-Injection-Schutz** | Ausschließliche Nutzung von Doctrine DBAL Prepared Statements mit typisierten Parametern (`ParameterType::STRING`, etc.). | OWASP Top 10 A03:2021 |
| **DoS- / Brute-Force-Schutz** | Registrierung eines dedizierten Symfony Rate-Limiters (`preorder_notify`) über `PrependExtensionInterface`. | Zero-Trust (§2) |
| **DSGVO / GDPR Compliance** | Keine unbestellten personenbezogenen Daten (PII), keine Drittanbieter-Tracker, keine Cookie-Pflicht. Autarke Kontingentsperre statt externer Warteliste. | DSGVO Art. 5, 25 |
| **Sauberer Deinstallations-Lifecycle** | Bei Deinstallation mit `keepUserData === false` werden alle CustomFields und Konfigurationen rückstandslos bereinigt – ohne fremde Daten zu gefährden. | Shopware Extension Guide |

---

## 🏛️ Architecture Decision Records (ADR-Index)

Grundlegende Architekturentscheidungen sind nach dem Michael Nygard ADR-Standard unter [`docs/adr/`](docs/adr/) dokumentiert:

| ADR | Titel | Status | Kernentscheidung |
|:---:|---|:---:|---|
| **[ADR-001](docs/adr/ADR-001-customfield-set-vs-dedicated-table.md)** | CustomField-Set vs. eigene Entity-Tabelle | `AKZEPTIERT` | Nutzung von `custom_preorder_set` auf `product` verhindert Schema-Locks und sichert automatische Variantenvererbung. |
| **[ADR-002](docs/adr/ADR-002-atomic-dbal-counter.md)** | Atomarer DBAL Counter vs. DAL Entity Write | `AKZEPTIERT` | Direktes DBAL-Update mit `Defaults::LIVE_VERSION` garantiert Race-Condition-Freiheit bei simultanen Checkouts. |
| **[ADR-003](docs/adr/ADR-003-lineitem-payload-enrichment.md)** | LineItem Payload Enrichment vs. Mail-Override | `AKZEPTIERT` | Priorität 4100 im CartCollector schleust Vorbestell-Flags konfliktfrei durch Core-Mails und PDF-Belege. |
| **[ADR-004](docs/adr/ADR-004-autarkic-scarcity-no-waitlist.md)** | Autarkes Scarcity-System vs. externe Warteliste | `AKZEPTIERT` | Verknappungsanzeige mit Button-Sperre schützt Kontingente ohne DSGVO- und DOI-Risiken externer E-Mail-Listen. |
| **[ADR-005](docs/adr/ADR-005-native-button-theming-color-picker.md)** | Natives Button-Theming & Color-Picker | `AKZEPTIERT` | Bereinigung harter Maße zugunsten nativer Theme-Klassen und dynamischer Farb-Injektion via CSS-Variablen. |
| **[ADR-006](docs/adr/ADR-006-admin-preorder-dashboard-listing.md)** | Admin Vorbestellungs-Dashboard | `AKZEPTIERT` | Expliziter `created()`-Hook, Assoziationen (`stateMachineState`, `currency`) und Scoped Slots mit Deep-Linking. |
| **[ADR-007](docs/adr/ADR-007-storefront-delivery-information-and-clean-cta.md)** | Storefront Lieferinformation & Clean CTA | `AKZEPTIERT` | Semantische 2-Ebenen-Hierarchie (Termin + Hinweistext), 'Voraussichtlich'-Rechtssicherheit und Icon-Entfall am CTA-Button. |
| **[ADR-008](docs/adr/ADR-008-end-to-end-preorder-delivery-information.md)** | Full-Funnel Vorbestellungs-Architektur, Quota Guard & Mischwarenkorb | `AKZEPTIERT` | Durchgängige Begleitung (Listing bis Kundenkonto), aktiver Cart-Quota-Guard mit Shopware-Alerts und flexibler Mischwarenkorb-Versandhinweis. |

---

## 📄 Lizenz & Support

* **Lizenz:** Proprietäre Enterprise-Lizenz. Entwickelt für die Plattform **[Grow & Style](https://growandstyle.de)**. Alle Rechte vorbehalten.
* **Support & Wartung:** [Grow & Style Engineering Team](mailto:kontakt@growandstyle.de)
* **Code-Qualitätsstandard:** Shopware Enterprise Tier-1 Compliant.
