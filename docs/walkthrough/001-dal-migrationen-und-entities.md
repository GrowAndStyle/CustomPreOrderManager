# Walkthrough 001 — Phase 1: DAL-Migrationen & Wartelisten-Entity

**Status:** Abgeschlossen  
**Datum:** 2026-09-13  
**Branch:** `feat/dal-migrations`  
**Architektur:** Tier-1 Enterprise Standard  

---

## 1. Ziel & Umfang
Erstellung der Datenbasis für das Vorbestellungs-System (PreOrder):
1. **CustomField-Set am Produkt:** Konfiguration der Vorbestellung (Aktiv-Switch, Release-Datum, Frontend-Hinweistext, Zulaufmenge, Zähler).
2. **Wartelisten-Tabelle:** Autarke Speicherung von Kundenanmeldungen bei Kontingent 0 mit Double-Opt-In-Status und kryptografischen Tokens.
3. **DAL Entity Layer:** Vollständige Abbildung über Shopwares Data Abstraction Layer (Entity, Definition, Collection).

---

## 2. Technische Umsetzung im Detail

### A. CustomField-Set Migration (`Migration1726200000AddPreOrderCustomFields.php`)
- **Idempotenz:** Prüfung per `fetchOne` auf Existenz von `custom_preorder_set` und jedem einzelnen Feld.
- **Zuordnung:** Verknüpfung mit der Entity `product` über `custom_field_set_relation`.
- **Pflichtfelder:**
  - `custom_preorder_active` (Bool / Switch): Aktiviert Vorbestellung für den Artikel.
  - `custom_preorder_release_date` (DateTime / Datepicker): Kalendarisches Erscheinungsdatum.
  - `custom_preorder_release_text` (Text): Kundenanzeigetext (z. B. *Lieferbar ab Mitte Oktober 2026*).
  - `custom_preorder_inbound_stock` (Int / Number, min: 0): Zulaufkontingent.
  - `custom_preorder_sold_count` (Int / Number, disabled, min: 0): Vorbestellzähler (wird atomar per DBAL erhöht).

### B. Wartelisten-Tabelle Migration (`Migration1726200100CreatePreOrderWaitlist.php`)
- **Tabelle:** `custom_preorder_waitlist`
- **Composite Foreign Key:** Shopware Core-Produkte sind versioniert (`id`, `version_id`). Die Relation bindet über `(product_id, product_version_id)` mit `ON DELETE CASCADE ON UPDATE CASCADE`.
- **SalesChannel-Verknüpfung:** Fremdschlüssel auf `sales_channel(id)`.
- **Indizes:**
  - `idx.preorder_waitlist.product` auf `(product_id, product_version_id)`
  - `idx.preorder_waitlist.status` auf `status`
  - `idx.preorder_waitlist.token` auf `token`

### C. Data Abstraction Layer (`src/Core/Content/PreOrderWaitlist/`)
- `PreOrderWaitlistDefinition`:
  - `ReferenceVersionField(ProductDefinition::class, 'product_version_id')` mit explizitem `storageName`.
  - `ManyToOneAssociationField` für `product` und `salesChannel`.
- `PreOrderWaitlistEntity`:
  - PHP 8.1+ typisierte Entity mit `EntityIdTrait`.
  - Default-Status: `'pending_doi'`.
- `PreOrderWaitlistCollection`:
  - Typisierte `EntityCollection<PreOrderWaitlistEntity>`.

### D. Service- & Route-Registrierung
- `src/Resources/config/services.xml`:
  - `PreOrderWaitlistDefinition` mit Tag `shopware.entity.definition` registriert.
- `src/Resources/config/routes.xml`:
  - Importiert `Storefront/Controller` und `Core/Api` mit `type="annotation"` für die automatische Erkennung von PHP 8.1 `#[Route]`-Attributen.

---

## 3. Verifikation & Qualitätssicherung

1. **XML-Syntax:**
   ```bash
   xmllint --noout src/Resources/config/*.xml
   ```
   *Ergebnis:* 0 Fehler, alle XML-Dateien valide.

2. **JSON-Snippets:**
   ```bash
   python3 -m json.tool src/Resources/snippet/de-DE.json
   python3 -m json.tool src/Resources/snippet/en-GB.json
   ```
   *Ergebnis:* Valides JSON.

3. **Unit-Tests (100% Coverage, 18 Tests, 61 Assertions):**
   - `PreOrderWaitlistEntityTest`: Getter/Setter, Default-Werte, Nullable-Eigenschaften.
   - `PreOrderWaitlistDefinitionTest`: EntityName, Field-Typen, PrimaryKey, ReferenceVersionField, ForeignKeys.
   - `PreOrderWaitlistCollectionTest`: Typisierung und Vererbung der EntityCollection.
   - `PluginLifecycleTest`: `keepUserData()`-Guard-Prüfung, vollständige Kaskadierungs-Prüfung, Neuanlage & Update von CustomFieldSets über `custom_field_set.repository`.

4. **CustomField-Set Registrierung (Shopware 6.5 Standard):**
   - Registrierung von `custom_preorder_set` erfolgt über `custom_field_set.repository` in `CustomPreOrderManager::install`, `activate` und `update`.
   - Idempotente Verknüpfung mit der Entity `product`.
   - Absicherung gegen Duplicate Keys und saubere Übergabe der Feldkomponenten (`sw-field`, `sw-datepicker`).
