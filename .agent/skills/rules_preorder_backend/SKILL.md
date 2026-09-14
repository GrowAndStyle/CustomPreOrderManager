---
name: load-rules-preorder-backend
description: ZWINGEND! Lade diese Datei für alle PHP-Backend-Aufgaben im CustomPreOrderManager — DAL, Migration, CartCollector, Subscriber, Event, Admin-Modul, Security und Testing.
---

# Backend & Plugin Architecture (CustomPreOrderManager)

**STACK:** PHP 8.1+, Shopware 6.5.x CE (kompatibel zu 6.6 & 6.7), Symfony 6, Doctrine DBAL, Vue.js 3 Admin SDK
**PARADIGMA:** DAL-First, Atomic DBAL Counters, Non-Destructive Extension, Single Source of Truth

> → Referenziert: `shopware_plugin` §2 (Plugin-Struktur & Lifecycle), §6 (Admin SDK), §9 (Security)

---

## §1 Verzeichnisstruktur & Scaffolding

> **Der Agent MUSS die vollständige Verzeichnisstruktur VOR dem ersten Code-Commit anlegen oder verifizieren.** Neue Dateien die NICHT in dieser Struktur sind → ADR-Pflicht.

```
CustomPreOrderManager/
├── src/
│   ├── CustomPreOrderManager.php              # Plugin-Hauptklasse (5 Lifecycle-Methoden)
│   ├── Core/Checkout/
│   │   ├── Cart/PreOrderCartCollector.php      # LineItem Payload Enrichment
│   │   ├── Subscriber/OrderPlacedSubscriber.php # Auto-Tagging + DBAL Counter
│   │   └── Event/PreOrderPlacedEvent.php       # Flow Builder Business Event
│   ├── DependencyInjection/
│   │   └── CustomPreOrderManagerExtension.php  # Rate-Limiter Config
│   ├── Migration/
│   │   └── Migration1726200000AddPreOrderCustomFields.php
│   └── Resources/
│       ├── config/
│       │   ├── services.xml                    # Alle Services + Snippet-Tags
│       │   └── config.xml                      # Plugin-Konfiguration (→ storefront §6)
│       ├── snippet/
│       │   ├── de_DE/{SnippetFile_de_DE.php, storefront.de-DE.json}
│       │   └── en_GB/{SnippetFile_en_GB.php, storefront.en-GB.json}
│       ├── views/storefront/                   # Twig-Templates (→ storefront §1-§2)
│       └── app/
│           ├── storefront/src/                 # JS-Plugin + SCSS (→ storefront §3-§4)
│           └── administration/src/             # Admin-Modul (§10)
│               ├── main.js
│               └── module/custom-preorder-manager/
│                   ├── index.js
│                   └── page/custom-preorder-manager-list/
│                       ├── index.js
│                       ├── custom-preorder-manager-list.html.twig
│                       └── custom-preorder-manager-list.scss
├── tests/Integration/
│   ├── TestBootstrap.php
│   ├── Cart/PreOrderCartCollectorTest.php
│   └── Subscriber/OrderPlacedSubscriberTest.php
└── composer.json
```

---

## §2 CustomField-Set (Migration, Registry & DAL-Patterns)

> **CustomFields statt eigener Tabelle:** Non-Destructive, kein Schema-Lock auf `product`, automatische Varianten-Vererbung.

### Migration (`Migration1726200000AddPreOrderCustomFields`)

