# ADR-005: Natives Button-Theming und dynamische Farb-Konfiguration via CSS Custom Properties

**Status:** ENTSCHIEDEN  
**Datum:** 2026-09-15  
**Autor:** Senior Fullstack Shopware 6 Architekt  

## Kontext
Der Vorbestell-Button (`.btn-preorder`) auf der Produktdetailseite und in den Kategorie-Produktkarten besaß zuvor harte Maßangaben (`min-height: 48px`, `font-size: 1rem`, `font-weight: 700`) im SCSS sowie ein unpassendes Unicode-Emoji `📅` im Twig-Markup. Dadurch wichen die Button-Dimensionen und Typografie sichtbar vom Shopware-Standard-Theme ab und störten die Höhenausrichtung im Kachel-Grid der Kategorieseiten.

Zudem benötigt der Händler die Möglichkeit, die Button-Farben (Hintergrund, Text, Rahmen sowie deren Hover-Zustände) und die Icon-Anzeige im Shopware-Admin per Color-Picker anzupassen – ohne dafür nach jeder Farbänderung ein serverweites Theme-Kompilieren (`bin/console theme:compile`) ausführen zu müssen.

Gleichzeitig untersagt das Enterprise-Regelwerk (`rules_preorder_storefront` §7) den Einsatz von Inline-Styles (`style="..."`) an HTML-Elementen zum Schutz der Theme-Isolation und Wartbarkeit.

## Entscheidung
1. **Dimensionen bereinigen:** Alle festen Größenangaben (`min-height`, `font-size`, `font-weight`) werden aus `.btn-preorder` im SCSS entfernt. Der Button nutzt und erbt die standardmäßigen Bootstrap- und Shopware-Klassen (`.btn.btn-primary.btn-buy.btn-preorder`) und passt sich nativ in die Höhen und Schriften des Themes ein.
2. **Natives Icon:** Das Emoji `📅` wird entfernt und durch das offizielle Shopware-SVG `{% sw_icon 'calendar' style { size: 'sm' } %}` ersetzt (konfigurierbar über `showButtonIcon`).
3. **Admin-Konfiguration:** In `config.xml` werden 6 `colorpicker`-Felder für Hintergrund-, Text- und Rahmenfarbe (inkl. Hover) sowie ein Boolean-Schalter `showButtonIcon` angelegt.
4. **Reaktive Farb-Injektion (Kanonisches CIS-Pattern):** Die konfigurierten Farbwerte werden über `src/Resources/views/storefront/base.html.twig` auf `:root` als Fallback bereitgestellt und zusätzlich direkt an den Vorbestell-Buttons per Inline-CSS-Variablen (`style="--custom-preorder-btn-bg: ...;"`) injiziert.
5. **Spezifitäts-Härtung im SCSS:** `.btn-preorder` nutzt Attribut-Selektoren `&[style*="--custom-preorder-btn-..."]` mit `!important`, um Theme-Overrides (z. B. `.product-box .btn-buy` oder Link-Farben auf `<a>`) zuverlässig zu überschreiben.

## Begründung
* **Kein Build-Overhead:** Farbänderungen greifen sofort nach dem Speichern im Admin und einfachem Cache-Leeren – kein Theme-Kompilieren oder Node/NPM nötig.
* **Layout-Integrität & Spezifitätsschutz:** Attribut-Selektoren garantieren, dass Listing-Karten (`<a>` oder `<button>`) und PDP-Buttons identisch und immun gegen Theme-Overrides gestylt werden.
* **Keine statischen Inline-CSS-Properties:** Es werden ausschließlich CSS Custom Properties (`--custom-preorder-btn-*`) injiziert, keine festen CSS-Eigenschaften (`background-color`, etc.) direkt im HTML.
* **Ausfallsicher (Graceful Fallback):** Fehlen die Variablen, greift das SCSS nahtlos auf die konfigurierten Defaults zurück.

## Abgelehnte Alternativen
| Alternative | Grund der Ablehnung |
|---|---|
| **Statische Inline-Styles am Button (`style="background-color: ..."`):** | Verletzt saubere CSS-Trennung und verhindert sauberes Hover/Focus-Handling via CSS. |
| **Ausschließliches `:root` ohne Scoped Properties:** | Fällt Spezifitätskonflikten mit Third-Party-Themes und Bootstrap `.btn-primary` zum Opfer. |
| **SCSS-Kompilierung via Theme-Variablen:** | Erfordert serverseitiges `theme:compile` bei jeder Farbänderung; erzeugt hohe Serverlast und bricht das Prinzip der Autarkie. |
