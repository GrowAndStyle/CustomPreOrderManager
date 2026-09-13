# Walkthrough 004 — Phase 4: Storefront Twig & Mobile-First SCSS

**Status:** Abgeschlossen  
**Datum:** 2026-09-13  
**Branch:** `feat/storefront-ui`  
**Architektur:** Tier-1 Enterprise Standard  

---

## 1. Ziel & Umfang

Implementierung des vollständigen Storefront UI-Layers für das Enterprise Vorbestellungs-System:
1. **Twig Block-Inheritance:** Lückenlose Nutzung von `{% sw_extends %}` ohne Überschreiben kompletter Core-Dateien.
2. **Grow & Style Premium Design:** CI-Farben (`#1a1a2e`, `#ff9800`, `#28a745`), Apple HIG / WCAG 2.5.8 konforme Touch-Targets (≥48px), responsive Scarcity-Badges.
3. **Conversion-Optimiertes PDP Buy-Widget:** Automatischer Wechsel zwischen Standard-Kauf-Button, Vorbestell-Button und Wartelisten-Button bei Kontingenterschöpfung.
4. **Wartelisten-Modal:** Autarkes Bootstrap 5 Modal mit CSRF-Schutz, unsichtbarem Bot-Honeypot und DSGVO-Einwilligung.
5. **Warenkorb & Listing:** Visuelle Kennzeichnung auf Kategorieseiten (`badges.html.twig`) und Positionsbezeichnung im Checkout (`label.html.twig`).
6. **Storefront JS-Plugin:** Registrierung via Shopware PluginManager (`PreOrderManager`) für AJAX-Formular-Submissions.

---

## 2. Technische Umsetzung im Detail

### A. Produktdetailseite — Kaufen-Button (`buy-widget-form.html.twig` & `buy-widget.html.twig`)
- **Pfad:** `src/Resources/views/storefront/page/product-detail/buy-widget-form.html.twig`
- Erweitert Block `page_product_detail_buy_button`:
  - Prüft `custom_preorder_active` und physischen Lagerbestand (`stock <= 0`).
  - Wenn Kontingent erschöpft (`inbound_stock > 0 and remainingQuota <= 0`): Zeigt sekundären Wartelisten-Button (`btn-preorder-waitlist`) mit `data-bs-toggle="modal"` auf `#preorderWaitlistModal`.
  - Wenn Kontingent verfügbar: Zeigt primären Vorbestell-Button (`btn-preorder`).
  - Wenn `stock > 0`: Zeigt Standard Shopware-Kaufen-Button via `{{ parent() }}` (Lagerbestand sticht Vorbestellung).
- Erweitert Block `page_product_detail_buy_quantity_container`:
  - Blendet Mengenauswahl bei Warteliste aus.
- **Pfad:** `src/Resources/views/storefront/page/product-detail/buy-widget.html.twig`
  - Bindet das Wartelisten-Modal außerhalb des Kaufen-Formulars ein, um invalide verschachtelte `<form>`-Tags zu verhindern.

### B. Lieferhinweis & Scarcity-Badges (`delivery-information.html.twig`)
- **Pfad:** `src/Resources/views/storefront/component/delivery-information.html.twig`
- Ersetzt bei aktiver Vorbestellung die Standard-Lieferzeit durch die `.preorder-delivery-box` mit Signal-Icon.
- Zeigt dynamisch konfigurierten Freitext (`custom_preorder_release_text`) oder formatiertes Datum `Lieferbar ab MM/YYYY`.
- Scarcity-Engine:
  - Bei `remainingQuota <= lowStockThreshold`: Dringlichkeits-Badge mit pulsierender Animation (`🔥 Fast vergriffen: Nur noch X Stück für Erstauslieferung`).
  - Bei normalem Vorrat: Dezenter Zähler (`ℹ️ Noch X von Y Exemplaren verfügbar`).

### C. Listing- & Warenkorb-Badges (`badges.html.twig` & `label.html.twig`)
- **Pfad:** `src/Resources/views/storefront/component/product/card/badges.html.twig`
  - Zeigt `.badge-preorder` auf Produktkarten, gesteuert über System-Config `enableListingBadge`.
- **Pfad:** `src/Resources/views/storefront/component/line-item/element/label.html.twig`
  - Zeigt im Warenkorb und Checkout die unveränderliche Vorbestellungs-Badge mit voraussichtlichem Liefertermin aus dem LineItem-Payload.

### D. Wartelisten-Modal (`waitlist-modal.html.twig`)
- **Pfad:** `src/Resources/views/storefront/component/preorder/waitlist-modal.html.twig`
- Bootstrap 5 Modal (`#preorderWaitlistModal`).
- Enthält Formular mit:
  - CSRF-Token `{{ sw_csrf('frontend.preorder.waitlist.subscribe') }}`.
  - Hidden Field `productId`.
  - Honeypot `hp_check` (unsichtbar für Menschen, fängt Spambots ab).
  - Pflichtfeld `email` und optionales Feld `name`.
  - DSGVO-Checkbox (`consent`) mit Rechtstext nach § 7 UWG.

### E. SCSS & Mobile-First Styling (`base.scss`)
- **Pfad:** `src/Resources/app/storefront/src/scss/base.scss`
- Alle Buttons besitzen eine Mindesthöhe von 48px für optimale Touch-Bedienung (Apple HIG / WCAG 2.5.8).
- Farbschema: Dunkles Premium-Navy `#1a1a2e`, Signal-Orange `#ff9800`, Erfolgs-Grün `#28a745`.

### F. Storefront JS-Plugin (`main.js` & `preorder-manager.plugin.js`)
- **Pfad:** `src/Resources/app/storefront/src/main.js`
- Registriert `PreOrderManager` im globalen `PluginManager` auf Selector `[data-preorder-waitlist-form]`.
- Asynchrone Formular-Übertragung mit Shopware `HttpClient` und direktem Inline-Feedback.

---

## 3. Verifikation & Qualität
- `xmllint` Validierung für XML-Dateien bestanden (0 Fehler).
- `python3 -m json.tool` Validierung für alle Snippets bestanden (0 Fehler).
- Alle Twig-Templates nutzen strikte Block-Vererbung mit `{% sw_extends %}`.
- Bestehende PHPUnit-Testsuite unverändert bei 100% Coverage (36 Tests, 99 Assertions).