```php
<?php declare(strict_types=1);

namespace CustomPreOrderManager\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class Migration1726200000AddPreOrderCustomFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1726200000;
    }

    public function update(Connection $connection): void
    {
        $setId = $connection->fetchOne(
            "SELECT `id` FROM `custom_field_set` WHERE `name` = 'custom_preorder_set'"
        );

        if (!$setId) {
            $setId = Uuid::randomBytes();
            $connection->insert('custom_field_set', [
                'id' => $setId,
                'name' => 'custom_preorder_set',
                'config' => json_encode([
                    'label' => ['de-DE' => 'Vorbestellung (Pre-Order)', 'en-GB' => 'Pre-Order'],
                    'translated' => true,
                ], JSON_THROW_ON_ERROR),
                'active' => 1,
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
            ]);

            $connection->insert('custom_field_set_relation', [
                'id' => Uuid::randomBytes(),
                'custom_field_set_id' => $setId,
                'entity_name' => 'product',
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
            ]);
        }

        $fields = [
            ['name' => 'custom_preorder_active', 'type' => CustomFieldTypes::BOOL, 'config' => [
                'label' => ['de-DE' => 'Vorbestellung aktivieren', 'en-GB' => 'Enable Pre-Order'],
                'componentName' => 'sw-field', 'type' => 'switch', 'customFieldPosition' => 1,
            ]],
            ['name' => 'custom_preorder_release_date', 'type' => CustomFieldTypes::DATETIME, 'config' => [
                'label' => ['de-DE' => 'Erscheinungsdatum', 'en-GB' => 'Release Date'],
                'componentName' => 'sw-datepicker', 'dateType' => 'date', 'customFieldPosition' => 2,
            ]],
            ['name' => 'custom_preorder_release_text', 'type' => CustomFieldTypes::TEXT, 'config' => [
                'label' => ['de-DE' => 'Hinweistext Frontend', 'en-GB' => 'Frontend Display Text'],
                'componentName' => 'sw-field', 'type' => 'text',
                'placeholder' => ['de-DE' => 'z. B. Lieferbar ab Mitte Oktober 2026', 'en-GB' => 'e.g. Expected October 2026'],
                'customFieldPosition' => 3,
            ]],
            ['name' => 'custom_preorder_inbound_stock', 'type' => CustomFieldTypes::INT, 'config' => [
                'label' => ['de-DE' => 'Zulaufmenge (Lieferanten-Kontingent)', 'en-GB' => 'Inbound Quota'],
                'componentName' => 'sw-field', 'type' => 'number', 'numberType' => 'int', 'min' => 0,
                'customFieldPosition' => 4,
            ]],
            ['name' => 'custom_preorder_sold_count', 'type' => CustomFieldTypes::INT, 'config' => [
                'label' => ['de-DE' => 'Bereits vorbestellte Menge', 'en-GB' => 'Sold Pre-Order Quantity'],
                'componentName' => 'sw-field', 'type' => 'number', 'numberType' => 'int', 'min' => 0,
                'disabled' => true, 'customFieldPosition' => 5,
            ]],
        ];

        foreach ($fields as $field) {
            $exists = $connection->fetchOne(
                "SELECT `id` FROM `custom_field` WHERE `name` = :name",
                ['name' => $field['name']]
            );
            if (!$exists) {
                $connection->insert('custom_field', [
                    'id' => Uuid::randomBytes(),
                    'name' => $field['name'],
                    'type' => $field['type'],
                    'config' => json_encode($field['config'], JSON_THROW_ON_ERROR),
                    'active' => 1,
                    'set_id' => $setId,
                    'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
                ]);
            }
        }
    }

    public function updateDestructive(Connection $connection): void {}
}
```

### CustomField-Zugriff im PHP-Code

```php
// ✅ KORREKT: Null-sichere Getter
$isPreOrder = $product->getCustomFieldsValue('custom_preorder_active') ?? false;
$releaseDate = $product->getCustomFieldsValue('custom_preorder_release_date');
$inboundStock = $product->getCustomFieldsValue('custom_preorder_inbound_stock') ?? 0;
$soldCount = $product->getCustomFieldsValue('custom_preorder_sold_count') ?? 0;

// ❌ VERBOTEN: Direkter Array-Zugriff → NPE wenn custom_fields NULL
$isPreOrder = $product->getCustomFields()['custom_preorder_active'];
```

> Für Twig-Zugriff mit `|default()`: → Siehe `rules_preorder_storefront` §1.

### DAL Criteria-Patterns

