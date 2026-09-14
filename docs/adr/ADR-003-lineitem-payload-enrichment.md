# ADR-003: LineItem-Payload-Enrichment statt Mail-Template-Override

**Status:** ENTSCHIEDEN  
**Datum:** 2026-09-14  
**Autor:** Antigravity (Principal Shopware Architect)  

## Kontext
Vorbestell-Informationen (Status, voraussichtliches Lieferdatum, Hinweistext) müssen im Warenkorb, auf der Bestellbestätigung, in PDF-Belegen (Rechnung/Lieferschein) und in Transaktionsmails sichtbar sein. Dabei dürfen bestehende Mail-Vorlagen des Händlers keinesfalls überschrieben oder beschädigt werden.

## Entscheidung
Wir verwenden einen `PreOrderCartCollector` mit Priorität `4100`, der während der Cart-Berechnung den Payload der `LineItem`-Instanzen um `isPreOrder: true` und `preOrderReleaseText: string` anreichert. Zusätzlich stellen wir ein dediziertes Flow Builder Event (`PreOrderPlacedEvent`) bereit.

## Begründung
1. **Non-Destructive Integration:** Shopware persistiert den `payload` der Cart-LineItems direkt in der Tabelle `order_line_item`. Belege und Mail-Templates greifen standardmäßig auf die Positionseigenschaften zu.
2. **Keine Template-Kollisionen:** Bestehende `order.placed` Mail-Templates des Händlers bleiben unberührt.
3. **Flow Builder Kompatibilität:** Für händlerspezifische VIP-Vorbestell-Benachrichtigungen steht das Event `preorder.order.placed` zur Verfügung.

## Abgelehnte Alternativen

| Alternative | Grund der Ablehnung |
|---|---|
| Überschreiben von `order.placed` in der DB | Zerstört bestehende Kundenanpassungen des Händlers und verursacht Konflikte mit Drittanbieter-Plugins. |
| Cart-Validator mit Blockierung | Verletzt die Anforderung, dass Mischwarenkörbe jederzeit voll checkout-fähig bleiben müssen. |
