---
name: load-rules-shopware-plugin
description: ZWINGEND! Lade diese Datei für alle Shopware 6 Plugin-Architektur, Entity-Definition, DAL, Admin-Modul, Storefront und Security-Aufgaben.
---

# Shopware 6 Plugin Architecture (Enterprise Standard 2026)

**STACK:** PHP 8.1+, Shopware 6.5.x CE, Symfony 6, Doctrine DBAL, Vue.js 3 (Admin), Twig + JS-Plugins (Storefront)
**PARADIGMA:** DAL-First, Entity-Driven, Cache-Safe, Zero-Native-SQL

## 1. DOCUMENTATION-FIRST (VOR JEDER IMPLEMENTIERUNG — KEINE AUSNAHME)

> **HARTE REGEL: Bevor du EINE EINZIGE ZEILE Shopware-Code schreibst, MUSST du die offizielle Dokumentation für das betreffende Pattern lesen.** Kein "ich kenne das schon", kein "das hab ich in einem anderen Plugin gesehen". Dein Training-Wissen ist VERALTET — Shopware ändert APIs, deprecatet Methoden und verschiebt Namespaces zwischen Minor-Versionen. Jeder Compile-Fehler der durch Doku-Ignoranz entsteht, kostet den Nutzer Zeit und Geld.

### Dokumentations-Quellen (in dieser Reihenfolge)

| Priorität | Quelle | Wie aufrufen |
|---|---|---|
| **1. Offizielle Doku** | `developer.shopware.com` | `search_web` mit Query: `site:developer.shopware.com/docs/guides {Thema}` |
| **2. Shopware Core-Code** | GitHub `shopware/shopware` | Branch `v6.5.x` lesen, NICHT `trunk`/`main` |
| **3. Shopware Recipes/Examples** | `developer.shopware.com/docs/resources` | Für Boilerplate und Beispiel-Plugins |

### Wann MUSS die Doku gelesen werden?

- **IMMER** bei: Entity-Definitionen, Admin-Komponenten, Subscriber/Events, Route-Definitionen, Migration-Klassen, Plugin-Lifecycle, SCSS/JS-Integration, Webpack/Build-Config
- **IMMER** wenn ein Compile-Fehler auftritt — ERST Doku lesen, DANN fixen
- **IMMER** wenn du ein Pattern aus einem anderen Plugin übernimmst — das andere Plugin kann veraltet sein

### Was VERBOTEN ist

- ❌ Code aus dem Training-Wissen schreiben ohne Doku-Abgleich
- ❌ Patterns aus anderen Plugins (auch eigene) als kanonische Referenz nutzen
- ❌ Bei Compile-Fehler raten statt Doku lesen
- ❌ `trunk`/`main`-Branch-Doku lesen wenn das Ziel 6.5.x ist

### 1b. Versionskompatibilität (KRITISCH)

**Ziel-Plattform:** Shopware 6.5 Community Edition (CE).
**Aufwärtskompatibilität:** Code MUSS auch auf 6.6 und 6.7 lauffähig sein.

| Regel | Begründung |
|---|---|
| **Keine Enterprise-only APIs** (`commercial` Plugin, Rule Scope Erweiterungen) | CE hat diese nicht — sofortiger Crash |
| **Keine 6.6/6.7-only Features** (`AbstractStorefrontController` Änderungen, neue Admin-SDK-APIs) | Muss auf 6.5 laufen |
| **`composer.json` Constraints:** `"shopware/core": "~6.5.8.0 \|\| ^6.6.0 \|\| ^6.7.0"` | Explizite Kompatibilitäts-Range |
| **`sw-*` Admin-Komponenten (6.5-Stil):** Nutze `sw-entity-listing`, `sw-card`, `sw-text-editor`, `sw-single-select` | 6.7 hat einen Compat-Layer für diese — Forward-Safe |
| **`sw-single-select`: IMMER `v-model`, NIEMALS `v-model:value`** | SW 6.5 nutzt Vue 3 mit Vue 2 Compat-Layer. `sw-single-select` emittiert `update:modelValue` → `v-model` korrekt. `v-model:value` bindet auf `update:value` → keine Reaktivität, kein Status-Update |
| **Kein `Shopware\Administration\*` Namespace im PHP-Code** | Admin-Modul ist rein JavaScript/Twig-basiert |
| **`#[Route]` PHP-Attribute (Standard)** | PHP 8.1 Attribute werden ab 6.5 unterstützt, ab 6.6 erzwungen |
| **`PrependExtensionInterface` für Framework-Config** | `Resources/config/packages/*.yaml` wird von Shopware bei Plugins NICHT automatisch geladen — nutze `prependExtensionConfig()` im DI-Container |

## 2. Plugin-Struktur (Verzeichnis-Konvention)

> **PFLICHT: Jedes Plugin bekommt sein eigenes Git-Repository** (siehe `project_scaffolding` §1 Repository-Isolation). Ein Plugin als Unterordner in einem bestehenden Repo (z.B. Content-Hub, Monorepo) ist **VERBOTEN**. Begründung: Git-History, `composer require`, Branch-per-Agent, `.agent/manifest.md` Routing.

**KRITISCH:** `Resources/` liegt INNERHALB von `src/`, NICHT auf Root-Level. Shopwares `Bundle.php` (Zeile 159) sucht Services unter `getPath()/Resources/config/services.*` — und `getPath()` gibt das Verzeichnis der Plugin-Klasse zurück (`src/`).

```
CustomPluginName/
├── src/
│   ├── CustomPluginName.php               # Plugin-Hauptklasse (Boot, Lifecycle)
│   ├── DependencyInjection/
│   │   └── CustomPluginNameExtension.php  # PrependExtensionInterface (RateLimiter etc.)
│   ├── Core/
│   │   ├── Content/
│   │   │   └── EntityName/
│   │   │       ├── EntityNameDefinition.php    # EntityDefinition
│   │   │       ├── EntityNameEntity.php        # Entity-Klasse
│   │   │       ├── EntityNameCollection.php    # EntityCollection
│   │   │       └── Aggregate/                  # Translations, Mappings
│   │   ├── Api/                           # Admin-API Controller
│   │   ├── Service/                       # Business-Logik (Service-Layer!)
│   │   ├── Exception/                     # Custom Exception-Klassen
│   │   └── Extension/                     # Entity-Extensions (bestehende Shopware-Entities erweitern)
│   ├── Storefront/
│   │   ├── Controller/                    # Store-API Routes
│   │   └── Subscriber/                    # Event-Subscriber (Page-Extensions)
│   ├── Migration/
│   │   └── Migration*.php                 # DB-Migrationen
│   └── Resources/                         # ⚠️ INNERHALB von src/ !
│       ├── config/
│       │   ├── services.xml               # DI-Container
│       │   ├── routes.xml                 # Route-Definitionen
│       │   └── plugin.png                 # Plugin-Logo (400×400px, PNG)
│       ├── views/
│       │   └── storefront/                # Twig-Templates
│       ├── app/
│       │   ├── administration/src/        # Vue.js Admin-Modul
│       │   └── storefront/src/            # JS-Plugins + SCSS
│       └── snippet/                       # Übersetzungen (de_DE, en_GB)
└── composer.json
```

**Namens-Konvention:**
- **Plugin-Prefix:** `Custom` (Shopware-Standard für private/wiederverwendbare Plugins) — `CustomRedirectManager`, `CustomModalManager`
- **Tabellen-Prefix:** `custom_` für Custom Entities — `custom_redirect_rule`, `custom_modal`
- **Entity-Name im Code:** PascalCase ohne Prefix — `RedirectRuleDefinition`, nicht `CustomRedirectRuleDefinition`

### `composer.json` Konvention (PFLICHT)

> **Vendor-Prefix ist `growandstyle`.** Nicht `gsd`, nicht `grow-and-style`, nicht `gs`. Einheitlich über alle Plugins.

```json
{
    "name": "growandstyle/custom-plugin-name",
    "description": "Kurze, präzise Beschreibung auf Deutsch",
    "type": "shopware-platform-plugin",
    "license": "proprietary",
    "authors": [
        {
            "name": "GrowAndStyle",
            "homepage": "https://growandstyle.de"
        }
    ],
    "autoload": {
        "psr-4": {
            "CustomPluginName\\": "src/"
        }
    },
    "extra": {
        "shopware-plugin-class": "CustomPluginName\\CustomPluginName",
        "label": {
            "de-DE": "Plugin-Titel auf Deutsch",
            "en-GB": "Plugin Title in English"
        },
        "description": {
            "de-DE": "Beschreibung auf Deutsch",
            "en-GB": "Description in English"
        }
    },
    "require": {
        "shopware/core": "~6.5.8.0 || ^6.6.0 || ^6.7.0"
    }
}
```