```php
// Aktive Vorbestellungen filtern
$criteria = new Criteria();
$criteria->addFilter(new EqualsFilter('customFields.custom_preorder_active', true));

// ⚠️ Cross-Field-Filter (inbound > sold) existiert NICHT in Shopware DAL.
// Restmengen-Berechnung erfolgt in PHP (§7).

// ✅ KORREKT: NandFilter als NOT-Ersatz (NotEqualsFilter existiert NICHT in 6.5.x!)
$criteria->addFilter(new NandFilter([
    new EqualsFilter('customFields.custom_preorder_active', true),
]));
```

> → Siehe auch: `shopware_plugin` §2.E für die vollständige DAL-Filter-Referenz.

---

## §3 DBAL-Operationen (Live-Version & Atomic Counter)

> **KRITISCH:** Die `product`-Tabelle ist versioniert (Composite PK: `id` + `version_id`). Jede DBAL-Operation MUSS `version_id` im WHERE enthalten.

### Pflicht-Pattern: version_id

```php
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

// ✅ KORREKT: version_id im WHERE
$connection->executeStatement(
    'UPDATE `product` SET ... WHERE `id` = :id AND `version_id` = :versionId',
    [
        'id' => Uuid::fromHexToBytes($productId),
        'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
    ]
);

// ❌ VERBOTEN: Ohne version_id — trifft ALLE Versionen (Live + Draft)!
$connection->executeStatement('UPDATE `product` SET ... WHERE `id` = :id', [...]);
```

### Atomares Counter-Inkrement (Race-Condition-sicher)

> **Zähler werden IMMER per atomarem DBAL-Statement erhöht.** DAL-Read-Modify-Write erzeugt Race-Conditions bei parallelen Käufen.

```php
// ✅ KORREKT: Atomares JSON-Inkrement
$connection->executeStatement(
    'UPDATE `product`
     SET `custom_fields` = JSON_SET(
         COALESCE(`custom_fields`, "{}"),
         "$.custom_preorder_sold_count",
         COALESCE(JSON_UNQUOTE(JSON_EXTRACT(`custom_fields`, "$.custom_preorder_sold_count")), 0) + :qty
     )
     WHERE `id` = :id AND `version_id` = :versionId',
    [
        'id' => Uuid::fromHexToBytes($productId),
        'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        'qty' => $quantity,
    ]
);

// ❌ VERBOTEN: DAL-Read-Modify-Write (Race Condition bei Hype-Drops)
$current = $product->getCustomFieldsValue('custom_preorder_sold_count') ?? 0;
$productRepository->update([['id' => $id, 'customFields' => ['custom_preorder_sold_count' => $current + 1]]], $context);
```

---

## §4 Service-Registrierung (services.xml)

**Pfad:** `src/Resources/config/services.xml`

```xml
<?xml version="1.0" ?>
<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services https://symfony.com/schema/dic/services/services-1.0.xsd">
    <services>
        <!-- Cart-Collector: Reichert LineItems mit Vorbestell-Payload an -->
        <service id="CustomPreOrderManager\Core\Checkout\Cart\PreOrderCartCollector">
            <tag name="shopware.cart.collector" priority="4100" />
        </service>

        <!-- Order-Placed Subscriber: Auto-Tagging, DBAL-Counter & Event-Dispatch -->
        <service id="CustomPreOrderManager\Core\Checkout\Subscriber\OrderPlacedSubscriber">
            <argument type="service" id="order.repository" />
            <argument type="service" id="tag.repository" />
            <argument type="service" id="Doctrine\DBAL\Connection" />
            <argument type="service" id="event_dispatcher" />
            <argument type="service" id="logger" />
            <tag name="kernel.event_subscriber" />
        </service>

        <!-- Storefront-Snippets -->
        <service id="CustomPreOrderManager\Resources\Snippet\de_DE\SnippetFile_de_DE">
            <tag name="shopware.snippet.file" />
        </service>
        <service id="CustomPreOrderManager\Resources\Snippet\en_GB\SnippetFile_en_GB">
            <tag name="shopware.snippet.file" />
        </service>
    </services>
</container>
```

