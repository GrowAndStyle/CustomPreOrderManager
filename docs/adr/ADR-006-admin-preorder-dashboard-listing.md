# ADR-006: Admin Vorbestellungs-Dashboard — Listing-Architektur, Lifecycle-Steuerung und Datensynchronisation

**Status:** ENTSCHIEDEN  
**Datum:** 2026-09-17  
**Autor:** Senior Fullstack Shopware 6 Architekt  

## Kontext
Das Shopware 6 Administrationsmodul `CustomPreOrderManager` stellt unter dem Menüpunkt *Bestellungen → Vorbestellungen* (`custom-preorder-manager-list`) eine dedizierte Übersicht aller getätigten Vorbestellungen bereit. Die Datengrundlage bilden Shopware-Bestellungen (`order`), die beim Checkout über den `OrderPlacedSubscriber` automatisiert mit dem Tag `Vorbestellung` versehen wurden.

In der Praxis traten folgende Probleme auf:
1. **Stiller Ladeausfall:** Die Übersicht zeigte eine leere Tabelle, obwohl in der Datenbank Vorbestellungen mit dem Tag `Vorbestellung` vorhanden waren (über API verifiziert: Order 10064).
   * *Ursache:* Das Shopware 6.5 `listing`-Mixin implementiert im `created()`-Hook die Bedingung `if (this.getMainComponent() === this) { this.getList(); }`. Bei verschachtelten Modulen, in denen die Komponente ein `<sw-page>` im eigenen Template einbettet, schlägt dieser Gleichheitscheck fehl. Infolgedessen wurde `getList()` beim Aufruf der Seite niemals getriggert, `items` verblieb auf `null` und `<sw-entity-listing>` wurde nicht initialisiert.
2. **Fehlende Assoziationen & unformatierte Spalten:**
   * In den DAL-Criteria fehlte die ManyToOne-Assoziation `stateMachineState`, wodurch die Statusspalte leer blieb.
   * `orderCustomer` wurde als Deep-Join (`orderCustomer.customer`) geladen, obwohl `firstName` und `lastName` direkt auf `orderCustomer` liegen.
   * Spalten wie `amountTotal` und `orderDateTime` besaßen keine Scoped-Template-Slots und wurden unformatiert bzw. ohne Währung dargestellt.
   * Es existierte keine Klick-Verlinkung oder Kontext-Aktion zur eigentlichen Shopware-Bestellungsdetailseite (`sw.order.detail`).
3. **Konzeptionelle Klärung der Tag-Architektur:**
   * Es bestand Unklarheit darüber, warum am Produkt/Artikel kein Tag `Vorbestellung` gesetzt wird und wie der Lebenszyklus des Tags an der Bestellung definiert ist.

## Entscheidung

### 1. Explizite Lifecycle-Initialisierung der Vue.js-Komponente
Die Komponente `custom-preorder-manager-list` verlässt sich für den initialen Ladevorgang nicht mehr ausschließlich auf die unzuverlässige `getMainComponent()`-Bedingung des `listing`-Mixins, sondern deklariert einen expliziten `created()`-Lifecycle-Hook:
```javascript
created() {
    this.createdComponent();
},
methods: {
    createdComponent() {
        this.getList();
    },
    // ...
}
```
Zusätzlich wird ein defensiver `.catch()`-Block an `orderRepository.search()` angehängt, der im Fehlerfall `isLoading = false` setzt und `items = []` zuweist, um ein Einfrieren der Oberfläche zu verhindern.

### 2. Standardisierte DAL-Criteria mit Sortierung und Assoziationen
Die Abfragekriterien werden in `orderCriteria` konsolidiert:
* **Filter:** `Criteria.equals('tags.name', 'Vorbestellung')`
* **Assoziationen:** `orderCustomer`, `stateMachineState`, `currency`, `lineItems`
* **Sortierung:** Standardmäßig `orderDateTime` absteigend (`DESC`), sodass die neuesten Vorbestellungen oben stehen.
* **Pagination & Suche:** Anbindung an `this.page`, `this.limit` und Suchbegriff `this.term`.