| Regel | Begründung |
|---|---|
| **Vendor:** `growandstyle` (lowercase, ein Wort) | Einheitlichkeit über alle Plugins — `gsd`, `grow-and-style` etc. sind verboten |
| **Name:** `growandstyle/custom-plugin-name` (kebab-case) | Composer-Standard für Package-Namen |
| **Version:** KOMPLETT WEGLASSEN in `composer.json` | `shopware-cli extension zip --use-git-tag-as-version` injiziert die Version automatisch beim Build. Ein lokaler `version`-Eintrag führt zu Out-of-Sync-Fehlern. Workflow: commit → `git tag v1.2.0` → `shopware-cli extension zip .` |
| **Autor:** `"name": "GrowAndStyle"` mit Homepage | Konsistent über alle Plugins — `Grow & Style`, `Grow and Style`, `GSD` etc. sind verboten |
| **`shopware-plugin-class`:** Vollqualifizierter Namespace | Muss auf die Plugin-Hauptklasse in `src/` zeigen |

### 2b. Plugin-Lifecycle (PFLICHT)

> **Jede Plugin-Hauptklasse MUSS alle 5 Lifecycle-Methoden implementieren.** Auch wenn der Body leer ist — das dokumentiert bewusst, dass nichts passiert. Ein fehlendes `uninstall()` hinterlässt verwaiste Einträge in der Datenbank.

### Vollständiges Uninstall-Pattern (KRITISCH)

> **`scheduled_task`-Einträge werden von Shopware NICHT automatisch entfernt** (verifiziert). Wer einen Scheduled Task registriert, MUSS ihn in `uninstall()` manuell aus der DB löschen — sonst bleibt ein toter Cronjob-Eintrag zurück, der bei jeder Ausführung einen Fehler wirft.

**Vollständige Uninstall-Checkliste — was MUSS manuell entfernt werden:**

| Was | Wird auto-entfernt? | Methode |
|---|:---:|---|
| Eigene Tabellen (`custom_*`) | ❌ | `DROP TABLE IF EXISTS` via DBAL |
| `scheduled_task`-Einträge | ❌ **NEIN** | `DELETE FROM scheduled_task WHERE name = '...'` |
| `custom_field_set` / Custom Fields | ❌ | DAL: `customFieldSetRepository->delete()` |
| System-Config (`plugin.config.*`) | ❌ | `SystemConfigService->deletePluginConfiguration()` |
| ACL-Rollen (`acl_role`) | ❌ | DAL: `aclRoleRepository->delete()` |
| DB-Migrationen (Schema) | ✅ (via `updateDestructive`) | Automatisch wenn korrekt implementiert |
| Event-Subscriber | ✅ | Automatisch via DI-Container |

```php
<?php declare(strict_types=1);

namespace CustomPluginName;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

class CustomPluginName extends Plugin
{
    public function install(InstallContext $context): void
    {
        // Migrationen werden automatisch ausgeführt
    }

    public function update(UpdateContext $context): void
    {
        // Schema-Updates über Migrationen gesteuert
    }

    public function activate(ActivateContext $context): void
    {
        // z.B. Default-Config setzen via SystemConfigService
    }

    public function deactivate(DeactivateContext $context): void
    {
        // Subscriber werden automatisch deregistriert
    }

    /**
     * KRITISCH: keepUserData() abfragen!
     * true  → Nutzer will Daten behalten → NUR return
     * false → Nutzer will saubere Deinstallation → ALLES entfernen
     */
    public function uninstall(UninstallContext $context): void
    {
        parent::uninstall($context);

        if ($context->keepUserData()) {
            return;
        }

        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);

        // 1. Eigene Tabellen droppen (Reihenfolge: Child vor Parent wegen FK-Constraints)
        $connection->executeStatement('DROP TABLE IF EXISTS `custom_entity_child`');
        $connection->executeStatement('DROP TABLE IF EXISTS `custom_entity_name`');

        // 2. Scheduled Tasks entfernen — NICHT auto-entfernt von Shopware! (verifiziert)
        $connection->executeStatement(
            "DELETE FROM `scheduled_task` WHERE `name` = 'custom_plugin_name.task_name'"
        );

        // 3. Custom Fields / Custom Field Sets entfernen
        // (via DAL wenn Container verfügbar, sonst direkt via DBAL)
        $connection->executeStatement(
            "DELETE FROM `custom_field_set` WHERE `name` = 'custom_plugin_name_fields'"
        );

        // 4. System-Config des Plugins löschen
        $connection->executeStatement(
            "DELETE FROM `system_config` WHERE `configuration_key` LIKE 'CustomPluginName.config.%'"
        );
    }
}
```

### Regeln

| Regel | Begründung |
|---|---|
| `keepUserData()` MUSS abgefragt werden | Ohne Check: Datenverlust bei Reinstall |
| `parent::uninstall()` MUSS aufgerufen werden | Entfernt Plugin-Registrierung aus Shopware-Core |
| `DROP TABLE IF EXISTS` — immer mit Guard | Ohne Guard crasht Deinstallation wenn Tabelle fehlt |
| **`scheduled_task`-Einträge MANUELL löschen** | Werden von Shopware NICHT auto-entfernt — verifiziert |
| Tabellen-Reihenfolge: Child vor Parent | FK-Constraints verhindern Parent-Drop wenn Children existieren |
| `custom_field_set` manuell löschen | Bleiben sonst als verwaiste Einträge im Admin sichtbar |
| System-Config per `LIKE 'PluginName.config.%'` löschen | Alle Config-Werte des Plugins auf einmal entfernen |
| **NIEMALS löschen:** Bestellungen, Rechnungen, Kundendaten, Media-Uploads | Gehören dem Shopbetreiber, nicht dem Plugin |

## 3. Entity-Definitionen (DAL-Regeln)

### A. Strikte Namenskonvention
- **Tabellen-Prefix:** `custom_` für alle Entitäten aller GrowAndStyle-Plugins.
- **Entity-Name:** PascalCase ohne Prefix (`RedirectRuleDefinition`, nicht `CustomRedirectRuleDefinition`).
- **Feld-Typen:** Nutze ausschließlich die Shopware Field-Klassen (`StringField`, `JsonField`, `FkField`, `ManyToOneAssociationField`, etc.).
- **ID-Felder:** Immer `IdField` + `Required` + `PrimaryKey` Flags. UUIDs, keine Auto-Increments.

### B. Übersetzbare Felder (TranslatedField)
- Titel und Content-Felder die mehrsprachig sein müssen → `TranslatedField`.
- Die Translation-Entity erbt automatisch von `EntityTranslationDefinition`.
- Nutze `AllowHtml` Flag für HTML-Content-Felder.
- **ApiAware auf ZWEI Ebenen:** `TranslatedField` in der Parent-Definition UND das konkrete Feld in der Translation-Definition brauchen BEIDE das `ApiAware`-Flag.
- **Translation-Fallback:** Store-API Criteria MÜSSEN `$criteria->addAssociation('translations')` setzen, sonst kein Sprach-Fallback.

### C. Foreign Keys & Relationen
```php
// Korrekt: FK + Association immer als Paar
(new FkField('customer_id', 'customerId', CustomerDefinition::class))
    ->addFlags(new ApiAware()),
(new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id', false))
    ->addFlags(new ApiAware()),
```
- **Niemals** eine `FkField` ohne zugehörige `AssociationField` definieren.
- **Nullable FKs:** Wenn eine Relation optional ist, darf `Required` NICHT gesetzt werden.

### C2. ReferenceVersionField bei versionierten Core-Entities (KRITISCH)

> **Viele Shopware Core-Entities sind versioniert** (`product`, `product_manufacturer`, `order`, etc.). Ihre Tabellen haben eine Composite PK (`id`, `version_id`). Wenn du per FK auf eine solche Entity referenzierst, MUSST du:
> 1. In der **Migration**: Eine zusätzliche `*_version_id`-Spalte anlegen + Composite FK
> 2. In der **Definition**: Ein `ReferenceVersionField` mit explizitem `storageName` hinzufügen