> [!CAUTION]
> **Cart-Collector Priority `4100`:** Liegt NACH `ProductCartProcessor` (5000) und `ProductCollector` (4500), aber VOR der Preis-Berechnung. `customFields` sind im Payload bereits verfügbar.

---

## §5 Cart-Collector Pipeline

> **LineItem Payload Enrichment:** Vorbestell-Metadaten direkt an der Position verankern — erscheint automatisch in allen Belegen und Mails.

**Pfad:** `src/Core/Checkout/Cart/PreOrderCartCollector.php`

```php
<?php declare(strict_types=1);

namespace CustomPreOrderManager\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PreOrderCartCollector implements CartDataCollectorInterface
{
    public function collect(
        CartDataCollection $data,
        Cart $original,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        foreach ($original->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE) as $lineItem) {
            $payload = $lineItem->getPayload();
            $customFields = $payload['customFields'] ?? [];

            if (!($customFields['custom_preorder_active'] ?? false)) {
                continue;
            }

            $releaseText = $customFields['custom_preorder_release_text'] ?? null;
            if (empty($releaseText) && !empty($customFields['custom_preorder_release_date'])) {
                try {
                    $date = new \DateTimeImmutable($customFields['custom_preorder_release_date']);
                    $releaseText = 'Lieferbar ab ' . $date->format('m/Y');
                } catch (\Throwable) {
                    $releaseText = 'Vorbestellung';
                }
            }

            $lineItem->setPayloadValue('isPreOrder', true);
            $lineItem->setPayloadValue('preOrderReleaseText', $releaseText);
        }
    }
}
```

---

## §6 Order-Placed & Auto-Tagging Subscriber

> **Jede Bestellung mit mindestens einem Vorbestell-Artikel erhält automatisch den Tag `Vorbestellung`.**

**Pfad:** `src/Core/Checkout/Subscriber/OrderPlacedSubscriber.php`

```php
<?php declare(strict_types=1);

namespace CustomPreOrderManager\Core\Checkout\Subscriber;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use CustomPreOrderManager\Core\Checkout\Event\PreOrderPlacedEvent;

class OrderPlacedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $tagRepository,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [CheckoutOrderPlacedEvent::class => 'onOrderPlaced'];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $order = $event->getOrder();
        $lineItems = $order->getLineItems();
        if (!$lineItems) {
            return;
        }

        $preOrderItemCount = 0;
        $context = $event->getContext();

        foreach ($lineItems as $item) {
            if (!($item->getPayload()['isPreOrder'] ?? false)) {
                continue;
            }

            $preOrderItemCount++;
            $productId = $item->getReferencedId();

            if ($productId) {
                // Atomares Inkrement (§3)
                $this->connection->executeStatement(
                    'UPDATE `product`
                     SET `custom_fields` = JSON_SET(
                         COALESCE(`custom_fields`, "{}"),
                         "$.custom_preorder_sold_count",
                         COALESCE(JSON_UNQUOTE(JSON_EXTRACT(`custom_fields`, "$.custom_preorder_sold_count")), 0) + :qty
                     )
                     WHERE `id` = :id AND `version_id` = :versionId',
                    [
                        'id' => Uuid::fromHexToBytes($productId),
                        'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                        'qty' => $item->getQuantity(),
                    ]
                );
            }
        }

        if ($preOrderItemCount === 0) {
            return;
        }

        // Tag idempotent finden oder erstellen
        $tagCriteria = (new Criteria())->addFilter(new EqualsFilter('name', 'Vorbestellung'));
        $tagId = $this->tagRepository->searchIds($tagCriteria, $context)->firstId();

        if (!$tagId) {
            $tagId = Uuid::randomHex();
            $this->tagRepository->create([['id' => $tagId, 'name' => 'Vorbestellung']], $context);
        }

        $this->orderRepository->update([
            ['id' => $order->getId(), 'tags' => [['id' => $tagId]]],
        ], $context);

        $this->logger->info('PreOrder: Tag assigned to order', [
            'orderId' => $order->getId(),
            'preOrderItemCount' => $preOrderItemCount,
        ]);

        // Business Event für Flow Builder
        $this->dispatcher->dispatch(
            new PreOrderPlacedEvent($order, $context, $preOrderItemCount),
            PreOrderPlacedEvent::EVENT_NAME
        );
    }
}
```

