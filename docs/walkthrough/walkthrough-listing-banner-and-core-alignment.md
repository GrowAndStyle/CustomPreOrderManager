# Walkthrough: Listing-Bild-Overlay Banner & Core-Alignment

**Datum:** 18.09.2026  
**Betroffene Komponenten:** Storefront Kategorie-Listing (`box-standard.html.twig`, `box-image.html.twig`, `action.html.twig`, `base.scss`)  
**Branch:** `feat/storefront-delivery-styling`  
**Letzter Commit:** `755aa3c` (`fix(storefront): Bild-Overlay Banner in Core-Block component_product_box_image verankert und Altlasten bereinigt`)  
**Build-Artefakt:** `dist/CustomPreOrderManager.zip`

---

## 1. Ziel & Fachliche Anforderung

Im Kategorie-Listing (z. B. auf dem Testsystem unter `http://192.168.2.222:8080/freizeit-elektro/`) soll für Vorbestellungsartikel (z. B. *Bluelab EC-Pen*) ein zweizeiliges Banner direkt über dem Produktbild platziert werden:
- **Zeile 1:** „Voraussichtlich ab“ (Snippet `custom-preorder.listing.availableFromPrefix`)
- **Zeile 2:** Release-Datum im Format `DD.MM.YYYY` (z. B. `30.09.2026`)
- **Optik:** Halbtransparenter Frosted-Glas Hintergrund (`rgba(15, 23, 42, 0.72)` mit `backdrop-filter: blur(6px)`), weiße Schrift, kein vorangestellter Puls-Dot, kein zusätzlicher Hinweistext im Listing.
- **Klickverhalten:** `pointer-events: none`, damit Klicks auf das Banner direkt zum Produktlink durchgereicht werden.
- **Grid-Symmetrie (Kritisch):** 100 % horizontale Fluchtlinie aller Kaufen-/Vorbestellen-Buttons im Grid. In `action.html.twig` darf keine Information über dem Button liegen, die die Kachelhöhe asymmetrisch verzieht.

---

## 2. Chronologie der Fehlversuche & Ursachen-Analyse

Damit nachfolgende Entwickler und Agenten nicht in dieselben Fallen tappen:

### Fehler 1: Infobox in `action.html.twig`
- **Was wurde gemacht:** Eine Box `.product-card-preorder-info` wurde direkt über dem Button in `action.html.twig` gerendert.
- **Warum es falsch war:** Die Vorbestellkachel wurde dadurch höher als benachbarte Standardartikel. Die Buttons lagen nicht mehr auf einer horizontalen Linie, das Kategorie-Grid war asymmetrisch verzerrt.

### Fehler 2: Nicht existierendes Template `image.html.twig`
- **Was wurde gemacht:** Es wurde versucht, ein Template `src/Resources/views/storefront/component/product/card/image.html.twig` anzulegen.
- **Warum es falsch war:** Im Shopware 6.5.x CE Core existiert unter `component/product/card/` keine Datei `image.html.twig`. Shopware nutzt `box-standard.html.twig` als Basis für alle Produktkarten.

### Fehler 3: Veralteter Block `component_product_box_image_link`
- **Was wurde gemacht:** In `box-standard.html.twig` wurde `{% block component_product_box_image_link %}` erweitert.
- **Warum es fehlschlug:** Im Core von Shopware 6.5.x existiert dieser Block nicht mehr (im Zuge des `stretched-link` Barrierefreiheits-Refactorings deprecated/entfernt). Twig ignoriert unbekannte Kind-Blöcke bei `{% sw_extends %}` stillschweigend. Auf dem Testserver wurde das Banner schlicht nicht ins HTML gerendert.

### Fehler 4: Ungereinigtes SCSS und doppelte Badges
- **Was übersehen wurde:** 
  - Die CSS-Regeln für `.product-card-preorder-info` und `.preorder-image-badge` blieben nach der Entfernung der HTML-Elemente als toter Code in `base.scss`.
  - In `badges.html.twig` wurde zusätzlich ein separates Vorbestell-Badge oben links gerendert, was mit dem Bild-Overlay kollidierte.
  - In der Skill-Datei `.agent/skills/rules_preorder_storefront/SKILL.md` wurde unautorisiert editiert, anstatt sie unberührt zu lassen (Manifest-Verstoß).

---

## 3. Aktuelle technische Lösung (Stand: Commit `755aa3c`)

