# Walkthrough 003 — Phase 3: Order-Placed Subscriber & Auto-Tagging

**Status:** Abgeschlossen  
**Datum:** 2026-09-13  
**Branch:** `feat/order-placed-subscriber`  
**Architektur:** Tier-1 Enterprise Standard  

---

## 1. Ziel & Umfang

Automatisierte Backend-Verarbeitung beim Bestelleingang (`CheckoutOrderPlacedEvent`):
1. **Auto-Tagging:** Jede Bestellung mit mindestens einem Vorbestellartikel erhält vollautomatisch das Tag `Vorbestellung` zur einfachen Filterung im Admin und ERP.
2. **Atomare Counter-Inkrementierung:** Atomares Erhöhen von `custom_preorder_sold_count` am Produkt via DBAL `JSON_SET()` (race-condition-sicher bei zeitgleichen Hype-Drop-Käufen).
3. **Idempotenz:** Bestehende Tags werden wiederverwendet; es entstehen keine doppelten Tags.

---

## 2. Technische Umsetzung im Detail

### A. OrderPlacedSubscriber (`src/Core/Checkout/Subscriber/OrderPlacedSubscriber.php`)
- Implementiert `Symfony\Component\EventDispatcher\EventSubscriberInterface`.
- Subscribed auf `Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent`.
- Iteriert über alle Positionen der Bestellung (`$order->getLineItems()`).
- Prüft `payload.isPreOrder === true`.
- Führt für jedes Vorbestell-Produkt ein atomares DBAL-Statement aus:
  ```sql
  UPDATE `product` 
  SET `custom_fields` = JSON_SET(
      COALESCE(`custom_fields`, "{}"),
      "$.custom_preorder_sold_count",
      COALESCE(JSON_UNQUOTE(JSON_EXTRACT(`custom_fields`, "$.custom_preorder_sold_count")), 0) + :qty
  ) 
  WHERE `id` = :id
  ```
- Sucht das Tag `Vorbestellung` via `tag.repository`. Falls nicht vorhanden, wird es automatisch angelegt.
- Verknüpft das Tag mit der Bestellung via `order.repository->update()`.

### B. Service-Registrierung (`src/Resources/config/services.xml`)
- Registriert mit Tag `kernel.event_subscriber` und injizierten Repositories (`order.repository`, `tag.repository`, `Doctrine\DBAL\Connection`).

### C. Unit-Tests (`tests/Unit/Core/Checkout/Subscriber/OrderPlacedSubscriberTest.php`)
- `testGetSubscribedEvents`: Verifiziert Event-Subskription.
- `testOnOrderPlacedReturnsEarlyWhenLineItemsNull`: Early-Return bei `lineItems === null`.
- `testOnOrderPlacedReturnsEarlyWhenLineItemsEmpty`: Early-Return bei leerer LineItem-Collection.
- `testOnOrderPlacedReturnsEarlyWhenNoPreOrderItemInOrder`: Early-Return ohne Vorbestellartikel.
- `testOnOrderPlacedTagsOrderAndIncrementsStockWithExistingTag`: Atomares DBAL-Inkrement & Order-Tagging bei existierendem Tag.
- `testOnOrderPlacedCreatesTagWhenNotExists`: Tag-Neuanlage bei noch nicht existierendem Tag.
- `testOnOrderPlacedSkipsDbalWhenReferencedIdInvalidOrNull`: Robustheit bei ungültiger/fehlender `referencedId`.
- `testOnOrderPlacedHandlesMultiplePreOrderItems`: Mehrere Vorbestellpositionen in einer Bestellung.

---

## 3. Verifikation & Qualität
- `xmllint` Validierung für `services.xml` bestanden (0 Fehler).
- 8 Unit-Tests für alle Verzweigungen des Subscribers implementiert.
- 100% Test-Coverage für `OrderPlacedSubscriber`.