---

## §7 Scarcity Engine & Stock-Lifecycle

### Scarcity-Berechnung

$$\text{Restmenge} = \text{custom\_preorder\_inbound\_stock} - \text{custom\_preorder\_sold\_count}$$

| Zustand | Bedingung | Storefront |
|---|---|---|
| **Normal** | `Restmenge > lowStockThreshold` | Badge: 📅 *Vorbestellung* |
| **Dringlich** | `1 ≤ Restmenge ≤ lowStockThreshold` | 🔥 *Fast vergriffen: Nur noch X Stück!* |
| **Erschöpft** | `Restmenge ≤ 0` | *Aktuell nicht vorbestellbar* — Button deaktiviert, Hinweistext |

> → Twig-Implementierung: `rules_preorder_storefront` §1A (Buy-Button) + §2 (Scarcity-Badge).
> Config-Keys `enableScarcityCounter` / `lowStockThreshold`: → `rules_preorder_storefront` §6.

### Stock-Lifecycle — HARTE REGEL

> **Physischer Lagerbestand sticht IMMER den Vorbestell-Modus.**

Wareneingang (`stock > 0`) → Vorbestell-UI automatisch deaktiviert → normaler Kaufen-Button.

```twig
{# ✅ Pflicht-Guard in JEDEM Vorbestell-Template #}
{% set isPreOrder = page.product.customFields.custom_preorder_active|default(false) %}
{% set hasStock = page.product.stock > 0 %}

{% if isPreOrder and not hasStock %}
    {# Vorbestell-UI #}
{% else %}
    {{ parent() }}
{% endif %}
```

Kein manuelles Umschalten nötig. Der Guard ist in jedem Storefront-Template (→ `rules_preorder_storefront` §1).

---

## §8 Flow Builder Integration (PreOrderPlacedEvent)

**Pfad:** `src/Core/Checkout/Event/PreOrderPlacedEvent.php`

```php
<?php declare(strict_types=1);

namespace CustomPreOrderManager\Core\Checkout\Event;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Symfony\Contracts\EventDispatcher\Event;

class PreOrderPlacedEvent extends Event implements FlowEventAware, ScalarValuesAware
{
    public const EVENT_NAME = 'preorder.order.placed';

    public function __construct(
        private readonly OrderEntity $order,
        private readonly Context $context,
        private readonly int $preOrderItemCount
    ) {}

    public function getName(): string { return self::EVENT_NAME; }
    public function getOrder(): OrderEntity { return $this->order; }
    public function getContext(): Context { return $this->context; }
    public function getPreOrderItemCount(): int { return $this->preOrderItemCount; }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('order', new EntityType(OrderDefinition::class))
            ->add('preOrderItemCount', new ScalarValueType(ScalarValueType::TYPE_INT));
    }

    public function getValues(): array
    {
        return ['preOrderItemCount' => $this->preOrderItemCount];
    }
}
```

**Flow Builder Verwendung:** Event `preorder.order.placed` → Bedingung `preOrderItemCount >= 1` → Mail-Template `preorder_vip_order_placed`.

---

## §9 API-Contract & Security

### Error-Codes

```json
{ "error_code": "GAS-XXXX", "message": "..." }
```

| Code | Bedeutung |
|---|---|
| `GAS-4201` | Tag `Vorbestellung` nicht im System |
| `GAS-4202` | Produkt kein Vorbesteller (`custom_preorder_active !== true`) |
| `GAS-4203` | Kontingent erschöpft (`sold_count >= inbound_stock`) |
| `GAS-4204` | Produkt bereits ab Lager (`stock > 0`) |
| `GAS-5001` | DBAL Counter-Inkrement fehlgeschlagen |
| `GAS-5002` | PreOrderPlacedEvent Dispatch fehlgeschlagen |