### A. Template: `src/Resources/views/storefront/component/product/card/box-standard.html.twig`
Erweitert den universellen, offiziellen Shopware 6.5 CE Core-Block `component_product_box_image`:
```twig
{% sw_extends '@Storefront/storefront/component/product/card/box-standard.html.twig' %}

{% block component_product_box_image %}
    <div class="position-relative">
        {{ parent() }}

        {% set currentProduct = product|default(page.product) %}
        {% set customFields = currentProduct.translated.customFields|default(currentProduct.customFields)|default({}) %}
        {% set isPreOrder = customFields.custom_preorder_active|default(false) or (customFields.custom_preorder_release_date is defined and customFields.custom_preorder_release_date is not empty) %}
        {% set hasStock = currentProduct.stock > 0 %}

        {% if isPreOrder and not hasStock and config('CustomPreOrderManager.config.enableListingBadge')|default(true) %}
            {% set releaseDate = customFields.custom_preorder_release_date %}
            {% set releaseText = customFields.custom_preorder_release_text %}

            <div class="product-image-preorder-banner">
                <span class="preorder-banner-prefix">
                    {{ "custom-preorder.listing.availableFromPrefix"|trans }}
                </span>
                <span class="preorder-banner-date">
                    {% if releaseDate %}
                        {{ releaseDate|date('d.m.Y') }}
                    {% elseif releaseText %}
                        {{ releaseText }}
                    {% else %}
                        {{ "custom-preorder.badge.preOrder"|trans }}
                    {% endif %}
                </span>
            </div>
        {% endif %}
    </div>
{% endblock %}
```
*Warum `position-relative`:* `parent()` rendert das Core-Bild (`.product-image-wrapper`). Der umgebende Container spannt exakt die Bildhöhe auf, sodass `.product-image-preorder-banner` mit `position: absolute; bottom: 0; left: 0; right: 0;` bündig an der Unterkante des Bildbereichs klebt.

### B. Template: `src/Resources/views/storefront/component/product/card/box-image.html.twig`
Falls in der Erlebniswelt der Kategorie `/freizeit-elektro/` das Box-Layout **„Großes Bild“ (`box-image`)** hinterlegt ist, stellt diese Datei sicher, dass die gleiche Logik greift:
```twig
{% sw_extends '@Storefront/storefront/component/product/card/box-image.html.twig' %}

{% block component_product_box_image %}
    <div class="position-relative">
        {{ parent() }}
        {# identische Banner-Logik wie in box-standard #}
    </div>
{% endblock %}
```

### C. Bereinigung: `badges.html.twig` gelöscht
- Die Datei `src/Resources/views/storefront/component/product/card/badges.html.twig` wurde via `git rm` vollständig entfernt. Es gibt kein doppeltes Badge mehr in der Ecke.

### D. SCSS: `src/Resources/app/storefront/src/scss/base.scss`
- Toter Code (`.product-card-preorder-info`, `.preorder-image-badge`) restlos entfernt.
- Definition des Banners:
```scss
.product-image-preorder-banner {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 0.35rem 0.5rem;
    background: rgba(15, 23, 42, 0.72);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    color: #ffffff;
    text-align: center;
    pointer-events: none;
    z-index: 5;
    transition: background-color 0.2s ease;

    .preorder-banner-prefix {
        font-size: 0.75rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        line-height: 1.2;
    }

    .preorder-banner-date {
        font-size: 0.8125rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        line-height: 1.25;
        color: #ffffff;
    }

    @media (max-width: $breakpoint-mobile) {
        padding: 0.25rem 0.35rem;

        .preorder-banner-prefix {
            font-size: 0.75rem;
        }

        .preorder-banner-date {
            font-size: 0.75rem;
        }
    }
}
```

### E. Bereinigung: Skill-Dateien
- `.agent/skills/rules_preorder_storefront/SKILL.md` ist wieder im unberührten Originalzustand.

---

## 4. Test- und Deployment-Instruktionen

Auf der Teststation (`192.168.2.222:8080`):
1. **ZIP hochladen / entpacken:**
   `dist/CustomPreOrderManager.zip` nach `custom/plugins/CustomPreOrderManager` entpacken.
2. **Shopware-Befehle ausführen:**
   ```bash
   bin/console plugin:refresh
   bin/console plugin:update CustomPreOrderManager
   bin/console theme:compile
   bin/console cache:clear
   ```
3. **Visuelle Prüfung:**
   - Aufruf von `http://192.168.2.222:8080/freizeit-elektro/`
   - Kachel *Bluelab EC-Pen*:
     - Zweizeiliges Banner („Voraussichtlich ab“ / `30.09.2026`) sitzt unten auf dem Bild/Platzhalter.
     - Der Button „Jetzt vorbestellen“ fluchtet 100 % symmetrisch mit den Nachbar-Buttons („In den Warenkorb“).
