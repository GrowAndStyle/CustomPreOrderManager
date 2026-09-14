---
name: load-rules-preorder-storefront
description: ZWINGEND! Lade diese Datei für alle Storefront-Aufgaben im CustomPreOrderManager — Twig-Templates, SCSS, JS-Plugin, Config und Snippets.
---

# Storefront & UI Architecture (CustomPreOrderManager)

**STACK:** Shopware 6.5.x CE Storefront (Bootstrap 5), Twig 3, Vanilla JS Plugins, Mobile-First SCSS
**PARADIGMA:** Block-Level Inheritance, Zero Layout-Shift, Touch-Targets ≥44px, Grow & Style Premium Theme

> → Referenziert: `shopware_plugin` §2 (Plugin-Struktur), §5 (Storefront-Architektur)
> → Backend-Logik & Scarcity-Berechnung: `rules_preorder_backend` §7

---

## §1 Twig-Block-Erweiterungen

> **Jedes Template MUSS `{% sw_extends %}` nutzen.** Überschreiben ganzer Templates ohne Block-Inheritance ist streng verboten.

### A. Produktdetailseite — Kaufen-Button

**Pfad:** `src/Resources/views/storefront/page/product-detail/buy-widget-form.html.twig`

```twig
{% sw_extends '@Storefront/storefront/page/product-detail/buy-widget-form.html.twig' %}

{% block page_product_detail_buy_button %}
    {% set isPreOrder = page.product.customFields.custom_preorder_active|default(false) %}
    {% set hasStock = page.product.stock > 0 %}
    {% set inboundStock = page.product.customFields.custom_preorder_inbound_stock|default(0) %}
    {% set soldCount = page.product.customFields.custom_preorder_sold_count|default(0) %}
    {% set remainingQuota = inboundStock - soldCount %}

    {% if isPreOrder and not hasStock %}
        {% if inboundStock > 0 and remainingQuota <= 0 %}
            <div class="alert alert-info preorder-exhausted-notice">
                {{ "custom-preorder.badge.quotaExhausted"|trans }}
            </div>
        {% else %}
            <button class="btn btn-primary btn-block btn-buy btn-preorder"
                    title="{{ "custom-preorder.button.preOrder"|trans }}"
                    aria-label="{{ "custom-preorder.button.preOrder"|trans }}">
                <span class="btn-preorder-icon me-2">📅</span>
                {{ "custom-preorder.button.preOrder"|trans }}
            </button>
        {% endif %}
    {% else %}
        {{ parent() }}
    {% endif %}
{% endblock %}
```

### B. Lieferzeit & Erscheinungshinweis

**Pfad:** `src/Resources/views/storefront/component/delivery-information.html.twig`

```twig
{% sw_extends '@Storefront/storefront/component/delivery-information.html.twig' %}

{% block component_delivery_information %}
    {% set isPreOrder = page.product.customFields.custom_preorder_active|default(false) %}
    {% set hasStock = page.product.stock > 0 %}

    {% if isPreOrder and not hasStock %}
        {% set releaseText = page.product.customFields.custom_preorder_release_text %}
        {% set releaseDate = page.product.customFields.custom_preorder_release_date %}

        <div class="product-delivery-information preorder-delivery-box">
            <span class="delivery-status-indicator is-preorder"></span>
            <span class="preorder-delivery-text">
                {% if releaseText %}
                    {{ releaseText }}
                {% elseif releaseDate %}
                    {{ "custom-preorder.badge.availableFrom"|trans({'%date%': releaseDate|date('m/Y')}) }}
                {% else %}
                    {{ "custom-preorder.badge.preOrder"|trans }}
                {% endif %}
            </span>
        </div>
    {% else %}
        {{ parent() }}
    {% endif %}
{% endblock %}
```

### C. Kategorieseite & Suche — Badges

**Pfad:** `src/Resources/views/storefront/component/product/card/badges.html.twig`

