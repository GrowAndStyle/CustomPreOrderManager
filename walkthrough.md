# Walkthrough — CustomPreOrderManager

## Phase 0: Initiales Scaffolding abgeschlossen
- Verzeichnisstruktur nach Archetyp A (Shopware 6 Plugin) erstellt.
- AI Context Manifest und Fach-Skills aus dem zentralen AgentSkill-Repo deployed.
- ADR-001 dokumentiert und verankert.
- Lifecycle-Methoden und keepUserData() Uninstall-Guards implementiert.

## Phase 1: DAL-Migrationen & Wartelisten-Entity abgeschlossen
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
  - Unit-Tests für Entity, Definition und Lifecycle-Guards erstellt.