### Rate-Limiter (PrependExtensionInterface)

```php
<?php declare(strict_types=1);

namespace CustomPreOrderManager\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

class CustomPreOrderManagerExtension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('framework', [
            'rate_limiter' => [
                'preorder_notify' => ['policy' => 'fixed_window', 'limit' => 5, 'interval' => '1 minute'],
            ],
        ]);
    }
}
```

### Security-Regeln

1. **CSRF:** Alle Storefront-POST-Routen nutzen `{{ sw_csrf('route') }}`.
2. **XSS:** `release_text` durchläuft `strip_tags()` vor dem Persistieren:

```php
// ✅ KORREKT: Sanitized in Payload
$sanitized = strip_tags($product->getCustomFields()['custom_preorder_release_text'] ?? '');
$lineItem->setPayloadValue('preOrderReleaseText', $sanitized);

// ❌ VERBOTEN: Unsanitized
$lineItem->setPayloadValue('preOrderReleaseText', $product->getCustomFields()['custom_preorder_release_text']);
```

3. **IDOR:** Account-Abfragen filtern strikt auf `SalesChannelContext->getCustomer()->getId()`.

> → Siehe auch: `shopware_plugin` §9 (Security), `security_hardening` für Input-Sanitization.

---

## §10 Admin-Modul (Produkt-Tab, Dashboard, Snippets)

> → Referenziert: `shopware_plugin` §6 (Admin SDK), §6.B2 (httpClient), §6.C (Listing-Mixin)

### Produkt-CustomField-Tab

Override von `sw-product-detail` mit eigenem Tab „Vorbestellung":

```twig
<sw-card :title="$tc('custom-preorder-manager.productDetail.cardTitle')">
    <sw-switch-field v-model="product.customFields.custom_preorder_active"
        :label="$tc('custom-preorder-manager.productDetail.activeLabel')"
        :helpText="$tc('custom-preorder-manager.productDetail.activeHelp')">
    </sw-switch-field>

    <sw-datepicker dateType="date"
        v-model="product.customFields.custom_preorder_release_date"
        :label="$tc('custom-preorder-manager.productDetail.releaseDateLabel')"
        :helpText="$tc('custom-preorder-manager.productDetail.releaseDateHelp')">
    </sw-datepicker>

    <sw-text-field v-model="product.customFields.custom_preorder_release_text"
        :label="$tc('custom-preorder-manager.productDetail.releaseTextLabel')"
        :helpText="$tc('custom-preorder-manager.productDetail.releaseTextHelp')">
    </sw-text-field>

    <sw-number-field v-model="product.customFields.custom_preorder_inbound_stock"
        :label="$tc('custom-preorder-manager.productDetail.inboundStockLabel')"
        :helpText="$tc('custom-preorder-manager.productDetail.inboundStockHelp')">
    </sw-number-field>

    <sw-number-field v-model="product.customFields.custom_preorder_sold_count" disabled
        :label="$tc('custom-preorder-manager.productDetail.soldCountLabel')"
        :helpText="$tc('custom-preorder-manager.productDetail.soldCountHelp')">
    </sw-number-field>

    <div class="custom-preorder-manager-remaining-stock">
        <strong>{{ $tc('custom-preorder-manager.productDetail.remainingStockLabel') }}:</strong>
        {{ (product.customFields.custom_preorder_inbound_stock || 0) - (product.customFields.custom_preorder_sold_count || 0) }}
    </div>
</sw-card>
```

### Vorbestellungs-Dashboard (Listing)

