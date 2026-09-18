# ADR-011: Listing-Overlay-Anchor — image.html.twig vs. badges.html.twig

**Status:** ENTSCHIEDEN
**Datum:** 2026-09-18
**Autor:** Agent (Review und Freigabe durch Nico Schultz)

## Kontext

Auf der Kategorieseite (Listing) werden zwei Overlay-Elemente über dem Produktbild gerendert:

1. **Date-Banner** (`product-preorder-date-banner`) — zeigt das voraussichtliche Lieferdatum oben auf dem Bild
2. **Scarcity-Badge** (`preorder-scarcity-listing`) — zeigt die Restmenge unten auf dem Bild

Beide Elemente sind aktuell (Stand ADR-010) in `badges.html.twig` implementiert. Dieses Template überschreibt den Twig-Block `component_product_badges`, der in der Core-Template-Hierarchie **vor** dem `product-image-wrapper` gerendert wird.

```
box-standard.html.twig
│
├── {% block component_product_box_badges %}   ← badges.html.twig greift hier ein
│   (liegt im card-body, AUSSERHALB product-image-wrapper)
│
└── {% block component_product_box_image %}
    └── <div class="product-image-wrapper">     ← position: relative ✅
        └── image.html.twig
```

Da die Overlays `position: absolute` benötigen, aber ihr CSS-Containing-Block nicht `product-image-wrapper` (mit `position: relative`) ist, wird die Positionierung aktuell über einen **berechneten Pixel-Offset** simuliert:

```scss
.preorder-scarcity-listing {
    top: calc(var(--bs-card-spacer-y, 1rem) + 200px);
    transform: translateY(-100%);
}
```

Diese Berechnung basiert auf einer angenommenen Image-Wrapper-Höhe von 200px. Sie funktioniert, ist aber:
- **Fragil:** Bricht bei anderen Bildgrößen, Theme-Anpassungen oder Padding-Änderungen
- **Nicht skalierbar:** Jede Layout-Änderung erfordert manuelle Pixel-Kalibrierung
- **Nicht wartbar:** Implizites Wissen über die exakte Höhe des image-wrapper ist nicht dokumentiert und kann sich mit Shopware-Updates ändern

### sw_include-Problem (historisch, aus ADR-010 resultierend)

ADR-010 sah ein `scarcity-badge.html.twig` als Shared Partial vor, das via `sw_include '@CustomPreOrderManager/...'` eingebunden werden sollte. Diese Technik schlägt **silently fehl**: Plugin-eigene Partials, die keinen Core-Template-Override darstellen, werden vom Shopware `TemplateFinder` im Kontext von `sw_include` nicht aufgelöst. Sie sind ausschließlich unter dem `@PluginName`-Namespace registriert, nicht in der `@Storefront` Theme-Chain. Das Partial rendert ohne Fehler nichts. Die Lösung war die Inline-Implementierung der Scarcity-Logik direkt in `badges.html.twig`.

> **Hinweis:** `delivery-information.html.twig` kann `sw_include '@CustomPreOrderManager/...'` erfolgreich verwenden, weil dieses Template selbst ein `@Storefront`-Override ist und der Twig-Kontext dadurch korrekt aufgelöst wird.

## Entscheidung

**VORGESCHLAGEN:** Wir verlagern Date-Banner und Scarcity-Badge von `badges.html.twig` in ein neues `image.html.twig`-Override, das `component_product_image`-Block innerhalb des `product-image-wrapper` (mit `position: relative`) überschreibt.

```
image.html.twig (NEU)
│
└── {% block component_product_image %}
    └── <div class="product-image-wrapper preorder-image-wrapper">
            {{ parent() }}    ← Produktbild
            ┌────────────────────────────────────────┐
            │ [Date-Banner — position: absolute top]  │  ← korrekt im Containing Block
            │           Produktbild                   │
            │ [Scarcity-Badge — position: abs bottom] │  ← korrekt im Containing Block
            └────────────────────────────────────────┘
```

`badges.html.twig` behält ausschließlich die originäre Funktion: Anzeige des `badge-preorder`-Badges (Text-Badge, kein Overlay).

## Begründung

| Kriterium | badges.html.twig (aktuell) | image.html.twig (vorgeschlagen) |
|---|---|---|
| **CSS-Containing-Block** | `card` oder `card-body` (kein `position: relative` gesetzt) | `product-image-wrapper` mit `position: relative` — Standard-Containing-Block für absolute Overlays |
| **Positionierbarkeit** | Pixel-Hack mit `calc(1rem + 200px)` — hardcodierte Bildgröße | `top: 0 / bottom: 0` — natürliche Ausrichtung, keine Pixel-Kalibrierung |
| **Stabilität bei Layout-Änderungen** | Fragil — jede Änderung an `card-spacer-y` oder Bildgröße bricht das Layout | Stabil — Containing Block ist immer der image-wrapper, unabhängig von umliegendem Layout |
| **Shopware-Template-Konvention** | badges-Block = semantisch für Produktbadges (Rabatt, Neu, Sale), nicht für Bild-Overlays | image-Block = semantisch korrekt für Overlays auf dem Produktbild |
| **Wartbarkeit** | Implizites Wissen über Bildgröße erforderlich | Selbsterklärend — Overlay liegt im Bild-Kontext |
| **Skill-Compliance** | Skill §1.E definiert image.html.twig als korrekten Extension-Point für Bild-Overlays | Konform zu Skill §1.E |

## Abgelehnte Alternativen

| Alternative | Grund der Ablehnung |
|---|---|
| **Status quo belassen** (badges.html.twig mit Pixel-Hack) | Technische Schuld — `calc(1rem + 200px)` bricht bei Bildgröße ≠ 200px. Funktioniert nur im aktuellen Theme-Layout. Nicht akzeptabel für Enterprise Tier-1 Standard. |
| **`position: relative` auf card-body per SCSS setzen** | Side-Effect-Risiko: Beeinflusst alle Produkt-Cards im Shop, nicht nur Pre-Order-Cards. Potenzielle Konflikte mit anderen Plugins oder dem Theme. |
| **JavaScript-Positionierung** | Verstößt gegen Skill §7 Kill-Kriterien (JS-DOM-Manipulation für Positionierung). Layout-Shift-Risiko. |
| **Beibehaltung von sw_include für Scarcity-Partial** | Technisch geprüft und als inkompatibel identifiziert (s. Kontext). Plugin-Partials ohne Core-Gegenstück sind in `sw_include` nicht auflösbar. |
