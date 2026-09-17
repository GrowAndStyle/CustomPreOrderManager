# Architektur-Dokumentation (CustomPreOrderManager)

## 1. Übersicht & Architektur-Entscheidungen (ADR-Index)

| ADR | Titel | Status | Datum |
|---|---|:---:|:---:|
| [ADR-001](docs/adr/ADR-001-customfield-set-vs-dedicated-table.md) | CustomField-Set statt eigener Entitätstabelle | ENTSCHIEDEN | 2026-09-14 |
| [ADR-002](docs/adr/ADR-002-atomic-dbal-counter.md) | Atomares DBAL JSON-Counter-Inkrement | ENTSCHIEDEN | 2026-09-14 |
| [ADR-003](docs/adr/ADR-003-lineitem-payload-enrichment.md) | LineItem-Payload-Enrichment statt Mail-Template-Override | ENTSCHIEDEN | 2026-09-14 |
| [ADR-004](docs/adr/ADR-004-autarkic-scarcity-no-waitlist.md) | Autarkes Scarcity-System ohne Warteliste | ENTSCHIEDEN | 2026-09-14 |
| [ADR-005](docs/adr/ADR-005-native-button-theming-color-picker.md) | Natives Button-Theming und dynamische Farb-Konfiguration via CSS Custom Properties | ENTSCHIEDEN | 2026-09-15 |
| [ADR-006](docs/adr/ADR-006-admin-preorder-dashboard-listing.md) | Admin Vorbestellungs-Dashboard — Listing-Architektur, Lifecycle-Steuerung und Datensynchronisation | ENTSCHIEDEN | 2026-09-17 |
| [ADR-007](docs/adr/ADR-007-storefront-delivery-information-and-clean-cta.md) | Storefront Lieferinformation — Semantische Trennung von Liefertermin und Hinweistext sowie Entfall des Button-Icons | ENTSCHIEDEN | 2026-09-17 |

---

## 2. System- & Komponenten-Architektur

```
CustomPreOrderManager/
├── src/
│   ├── CustomPreOrderManager.php               # Plugin-Hauptklasse mit 5 Lifecycle-Methoden
│   ├── Core/Checkout/
│   │   ├── Cart/PreOrderCartCollector.php       # LineItem Payload Enrichment (Priority 4100)
│   │   ├── Subscriber/OrderPlacedSubscriber.php # Auto-Tagging & atomarer DBAL Counter
│   │   └── Event/PreOrderPlacedEvent.php        # Flow Builder Business Event (FlowEventAware)
│   ├── DependencyInjection/
│   │   └── CustomPreOrderManagerExtension.php   # PrependExtensionInterface (Rate Limiter)
│   ├── Migration/
│   │   └── Migration1726200000AddPreOrderCustomFields.php # CustomFields auf product
│   └── Resources/
│       ├── config/                              # services.xml, config.xml, plugin.png
│       ├── Snippet/                             # de_DE & en_GB SnippetFiles & JSON
│       ├── views/storefront/                    # Twig Template Extensions mit Stock-Guard
│       └── app/
│           ├── storefront/                      # JS-Plugin & SCSS (Mobile-First)
│           └── administration/                  # Vue.js 3 Admin-Modul & Dashboard-Listing
└── tests/
    ├── Unit/                                    # Isolierte Unit-Tests mit Mocks (35 Tests)
    └── Integration/                             # IntegrationTests mit echtem Container (7 Tests)
```

---

## 3. Datenfluss & Checkout-Pipeline

```
[Produkt-Katalog] 
       │
       ▼ (custom_preorder_active = true, inbound_stock > 0)
[Storefront PDP / Listing] ── (Stock-Guard: stock <= 0) ──► „Jetzt vorbestellen" Button
       │
       ▼ (Kunde legt Artikel in Warenkorb)
[PreOrderCartCollector] (Priority 4100)
       │  Enrichment: isPreOrder=true, preOrderReleaseText (mit strip_tags Sanitization)
       ▼
[Checkout / OrderPlaced]
       │
       ├─► [OrderPlacedSubscriber]
       │        ├─► Atomares DBAL-Statement: sold_count += quantity (product_translation, WHERE version_id = LIVE_VERSION)
       │        ├─► Tag 'Vorbestellung' idempotent suchen/erstellen & Order taggen
       │        └─► PreOrderPlacedEvent dispatchen
       │
       └─► [Flow Builder] ──► Automatisierte Mails / Benachrichtigungen
```

---

## 4. Test- & Qualitäts-Architektur (Enterprise Tier-1)

* **Unit vs. Integration Separation:**
  * `tests/Unit/`: Schnelle, vollständig isolierte Unit-Tests mit Mocks für Services, Events, Extension und Plugin-Deinstallation.
  * `tests/Integration/`: Verifikation gegen den echten Shopware Test-Container (`IntegrationTestBehaviour`) ohne Repository-Mocks.
* **Coverage-Standard:**
  * Gesetzte Mindestanforderung: Line-Coverage ≥ 95%, Method-Coverage ≥ 90%.
  * Verifiziertes Testergebnis (Teststation Dockware PHP 8.1 / Shopware 6.5.x CE):
    * **34 Tests, 80 Assertions, 0 Fehler**
    * **Classes: 100.00% (5/5)**
    * **Methods: 100.00% (20/20)**
    * **Lines: 100.00% (91/91)**