```javascript
import template from './custom-preorder-manager-list.html.twig';
import './custom-preorder-manager-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('custom-preorder-manager-list', {
    template,
    inject: ['repositoryFactory'],
    mixins: [Mixin.getByName('listing')],

    data() {
        return { items: null, isLoading: false, total: 0 };
    },

    computed: {
        orderRepository() {
            return this.repositoryFactory.create('order');
        },
        defaultCriteria() {
            const criteria = new Criteria();
            criteria.setPage(this.page);
            criteria.setLimit(this.limit);
            criteria.addFilter(Criteria.equals('tags.name', 'Vorbestellung'));
            criteria.addAssociation('orderCustomer.customer');
            criteria.addAssociation('lineItems');
            return criteria;
        },
        columns() {
            return [
                { property: 'orderNumber', label: this.$tc('custom-preorder-manager.list.columnOrderNumber'), rawData: true },
                { property: 'orderCustomer.firstName', label: this.$tc('custom-preorder-manager.list.columnCustomer'), rawData: true },
                { property: 'orderDateTime', label: this.$tc('custom-preorder-manager.list.columnDate'), rawData: true },
                { property: 'stateMachineState.name', label: this.$tc('custom-preorder-manager.list.columnStatus'), rawData: true },
                { property: 'amountTotal', label: this.$tc('custom-preorder-manager.list.columnAmount'), rawData: true, align: 'right' },
            ];
        },
    },

    methods: {
        getList() {
            this.isLoading = true;
            this.orderRepository.search(this.defaultCriteria, Shopware.Context.api).then((result) => {
                this.total = result.total;
                this.items = result;
                this.isLoading = false;
            });
        },
    },
});
```

### Admin-Fallstricke

```javascript
// ✅ httpClient-Zugriff (falls benötigt)
const httpClient = Shopware.Application.getContainer('init').httpClient;
const token = Shopware.Service('loginService').getToken();

// ❌ VERBOTEN: inject: ['httpClient'] → undefined zur Laufzeit
// ❌ VERBOTEN: loginService.getHeader() → existiert nicht
// ❌ VERBOTEN: new Criteria(this.page, this.limit) → Endlosschleifen-Gefahr
```

> **Warteliste:** Dieses Plugin implementiert KEINE Warteliste. Bei erschöpftem Kontingent wird ein Hinweistext angezeigt — keine weitere Logik.

---

## §11 Observability & Testing

### Log-Level-Zuordnung

```php
// services.xml: <argument type="service" id="logger" />
$this->logger->info('PreOrder: Tag assigned', ['orderId' => $id, 'preOrderItemCount' => $count]);
$this->logger->warning('PreOrder: Quota exhausted', ['productId' => $id]);
$this->logger->error('PreOrder: DBAL counter failed', ['productId' => $id, 'exception' => $e->getMessage()]);
```

| Situation | Level |
|---|---|
| Erfolgreiche Verarbeitung | `info` |
| Kontingent erschöpft | `warning` |
| DBAL-Operation fehlgeschlagen | `error` |
| Config-Keys fehlen | `critical` |

### Testing

> **KEIN PHP lokal installiert.** Tests werden als Dateien geschrieben und auf 192.168.2.222:8080 ausgeführt.

```
tests/
├── TestBootstrap.php
└── Integration/
    ├── Cart/PreOrderCartCollectorTest.php
    └── Subscriber/OrderPlacedSubscriberTest.php
```

---

## §12 Plugin-Uninstall & Indexierung

> → Siehe auch: `shopware_plugin` §2b für das generische Uninstall-Pattern.

### Cleanup bei `keepUserData === false`

| # | Ressource | Methode |
|---|---|---|
| 1 | CustomField-Set + 5 Fields | Multi-Table DELETE (Kaskade) |
| 2 | System-Config | `DELETE WHERE LIKE 'CustomPreOrderManager.config.%'` |

**Was BEHALTEN wird:** Tag `Vorbestellung`, JSON-Werte am Produkt, Bestellungen.