```twig
{% sw_extends '@Storefront/storefront/component/product/card/badges.html.twig' %}

{% block component_product_badges %}
    {{ parent() }}

    {% set isPreOrder = product.customFields.custom_preorder_active|default(false) %}
    {% set hasStock = product.stock > 0 %}

    {% if isPreOrder and not hasStock and config('CustomPreOrderManager.config.enableListingBadge') %}
        <span class="badge badge-preorder">
            {{ "custom-preorder.badge.preOrder"|trans }}
        </span>
    {% endif %}
{% endblock %}
```

### D. Warenkorb & Checkout — Positionsbezeichnung

**Pfad:** `src/Resources/views/storefront/component/line-item/element/label.html.twig`

```twig
{% sw_extends '@Storefront/storefront/component/line-item/element/label.html.twig' %}

{% block component_line_item_label %}
    {{ parent() }}

    {% if lineItem.payload.isPreOrder %}
        <div class="line-item-preorder-badge">
            <span class="badge bg-light text-dark border">
                📅 {{ "custom-preorder.cart.lineItemNotice"|trans({'%releaseText%': lineItem.payload.preOrderReleaseText|default("custom-preorder.badge.preOrder"|trans)}) }}
            </span>
        </div>
    {% endif %}
{% endblock %}
```

### E. Produktbild Badge

**Pfad:** `src/Resources/views/storefront/component/product/card/image.html.twig`

```twig
{% sw_extends '@Storefront/storefront/component/product/card/image.html.twig' %}

{% block component_product_image %}
    <div class="product-image-wrapper preorder-image-wrapper">
        {{ parent() }}

        {% set isPreOrder = product.customFields.custom_preorder_active|default(false) %}
        {% set hasStock = product.stock > 0 %}

        {% if isPreOrder and not hasStock %}
            <div class="preorder-image-badge" aria-label="{{ "custom-preorder.badge.preOrderImage"|trans }}">
                {{ "custom-preorder.badge.preOrderImage"|trans }}
            </div>
        {% endif %}
    </div>
{% endblock %}
```

> **Hinweis:** `position: relative` auf `.preorder-image-wrapper` per SCSS (§3), NICHT per Inline-Style.

---

## §2 Scarcity-Twig (Config-gesteuert)

> Die Config-Keys `enableScarcityCounter` und `lowStockThreshold` aus §6 steuern die Anzeige.
> Scarcity-Berechnungsformel: → `rules_preorder_backend` §7.

**Pfad:** `src/Resources/views/storefront/page/product-detail/buy-widget.html.twig`

```twig
{% sw_extends '@Storefront/storefront/page/product-detail/buy-widget.html.twig' %}

{% block page_product_detail_buy_container %}
    {{ parent() }}

    {% set preorderActive = page.product.customFields.custom_preorder_active|default(false) %}
    {% set enableScarcity = config('CustomPreOrderManager.config.enableScarcityCounter') %}
    {% set threshold = config('CustomPreOrderManager.config.lowStockThreshold') %}

    {% if preorderActive and enableScarcity %}
        {% set inboundStock = page.product.customFields.custom_preorder_inbound_stock|default(0) %}
        {% set soldCount = page.product.customFields.custom_preorder_sold_count|default(0) %}
        {% set remaining = inboundStock - soldCount %}

        {% if remaining > 0 and remaining <= threshold %}
            <div class="preorder-scarcity-badge badge-preorder">
                {{ "preOrder.scarcity.fewLeft"|trans({'%count%': remaining}) }}
            </div>
        {% endif %}
    {% endif %}
{% endblock %}
```

---

## §3 SCSS (Grow & Style Premium Look)

**Pfad:** `src/Resources/app/storefront/src/scss/base.scss`

