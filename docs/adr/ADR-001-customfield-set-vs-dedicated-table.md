# ADR-001: CustomField-Set statt eigener Entitätstabelle

**Status:** ENTSCHIEDEN  
**Datum:** 2026-09-14  
**Autor:** Antigravity (Principal Shopware Architect)  

## Kontext
Vorbestellungsdaten (Aktivierungsstatus, Erscheinungsdatum, Hinweistext, Zulaufkontingent, verkaufte Vorbestellungsmenge) müssen direkt an Shopware-Produkten hinterlegt und gepflegt werden. Hierbei muss sichergestellt werden, dass keine Schema-Locks auf Core-Tabellen entstehen und Varianten diese Daten erben können.

## Entscheidung
Wir verwenden ein Shopware CustomField-Set (`custom_preorder_set`) verknüpft mit der Entität `product` mit den Feldern `custom_preorder_active`, `custom_preorder_release_date`, `custom_preorder_release_text`, `custom_preorder_inbound_stock` und `custom_preorder_sold_count`.

## Begründung
1. **Non-Destructive:** Keine DDL-Schema-Mutationen oder Spaltenerweiterungen auf der Core-Tabelle `product`.
2. **Varianten-Vererbung:** Shopware vererbt `customFields` von Hauptartikeln automatisch an Varianten, sofern diese nicht überschrieben sind.
3. **Admin-Standard:** Die Pflege kann nativ über Shopware-Standardkomponenten (`sw-field`, `sw-datepicker`) erfolgen.

## Abgelehnte Alternativen

| Alternative | Grund der Ablehnung |
|---|---|
| Eigene Relationstabelle (`custom_preorder_product`) | Erfordert manuelle Joins in allen Produktabfragen, bricht Varianten-Vererbung und erhöht die Abfragekomplexität signifikant. |
| Spalten-Erweiterung via Entity-Extension (`product.preorder_active`) | DDL-Schema-Änderung an `product` birgt Migrations- und Lock-Risiken bei großen Katalogen. |