```php
public function uninstall(UninstallContext $uninstallContext): void
{
    parent::uninstall($uninstallContext);

    if ($uninstallContext->keepUserData()) {
        return;
    }

    $connection = $this->container->get(Connection::class);

    $connection->executeStatement("
        DELETE cf, cfsr, cfs
        FROM custom_field_set cfs
        LEFT JOIN custom_field_set_relation cfsr ON cfsr.set_id = cfs.id
        LEFT JOIN custom_field cf ON cf.set_id = cfs.id
        WHERE cfs.name = 'custom_preorder_set'
    ");

    $connection->executeStatement("
        DELETE FROM system_config
        WHERE configuration_key LIKE 'CustomPreOrderManager.config.%'
    ");
}
```

### CustomField-Indexierung & Performance

| Zugriffsmuster | Performance | Empfehlung |
|---|---|---|
| `EqualsFilter` auf CustomField | ⚠️ JSON-Extract | OK für Admin (geringe Mengen) |
| Storefront-Listing (1000+ Produkte) | 🔴 Langsam | Kategorie-Filter VOR PreOrder-Filter |
| DBAL `JSON_EXTRACT` | ✅ Schnell (MySQL 8.0+) | Bevorzugt für Counter |

> **VERBOTEN:** Reiner CustomField-Filter ohne Vor-Filter auf dem gesamten Katalog. Bei > 10.000 Produkten → Timeout.

---

## §13 Anti-Bloat & Code-Hygiene

> **Dieses Plugin hat GENAU 3 PHP-Klassen + 1 Event + 1 Extension + 1 Migration. Nicht mehr.**

| Regel | Begründung |
|---|---|
| Keine Service-Klasse wenn Subscriber reicht | `OrderPlacedSubscriber` IST der Service |
| Kein Repository-Wrapper | DAL-Repositories direkt injizieren |
| Kein DTO wenn Array reicht | LineItem-Payload = Array |
| Keine Abstract-Klasse bei 1 Implementierung | Overhead ohne Nutzen |
| Code-Block genau 1× im Skill | Duplikate erzeugen Widersprüche — Cross-Ref statt Copy-Paste |
| Kein Feature ohne Config-Toggle | Alles Sichtbare abschaltbar (→ storefront §6) |
| Neue Datei nur wenn in §1 gelistet | Wildwuchs per ADR-Pflicht blockiert |

---

## §14 Kill-Kriterien

| Verstoß | Konsequenz |
|---|---|
| Native SQL ohne DBAL-Parameterbindung | **VERBOTEN** — SQL-Injection |
| `sold_count` per DAL-Read-Modify-Write | **VERBOTEN** — Race Condition; atomares DBAL-Update Pflicht (§3) |
| DBAL-Operation ohne `version_id` im WHERE | **SOFORTIGER ABSTURZ** — trifft alle Versionen |
| Mischwarenkörbe technisch blockiert | **VERBOTEN** — Conversion-Vorgabe verletzt |
| Vorbestell-UI aktiv trotz `stock > 0` | **VERBOTEN** — Stock-Guard fehlt (§7) |
| Fehlender CSRF-Schutz auf Storefront-Routes | **VERBOTEN** — Sicherheitslücke |
| `PreOrderPlacedEvent` ohne `FlowEventAware` | **CRASH** — Flow Builder erkennt Event nicht |
| Event ohne `getAvailableData()` | **CRASH** — Flow Builder zeigt keine Daten |
| `export default` statt `Component.register()` | Komponente wird nicht geladen |
| `inject: ['httpClient']` im Admin | `httpClient` ist undefined zur Laufzeit |
| `loginService.getHeader()` aufgerufen | Existiert nicht — `getToken()` nutzen |
| SCSS ohne Import in `index.js` | Styling wird nicht kompiliert |
| Snippet-Key ohne JSON-Definition | Rohe Keys im UI |
| Wartelisten-Code im Plugin | Architektur-Verstoß — dieses Plugin hat KEINE Warteliste |
| Neue Datei ohne Eintrag in §1 Scaffolding | ADR-Pflicht verletzt |
| Destruktive DB-Befehle in `update()` | Datenverlust — nur in `uninstall()` |