```scss
$breakpoint-mobile: 575px;

.badge-preorder {
    background-color: #1a1a2e;
    color: #ffffff;
    font-weight: 600;
    font-size: 0.75rem;
    padding: 0.35rem 0.65rem;
    border-radius: 4px;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}

.preorder-delivery-box {
    background: #f8f9fa;
    border-left: 4px solid #1a1a2e;
    padding: 10px 14px;
    margin: 12px 0;
    border-radius: 4px;
    display: flex;
    align-items: center;
    font-weight: 500;
    color: #333333;

    .is-preorder {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background-color: #ff9800;
        display: inline-block;
        margin-right: 8px;
    }
}

.btn-preorder {
    background-color: #1a1a2e;
    border-color: #1a1a2e;
    color: #ffffff;
    min-height: 48px; // Touch-Target Apple HIG / WCAG
    font-size: 1rem;
    font-weight: 700;
    transition: background-color 0.2s ease-in-out;

    &:hover, &:focus {
        background-color: #2b2b48;
        border-color: #2b2b48;
        color: #ffffff;
    }
}

.line-item-preorder-badge {
    margin-top: 4px;

    .badge {
        font-size: 0.75rem;
        font-weight: 500;
    }
}

.preorder-image-wrapper {
    position: relative;
}

.preorder-image-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background-color: #ff9800;
    color: #fff;
    padding: 0.25rem 0.5rem;
    font-size: 0.85rem;
    font-weight: bold;
    border-radius: 4px;
    z-index: 10;

    @media (max-width: $breakpoint-mobile) {
        top: 5px;
        left: 5px;
        font-size: 0.7rem;
        padding: 0.15rem 0.3rem;
    }
}
```

---

## §4 JS-Plugin & Registrierung

**Pfad:** `src/Resources/app/storefront/src/plugin/preorder-manager.plugin.js`

```javascript
import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import HttpClient from 'src/service/http-client.service';

export default class PreOrderManagerPlugin extends Plugin {
    init() {
        this.client = new HttpClient();
        this._registerEvents();
    }

    _registerEvents() {
        // UI Events für PreOrder
    }
}
```

**Pfad:** `src/Resources/app/storefront/src/main.js`

```javascript
import PreOrderManagerPlugin from './plugin/preorder-manager.plugin';

const PluginManager = window.PluginManager;
PluginManager.register('PreOrderManager', PreOrderManagerPlugin, '[data-preorder]');
```

---

## §5 Storefront-Snippets (de-DE + en-GB)

> **Shopware 6 lädt Storefront-Snippets NICHT automatisch.** SnippetFile-Klasse + services.xml-Tag erforderlich.

### SnippetFile-Klasse (PHP)

**Pfad:** `src/Resources/snippet/de_DE/SnippetFile_de_DE.php`

```php
<?php declare(strict_types=1);

namespace CustomPreOrderManager\Resources\Snippet\de_DE;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFile_de_DE implements SnippetFileInterface
{
    public function getName(): string { return 'storefront.de-DE'; }
    public function getPath(): string { return __DIR__ . '/storefront.de-DE.json'; }
    public function getIso(): string { return 'de-DE'; }
    public function getAuthor(): string { return 'Grow & Style'; }
    public function isBase(): bool { return false; }
}
```

> services.xml-Registrierung: → `rules_preorder_backend` §4.

### Snippet-JSON (de-DE)

**Pfad:** `src/Resources/snippet/de_DE/storefront.de-DE.json`

```json
{
    "custom-preorder": {
        "button": {
            "preOrder": "Jetzt vorbestellen",
            "notifyMe": "Benachrichtigen wenn verfügbar"
        },
        "badge": {
            "preOrder": "Vorbestellung",
            "preOrderImage": "Vorbestellbar",
            "availableFrom": "Lieferbar ab %date%",
            "urgentFewLeft": "Fast vergriffen: Nur noch %count% Stück!",
            "quotaExhausted": "Kontingent erschöpft"
        },
        "cart": {
            "lineItemNotice": "Vorbestellung — %releaseText%"
        },
        "scarcity": {
            "remaining": "Noch %remaining% von %total% verfügbar",
            "fewLeft": "Nur noch %remaining% Stück für %releaseText% sicherbar"
        }
    }
}
```

### Snippet-JSON (en-GB)

**Pfad:** `src/Resources/snippet/en_GB/storefront.en-GB.json`