```php
// ✅ KORREKT: Definition mit ReferenceVersionField + explizitem storageName
(new FkField('manufacturer_id', 'manufacturerId', ProductManufacturerDefinition::class))
    ->addFlags(new Required(), new ApiAware()),
(new ReferenceVersionField(ProductManufacturerDefinition::class, 'manufacturer_version_id'))
    ->addFlags(new Required()),

// ❌ VERBOTEN: ReferenceVersionField OHNE storageName
// Auto-generiert 'product_manufacturer_version_id' — passt nicht zur DB-Spalte!
(new ReferenceVersionField(ProductManufacturerDefinition::class))
    ->addFlags(new Required()),
```

```sql
-- ✅ KORREKT: Migration mit version_id Spalte + Composite FK
CREATE TABLE IF NOT EXISTS `custom_entity` (
    `id`                      BINARY(16) NOT NULL,
    `manufacturer_id`         BINARY(16) NOT NULL,
    `manufacturer_version_id` BINARY(16) NOT NULL,
    -- ...
    PRIMARY KEY (`id`),
    CONSTRAINT `fk.manufacturer_id`
        FOREIGN KEY (`manufacturer_id`, `manufacturer_version_id`)
        REFERENCES `product_manufacturer` (`id`, `version_id`)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- ❌ VERBOTEN: FK nur auf `id` bei versionierter Tabelle — MySQL Constraint-Error
FOREIGN KEY (`manufacturer_id`) REFERENCES `product_manufacturer` (`id`)
```

**SQL-Lifecycle (INSERT):** Bei versionierten Entities immer auf `Defaults::LIVE_VERSION` filtern:
```php
// Shopware\Core\Defaults::LIVE_VERSION = '0fa91ce3e96a4bc2be4bd9ce752c3425'
$criteria->addFilter(new EqualsFilter('versionId', Defaults::LIVE_VERSION));
```

### C3. DBAL-INSERT: Physische Spaltennamen verwenden (KRITISCH)

> **DBAL (`$connection->insert()`) nutzt die physischen DB-Spaltennamen, NICHT die DAL-Property-Namen.** Die DAL mappt camelCase-Properties auf snake_case-Spalten, aber bei Migrationen/DBAL-Code muss der echte Spaltenname aus der Tabelle verwendet werden. Diese weichen bei FK-Spalten oft vom erwarteten Muster ab.

```php
// ❌ VERBOTEN: DAL-Property-Name in DBAL-INSERT
$connection->insert('custom_field_set_relation', [
    'custom_field_set_id' => $setId,  // Spalte existiert NICHT — heißt `set_id`!
]);

// ✅ KORREKT: Physischer DB-Spaltenname
$connection->insert('custom_field_set_relation', [
    'set_id' => $setId,  // Echte Spalte in der DB
]);
```

**Häufig betroffene Tabellen:**

| Tabelle | DAL-Property | Physische DB-Spalte |
|---|---|---|
| `custom_field_set_relation` | `customFieldSetId` | `set_id` |
| `custom_field` | `customFieldSetId` | `set_id` |
| `product_translation` | `productId` | `product_id` |

**Regel:** Bei DBAL-Code im Zweifel die Tabellenstruktur in der offiziellen Shopware-Doku oder per `DESCRIBE {table}` auf dem Server prüfen.

### D. JSON-Felder
- Nutze `JsonField` mit expliziten Property-Definitionen für strukturierte Daten.
- Für dynamische Daten: `JsonField` ohne Property-Constraints, aber mit Backend-Validierung im Controller.

### E. DAL-Filter-Klassen (ACHTUNG — Versions-Fallen)

> **`NotEqualsFilter` existiert NICHT in Shopware 6.5.x.** Das ist die häufigste Compile-Falle. Stattdessen: `NandFilter` mit verschachteltem `EqualsFilter`.

