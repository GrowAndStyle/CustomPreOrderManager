# ADR-011: Listing-Overlay Anker — box-standard.html.twig vs. badges.html.twig

**Status:** ENTSCHIEDEN  
**Datum:** 2026-09-19  
**Autor:** Agent (Review durch Nico Schultz)

---

## Kontext

Nach ADR-010 wurden Date-Banner und Scarcity-Badge auf Listing-Karten über `badges.html.twig` implementiert. Die Overlays lagen `position: absolute` im Card-Context und wurden mit einem Pixel-Hack positioniert:

```scss
top: calc(var(--bs-card-spacer-y, 1rem) + 200px);
transform: translateY(-100%);
```

Dieser Hack ist fragil, wartungsarm und abhängig von einer geschätzten Bildhöhe (200px). Ändert der Shopbetreiber die Kachelhöhe oder das Theme, brechen die Overlays.

Gleichzeitig teilte der Scarcity-Badge die CSS Custom Properties des Card/PDP-Designs (`--custom-preorder-card-accent`, `--custom-preorder-banner-opacity`). Eine separate Konfiguration war nicht möglich.

**Ziel:** Overlays strukturell korrekt im `<div class="product-image-wrapper">` verankern — ohne Pixel-Hack, ohne Config-Kopplung.

---

## Verifikation: Shopware 6.5.8.19 Block-Hierarchie

Vor der Implementierung wurde die offizielle Shopware-Quelle geprüft:

```
https://raw.githubusercontent.com/shopware/shopware/v6.5.8.19/
  src/Storefront/Resources/views/storefront/component/product/card/box-standard.html.twig
```

**Verifizierte Block-Hierarchie (6.5.x):**

```
component_product_box_image              ← umschließt <div class="product-image-wrapper">
  └── component_product_box_image_link   ← <a href="..."> Tag
        └── component_product_box_image_link_inner  ← Bildinhalt (img / placeholder)
              └── component_product_box_image_thumbnail
              └── component_product_box_image_placeholder
  └── component_product_box_wishlist_action
```

**Kritischer Fund:** `component_product_box_image_inner` existiert in 6.5.x **NICHT**. Dieser Block wurde erst in Shopware trunk / 6.7+ eingeführt. Jede Implementierung die ihn verwendet schlägt in 6.5.x silently fehl.

---

## Entscheidung

**Overlays werden in `box-standard.html.twig` über den Block `component_product_box_image` implementiert.**

`parent()` rendert den originalen `<div class="product-image-wrapper">` mit Bild und Wishlist. Dieser wird in einen neuen `<div class="preorder-image-overlay-root">` (mit `position: relative`) eingewickelt. Die Overlays folgen als absolute Kinder dieses Containers.

### DOM-Struktur (Resultat)

```html
<div class="preorder-image-overlay-root">         ← position: relative (Containing Block)
    <div class="product-image-wrapper">            ← parent() — unverändert
        <a href="..."><img ...></a>
        [wishlist]
    </div>
    <div class="product-preorder-date-banner">     ← position: absolute; top: 0
    <span class="preorder-scarcity-listing">       ← position: absolute; bottom: 0
</div>
```

### Kein Pixel-Hack mehr

| Vor ADR-011 | Nach ADR-011 |
|---|---|
| `top: calc(var(--bs-card-spacer-y, 1rem) + 200px)` | `bottom: 0` |
| `transform: translateY(-100%)` | — |
| Containing Block: Card-Body (`position: relative` per Bootstrap) | Containing Block: `preorder-image-overlay-root` (`position: relative` via SCSS) |

### badges.html.twig

Wird auf das absolute Minimum reduziert — kein Overlay, nur `{{ parent() }}`:

```twig
{% sw_extends '@Storefront/storefront/component/product/card/badges.html.twig' %}

{% block component_product_badges %}
    {{ parent() }}
{% endblock %}
```

### Listing-Scarcity-Badge: eigene Config-Parameter

Der Scarcity-Badge erhält drei dedizierte Plugin-Konfigurationsfelder (in der Karte „Listing-Banner"), vollständig entkoppelt von `--custom-preorder-card-accent`:

| Config-Key | Typ | Default | CSS Custom Property |
|---|---|---|---|
| `listingScarcityBackgroundColor` | colorpicker | `#b45309` | `--custom-preorder-listing-scarcity-bg` |
| `listingScarcityTextColor` | colorpicker | `#ffffff` | `--custom-preorder-listing-scarcity-color` |
| `listingScarcityOpacity` | int (0–100) | `65` | `--custom-preorder-listing-scarcity-opacity` |

### Zweizeiliges Scarcity-Badge

Neue Snippet-Keys unter `listing` (unabhängig von `badge.urgentFewLeft`, das für Card/PDP bleibt):

| Key | de-DE | en-GB |
|---|---|---|
| `custom-preorder.listing.scarcityPrefix` | `Fast vergriffen:` | `Almost gone:` |
| `custom-preorder.listing.scarcityCount` | `Nur noch %count% Stück verfügbar!` | `Only %count% left in stock!` |

---

## Begründung

1. **Korrekte Containing-Block-Semantik:** `position: relative` auf dem direkten Elternelement des Bilds ist CSS-Standard. Kein Raten von Geometrie.
2. **Wartungsfrei:** Ändert sich die Kachelhöhe oder das Theme, folgen die Overlays automatisch.
3. **6.5.x-verifiziert:** Block-Name gegen offizielle GitHub-Quelle `v6.5.8.19` geprüft.
4. **Config-Entkopplung:** Card-Farben (PDP) und Listing-Badge-Farben sind unabhängig konfigurierbar.
5. **UWG-Konformität:** Kein Pixel-Hack beeinflusst die inhaltliche Sachlichkeit der Darstellung (ADR-010 bleibt vollständig gültig).

---

## Abgelehnte Alternativen

| Alternative | Grund der Ablehnung |
|---|---|
| Pixel-Hack beibehalten | Fragil, wartungsarm, bricht bei Theme-Änderungen |
| `component_product_box_image_inner` nutzen | Existiert in 6.5.x nicht — silently fehlschlagend |
| `component_product_box_image_link_inner` nutzen | Ist inside `<a>` — Overlay überlagert Klick-Bereich |
| badges.html.twig mit Inline-SCSS Trick | Overlay liegt außerhalb des Bild-Containers — funktional falsch |
| Neue Config-Keys für Listing-Scarcity weglassen | Kopplung an Card-Accent — verhindert unabhängige Anpassung |