```json
{
    "custom-preorder": {
        "button": {
            "preOrder": "Pre-order now",
            "notifyMe": "Notify me when available"
        },
        "badge": {
            "preOrder": "Pre-Order",
            "preOrderImage": "Pre-Order",
            "availableFrom": "Available from %date%",
            "urgentFewLeft": "Almost gone: Only %count% left!",
            "quotaExhausted": "Quota exhausted"
        },
        "cart": {
            "lineItemNotice": "Pre-order — %releaseText%"
        },
        "scarcity": {
            "remaining": "%remaining% of %total% available",
            "fewLeft": "Only %remaining% left for %releaseText%"
        }
    }
}
```

---

## §6 Plugin-Konfiguration (config.xml)

> **Alle steuerbaren Features per Plugin-Konfiguration schaltbar.** Kein Feature hartkodiert.

**Pfad:** `src/Resources/config/config.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/platform/trunk/src/Core/System/SystemConfig/Schema/config.xsd">
    <card>
        <title>Vorbestellung — Allgemein</title>
        <title lang="en-GB">Pre-Order — General</title>

        <input-field type="bool">
            <name>enableListingBadge</name>
            <label>Vorbestell-Badge in Kategorieseiten anzeigen</label>
            <label lang="en-GB">Show pre-order badge on listing pages</label>
            <defaultValue>true</defaultValue>
        </input-field>

        <input-field type="bool">
            <name>enableScarcityCounter</name>
            <label>Restmengen-Zähler auf der Produktdetailseite anzeigen</label>
            <label lang="en-GB">Show remaining quota counter on product detail page</label>
            <defaultValue>true</defaultValue>
        </input-field>

        <input-field type="int">
            <name>lowStockThreshold</name>
            <label>Schwellenwert für Dringlichkeits-Anzeige (Restmenge)</label>
            <label lang="en-GB">Threshold for urgency display (remaining quantity)</label>
            <defaultValue>5</defaultValue>
            <helpText>Ab dieser Restmenge wird das „Fast vergriffen"-Badge angezeigt.</helpText>
            <helpText lang="en-GB">The urgency badge is shown when remaining quantity drops to this value.</helpText>
        </input-field>
    </card>
</config>
```

### Konfigurations-Referenz

| Config-Key | Typ | Default | Verwendung |
|---|---|---|---|
| `CustomPreOrderManager.config.enableListingBadge` | `bool` | `true` | Badges auf Kategorieseiten/Suche (§1.C) |
| `CustomPreOrderManager.config.enableScarcityCounter` | `bool` | `true` | Restmengen-Zähler auf PDP (§2) |
| `CustomPreOrderManager.config.lowStockThreshold` | `int` | `5` | Schwellenwert für Dringlichkeits-UI (§2) |

> [!IMPORTANT]
> **Config-Key Prefix:** Immer `CustomPreOrderManager.config.` — Plugin-Klassenname. **NICHT** `CustomPreOrder.config.`!
> Twig: `config('CustomPreOrderManager.config.enableListingBadge')`
> PHP: `$this->systemConfigService->get('CustomPreOrderManager.config.enableListingBadge')`

---

## §7 Kill-Kriterien

| Verstoß | Konsequenz |
|---|---|
| Kaufen-Button mit `innerHTML` manipuliert | **VERBOTEN** — XSS; immer Twig-Blöcke |
| `innerHTML` mit Server-Daten | **VERBOTEN** — XSS; `textContent` oder `createElement` |
| Touch-Targets < 44px auf Mobile | **VERBOTEN** — Apple HIG / WCAG 2.5.8 |
| `{% sw_extends %}` weggelassen | Core-Templates überschrieben; bricht andere Plugins |
| Inline-Styles statt CSS-Klassen | Unwartbar, verletzt Theme-Isolation |
| Config-Key `CustomPreOrder.config.*` | Config wird nie gelesen — Feature permanent deaktiviert |
| Snippet-Key ohne JSON-Definition | Roher Key statt Text — zerstört Kunden-UX |
| JS-Plugin nicht per `PluginManager.register()` registriert | Plugin wird nie initialisiert |
| Stock-Guard (`stock > 0`) fehlt im Template | Vorbestell-UI trotz Lagerverfügbarkeit (→ backend §7) |