**Verfügbare Filter in 6.5.x (Namespace: `Shopware\Core\Framework\DataAbstraction\Search\Filter\`):**

| Filter | Verwendung | Beispiel |
|---|---|---|
| `EqualsFilter` | Exakter Wert | `new EqualsFilter('active', true)` |
| `EqualsAnyFilter` | Wert in Liste | `new EqualsAnyFilter('id', [$id1, $id2])` |
| `ContainsFilter` | String enthält | `new ContainsFilter('name', 'Athena')` |
| `PrefixFilter` | String beginnt mit | `new PrefixFilter('sourceUrl', '/shop/')` |
| `SuffixFilter` | String endet mit | `new SuffixFilter('sourceUrl', '.html')` |
| `RangeFilter` | Numerischer Bereich | `new RangeFilter('hitCount', [RangeFilter::GTE => 10])` |
| `MultiFilter` | AND/OR Verknüpfung | `new MultiFilter(MultiFilter::CONNECTION_OR, [...])` |
| `NotFilter` | Negation (Wrapper) | `new NotFilter(NotFilter::CONNECTION_AND, [...])` |
| `NandFilter` | NOT-AND (Shortcut) | `new NandFilter([new EqualsFilter('status', 'deleted')])` |
| `NorFilter` | NOT-OR (Shortcut) | `new NorFilter([...])` |

**Häufige Fehler:**

```php
// ❌ EXISTIERT NICHT in 6.5.x — Compile-Error!
use Shopware\Core\Framework\DataAbstraction\Search\Filter\NotEqualsFilter;
$criteria->addFilter(new NotEqualsFilter('status', 'deleted'));

// ✅ KORREKT: NandFilter als Ersatz (verfügbar seit 6.0)
use Shopware\Core\Framework\DataAbstraction\Search\Filter\NandFilter;
use Shopware\Core\Framework\DataAbstraction\Search\Filter\EqualsFilter;
$criteria->addFilter(new NandFilter([new EqualsFilter('status', 'deleted')]));

// ✅ ALTERNATIV: NotFilter mit EqualsFilter
use Shopware\Core\Framework\DataAbstraction\Search\Filter\NotFilter;
$criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
    new EqualsFilter('status', 'deleted'),
]));
```

## 4. Migration-Regeln

- **Eine Migration pro Schema-Änderung.** Keine Multi-Table-Migrationen.
- **Timestamps:** `Migration{UNIX_TIMESTAMP}CreateEntityTable.php` — der Timestamp MUSS unique sein.
- **Destruktive Operationen:** `DROP TABLE` und `DROP COLUMN` sind in Produktions-Migrationen VERBOTEN. Nutze `IF EXISTS` Guards.
- **Update-Methode:** `updateDestructive()` nur für bewusste Daten-Löschung, niemals in `update()`.
- **Charset:** `CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci` — immer explizit setzen.

**Beispiel:**
```php
public function update(Connection $connection): void
{
    $connection->executeStatement('
        CREATE TABLE IF NOT EXISTS `custom_redirect_rule` (
            `id`            BINARY(16)      NOT NULL,
            `source_url`    VARCHAR(2048)   NOT NULL,
            `target_url`    VARCHAR(2048)   NOT NULL,
            `status_code`   SMALLINT        NOT NULL DEFAULT 301,
            `active`        TINYINT(1)      NOT NULL DEFAULT 1,
            `created_at`    DATETIME(3)     NOT NULL,
            `updated_at`    DATETIME(3)     NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ');
}
```

## 5. Store-API & Controller-Architektur

### A. Route-Definition
- **Standard: PHP 8.1 Native Attributes** (`#[Route]`) — zwingendes Format ab Shopware 6.5+. Doctrine `@Route` Annotations sind veraltet und dürfen **nicht** neu verwendet werden.
- **Cache-Sicherheit:** Dynamische Daten MÜSSEN über asynchrone Store-API Calls geladen werden, NICHT über Page-Loader (ESI/Varnish-inkompatibel).
- **Login-pflichtige Routen:** `defaults: ['_loginRequired' => true]` im `#[Route]`-Attribut.

**Store-API Beispiel:**
```php
#[Route(
    path: '/store-api/custom-redirect/rules',
    name: 'store-api.custom-redirect.rules',
    methods: ['GET'],
    defaults: ['_noStore' => true]
)]
```

**Admin-API Beispiel (PFLICHT: `_acl`):**

> **Frontend-ACL-Checks (`v-if`, `acl.can()`) sind KEIN Schutz.** Jeder authentifizierte Admin kann Endpoints direkt per cURL ansprechen. Admin-API-Routes MÜSSEN `_acl`-Defaults haben.

```php
// ✅ KORREKT: Backend-ACL auf Admin-API-Route
#[Route(
    path: '/api/_action/custom-redirect-manager/import',
    name: 'api.action.custom_redirect_manager.import',
    methods: ['POST'],
    defaults: ['_acl' => ['custom_redirect_rule.editor']],
)]

// ❌ VERBOTEN: Admin-Route ohne _acl (jeder eingeloggte Admin kann zugreifen)
#[Route(
    path: '/api/_action/custom-redirect-manager/import',
    name: 'api.action.custom_redirect_manager.import',
    methods: ['POST'],
)]
```

### B. Request-Validierung (Symfony Constraints)
```php
// PFLICHT für jeden Store-API Endpunkt der Nutzerdaten annimmt
use Symfony\Component\Validator\Constraints as Assert;

$constraints = new Assert\Collection([
    'source_url'  => [new Assert\NotBlank(), new Assert\Length(['max' => 2048])],
    'target_url'  => [new Assert\NotBlank(), new Assert\Url()],
    'status_code' => [new Assert\Choice([301, 302, 307, 308])],
]);
```
- **XSS-Prävention:** Alle String-Inputs durch `strip_tags()` sanitizen BEVOR sie in die DB geschrieben werden.

### C. Service-Layer (PFLICHT)
Business-Logik gehört NIEMALS in den Controller/Route-Handler. Immer einen dedizierten Service nutzen:

```php
// ✅ KORREKT: Controller delegiert an Service
#[Route(path: '/store-api/custom-redirect/create', name: 'store-api.custom-redirect.create', methods: ['POST'])]
public function create(RequestDataBag $data, SalesChannelContext $context): JsonResponse
{
    return $this->redirectService->create($data, $context);
}

// ❌ VERBOTEN: Business-Logik direkt im Controller
#[Route(path: '/store-api/custom-redirect/create', ...)]
public function create(RequestDataBag $data, SalesChannelContext $context): JsonResponse
{
    $entity = new RedirectRuleEntity();
    $entity->setSourceUrl($data->get('source_url'));
    $this->repository->create([$entity->toArray()], $context->getContext());
    return new JsonResponse(['success' => true]);
}
```

### D. Admin-API Controller — JSON-Body lesen (ACHTUNG)

> **Der Admin-`httpClient` (Axios) sendet IMMER `Content-Type: application/json`.** Symfony parsed JSON-Bodies NICHT automatisch in `$request->request`. Wer `$request->request->get()` nutzt, bekommt **immer `null` zurück**.

```php
// ✅ KORREKT: JSON-Body lesen via $request->toArray() (Symfony 5.4+)
#[Route(path: '/api/_action/custom-redirect-manager/test', name: 'api.action.custom_redirect_manager.test', methods: ['POST'])]
public function test(Request $request): JsonResponse
{
    $data = $request->toArray();  // Parsed JSON automatisch
    $sourceUrl = strip_tags((string) ($data['sourceUrl'] ?? ''));
    // ...
}

// ❌ VERBOTEN: $request->request->get() für JSON-Requests
public function test(Request $request): JsonResponse
{
    $sourceUrl = $request->request->get('sourceUrl');  // IMMER null bei JSON!
}
```

| Request-Typ | Quelle | Methode |
|------------|--------|---------|
| **Admin-API** (Axios, JSON) | `Content-Type: application/json` | `$request->toArray()` |
| **Store-API** (Shopware Frontend) | `RequestDataBag` (Shopware-Abstraktion) | `$data->get('key')` |
| **Storefront-Formulare** (HTML POST) | `application/x-www-form-urlencoded` | `$request->request->get('key')` |
| **File-Upload** (CSV Import) | `multipart/form-data` | `$request->files->get('file')` |

## 6. Vue.js 3 Admin-Modul (Shopware Admin SDK)

### A. Modul-Registrierung
```javascript
// Generisches Pattern — Plugin-Name anpassen
Module.register('custom-plugin-name', {
    type: 'plugin',
    name: 'plugin-name',
    title: 'custom-plugin-name.general.title',
    description: 'custom-plugin-name.general.description',
    color: '#ff6b35',
    icon: 'regular-cog',
    routes: { /* ... */ },
    navigation: [{ /* ... */ }],
});
```

### B. Komponenten-Registrierung (PFLICHT)

> **KERNREGEL:** Admin-Komponenten MÜSSEN über `Component.register()` registriert werden. Ein bloßes `export default {}` ohne `Component.register()` ist **VERBOTEN** — Shopware kennt die Komponente sonst nicht und sie wird nicht gerendert.

```javascript
// ✅ KORREKT: Component.register mit Shopware-API
const { Component } = Shopware;

Component.register('custom-redirect-manager-list', {
    template,
    inject: ['repositoryFactory'],
    mixins: [Mixin.getByName('listing')],
    // ...
});

// ❌ VERBOTEN: Nur export default (Shopware weiß nichts von der Komponente)
export default {
    template,
    inject: ['repositoryFactory'],
    // ...
};
```

### B2. HTTP-Requests aus Admin-Komponenten (ACHTUNG)

> **`httpClient` ist KEIN injizierbarer Service** in Shopware 6.5 Admin-Components. `inject: ['httpClient']` ergibt `undefined`. Der korrekte Zugriff:

```javascript
// ✅ EMPFOHLEN: httpClient + loginService (sauberster Weg)
const httpClient = Shopware.Application.getContainer('init').httpClient;
const loginService = Shopware.Service('loginService');

const response = await httpClient.post(
    '/api/_action/custom-plugin/endpoint',
    { payload: data },
    {
        headers: {
            Authorization: `Bearer ${loginService.getToken()}`,
            'Content-Type': 'application/json',
        },
    },
);

// ✅ ALTERNATIV: Auth-Token direkt aus dem Context (funktioniert ebenfalls)
const response = await httpClient.post(
    '/api/_action/custom-plugin/endpoint',
    { payload: data },
    {
        headers: {
            Authorization: `Bearer ${Shopware.Context.api.authToken.access}`,
            'Content-Type': 'application/json',
        },
    },
);

// ❌ VERBOTEN: loginService.getHeader() — Methode existiert NICHT!
{ headers: Shopware.Service('loginService').getHeader() }  // TypeError → catch verschluckt den Fehler

// ❌ VERBOTEN: httpClient per inject (gibt undefined)
inject: ['httpClient'],  // Existiert nicht als Injectable!
```

**Injizierbare Services (funktionieren mit `inject: []`):**
- `repositoryFactory` — DAL Repository-Zugriff
- `acl` — Berechtigungsprüfung
- `systemConfigApiService` — Plugin-Config lesen
- `snippetService` — Übersetzungen

### C. Entity-Listing mit `listing`-Mixin (Standard)

> **Das `listing`-Mixin ist PFLICHT für alle Entity-Listing-Seiten.** Es synchronisiert Pagination, Sortierung und Suche automatisch mit der URL (Deep-Linking). Ohne Mixin: User auf Seite 3 drückt F5 → landet auf Seite 1. Das ist kein Enterprise-Standard.

**Häufigster Fehler: Endlos-Loop durch falsche Criteria-Initialisierung:**

```javascript
// ❌ VERBOTEN: Page/Limit im Criteria-Konstruktor → triggert Route-Push → Loop
const criteria = new Criteria(this.page, this.limit);

// ✅ KORREKT: Page/Limit über Setter-Methoden
const criteria = new Criteria();
criteria.setPage(this.page);
criteria.setLimit(this.limit);
criteria.addSorting(Criteria.sort('createdAt', 'DESC'));
```

Das `listing`-Mixin stellt `this.page`, `this.limit` und `this.sortBy`/`this.sortDirection` bereit. Die `getList()`-Methode wird vom Mixin automatisch aufgerufen — kein manueller Aufruf in `created()` nötig.

### C2. `fullPage`-Prop auf `sw-entity-listing` / `sw-data-grid` (ACHTUNG)

> **`sw-entity-listing` hat `fullPage` per Default auf `true`.** Das fügt die CSS-Klasse `sw-data-grid--full-page` hinzu, die negative Margins setzt. Diese Margins sind für `sw-card`-Wrapper gedacht — sie gleichen das Card-Padding aus. **Ohne `sw-card`-Wrapper verursacht `fullPage=true` horizontalen Overflow.**

```twig
{# ✅ KORREKT: fullPage=false wenn die Listing NICHT in einer sw-card liegt #}
<sw-entity-listing
    :repository="repository"
    :items="items"
    :columns="columns"
    :fullPage="false"
/>

{# ✅ KORREKT: fullPage=true (Default) NUR wenn die Listing IN einer sw-card liegt #}
<sw-card>
    <sw-entity-listing
        :repository="repository"
        :items="items"
        :columns="columns"
    />
</sw-card>

{# ❌ VERBOTEN: Listing ohne sw-card mit fullPage=true (Default) → Overflow rechts #}
<sw-card-view>
    <sw-entity-listing
        :repository="repository"
        :items="items"
        :columns="columns"
    />
</sw-card-view>
```

**Warum passiert das?** `sw-data-grid--full-page` setzt `margin-left: -20px; margin-right: -20px` um die Tabelle über die Card-Grenzen hinaus zu strecken. Ohne Card-Padding das dagegen hält, schiebt die Tabelle über den Viewport.

### C3. `inline-edit-save` Event-Signatur in `sw-entity-listing` (KRITISCH)

> **`sw-entity-listing` überschreibt die `save()`-Methode von `sw-data-grid`** und ändert die Event-Signatur! Das ist die häufigste Falle bei Inline-Edit-Grids.

**`sw-data-grid` (Basis-Klasse):**
```javascript
save(item) {
    this.$emit('inline-edit-save', item);  // 1 Argument: das Entity-Record
}
```

**`sw-entity-listing` (überschreibt save):**
```javascript
save(record) {
    const promise = this.repository.save(record, this.items.context).then(() => {
        return this.doSearch();  // Reload nach Save
    });
    this.$emit('inline-edit-save', promise, record);  // ⚠️ 2 Argumente!
    return promise;
}
```

| Aspekt | Detail |
|---|---|
| **1. Argument** | Promise (NICHT das Entity-Record!) |
| **2. Argument** | Das eigentliche Record-Objekt |
| **Save ist intern** | `repository.save()` wird von `sw-entity-listing` automatisch aufgerufen |
| **Reload ist intern** | `doSearch()` lädt die Items nach dem Save automatisch neu |
| **Manueller httpClient.patch() ist VERBOTEN** | Führt zu Doppel-Saves und Konflikten |

```javascript
// ❌ VERBOTEN: item ist das Promise, nicht der Record!
onInlineEditSave(item) {
    httpClient.patch(`/api/entity/${item.id}`, ...);  // item.id === undefined!
}

// ✅ KORREKT: sw-entity-listing speichert intern, nur Error-Handling nötig
onInlineEditSave(promise) {
    Promise.resolve(promise).catch((error) => {
        this.createNotificationError({ message: this.$tc('...saveError') });
    });
},

// ✅ ALTERNATIV: Wenn der Record gebraucht wird, 2. Argument nutzen
onInlineEditSave(promise, record) {
    // record enthält das Entity-Objekt mit korrekter id
    Promise.resolve(promise).catch((error) => { /* Error-Handling */ });
},
```

**Verifiziert gegen:** Shopware 6.5.8.0 Source (`sw-data-grid/index.js` Zeile 718–722, `sw-entity-listing/index.js` Zeile 243–248)

### D. Komponentenstruktur
- **Listing-Seite:** `sw-entity-listing` + `listing`-Mixin für automatische Pagination, Search, Sorting. `:fullPage="false"` setzen wenn kein `sw-card`-Wrapper vorhanden.
- **Detail-Seite:** `sw-card` Container, passende Input-Felder (`sw-text-field`, `sw-number-field`, `sw-single-select`).
- **Tabs:** Bei mehreren Content-Bereichen `sw-tabs` + `sw-tabs-item` nutzen (siehe §5.D2 für Fallstricke).

### D2. `sw-tabs` API — Kritische Fallstricke (verifiziert 6.5.8.0)

> **Alle drei Punkte wurden per `console.log` in einer produktiven Shopware 6.5.8.0 Installation verifiziert.** Widersprüche zur offiziellen Doku wurden durch direktes Lesen des GitHub-Sourcecodes aufgelöst.

**Quellen (Branch `v6.5.8.0`):**
- `src/app/component/base/sw-tabs/index.js`
- `src/app/component/base/sw-tabs/sw-tabs.html.twig`
- `src/app/component/base/sw-tabs-item/index.js`

#### Fallstrick 1: `position-identifier` ist ein Pflicht-Prop

`positionIdentifier` hat `required: true` im Source. Ohne diesen Prop gibt Vue eine Warnung aus und das Tab-Rendering kann instabil sein.

```twig
{# ✅ KORREKT: position-identifier IMMER setzen #}
<sw-tabs position-identifier="mein-plugin-tabs" :default-item="activeTab" @new-item-active="onTabChange">
    ...
</sw-tabs>

{# ❌ VERBOTEN: Fehlendes position-identifier — Pflicht-Prop! #}
<sw-tabs :default-item="activeTab" @new-item-active="onTabChange">
    ...
</sw-tabs>
```

#### Fallstrick 2: `@new-item-active` liefert die Vue-Komponenteninstanz, keinen String

Das `@new-item-active`-Event gibt die **gesamte `sw-tabs-item` Komponenteninstanz** weiter — nicht den Tab-Namen als String.

```javascript
// ❌ FALSCH: tabName ist eine Vue-Instanz, kein String!
onTabChange(tabName) {
    this.activeTab = tabName;  // → {_uid: 336, _isVue: true, ...}
},

// ✅ KORREKT: .name aus der Instanz extrahieren
onTabChange(tabItem) {
    const tabName = (tabItem && typeof tabItem === 'object') ? tabItem.name : tabItem;
    this.activeTab = tabName;
},
```

#### Fallstrick 3: Es gibt keinen `#content`-Slot in `sw-tabs`

Das Template hat ausschließlich einen Default-Slot. Content-Rendering muss **außerhalb** von `sw-tabs` erfolgen:

```twig
{# ✅ KORREKT: Vollständiges sw-tabs Pattern für Shopware 6.5.x #}
<sw-tabs
    position-identifier="mein-plugin-tabs"
    :default-item="activeTab"
    @new-item-active="onTabChange">
    <template #default="{ active }">
        <sw-tabs-item name="tab1" :active-tab="active">Tab 1</sw-tabs-item>
        <sw-tabs-item name="tab2" :active-tab="active">Tab 2</sw-tabs-item>
    </template>
</sw-tabs>

{# Content AUSSERHALB von sw-tabs — v-if auf eigene data()-Variable #}
<template v-if="activeTab === 'tab1'">
    Inhalt Tab 1
</template>
<template v-else-if="activeTab === 'tab2'">
    Inhalt Tab 2
</template>
```

```javascript
// index.js
data() {
    return {
        activeTab: 'tab1',
    };
},
methods: {
    onTabChange(tabItem) {
        this.activeTab = (tabItem && typeof tabItem === 'object') ? tabItem.name : tabItem;
    },
},
```

**Verifiziert gegen:** Shopware 6.5.8.0 Source (GitHub) + produktiver Test via `console.log`

### E. SCSS in Admin-Komponenten (ACHTUNG)

> **Shopware 6 lädt SCSS-Dateien im Admin NICHT automatisch** — weder nach Dateiname noch nach Ordnerstruktur. SCSS MUSS explizit in der `index.js` der Komponente importiert werden.

```javascript
// ✅ KORREKT: Expliziter Import in index.js der Komponente
import template from './custom-redirect-manager-list.html.twig';
import './custom-redirect-manager-list.scss';  // PFLICHT — ohne Import kein Styling

const { Component, Mixin } = Shopware;

Component.register('custom-redirect-manager-list', {
    template,
    // ...
});

// ❌ VERBOTEN: SCSS im selben Ordner ohne Import (wird ignoriert)
// Die Datei existiert, wird aber vom Webpack-Build nicht erfasst
```

**Häufige Halluzination:** "SCSS wird automatisch geladen wenn der Dateiname dem Komponentennamen entspricht" — das ist **falsch** und stammt aus Vue-CLI-Patterns, nicht aus Shopware.

### C. Snippet-Konvention
- Alle UI-Strings in `/snippet/de-DE.json` und `/snippet/en-GB.json`.
- Key-Struktur: `custom-plugin-name.detail.labelTitle`, `custom-plugin-name.list.columnStatus`.

### D. Tooltip-Pflicht für Admin-Felder (ZWINGEND)
Jedes Eingabefeld im Admin MUSS einen erklärenden Tooltip (`helpText`) haben. Der Shopbetreiber ist kein Entwickler.

```twig
{# KORREKT: Feld mit helpText #}
<sw-text-field
    :label="$tc('custom-plugin-name.detail.labelSourceUrl')"
    :helpText="$tc('custom-plugin-name.detail.helpTextSourceUrl')"
    v-model="entity.sourceUrl"
/>

{# VERBOTEN: Feld ohne helpText #}
<sw-text-field
    :label="$tc('custom-plugin-name.detail.labelSourceUrl')"
    v-model="entity.sourceUrl"
/>
```

### F. Clipboard-Zugriff mit HTTP-Fallback (PFLICHT)

> **`navigator.clipboard.writeText()` funktioniert NUR \u00fcber HTTPS** (Secure Context). Test-Server laufen auf HTTP \u2192 API nicht verf\u00fcgbar \u2192 Fehler. **Jede Clipboard-Funktion MUSS einen Fallback haben.**

```javascript
// \u2705 KORREKT: Clipboard mit Fallback f\u00fcr HTTP
async copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        // Fallback f\u00fcr HTTP / nicht-sichere Kontexte
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    }
    // Notification anzeigen
    this.createNotificationSuccess({ message: this.$tc('...copySuccess') });
},

// \u274c VERBOTEN: Nur navigator.clipboard ohne Fallback
async copyToClipboard(text) {
    await navigator.clipboard.writeText(text);  // Crasht auf HTTP!
}
```

## 7. Storefront JS-Plugin

### A. Plugin-Registrierung
```javascript
// main.js
import CustomPlugin from './plugin/custom-plugin/custom-plugin.plugin';
PluginManager.register('CustomPlugin', CustomPlugin, '[data-custom-plugin]');
```

### B. Data-Attribute API
- `data-custom-plugin="config-value"` — Bindet Verhalten an DOM-Elemente.
- JS-Plugins laden Daten **lazy** via Store-API beim ersten Interaktionspunkt.
- Kein globales Preloading von Daten die nicht sofort benötigt werden.

### C. SCSS-Integration
- Plugin-SCSS in `Resources/app/storefront/src/scss/` ablegen.
- Shopwares Build-System kompiliert diese automatisch.
- Eigene Variablen nutzen statt Shopware-Variablen zu überschreiben.

## 8. Event-System & Flow Builder

### A. Custom Business Events
- Events MÜSSEN `FlowEventAware` implementieren für Flow-Builder-Kompatibilität.
- Event-Klasse stellt `getAvailableData()` bereit (relevante Entities).

**Beispiel:**
```php
class RedirectCreatedEvent extends Event implements FlowEventAware
{
    public function __construct(
        private readonly Context $context,
        private readonly string $salesChannelId,
        private readonly RedirectRuleEntity $redirect
    ) {}

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('redirect', new EntityType(RedirectRuleDefinition::class));
    }
}
```

### B. Subscriber-Pattern
```php
// Event-Subscriber für automatische Redirects bei SEO-URL-Änderungen
class SeoUrlChangeSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'seo_url.written' => 'onSeoUrlChange',
        ];
    }

    public function onSeoUrlChange(EntityWrittenEvent $event): void
    {
        // Alte URL → Neue URL als Redirect anlegen
    }
}
```

## 9. Security Hardening (OWASP + Shopware-Spezifisch)

**GRUNDSATZ:** Jedes Plugin das Nutzerdaten entgegennimmt ist ein potenzieller Angriffsvektor. Sicherheit ist kein Feature — sie ist unverrückbare Grundlage.

### A. XSS-Prävention — 3-Schichten-Modell

**Schicht 1 — Backend Input-Sanitizing (PHP):**
- ALLE Freitext-Inputs durchlaufen `strip_tags()` BEVOR sie in die DB geschrieben werden.
- HTML-Felder vom Admin sind davon ausgenommen — diese werden im Frontend sanitized.

**Schicht 2 — Frontend Output-Sanitizing (JavaScript):**
- **`innerHTML` ohne Sanitization ist VERBOTEN.**
- DOMPurify als ES-Modul bündeln (keine CDN-Abhängigkeit).
- Für nicht-HTML-Inhalte: DOM-API (`createElement`, `textContent`) statt String-Interpolation.

```javascript
// ✅ DOMPurify für Admin-gepflegte HTML-Inhalte
import DOMPurify from '../vendor/dompurify.es.mjs';
contentEl.innerHTML = DOMPurify.sanitize(entity.translated.content);

// ✅ DOM-API für strukturierte Inhalte
const link = document.createElement('a');
link.textContent = linkText;  // textContent, NICHT innerHTML
link.href = validatedUrl;

// ❌ VERBOTEN
contentEl.innerHTML = apiResponse.content;
```

**Schicht 3 — Twig Output-Encoding:**
- `{{ variable }}` escaped automatisch — Standard nutzen.
- `{{ content|raw }}` NUR für Admin-gepflegten, trusted Content.
- NIEMALS `|raw` auf User-Input.

### B. CSRF-Schutz
- **Store-API-Routes:** Automatisch via `sw-access-key` Header geschützt.
- **Storefront-POST-Routes:** MÜSSEN `_csrf_protected: true` haben + `{{ sw_csrf('route_name') }}` im Formular.
- **Admin-Routes:** Session-basierte Auth via Symfony Firewall + `_acl`-Defaults (§5.A).

### C. Error-Disclosure-Prävention (PFLICHT)

> **Exception-Messages dürfen NIEMALS an das Frontend durchgereicht werden.** Sie können interne Hostnamen, Ports, SQL-Strukturen oder Dateipfade enthalten.

```php
// ✅ KORREKT: Generische Fehlermeldung, Details serverseitig loggen
try {
    $result = $this->service->testRedirect($sourceUrl);
} catch (\Throwable $e) {
    $this->logger->error('Redirect-Test fehlgeschlagen', ['error' => $e->getMessage()]);
    return new JsonResponse(['success' => false, 'message' => 'Interner Fehler beim Testen.'], 500);
}

// ❌ VERBOTEN: Exception-Message direkt zurückgeben
catch (\Throwable $e) {
    return new JsonResponse(['message' => $e->getMessage()], 500);  // Leakt Interna!
}
```

**Frontend-Seite (Vue.js):**
```javascript
// ✅ KORREKT: Generischer Snippet
this.createNotificationError({ message: this.$tc('custom-plugin-name.notification.genericError') });

// ❌ VERBOTEN: Server-Fehlermeldung direkt anzeigen
this.createNotificationError({ message: error.response.data.message });  // Leakt Interna!
```

### D. SSRF-Prävention bei Server-seitigen HTTP-Requests

> **Wenn das Plugin HTTP-Requests vom Server aus macht** (z.B. Redirect-Test), MUSS die Ziel-URL validiert werden:

```php
// Validierung VOR dem HTTP-Request:
if (!str_starts_with($sourceUrl, '/') || str_contains($sourceUrl, '://') || str_contains($sourceUrl, '\\')) {
    return ['success' => false, 'message' => 'Ungültige URL: Nur relative Pfade erlaubt.'];
}
```

### E. IDOR-Schutz (Insecure Direct Object References)
- **Kunden-ID kommt IMMER aus `$context->getCustomer()->getId()`**, NIEMALS aus dem Request.
- Jede Criteria-Abfrage für Kunden-Daten MUSS `EqualsFilter('customerId', $loggedInCustomerId)` haben.
- Vor jeder Mutation: Eigentumsprüfung.

```php
// ✅ KORREKT: Eigentumsprüfung
$criteria = new Criteria([$entityId]);
$criteria->addFilter(new EqualsFilter('customerId', $customer->getId()));
$entity = $this->repository->search($criteria, $context)->first();
if (!$entity) { throw new AccessDeniedException(); }

// ❌ VERBOTEN: Direkter Zugriff ohne Kundenfilter
$entity = $this->repository->search(new Criteria([$entityId]), $context)->first();
```

### F. ApiAware — Least Privilege
- Nur Felder die der Storefront-JS-Code BRAUCHT bekommen `ApiAware`.
- Interne Felder (Admin-Kommentare, interne Notizen) NIEMALS `ApiAware`.
- **Prüf-Checkliste:** Wird es im Storefront gelesen? → `ApiAware`. Nur im Admin? → Kein `ApiAware`. Sensitive Daten? → Kein `ApiAware`.

### G. SQL Injection — DAL als Schutzschild
- **Native SQL ist VERBOTEN.** Der Shopware DAL parametrisiert alle Queries automatisch.
- **Einzige Ausnahme:** Lifecycle-Methoden (`postInstall()`, `postUpdate()`), wo der DAL nicht verfügbar ist. Hier DBAL mit Prepared Statements:

```php
// ✅ DBAL mit Parameter-Binding
$connection->executeStatement(
    'INSERT INTO custom_entity (...) VALUES (:id, :name)',
    ['id' => Uuid::randomHex(), 'name' => $value]
);

// ❌ String-Interpolation
$connection->executeStatement("INSERT INTO custom_entity VALUES ('$id', '$name')");
```

### H. Rate Limiting
- Jede Store-API-Route die Nutzerdaten entgegennimmt MUSS Rate-Limiting haben.
- Nutze Shopwares `RateLimiter` Service (IP-basiert, `sliding_window`).
- **Registrierung:** Via `PrependExtensionInterface` — NICHT via `packages/shopware.yaml`.
- **Empfohlene Limits:** 3-5 Requests pro 300 Sekunden pro IP für Formular-Submissions.

### I. URL- & Input-Validierung

| Eingabe-Typ | Validierung | Tool |
|---|---|---|
| E-Mail | `Assert\Email(mode: 'strict')` | Symfony Validator |
| URLs | `parse_url()` + Schema-Whitelist `['http', 'https']` | PHP-nativ |
| UUIDs (Shopware) | Regex: `/^[0-9a-f]{32}$/i` | NICHT `Assert\Uuid` (Shopware nutzt 32-Hex, nicht RFC 4122) |
| Freitext | `Assert\Length(max: 255/2000)` + `strip_tags()` | Symfony Validator + PHP |
| JSON-Felder | Rekursives `strip_tags()` auf alle String-Werte | Custom Sanitizer |
| Dropdowns/Enums | `Assert\Choice` gegen PHP Enum Werte | Symfony Validator |

### J. Output-Encoding & Sichere Kommunikation
- **Twig:** Auto-Escaping aktiv. `|raw` NUR auf trusted Admin-Content.
- **JavaScript:** `textContent` statt `innerHTML`. `encodeURIComponent()` für URL-Parameter.
- **Store-API:** `_noStore: true` auf alle personalisierten Routes — verhindert HTTP-Cache-Poisoning.
- **Links:** `target="_blank"` IMMER mit `rel="noopener noreferrer"`.

## 10. Mobile-First (PFLICHT)

### A. Grundprinzip
- **ALLE Storefront-CSS werden zuerst für Mobile geschrieben.** Desktop via `@media (min-width: ...)`.
- Shopwares SCSS nutzt `max-width` (Desktop-First) — für Plugin-eigene Komponenten gilt trotzdem Mobile-First im Geist.

### B. Breakpoints

| Breakpoint | Wert | Nutzung |
|---|---|---|
| Mobile | `max-width: 575px` | Smartphones (Portrait) |
| Tablet | `max-width: 767px` | Tablets, Landscape-Phone |
| Desktop | Standard | Alle größeren Viewports |

### C. Pflichtregeln
1. **Feste Pixel-Abstände:** Jeder fixe Wert MUSS einen Mobile-Override haben.
2. **Z-Index-Stacking:** Fixed/sticky Elemente auf Mobile prüfen — Überlappung vermeiden.
3. **Touch-Targets:** Interaktive Elemente mindestens **44×44px** (Apple HIG / WCAG 2.5.5).
4. **Font-Sizes:** Keine `px`-basierten Font-Sizes unter `14px` auf Mobile.

### D. Agent-Checkliste (Vor jedem Storefront-Commit)
- [ ] Alle `position: fixed`/`sticky` Elemente auf 375px getestet?
- [ ] Alle Pixel-Abstände haben Mobile-Override?
- [ ] Touch-Targets ≥44px?
- [ ] Z-Index-Konflikte geprüft?

### 10b. Build & Release (shopware-cli)

> **Release-ZIPs werden ausschließlich mit `shopware-cli` gebaut.** Keine manuellen ZIP-Skripte, kein `tar`, kein `zip`-Befehl. Das CLI kompiliert Admin-JS/CSS automatisch und erzeugt ein installationsfertiges ZIP.

### Build-Befehl

```bash
# Standard: baut aus dem letzten Git-Tag
shopware-cli extension zip . --output-directory dist/

# Ohne Git (z.B. für lokale Tests) — ACHTUNG: .sw-zip-blacklist greift NICHT
shopware-cli extension zip . --disable-git --output-directory dist/
```

### Pre-Build-Validierung (PFLICHT)

> **Vor jedem Build MÜSSEN alle XML-Dateien validiert werden.** Invalides XML in `services.xml`, `config.xml` oder `routes.xml` crasht das Plugin ohne brauchbare Fehlermeldung.

```bash
# XML-Syntax prüfen — MUSS vor jedem Build laufen
xmllint --noout src/Resources/config/*.xml 2>&1 || echo "XML INVALID — Build abgebrochen"

# JSON-Snippets prüfen (Storefront + Admin)
find src/Resources/snippet -name "*.json" -exec python3 -m json.tool {} > /dev/null 2>&1 \; -print || echo "Storefront Snippet JSON INVALID"
find src/Resources/app/administration -name "*.json" -path "*/snippet/*" -exec python3 -m json.tool {} > /dev/null 2>&1 \; -print || echo "Admin Snippet JSON INVALID"
```

| Datei | Typische Fehler |
|---|---|
| `services.xml` | Fehlende schließende Tags, falsche Attribute in `<service>` oder `<argument>` |
| `config.xml` | Ungültiges Nesting in `<input-field>`, fehlende `<card>` Wrapper |
| `routes.xml` | Falsche `resource`-Pfade, fehlende `type="annotation"` |
| `snippet/*.json` | Trailing Comma (häufigstes Problem), fehlende Anführungszeichen, doppelte Keys |

### `.sw-zip-blacklist` (PFLICHT)

Jedes Plugin MUSS eine `.sw-zip-blacklist` im Root haben. Diese Datei bestimmt was NICHT ins Release-ZIP kommt:

```
.agent
.gemini
.git
.gitignore
.env
.env.example
.env.local
.idea
.vscode
.DS_Store
.sw-zip-blacklist
Thumbs.db
dist
node_modules
tests
masterplan.md
architecture.md
LICENSE.md
README.md
phpunit.xml.dist
*.swp
*.swo
*.map
```

### Regeln

| Regel | Begründung |
|---|---|
| `.agent/` MUSS in der Blacklist stehen | Skills, Manifeste und Skripte haben im Release-ZIP nichts verloren |
| `.sw-zip-blacklist` greift nur im Git-Modus | Bei `--disable-git` wird die Blacklist ignoriert — nur für lokale Tests nutzen |
| Blacklist greift erst ab dem **nächsten Git-Tag** | Wenn die Blacklist neu erstellt wurde, muss erst ein neuer Tag/Release gemacht werden |
| `dist/` in `.gitignore` aufnehmen | Build-Artefakte gehören nicht ins Repository |
| Kompilierte Assets (JS/CSS) werden automatisch gebaut | `shopware-cli` führt `bin/build-js.sh` intern aus — kein manueller Build nötig |

### 10c. Testing & Coverage (PFLICHT)

> **Mindest-Coverage: 95% Line-Coverage, 90% Method-Coverage.** Jeder PR der unter diesen Schwellenwerten liegt, wird abgelehnt. Coverage wird mit `--coverage-text` bei jedem Testlauf geprüft.

#### `phpunit.xml.dist` (PFLICHT im Projekt-Root)

Jedes Plugin MUSS eine `phpunit.xml.dist` im Root haben:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.5/phpunit.xsd"
         bootstrap="tests/TestBootstrap.php"
         cacheResultFile=".phpunit.cache/test-results"
         executionOrder="depends,defects"
         colors="true">
    <testsuites>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    <coverage cacheDirectory=".phpunit.cache/code-coverage">
        <include>
            <directory suffix=".php">src</directory>
        </include>
        <exclude>
            <directory>src/Migration</directory>
            <directory>src/Resources</directory>
        </exclude>
    </coverage>
</phpunit>
```

#### `tests/TestBootstrap.php` (PFLICHT)

```php
<?php declare(strict_types=1);

use Shopware\Core\TestBootstrapper;

require __DIR__ . '/../vendor/autoload.php';

(new TestBootstrapper())
    ->setForceInstallPlugins(true)
    ->addActivePlugins('CustomPluginName')
    ->bootstrap();
```

#### Coverage-Regeln

| Metrik | Schwellenwert | Konsequenz bei Unterschreitung |
|---|---|---|
| **Line-Coverage** | ≥ 95% | PR wird abgelehnt |
| **Method-Coverage** | ≥ 90% | PR wird abgelehnt |
| **Class-Coverage** | ≥ 80% | Warnung + Begründungspflicht |

#### Was MUSS getestet werden

| Klasse/Layer | Pflicht-Tests | Typ |
|---|---|---|
| **Service-Layer** | Jede Public Method | Unit + Integration |
| **Subscriber/Listener** | Jeder Event-Handler | Integration |
| **CartCollector/Processor** | collect()/process() mit Edge-Cases | Integration |
| **Controller/Route** | Request → Response Zyklus | Integration |
| **Entity-Definition** | Feld-Typen, Flags, Relationen | Unit |
| **Migration** | update() + updateDestructive() | Integration (via TestBootstrap) |

#### Was NICHT in die Coverage zählt (excludiert in `phpunit.xml.dist`)

- `src/Migration/` — Migrationen werden via TestBootstrap implizit getestet
- `src/Resources/` — Templates, Config, Snippets (kein PHP-Code)

#### Coverage-Befehl

```bash
# Coverage-Report als Text (CI/Lokal)
php vendor/bin/phpunit --coverage-text

# Coverage-Report als HTML (lokale Analyse)
php vendor/bin/phpunit --coverage-html coverage/
```

## 11. Kill-Kriterien

| Verstoß | Konsequenz |
|---|---|
| Code geschrieben ohne vorher die offizielle Shopware-Doku zu konsultieren (§1) | **SOFORT STOPPEN** — Doku lesen, Code verwerfen, neu schreiben |
| Bei Compile-Fehler geraten statt Doku gelesen (§1) | **SOFORT STOPPEN** — `search_web site:developer.shopware.com`, dann fixen |
| Native SQL statt DAL | Sofort refactoren — DAL ist Pflicht für Cache-Invalidation |
| `customer_id` aus Request-Body statt Context | SICHERHEITSLÜCKE — sofort fixen |
| Entity-Zugriff ohne Kundenfilter (IDOR) | SICHERHEITSLÜCKE — `EqualsFilter('customerId')` erzwingen |
| HTML-Content ohne Sanitizing in DB | XSS-Vektor — `strip_tags()` erzwingen |
| `innerHTML` ohne DOMPurify im Storefront JS | XSS-Vektor — DOMPurify als ES-Modul bündeln |
| `|raw` auf User-Input in Twig | XSS-Vektor — nur für Admin-Content erlaubt |
| Interne Felder mit `ApiAware` | Daten-Leak über Store-API — Flag entfernen |
| Store-API Route ohne Rate-Limiting | Spam-Vektor — `RateLimiter` nutzen |
| POST-Route ohne CSRF-Schutz | CSRF-Vektor — `_csrf_protected: true` setzen |
| String-Interpolation in DBAL-Queries | SQL-Injection — Prepared Statements nutzen |
| Migration ohne `IF EXISTS` Guards | Deployment-Crash — immer Guards setzen |
| Hardcodierte Strings in Admin-Templates | Snippet-System nutzen |
| Admin-Feld ohne `:helpText` | UX-Verstoß — Shopbetreiber versteht das Feld nicht |
| `Resources/` auf Root-Level statt in `src/` | `services.xml` wird nicht gefunden — Plugin crashed |
| Business-Logik im Controller statt Service | Refactor in dedizierten Service |
| Storefront-UI ohne Mobile-Test | Mobile-Regression — Review vor jedem Commit |
| 6.6/6.7-only API ohne 6.5 Fallback | Bricht auf Ziel-Plattform |
| Admin-Component mit `export default` ohne `Component.register()` | Komponente wird nicht gerendert — sofort auf `Component.register()` umstellen |
| Entity-Listing ohne `listing`-Mixin | URL-State-Sync fehlt — Mixin einbinden, Criteria-Initialisierung prüfen |
| Admin-Pattern aus anderem Plugin kopiert ohne Doku-Check | Pattern kann veraltet sein — offizielle Shopware-Doku konsultieren |
| Admin-API-Route ohne `_acl`-Default | Jeder eingeloggte Admin kann zugreifen — ACL ergänzen (§5.A) |
| Exception-Message an Frontend durchgereicht (`$e->getMessage()`) | Information Disclosure — generische Meldung verwenden (§9.C) |
| Server-seitiger HTTP-Request ohne URL-Validierung | SSRF-Vektor — relative Pfade erzwingen (§9.D) |
| `RedirectResponse` mit externer URL aus DB | Open Redirect — Target-URLs auf relative Pfade validieren |
| PII ungekürzt in DB (User-Agent, Referer) | DSGVO-Verstoß — minimieren (→ `dsgvo_compliance` Skill) |
| `uninstall()` ohne `keepUserData()`-Check (§2b) | Daten werden bei jeder Deinstallation gelöscht — auch bei Reinstall |
| `parent::uninstall()` fehlt in `uninstall()` (§2b) | Plugin-Registrierung bleibt in Shopware-Core — sofort ergänzen |
| `scheduled_task`-Einträge nicht in `uninstall()` gelöscht (§2b) | Toter Cronjob bleibt zurück — wirft bei jeder Ausführung Fehler (verifiziert) |
| Plugin-Klasse ohne alle 5 Lifecycle-Methoden (§2b) | Undokumentiertes Verhalten — alle Methoden explizit implementieren |
| Release-ZIP manuell gebaut statt mit `shopware-cli` (§10b) | Kompilierte Assets fehlen oder sind veraltet |
| `.agent/` Ordner im Release-ZIP (§10b) | Skills und Manifeste landen beim Kunden — `.sw-zip-blacklist` prüfen |
| `composer.json` Vendor-Prefix nicht `growandstyle` (§2) | Einheitlichkeit — `gsd`, `grow-and-style` etc. sofort korrigieren |
| `version`-Feld in `composer.json` vorhanden (§2) | Version-Drift — Feld entfernen, `shopware-cli` injiziert aus Git-Tag |
| `NotEqualsFilter` oder andere nicht-existierende DAL-Filter verwendet (§3.E) | Compile-Error — `NandFilter([new EqualsFilter(...)])` nutzen |
| `ReferenceVersionField` ohne expliziten `storageName` (§3.C2) | Auto-generierter Name passt nicht zur DB-Spalte — `storageName` immer angeben |
| `v-model:value` statt `v-model` auf `sw-single-select` (§1b) | Keine Reaktivität — Vue 2 Compat-Layer emittiert `update:modelValue` |
| `inline-edit-save` Handler nutzt 1. Argument als Record (§6.C3) | 1. Argument ist Promise, Record kommt als 2. Argument |
| `sw-tabs` ohne `position-identifier`-Prop (§6.D2) | Pflicht-Prop (`required: true`) — Vue-Warnung + instabiles Rendering |
| `@new-item-active`-Handler nutzt Parameter direkt als String (§6.D2) | Event liefert Vue-Instanz, nicht String — `tabItem.name` verwenden |
| `#content`-Slot in `sw-tabs` verwendet (§6.D2) | Slot existiert nicht in 6.5.x — Content außerhalb mit `v-if` rendern |
| `loginService.getHeader()` aufgerufen (§6.B2) | Methode existiert nicht — `loginService.getToken()` verwenden |
| DAL-Property-Name statt physischen Spaltennamen in DBAL-INSERT (§3.C3) | Migration crasht — `DESCRIBE {table}` prüfen, physische Spalte verwenden |
| Line-Coverage unter 95% oder Method-Coverage unter 90% (§10c) | PR wird abgelehnt — fehlende Tests nachreichen |
| Plugin ohne `phpunit.xml.dist` im Root (§10c) | Pflichtdatei — vor erstem Commit anlegen |
| Plugin ohne `tests/TestBootstrap.php` (§10c) | Tests nicht ausführbar — Bootstrapper anlegen |
