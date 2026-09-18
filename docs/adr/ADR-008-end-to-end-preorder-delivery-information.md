# ADR-008: Durchgängige Vorbestellungs-Architektur (Full-Funnel, Aktiver Quota Guard & Flexibler Mischwarenkorb)

**Status:** ENTSCHIEDEN  
**Datum:** 2026-09-17  
**Autor:** Senior Fullstack Shopware 6 Architekt  

## Kontext
Ein Vorbestellungs-System auf **Enterprise Tier-1 Niveau** muss den Kunden über den gesamten E-Commerce Funnel lückenlos und rechtssicher begleiten sowie bei High-Concurrency-Szenarien (limitierte Drops, Flash Sales) das Backend und die Bestandsintegrität schützen.

Im Review wurden folgende 4 Kernaspekte identifiziert:
1. **Inkonsistenter Storefront-Funnel:**
   - Auf Kategorieseiten existierte nur der Button *„Jetzt vorbestellen“*, aber keine Information über das Erscheinungsdatum oder den Hinweistext.
   - Im Offcanvas-Warenkorb und im Checkout wurde das Erscheinungsdatum verschluckt, sobald ein Hinweistext hinterlegt war.
   - Die Line-Item Anzeige nutzte veraltete Unicode-Emojis (`📅`) und statische Bootstrap-Badges statt des 2026er Designs.
2. **Quota Guard darf keinesfalls still ablaufen:**
   - Wenn ein Kunde im Warenkorb die Menge über die Restquote hinaus erhöht, darf die Menge nicht stillschweigend korrigiert werden. Der Kunde muss transparent über eine native Shopware Flash-Notification (`CartError`) mit Produktname und Mengenanpassung abgeholt werden.
3. **Mischwarenkörbe erfordern flexible Fulfillment-Hinweise:**
   - Ein starrer Text wie *„Der Versand erfolgt gesammelt“* widerspricht der gängigen Händler-Praxis, in der je nach Warenverfügbarkeit und Kundenwunsch von Fall zu Fall entschieden wird, ob Teillieferungen erfolgen.
   - Der Hinweis muss über die Plugin-Konfiguration flexibel wählbar sein (Fall-zu-Fall / Teillieferung nach Verfügbarkeit, reine Teillieferung, Komplettlieferung oder individueller Freitext).
4. **Post-Purchase Transparenz (Kundenkonto):**
   - In der Bestellhistorie des Kundenkontos (`/account/order`) muss der Lieferstatus für den Kunden auch nach Abschluss der Bestellung dauerhaft einsehbar bleiben.

---

## Entscheidung

### 1. Strukturierte Payload-Pipeline ohne Datenverlust (`PreOrderCartCollector`)
In `PreOrderCartCollector.php` werden Datum und Hinweistext als eigenständige, typisierte Payload-Attribute geführt:
- `isPreOrder: true`
- `preOrderReleaseDate: "30.09.2026"` (oder null)
- `preOrderNotice: "Hinweistext"` (sanitized)
- `preOrderFormattedStatus: "Voraussichtlich lieferbar ab 30.09.2026"`
- `preOrderInboundStock: int`
- `preOrderSoldCount: int`
- `preOrderRemainingQuota: int`

### 2. Aktiver Quota Guard mit Shopware-Alerts (`PreOrderCartValidator`)
Ein neuer `PreOrderCartValidator` implementiert `Shopware\Core\Checkout\Cart\CartValidatorInterface`:
- Prüft bei jeder Warenkorb-Kalkulation, ob für Vorbestellartikel ein festes Kontingent (`inbound_stock > 0`) vorliegt.
- Wenn `quantity > remainingQuota`:
  - Kappen der Menge auf `remainingQuota`.
  - Auslösen von `PreOrderQuantityAdjustedError` (`Error::LEVEL_WARNING`).
- Wenn `remainingQuota <= 0`:
  - Entfernen des Artikels aus dem Cart.
  - Auslösen von `PreOrderQuotaExhaustedError` (`Error::LEVEL_ERROR`).
- Shopware rendert automatisch ein gut sichtbares Warning-Alert-Banner im Offcanvas und Cart mit Produktname und Mengenangaben.

### 3. Flexibler Mischwarenkorb-Hinweis via `config.xml`
Unter der Card *„Mischwarenkorb — Versandhinweis & Fulfillment“* stehen dem Händler 4 Modi zur Verfügung:
- `flexible`: Fall-zu-Fall / Teillieferung nach Verfügbarkeit (Default)
- `split`: Feste Teillieferung (sofort lieferbare Ware geht direkt raus, Vorbestellung separat)
- `consolidated`: Feste Komplettlieferung (gemeinsamer Versand zum Release)
- `custom`: Frei formulierbarer Hinweistext
Der Banner erscheint ausschließlich im Offcanvas-Warenkorb und Checkout, wenn tatsächlich Lagerware und Vorbestellware kombiniert im Warenkorb liegen.

### 4. Full-Funnel Storefront-Design (Design 2026)
- **Kategorieseite (`action.html.twig`):** Oberhalb des Buttons wird ein Micro-Panel mit Live-Pulse-Dot, dem rechtssicheren Liefertermin und dem Hinweistext gerendert.
- **Line-Items (`label.html.twig`):** 2-Ebenen Micro-Badge (Pill `VORBESTELLUNG`, Live-Pulse-Dot, Liefertermin und Info-Subline mit feinem SVG-Icon).
- **Kundenkonto (`order-item.html.twig`):** Der Lieferstatus bleibt in der Bestellhistorie für den Kunden dauerhaft transparent.

---

## Konsequenzen & Mehrwert
- **Maximale Kundentransparenz:** Niemand wird durch stilles Kappen von Warenkorbmengen überrascht.
- **Keine Bevormundung der Logistik:** Der Händler steuert den Mischwarenkorb-Hinweis passend zu seiner tatsächlichen Fulfillment-Strategie.
- **Null Überverkäufe:** Der Quota Guard schützt Händler vor rechtlichen Lieferverzugsschäden bei Überbestellungen im Cart.
- **Vollständige Rechtskonformität:** Transparente Lieferzeitangaben gem. § 312d BGB und Preisangabenverordnung.
