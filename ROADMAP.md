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
- Zweisprachige Snippets (de-DE, en-GB)

### v1.1.0 — Storefront Refinement
- Natives Button-Theming mit CSS Custom Properties und Plugin-Config Color-Picker
- Kategorieseite: Lieferdatum-Banner auf Produktbildern mit konfigurierbarer Hintergrundfarbe, Textfarbe und Opacity
- Bereinigung harter Maße zugunsten nativer Theme-Klassen
- ConfigXmlTest mit 7 Tests für bilinguale Labels und Hex-Defaults

### v1.2.0 — Post-Install Mail-Template-Dokumentation
- README.md: Post-Installations-Anleitung für Bestellbestätigungs-Template-Anpassung
- Vollständige Payload-Feldreferenz (8/8 Felder), Twig-Snippet, Mischwarenkorb-Dokumentation
- ROADMAP.md erstellt

### v1.3.0 — Payment-Aware Counter, Scarcity-Badge-Fix & Checkout-Mischwarenkorb
- **ADR-009:** `sold_count` erst bei Zahlungseingang (`paid`), Rollback bei Storno/Refund
- Neuer `PaymentStateSubscriber` für Shopware State Machine Events
- `OrderPlacedSubscriber` refactored: Counter entfernt, Tag + Event bleiben
- Scarcity-Badge PDP: Snippet-Key-Inkonsistenz behoben
- Mischwarenkorb-Hinweis auf `/checkout/cart` und `/checkout/confirm` (Twig-Partial)
- Snippet-Bereinigung: Verwaiste Root-Level-Keys entfernt

### v1.4.0 — ADR-011: Listing-Overlay Structural Refactor & Scarcity-Config
- **ADR-011:** Overlays (Date-Banner, Scarcity-Badge) in `box-standard.html.twig` via `component_product_box_image` (verifiziert Shopware 6.5.8.19) — kein Pixel-Hack mehr
- Containing Block `preorder-image-overlay-root` mit `position: relative`, `top: 0`, `bottom: 0`
- `badges.html.twig` auf minimales `{{ parent() }}` reduziert
- DROP TABLE aus Uninstaller entfernt — `keepUserData=false` per Integration-Test absicherbar
- 3 neue Config-Felder für Listing-Scarcity-Badge (Farbe, Textfarbe, Opacity) — entkoppelt von Card/PDP
- Scarcity-Badge zweizeilig: `listing.scarcityPrefix` / `listing.scarcityCount`
- 104 Tests, 741 Assertions, 100% Coverage (9/9 Klassen, 41/41 Methoden, 220/220 Zeilen)

### v1.4.1 — Listing-Overlay Visuelles Goldstandard-Finish
- Date-Banner: `top: calc(-1 * var(--bs-card-spacer-y, 1rem))` — theme-aware Offset via Bootstrap-Variable, kein Magic Number
- Date-Banner + Scarcity-Badge: `border: 1px solid rgba(255,255,255,0.15/0.18)` — Glassmorphism Micro-Border
- Date-Banner + Scarcity-Badge: `box-shadow: 0 4px 12px rgba(15,23,42,0.15–0.18)` — Soft Elevation
- Date-Banner + Scarcity-Badge: `backdrop-filter: blur(8px)` — verstärktes Frosted-Glass
- Scarcity-Badge: `border-radius: 8px` (alle Ecken) statt `8px 8px 0 0` — kein Flush-Look bei `bottom: 0`
- Rabatt-% Badge (`component_product_badges_discount`) wird bei aktiven Preorder-Overlays ausgeblendet — Rabattinfo bleibt als Durchstreich-Preis + rote Schrift sichtbar

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
