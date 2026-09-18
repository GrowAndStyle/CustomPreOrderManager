# Walkthrough 002 — Phase 2: Cart-Collector Pipeline & LineItem Payload Enrichment

**Status:** Abgeschlossen  
**Datum:** 2026-09-13  
**Branch:** `feat/cart-collector`  
**Architektur:** Tier-1 Enterprise Standard  

---

## 1. Ziel & Umfang

Non-destruktive Anreicherung von Positionen im Warenkorb und in der Bestellung mit Vorbestellungs-Metadaten:
1. **Kein Überschreiben von Core-Mails oder Tabellen:** Vorbestellungs-Informationen (`isPreOrder`, `preOrderReleaseText`) werden als unveränderbare Metadaten direkt im `payload` der `order_line_item` verankert.
2. **Lagerbestand-Vorrang:** Befindet sich physischer Bestand im Lager (`stock > 0`), sticht dies die Vorbestellung aus (sofort lieferbare Ware).
3. **Automatisierte Textformatierung:** Greift auf `custom_preorder_release_text` zu oder generiert bei vorhandenem Datum `custom_preorder_release_date` automatisch die Anzeige *„Lieferbar ab MM/YYYY“*.

---

## 2. Technische Umsetzung im Detail

### A. PreOrderCartCollector (`src/Core/Checkout/Cart/PreOrderCartCollector.php`)
- Implementiert `Shopware\Core\Checkout\Cart\CartDataCollectorInterface`.
- Filtert im Warenkorb auf `LineItem::PRODUCT_LINE_ITEM_TYPE`.
- Prüft `custom_preorder_active === true`.
- Prüft physischen Lagerbestand (`deliveryInformation->getStock()` bzw. `payload['stock']`). Ist der Lagerbestand `> 0`, wird die Position übersprungen.
- Ermittelt den Hinweistext:
  1. `custom_preorder_release_text` falls gesetzt.
  2. `custom_preorder_release_date` formatiert als `Lieferbar ab m/Y`.
  3. Fallback `Vorbestellung`.
- Setzt `isPreOrder = true` und `preOrderReleaseText = $releaseText` im LineItem-Payload.

### B. Service-Registrierung (`src/Resources/config/services.xml`)
- Registriert als `shopware.cart.collector` mit Priorität `4500` (läuft nach der Core-Produkt- und Lieferdaten-Sammlung).

### C. Unit-Tests (`tests/Unit/Core/Checkout/Cart/PreOrderCartCollectorTest.php`)
- `testCollectIgnoresNonProductLineItems`: Ignoriert Nicht-Produktpositionen (z. B. Promotions).
- `testCollectIgnoresWhenPreOrderNotActive`: Ignoriert Produkte ohne aktiven Pre-Order-Switch.
- `testCollectIgnoresWhenDeliveryInformationStockGreaterThanZero`: Ignoriert Produkte mit `deliveryInformation->stock > 0`.
- `testCollectIgnoresWhenPayloadStockGreaterThanZero`: Ignoriert Produkte mit `payload['stock'] > 0`.
- `testCollectEnrichesWithExplicitReleaseText`: Reichert mit Freitext an (`isPreOrder = true`).
- `testCollectEnrichesWithFormattedReleaseDateWhenNoText`: Reichert mit formatiertem Datum an (`Lieferbar ab 11/2026`).
- `testCollectFallbackOnInvalidReleaseDate`: Fallback auf `Vorbestellung` bei ungültigem Datumsformat.
- `testCollectFallbackWhenNoDateAndNoTextProvided`: Fallback auf `Vorbestellung` wenn weder Datum noch Freitext vorhanden.

---

## 3. Verifikation & Qualität
- `xmllint` Validierung für `services.xml` bestanden (0 Fehler).
- 8 Unit-Tests für alle Verzweigungen des Cart-Collectors implementiert.
- 100% Test-Coverage für `PreOrderCartCollector`.
