# Roadmap: CustomPreOrderManager

## Abgeschlossen

### v1.0.0 — Core Pre-Order System
- CustomField-Set auf `product` mit 5 Feldern (`active`, `release_date`, `release_text`, `inbound_stock`, `sold_count`)
- Cart-Collector Pipeline (Priority 4100) mit LineItem Payload Enrichment
- Atomarer DBAL-Counter für Race-Condition-sichere Kontingentierung
- Automatisches Order-Tagging (`Vorbestellung`)
- Flow Builder Event `preorder.order.placed` mit `FlowEventAware` und `ScalarValuesAware`
- Admin-Dashboard mit Vorbestellungs-Listing und Produkt-Detail-Tab
- Storefront: Vorbestell-Button, Scarcity-Badge, Lieferzeit-Box, Listing-Badges
- Rate-Limiter via `PrependExtensionInterface`
- 35 Tests, 100% Coverage (5/5 Klassen, 20/20 Methoden, 102/102 Zeilen)
- Zweisprachige Snippets (de-DE, en-GB)

### v1.1.0 — Storefront Refinement
- Natives Button-Theming mit CSS Custom Properties und Plugin-Config Color-Picker
- Kategorieseite: Lieferdatum-Banner auf Produktbildern mit konfigurierbarer Hintergrundfarbe, Textfarbe und Opacity
- Bereinigung harter Maße zugunsten nativer Theme-Klassen
- ConfigXmlTest mit 7 Tests für bilinguale Labels und Hex-Defaults

---

## Geplant

### v1.2.0 — Post-Install Mail-Template-Dokumentation
- README.md-Erweiterung: Post-Installations-Anleitung für die Anpassung des Bestellbestätigungs-Templates
- Vollständige Payload-Feldreferenz (8/8 Felder), Twig-Snippet, Mischwarenkorb-Dokumentation
- ROADMAP.md erstellt

### v1.3.0 — Payment-Aware Counter, Scarcity-Badge-Fix & Checkout-Mischwarenkorb
- **Status:** In Arbeit
- **ADR-009:** `sold_count` wird erst bei Zahlungseingang (`paid`) erhöht statt bei Bestelleingang
- Automatischer Rollback bei Stornierung (`cancelled`) und Rückerstattung (`refunded`)
- Neuer `PaymentStateSubscriber` für State Machine Events
- `OrderPlacedSubscriber` refactored: Counter entfernt, Tag + Event bleiben bei Bestelleingang
- Scarcity-Badge auf PDP: Snippet-Key-Inkonsistenz behoben
- Mischwarenkorb-Hinweis auf `/checkout/cart` und `/checkout/confirm` ergänzt (Twig-Partial)
- Snippet-Bereinigung: Verwaiste Root-Level-Keys entfernt
- Test-Hygiene: Duplizierte Tests gelöscht, defekte Mocks korrigiert, PaymentStateSubscriberTest neu

---

## Backlog (Priorisiert, ohne festes Release)

### Dediziertes Flow Builder Mail-Template
- **Priorität:** P2 (Should-have)
- **Beschreibung:** Registrierung eines eigenen Mail-Template-Types (`preorder_order_confirmation`) bei der Plugin-Installation. Der Händler kann dieses Template im Flow Builder als Aktion auf das Event `preorder.order.placed` konfigurieren — für eine dedizierte Vorbestellungs-Bestätigungsmail an den Kunden oder interne Benachrichtigungen (Lager-Team, Kundenservice).
- **Voraussetzung:** Das Event `preorder.order.placed` existiert bereits und feuert bei jeder Bestellung mit Vorbestellpositionen. Das Template muss in der Migration in die Tabellen `mail_template_type` und `mail_template` geschrieben werden.
- **Abgrenzung:** Kein Override der Standard-Bestellbestätigung. Das Template ist eine **zusätzliche Option**, die der Händler im Flow Builder nach Bedarf aktiviert.
- **Anwendungsfälle:**
  - Separate Vorbestellungs-Info-Mail an den Kunden mit Lieferzeitdetails
  - Interne Benachrichtigung an Lager/Einkauf bei neuen Vorbestellungen
  - VIP-Routing bei Großbestellungen limitierter Vorbestellartikel