### 3. Vollständige Scoped Slots & Deep-Linking im Template
In `custom-preorder-manager-list.html.twig` werden für alle Spalten Scoped Slots definiert:
* **Bestellnummer (`#column-orderNumber`):** Router-Link auf `sw.order.detail` mit Parameter `:to="{ name: 'sw.order.detail', params: { id: item.id } }"`.
* **Kunde (`#column-orderCustomer.firstName`):** Zusammenführung von Vor- und Nachname (`{{ item.orderCustomer.firstName }} {{ item.orderCustomer.lastName }}`).
* **Datum (`#column-orderDateTime`):** Lokalisierte Datums- und Zeitformatierung via Twig-Filter `{{ item.orderDateTime | date({ hour: '2-digit', minute: '2-digit' }) }}`.
* **Status (`#column-stateMachineState.name`):** Darstellung des übersetzten Statusnamens (`{{ item.stateMachineState.translated.name || item.stateMachineState.name }}`).
* **Gesamtbetrag (`#column-amountTotal`):** Währungsformatierung via `{{ item.amountTotal | currency(item.currency.isoCode) }}`.
* **Aktionen (`#actions`):** Kontextmenü mit Direktabsprung „Bestellung anzeigen" (`sw-context-menu-item`).
* **Empty-State (`#empty-state`):** Standardisiertes `sw-empty-state` mit zweisprachigen Snippets, wenn keine Vorbestellungen vorliegen.

### 4. Tag-Architektur: Trennung zwischen Artikel (Stock-Driven) und Bestellung (Logistics-Driven)
* **Artikel (Produkt):** Erhält **KEIN** Tag. Die Steuerung erfolgt ausschließlich über die Custom Fields (`custom_preorder_active`, Kontingente) und den physischen Lagerbestand (`stock`). Sobald Wareneingang eingebucht wird (`stock > 0`), sticht der physische Bestand sofort die Vorbestellung (`Stock-Guard`). Es ist kein manuelles Löschen von Tags erforderlich.
* **Bestellung:** Erhält beim Checkout über den `OrderPlacedSubscriber` das Tag `Vorbestellung`. Dieses Tag verbleibt dauerhaft an der Bestellung, um als Filter- und Sperrkriterium für ERP-, WWS- und Logistik-Schnittstellen zu dienen. Es wird vom Plugin **nicht** eigenmächtig gelöscht, da die Auftragsabwicklung und das Fulfillment der Domäne des Händlers bzw. seines ERP-Systems obliegen.

## Begründung
* **Deterministischer Ladezyklus:** Der explizite `created()`-Hook garantiert die Ausführung von `getList()` unter allen Shopware 6.5.x CE Bedingungen unabhängig von Vue-3-Komponentenverschachtelungen.
* **Enterprise UX:** Händler können direkt aus der Vorbestellungsliste per Klick in die Shopware-Bestellmaske abspringen.
* **Performance:** Vermeidung unnötiger Joins (`orderCustomer.customer`); alle benötigten Daten werden schlank über flache Assoziationen geladen.
* **Wartungsfreiheit & Fehlertoleranz:** Durch den Verzicht auf Tags am Artikel entfällt jeglicher Synchronisations-Overhead bei Lagerbuchungen.

## Abgelehnte Alternativen

| Alternative | Grund der Ablehnung |
|---|---|
| **Reines Verlassen auf `listing`-Mixin ohne `created()`:** | Führt in Shopware 6.5 CE bei verschachtelten Page-Komponenten zu stillem Ladeausfall, da `getMainComponent() !== this`. |
| **Tagging auch am Artikel (Produkt) setzen:** | Erfordert manuelle Tag-Löschung oder komplexe, fehleranfällige Event-Hooks bei jeder Lagerbestandsänderung. Widerspricht der autarken `stock > 0`-Architektur (ADR-004). |
| **Automatisches Entfernen des Order-Tags durch das Plugin:** | Ein eigenmächtiges Löschen des Tags bei Statuswechseln würde Drittanbieter-Logistiksysteme und ERP-Workflows (z. B. Picklisten, Teillieferungen) unkontrolliert sabotieren. |
