<?php declare(strict_types=1);

namespace CustomPreOrderManager\Tests\Unit\Administration;

use PHPUnit\Framework\TestCase;

/**
 * Unit-Tests für die Administrations-Modulstruktur und Vue.js Komponenten-Integrität.
 *
 * Verifiziert die Wohlgeformtheit der Admin-Snippets, lückenlose zweisprachige Parität
 * (de-DE / en-GB), das Vorhandensein aller Scoped Slots im Twig-Template sowie
 * den expliziten created()-Lifecycle-Hook und die DAL-Criteria nach Enterprise Tier-1 Standard (ADR-006).
 */
class AdminModuleStructureTest extends TestCase
{
    private string $adminPath;

    protected function setUp(): void
    {
        $this->adminPath = dirname(__DIR__, 3) . '/src/Resources/app/administration/src';
    }

    public function testAdminModuleFilesExist(): void
    {
        $files = [
            $this->adminPath . '/main.js',
            $this->adminPath . '/module/custom-preorder-manager/index.js',
            $this->adminPath . '/module/custom-preorder-manager/page/custom-preorder-manager-list/index.js',
            $this->adminPath . '/module/custom-preorder-manager/page/custom-preorder-manager-list/custom-preorder-manager-list.html.twig',
            $this->adminPath . '/module/custom-preorder-manager/page/custom-preorder-manager-list/custom-preorder-manager-list.scss',
            $this->adminPath . '/module/custom-preorder-manager/snippet/de-DE.json',
            $this->adminPath . '/module/custom-preorder-manager/snippet/en-GB.json',
        ];

        foreach ($files as $file) {
            static::assertFileExists($file, sprintf('Admin-Datei "%s" fehlt', basename($file)));
        }
    }

    public function testAdminSnippetsAreValidJsonAndHaveMatchingKeys(): void
    {
        $dePath = $this->adminPath . '/module/custom-preorder-manager/snippet/de-DE.json';
        $enPath = $this->adminPath . '/module/custom-preorder-manager/snippet/en-GB.json';

        $deContent = file_get_contents($dePath);
        $enContent = file_get_contents($enPath);

        static::assertIsString($deContent);
        static::assertIsString($enContent);

        $deJson = json_decode($deContent, true);
        $enJson = json_decode($enContent, true);

        static::assertIsArray($deJson, 'de-DE.json muss valides JSON sein');
        static::assertIsArray($enJson, 'en-GB.json muss valides JSON sein');

        // Rekursive Key-Prüfung auf 1:1 Parität
        $this->assertArrayKeysEqual($deJson, $enJson, 'Admin Snippets de-DE und en-GB müssen exakt dieselben Keys besitzen');
    }

    public function testAllRequiredListSnippetKeysExist(): void
    {
        $dePath = $this->adminPath . '/module/custom-preorder-manager/snippet/de-DE.json';
        $json = json_decode((string) file_get_contents($dePath), true);

        $listKeys = $json['custom-preorder-manager']['list'] ?? [];

        $expectedKeys = [
            'cardTitle',
            'columnOrderNumber',
            'columnCustomer',
            'columnDate',
            'columnStatus',
            'columnAmount',
            'searchPlaceholder',
            'buttonRefresh',
            'contextViewOrder',
            'emptyStateTitle',
            'emptyStateSubline',
        ];

        foreach ($expectedKeys as $key) {
            static::assertArrayHasKey($key, $listKeys, sprintf('Snippet-Key "%s" fehlt im list-Bereich', $key));
            static::assertNotEmpty($listKeys[$key], sprintf('Snippet-Key "%s" darf nicht leer sein', $key));
        }
    }

    public function testAdminTemplateContainsRequiredScopedSlotsAndComponents(): void
    {
        $twigPath = $this->adminPath . '/module/custom-preorder-manager/page/custom-preorder-manager-list/custom-preorder-manager-list.html.twig';
        $content = (string) file_get_contents($twigPath);

        $requiredElements = [
            'sw-entity-listing' => '<sw-entity-listing',
            'search-bar slot' => '<template #search-bar>',
            'smart-bar-actions slot' => '<template #smart-bar-actions>',
            'column orderNumber slot' => '<template #column-orderNumber="{ item }">',
            'column customer slot' => '<template #column-orderCustomer.firstName="{ item }">',
            'column date slot' => '<template #column-orderDateTime="{ item }">',
            'column status slot' => '<template #column-stateMachineState.name="{ item }">',
            'column amount slot' => '<template #column-amountTotal="{ item }">',
            'actions context menu slot' => '<template #actions="{ item }">',
            'empty-state component' => '<sw-empty-state',
            'empty-state svg' => 'custom-preorder-manager-list__empty-state-svg',
            'deep-link to sw.order.detail' => "to=\"{ name: 'sw.order.detail'",
            'deep-link to sw.customer.detail' => "to=\"{ name: 'sw.customer.detail'",
        ];

        foreach ($requiredElements as $name => $pattern) {
            static::assertStringContainsString($pattern, $content, sprintf('Template muss %s enthalten', $name));
        }
    }

    public function testAdminComponentDeclaresCreatedHookAndCriteriaAssociations(): void
    {
        $jsPath = $this->adminPath . '/module/custom-preorder-manager/page/custom-preorder-manager-list/index.js';
        $content = (string) file_get_contents($jsPath);

        // Lifecycle Hooks prüfen
        static::assertStringContainsString('created()', $content, 'Komponente muss created()-Hook deklarieren (ADR-006)');
        static::assertStringContainsString('createdComponent()', $content, 'Komponente muss createdComponent() implementieren');

        // Watcher prüfen
        static::assertStringContainsString('orderCriteria:', $content, 'Komponente muss orderCriteria überwachen');

        // DAL Criteria Assoziationen prüfen
        static::assertStringContainsString("addAssociation('orderCustomer')", $content, 'DAL Criteria muss orderCustomer assoziieren');
        static::assertStringContainsString("addAssociation('stateMachineState')", $content, 'DAL Criteria muss stateMachineState assoziieren');
        static::assertStringContainsString("addAssociation('currency')", $content, 'DAL Criteria muss currency assoziieren');
        static::assertStringContainsString("addAssociation('lineItems')", $content, 'DAL Criteria muss lineItems assoziieren');

        // Tag-Filter prüfen
        static::assertStringContainsString("Criteria.equals('tags.name', 'Vorbestellung')", $content, 'DAL Criteria muss nach Tag Vorbestellung filtern');

        // Helper-Filter prüfen
        static::assertStringContainsString('currencyFilter()', $content, 'Komponente muss currencyFilter bereitstellen');
        static::assertStringContainsString('dateFilter()', $content, 'Komponente muss dateFilter bereitstellen');
    }

    private function assertArrayKeysEqual(array $expected, array $actual, string $message): void
    {
        $expectedKeys = array_keys($expected);
        $actualKeys = array_keys($actual);
        sort($expectedKeys);
        sort($actualKeys);

        static::assertSame($expectedKeys, $actualKeys, $message);

        foreach ($expected as $key => $value) {
            if (is_array($value)) {
                static::assertIsArray($actual[$key], sprintf('Wert für Key "%s" muss ein Array sein', $key));
                $this->assertArrayKeysEqual($value, $actual[$key], sprintf('%s (Sub-Key: %s)', $message, $key));
            }
        }
    }
}
