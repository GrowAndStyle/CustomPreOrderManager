# CustomPreOrderManager (Shopware 6 Plugin)

**Vendor:** `growandstyle` | **Package:** `growandstyle/custom-pre-order-manager` | **Kompatibilität:** Shopware 6.5.x CE (aufwärtskompatibel zu 6.6 & 6.7)

High-Conversion Vorbestellungs-System im Grow & Style Premium Theme:
- Vorbestellungs-Steuerung per CustomField-Set am Produkt (Erscheinungsdatum, Display-Hinweis, Zulaufkontingent).
- Dynamischer „Jetzt vorbestellen"-Button auf der Produktdetailseite und Vorbestell-Badges im Listing.
- Scarcity-Engine mit konfigurierbaren Schwellenwerten („Noch X von Y verfügbar“, „Fast vergriffen“).
- Vollständig autarke Warteliste nach CIS-Design-Standard bei Kontingent 0 mit 100% DSGVO Double-Opt-In.
- Non-Destructive Mail-Integration über `order_line_item.payload` (schützt bestehende Mailtemplates).
- Automatischer Wechsel auf Standardverkauf bei physischem Wareneingang (`stock > 0`).
- Automatisches Shopware-Tagging (`Vorbestellung`) an der Order für 1-Klick-Filterung im Admin.
