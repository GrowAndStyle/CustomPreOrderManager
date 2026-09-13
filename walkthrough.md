# Walkthrough — CustomPreOrderManager

## Phase 0: Initiales Scaffolding abgeschlossen
- Verzeichnisstruktur nach Archetyp A (Shopware 6 Plugin) erstellt.
- AI Context Manifest und Fach-Skills aus dem zentralen AgentSkill-Repo deployed.
- ADR-001 dokumentiert und verankert.
- Lifecycle-Methoden und keepUserData() Uninstall-Guards implementiert.

## Phase 1: DAL-Migrationen & Wartelisten-Entity abgeschlossen
> Detaillierte Dokumentation: [docs/walkthrough/001-dal-migrationen-und-entities.md](file:///Users/nicoschultz/Documents/CustomPreOrderManager/docs/walkthrough/001-dal-migrationen-und-entities.md)

- **CustomField-Set Migration (`Migration1726200000AddPreOrderCustomFields.php`):**
  - Idempotente Anlage von `custom_preorder_set` für die Entity `product`.
  - 5 Felder angelegt: `custom_preorder_active` (bool/switch), `custom_preorder_release_date` (datetime/datepicker), `custom_preorder_release_text` (text), `custom_preorder_inbound_stock` (int), `custom_preorder_sold_count` (int/disabled).
- **Wartelisten-Tabelle Migration (`Migration1726200100CreatePreOrderWaitlist.php`):**
  - Idempotente Anlage der Tabelle `custom_preorder_waitlist`.
  - Versionierter Composite Foreign Key: `(product_id, product_version_id)` verweist auf `product(id, version_id)` mit `ON DELETE CASCADE ON UPDATE CASCADE`.
  - Fremdschlüssel auf `sales_channel(id)`.
  - Indizes auf `(product_id, product_version_id)`, `(status)` und `(token)`.
- **DAL-Klassen (`src/Core/Content/PreOrderWaitlist/`):**
  - `PreOrderWaitlistDefinition`: EntityDefinition mit `IdField`, `FkField`, `ReferenceVersionField('product_version_id')`, `ManyToOneAssociationField` für Produkt und SalesChannel.
  - `PreOrderWaitlistEntity`: PHP 8.1+ typisierte Entity mit Gettern/Settern und Default-Status `'pending_doi'`.
  - `PreOrderWaitlistCollection`: Typisierte EntityCollection.
- **Service- & Route-Konfiguration:**
  - `PreOrderWaitlistDefinition` in `src/Resources/config/services.xml` mit Tag `shopware.entity.definition` registriert.
  - `src/Resources/config/routes.xml` für die Erkennung von `#[Route]`-Attributen in Storefront- und Admin-Controllern erstellt.
- **Teardown-Härtung:**
  - `uninstall()` in `CustomPreOrderManager.php` erweitert um die vollständige Kaskadierung (Tabellen, CustomField-Sets, Mail-Templates & Types, System-Configs) bei `keepUserData() === false`.
- **Verifikation & Qualität:**
  - `xmllint` Validierung für alle XML-Dateien erfolgreich bestanden (0 Fehler).
  - JSON-Linting der Storefront-Snippets erfolgreich.
  - Unit-Tests: 18 Tests, 61 Assertions, **100.00% Coverage** (Classes: 4/4, Methods: 33/33, Lines: 198/198).
  - CustomField-Set wird im Plugin-Lifecycle über `custom_field_set.repository` verwaltet.

## Phase 2: Cart-Collector Pipeline & LineItem Payload Enrichment abgeschlossen
> Detaillierte Dokumentation: [docs/walkthrough/002-cart-collector-pipeline.md](file:///Users/nicoschultz/Documents/CustomPreOrderManager/docs/walkthrough/002-cart-collector-pipeline.md)

- **Cart-Collector (`src/Core/Checkout/Cart/PreOrderCartCollector.php`):**
  - Implementiert `CartDataCollectorInterface`.
  - Filtert auf Produkt-LineItems und prüft `custom_preorder_active === true`.
  - Strikte Regel: Physischer Lagerbestand sticht Vorbestellung (`deliveryInformation->stock > 0` bzw. `payload['stock'] > 0`).
  - Reichert LineItem mit `isPreOrder = true` und `preOrderReleaseText` an.
  - Automatische Text-Generierung: Freitext `custom_preorder_release_text` vor formatiertem Release-Datum `Lieferbar ab MM/YYYY`.
- **DI-Registrierung (`src/Resources/config/services.xml`):**
  - Registriert mit Tag `shopware.cart.collector` und Priorität `4500`.
- **Unit-Tests (`tests/Unit/Core/Checkout/Cart/PreOrderCartCollectorTest.php`):**
  - 8 Unit-Tests für alle Verzweigungen (Non-Product, Inaktiv, Lagerbestand > 0, Freitext, Datum, Fallbacks).
