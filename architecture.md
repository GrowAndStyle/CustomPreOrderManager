# Architecture Decision Record (ADR-001) — CustomPreOrderManager

## Status: Akzeptiert (Tier-1 Enterprise)

## Kontext
Grow & Style benötigt eine Lösung, um Neuheiten (z. B. die Spectron 7 AI+ Cam von AC Infinity) bereits vor Verkaufsstart im Oktober vorbestellbar zu machen, ohne das bestehende Theme oder Dritt-Plugins zu beeinträchtigen.

## Entscheidungen
1. **Autarkie:** Keine Abhängigkeiten zu Drittanbieter-Plugins oder anderen GrowAndStyle-Plugins.
2. **Kaufabwicklung:** Regulärer Kauf mit Sofortbezahlung im Checkout (keine ungesicherten Autorisierungen wegen 28-Tage-Verfall).
3. **Mischwarenkörbe:** Vollständig zulässig; keine künstlichen Sperren im Warenkorb (Conversion-First).
4. **Zulaufbestand:** Dynamische Mengensteuerung (`inbound_stock`) mit atomarem DBAL-Inkrement (`sold_count`).
5. **Warteliste:** Bei Kontingent 0 greift eine autarke Warteliste mit Double-Opt-In und CIS-identischen E-Mail-Tokens.
6. **Mail-Integrität:** Line-Item Payload Anreicherung statt Überschreiben von DB-Templates.
7. **Lifecycle:** Physischer Bestand (`stock > 0`) sticht Vorbestellung und schaltet automatisch auf Normalverkauf um.
