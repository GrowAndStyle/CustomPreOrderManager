<p align="center">
  <img src="src/Resources/config/plugin.png" alt="CustomPreOrderManager Logo" width="128">
</p>

<h1 align="center">CustomPreOrderManager</h1>

<p align="center">
  <strong>Autarkes High-Conversion Vorbestellungs-System mit Scarcity-Engine für Shopware 6</strong><br>
  <em>Self-contained high-conversion pre-order system with scarcity engine for Shopware 6</em>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Shopware-~6.5.8.x-189eff?style=flat-square&logo=shopware&logoColor=white" alt="Shopware">
  <img src="https://img.shields.io/badge/Version-v1.4.1-0f62fe?style=flat-square" alt="Version">
  <img src="https://img.shields.io/badge/PHP-8.1+-777bb4?style=flat-square&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/Tests-104%20passed-2ea44f?style=flat-square&logo=githubactions&logoColor=white" alt="Tests">
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
  - [Nach der Installation — Bestellbestätigung anpassen](#nach-der-installation--bestellbestätigung-anpassen-pflichtschritt)
- [Testing & Qualitätssicherung (100% Coverage)](#-testing--qualitätssicherung-100-coverage)
  - [Test-Architektur & Test-Matrix](#test-architektur--test-matrix)
  - [Offizieller PHPUnit-Prüfbericht (Dockware Teststation)](#offizieller-phpunit-prüfbericht-dockware-teststation)
- [Sicherheit, Concurrency & DSGVO](#-sicherheit-concurrency--dsgvo)
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
| `CustomPreOrderManager.config.scarcityDisplayMode` | `select` | `disabled` | Steuert Restmengen-Anzeige: `disabled`, `detail_only`, `listing_only`, `everywhere`. | Controls scarcity badge visibility: `disabled`, `detail_only`, `listing_only`, `everywhere`. |
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
| `CustomPreOrderManager.config.listingBannerBackgroundColor` | `colorpicker` | `#0f172a` | Datum-Banner Hintergrundfarbe (Kategorielisting). | Date banner background color (category listing). |
| `CustomPreOrderManager.config.listingBannerTextColor` | `colorpicker` | `#ffffff` | Datum-Banner Textfarbe. | Date banner text color. |
| `CustomPreOrderManager.config.listingBannerOpacity` | `int` | `55` | Datum-Banner Deckkraft in % (20–100). | Date banner opacity in % (20–100). |
| `CustomPreOrderManager.config.listingScarcityBackgroundColor` | `colorpicker` | `#b45309` | Scarcity-Badge Hintergrundfarbe (Kategorielisting). | Scarcity badge background color (category listing). |
| `CustomPreOrderManager.config.listingScarcityTextColor` | `colorpicker` | `#ffffff` | Scarcity-Badge Textfarbe. | Scarcity badge text color. |
| `CustomPreOrderManager.config.listingScarcityOpacity` | `int` | `65` | Scarcity-Badge Deckkraft in % (20–100). | Scarcity badge opacity in % (20–100). |

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

> **Hinweis zur Kontingent-Zählung (ADR-009):** Das Event `preorder.order.placed` feuert bei **Bestelleingang**, unabhängig vom Zahlungsstatus. Die Reservierung des Vorbestellkontingents (`sold_count`) erfolgt hingegen erst bei **Zahlungseingang** (`paid`). Bei Stornierung oder Rückerstattung wird das Kontingent automatisch freigegeben.

---

## 🏗️ Architektur & Verzeichnisstruktur

```text
CustomPreOrderManager/
├── src/
│   ├── CustomPreOrderManager.php                # Plugin-Hauptklasse (Lifecycle & Container-Extension)
│   ├── Core/Checkout/
│   │   ├── Cart/
│   │   │   ├── PreOrderCartCollector.php        # LineItem Payload Enrichment (Priority 4100)
│   │   │   ├── PreOrderCartValidator.php        # High-Concurrency Quota Guard
│   │   │   └── Error/
│   │   │       ├── PreOrderQuantityAdjustedError.php
│   │   │       └── PreOrderQuotaExhaustedError.php
│   │   ├── Subscriber/
│   │   │   ├── OrderPlacedSubscriber.php        # Auto-Tagging & Event-Dispatch bei Bestelleingang
│   │   │   └── PaymentStateSubscriber.php       # Counter Inkrement/Dekrement bei Zahlungsstatus (ADR-009)
│   │   └── Event/
│   │       └── PreOrderPlacedEvent.php           # Flow Builder Business Event
│   ├── DependencyInjection/
│   │   └── CustomPreOrderManagerExtension.php   # DI Extension & Rate Limiter Prepend
│   ├── Migration/
│   │   └── Migration1726200000AddPreOrderCustomFields.php
│   └── Resources/
│       ├── config/
│       │   ├── config.xml                       # 25 Konfigurationsfelder (5 Cards)
│       │   ├── services.xml                     # Symfony DI Service-Definitionen
│       │   └── plugin.png                       # Plugin-Icon (128x128)
│       ├── snippet/
│       │   ├── de_DE/storefront.de-DE.json      # Deutsche Snippets (custom-preorder.*)
│       │   └── en_GB/storefront.en-GB.json      # Englische Snippets (custom-preorder.*)
│       ├── views/storefront/
│       │   ├── base.html.twig                   # CSS Custom Properties Injection
│       │   ├── component/
│       │   │   ├── buy-widget/buy-widget-form.html.twig
│       │   │   ├── checkout/offcanvas-cart.html.twig   # Mischwarenkorb (via Partial)
│       │   │   ├── delivery-information.html.twig       # PDP Delivery Card
│       │   │   ├── line-item/element/label.html.twig    # Warenkorb LineItem
│       │   │   ├── preorder/
│       │   │   │   ├── mixed-cart-notice.html.twig      # Shared Mischwarenkorb-Partial
│       │   │   │   └── scarcity-badge.html.twig         # PDP Scarcity Badge (ADR-010)
│       │   │   └── product/card/
│       │   │       ├── action.html.twig                 # Listing Button
│       │   │       ├── badges.html.twig                 # % Badge Unterdrückung bei Preorder
│       │   │       └── box-standard.html.twig           # Listing-Overlays (ADR-011)
│       │   └── page/
│       │       ├── product-detail/
│       │       │   └── buy-widget-form.html.twig        # Pre-Order Button
│       │       ├── checkout/
│       │       │   ├── cart/index.html.twig              # Mischwarenkorb auf /checkout/cart
│       │       │   └── confirm/index.html.twig           # Mischwarenkorb auf /checkout/confirm
│       │       └── account/order-history/               # Bestellhistorie Pre-Order-Badge
│       └── app/
│           ├── storefront/                      # Vanilla JS Plugin & Mobile-First SCSS
│           └── administration/                  # Vue.js 3 Admin-Modul
└── composer.json                                # Plugin-Metadaten & Namespace-Autoloading
```

---

## 🚀 Installation & Build-Pipeline

### Systemvoraussetzungen

| Anforderung | Unterstützte Versionen |
|---|---|
| **Shopware Plattform** | `~6.5.8.0` (getestet auf 6.5.8.19 CE) |
| **PHP Runtime** | `8.1` · `8.2` · `8.3` |
| **Datenbank** | MySQL 8.0+ / MariaDB 10.5+ |
| **Externe Abhängigkeiten**| **Keine** (100 % autark, Zero Dependency Bloat) |

### Release-Build mit shopware-cli

Releases werden nach Enterprise Tier-1 Standard vollautomatisch über die offizielle `shopware-cli` gebaut:

```bash
# 1. Repository auf Release-Stand bringen
git checkout main
git pull

# 2. Versions-Tag vergeben
git tag -a v1.4.1 -m "Release v1.4.1"

# 3. Distributions-ZIP erstellen (kompiliert Assets & exkludiert Tests/Doku)
shopware-cli extension zip . --use-git-tag-as-version --output-directory dist/
```

Das erzeugte Archiv `dist/CustomPreOrderManager-v1.4.1.zip` ist sofort distributions- und marktplatzfähig.

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

### Nach der Installation — Bestellbestätigung anpassen (Pflichtschritt)

> **Wichtig:** Das Plugin reichert jede Bestellposition automatisch mit Vorbestellungs-Metadaten an (LineItem Payload Enrichment). Diese Daten stehen im Bestellbestätigungs-Mail-Template zur Verfügung, werden vom Standard-Template aber **nicht** angezeigt. Damit Kunden in der Bestellbestätigung erkennen, welche Positionen Vorbestellungen sind und wann die Lieferung voraussichtlich erfolgt, muss das Mail-Template einmalig angepasst werden.

#### Schritt-für-Schritt

1. Im Shopware Administration Panel navigieren: **Einstellungen → E-Mail-Templates**
2. Das Template **„Bestellbestätigung"** (`order_confirmation_mail`) öffnen
3. Im HTML-Bereich die Stelle finden, an der die Bestellpositionen aufgelistet werden (innerhalb der `{% for lineItem in order.lineItems %}` Schleife)
4. Direkt **nach** dem Produktnamen bzw. der Artikelbezeichnung folgenden Twig-Block einfügen:

```twig
{% if lineItem.payload.isPreOrder is defined and lineItem.payload.isPreOrder %}
    <div style="font-size: 12px; color: #e67e22; font-weight: 600; margin-top: 6px;">
        📅 Vorbestellung{% if lineItem.payload.preOrderAvailableFrom is defined and lineItem.payload.preOrderAvailableFrom %} — {{ lineItem.payload.preOrderAvailableFrom }}{% endif %}
    </div>
{% endif %}
```

5. Änderung speichern und eine Test-Bestellung mit einem Vorbestellartikel auslösen.

#### Verfügbare Payload-Felder pro Bestellposition

| Feld | Typ | Beschreibung | Beispielwert |
|---|:---:|---|---|
| `lineItem.payload.isPreOrder` | `bool` | `true` wenn die Position eine Vorbestellung ist | `true` |
| `lineItem.payload.preOrderReleaseDate` | `string\|null` | Formatiertes Erscheinungsdatum (`d.m.Y`) | `"30.09.2026"` |
| `lineItem.payload.preOrderAvailableFrom` | `string\|null` | Menschenlesbarer Lieferhinweis mit Datum | `"Voraussichtlich lieferbar ab 30.09.2026"` |
| `lineItem.payload.preOrderNotice` | `string\|null` | Vom Händler gepflegter Freitext (sanitized via `strip_tags`) | `"Limitierte Erstauflage"` |
| `lineItem.payload.preOrderReleaseText` | `string` | Legacy-Feld: Kombination aus Datum und Hinweistext | `"Voraussichtlich lieferbar ab 30.09.2026 — Limitierte Erstauflage"` |
| `lineItem.payload.preOrderInboundStock` | `int` | Zulaufmenge (Lieferanten-Kontingent) | `50` |
| `lineItem.payload.preOrderSoldCount` | `int` | Bereits vorbestellte Menge | `12` |
| `lineItem.payload.preOrderRemainingQuota` | `int\|null` | Restmenge (`inboundStock - soldCount`), `null` wenn kein Kontingent gesetzt | `38` |

#### Verhalten bei Mischwarenkörben

Die Payload-Felder sind **positionsbezogen**. Bei einer Bestellung mit Vorbestellartikeln und sofort lieferbaren Lagerartikeln erscheint der Vorbestellhinweis nur an den betroffenen Positionen. Lagerartikel bleiben unverändert — der Hinweis greift ausschließlich bei `isPreOrder === true`.

#### Beispiel: Bestellbestätigung mit Vorbestellposition

```text
Ihre Bestellung #10234

  1x  Hydroponic Starter Kit Pro          49,90 €
      📅 Vorbestellung — Voraussichtlich lieferbar ab 30.09.2026

  2x  LED Grow Light 600W                 89,90 €

  Gesamt:                                229,70 €
```

---

## 🧪 Testing & Qualitätssicherung (100% Coverage)

Die Qualitätssicherung erfolgt nach striktem Enterprise Tier-1 Standard mit vollständiger Trennung von isolierten Unit- und zustandsbehafteten Integration-Testsuites.

### Test-Architektur & Test-Matrix

```
tests/
├── Unit/ (101 Tests)
│   ├── Subscriber/
│   │   ├── OrderPlacedSubscriberTest.php          #  7 Tests — Tagging, Event, Edge Cases
│   │   └── PaymentStateSubscriberTest.php         # 10 Tests — Paid/Cancel/Refund/Guard (ADR-009)
│   ├── Core/Checkout/Cart/
│   │   ├── PreOrderCartCollectorTest.php          #  8 Tests — Payload, Sanitizing, Fallbacks
│   │   └── PreOrderCartValidatorTest.php          #  4 Tests — Quota Guard
│   ├── Config/ConfigXmlTest.php                   #  7 Tests — Wohlgeformtheit, bilinguale Labels, Hex-Defaults, 25 Felder
│   ├── Storefront/DeliveryInformationTemplateTest.php # 62 Tests — PDP-Card, Listing-Overlays, ADR-011
│   └── PluginLifecycleTest.php                    #  3 Tests — Lifecycle, Uninstall, keepUserData
└── Integration/ (3 Tests)
    ├── Subscriber/OrderPlacedSubscriberTest.php   #  2 Tests — Echter Container, DBAL, Repositories
    └── PluginLifecycleTest.php                    #  1 Test  — Kernel Boot & vollständiger Lifecycle
```

### Offizieller PHPUnit-Prüfbericht (Dockware Teststation)

Verifiziert auf Dockware Teststation (PHP 8.1 / Shopware 6.5.8.19):

```text
PHPUnit 9.6.35 by Sebastian Bergmann and contributors.

OK (104 tests, 741 assertions)

Code Coverage Report:
  2026-09-18 23:18:18

 Summary:
  Classes: 100.00% (9/9)
  Methods: 100.00% (41/41)
  Lines:   100.00% (220/220)

CustomPreOrderManager\Core\Checkout\Cart\Error\PreOrderQuantityAdjustedError
  Methods: 100.00% ( 6/ 6)   Lines: 100.00% ( 16/ 16)
CustomPreOrderManager\Core\Checkout\Cart\Error\PreOrderQuotaExhaustedError
  Methods: 100.00% ( 6/ 6)   Lines: 100.00% ( 12/ 12)
CustomPreOrderManager\Core\Checkout\Cart\PreOrderCartCollector
  Methods: 100.00% ( 1/ 1)   Lines: 100.00% ( 37/ 37)
CustomPreOrderManager\Core\Checkout\Cart\PreOrderCartValidator
  Methods: 100.00% ( 1/ 1)   Lines: 100.00% ( 24/ 24)
CustomPreOrderManager\Core\Checkout\Event\PreOrderPlacedEvent
  Methods: 100.00% ( 7/ 7)   Lines: 100.00% (  9/  9)
CustomPreOrderManager\Core\Checkout\Subscriber\OrderPlacedSubscriber
  Methods: 100.00% ( 3/ 3)   Lines: 100.00% ( 30/ 30)
CustomPreOrderManager\Core\Checkout\Subscriber\PaymentStateSubscriber
  Methods: 100.00% ( 6/ 6)   Lines: 100.00% ( 51/ 51)
CustomPreOrderManager\CustomPreOrderManager
  Methods: 100.00% ( 6/ 6)   Lines: 100.00% ( 24/ 24)
CustomPreOrderManager\DependencyInjection\CustomPreOrderManagerExtension
  Methods: 100.00% ( 5/ 5)   Lines: 100.00% ( 17/ 17)
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


## 📄 Lizenz & Support

* **Lizenz:** Proprietäre Enterprise-Lizenz. Entwickelt für die Plattform **[Grow & Style](https://growandstyle.de)**. Alle Rechte vorbehalten.
* **Support & Wartung:** [Grow & Style Engineering Team](mailto:kontakt@growandstyle.de)
* **Code-Qualitätsstandard:** Shopware Enterprise Tier-1 Compliant.
