<?php declare(strict_types=1);

namespace CustomPreOrderManager\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;

/**
 * Unit-Tests für die Plugin-Konfiguration (config.xml).
 *
 * Verifiziert die Wohlgeformtheit des XML, die Existenz aller 10 Config-Keys,
 * strikte Zweisprachigkeit (de-DE / en-GB), Pflicht-HelpTexts sowie valide
 * Farb- und Datentyp-Defaults nach Enterprise Tier-1 Standard.
 */
class ConfigXmlTest extends TestCase
{
    private string $configPath;
    private \SimpleXMLElement $xml;

    protected function setUp(): void
    {
        $this->configPath = dirname(__DIR__, 3) . '/src/Resources/config/config.xml';
        static::assertFileExists($this->configPath, 'config.xml muss unter src/Resources/config/ existieren');

        $content = file_get_contents($this->configPath);
        static::assertIsString($content);

        $xml = simplexml_load_string($content);
        static::assertInstanceOf(\SimpleXMLElement::class, $xml, 'config.xml muss valides XML sein');
        $this->xml = $xml;
    }

    public function testConfigXmlHasCards(): void
    {
        static::assertGreaterThanOrEqual(3, count($this->xml->card), 'config.xml muss mindestens 3 Cards besitzen');
    }

    public function testAllCardsHaveBilingualTitles(): void
    {
        foreach ($this->xml->card as $card) {
            $titles = $card->title;
            static::assertGreaterThanOrEqual(2, count($titles), 'Jede Card muss bilinguale Titel besitzen');

            $languages = [];
            foreach ($titles as $title) {
                $lang = (string) ($title->attributes()['lang'] ?? 'de-DE');
                $languages[] = $lang;
            }

            static::assertContains('de-DE', $languages, 'Card-Titel muss de-DE enthalten');
            static::assertContains('en-GB', $languages, 'Card-Titel muss en-GB enthalten');
        }
    }

    public function testAllExpectedConfigKeysExist(): void
    {
        $expectedKeys = [
            'enableListingBadge' => 'bool',
            'enableScarcityCounter' => 'bool',
            'lowStockThreshold' => 'int',
            'buttonBackgroundColor' => 'colorpicker',
            'buttonHoverBackgroundColor' => 'colorpicker',
            'buttonTextColor' => 'colorpicker',
            'buttonHoverTextColor' => 'colorpicker',
            'buttonBorderColor' => 'colorpicker',
            'buttonHoverBorderColor' => 'colorpicker',
            'cardBorderRadius' => 'int',
            'cardBackgroundColor' => 'colorpicker',
            'cardBorderColor' => 'colorpicker',
            'cardAccentColor' => 'colorpicker',
            'cardTextColor' => 'colorpicker',
            'cardEnableShadow' => 'bool',
            'cardEnableGradient' => 'bool',
        ];

        $foundKeys = [];
        foreach ($this->xml->xpath('//input-field') as $field) {
            $name = (string) $field->name;
            $type = (string) $field['type'];
            $foundKeys[$name] = $type;
        }

        foreach ($expectedKeys as $key => $expectedType) {
            static::assertArrayHasKey($key, $foundKeys, sprintf('Feld "%s" fehlt in config.xml', $key));
            static::assertSame($expectedType, $foundKeys[$key], sprintf('Feld "%s" muss Typ "%s" besitzen', $key, $expectedType));
        }

        static::assertCount(16, $foundKeys, 'config.xml muss exakt 16 Konfigurationsfelder enthalten');
    }

    public function testAllFieldsHaveBilingualLabelsAndHelpTexts(): void
    {
        foreach ($this->xml->xpath('//input-field') as $field) {
            $fieldName = (string) $field->name;

            // Labels prüfen
            $labels = [];
            foreach ($field->label as $label) {
                $lang = (string) $label->attributes()['lang'] ?? '';
                $labels[$lang] = (string) $label;
            }
            static::assertArrayHasKey('de-DE', $labels, sprintf('Feld "%s" benötigt ein de-DE Label', $fieldName));
            static::assertArrayHasKey('en-GB', $labels, sprintf('Feld "%s" benötigt ein en-GB Label', $fieldName));
            static::assertNotEmpty($labels['de-DE'], sprintf('de-DE Label für "%s" darf nicht leer sein', $fieldName));
            static::assertNotEmpty($labels['en-GB'], sprintf('en-GB Label für "%s" darf nicht leer sein', $fieldName));

            // HelpTexts prüfen (Enterprise Pflicht)
            $helpTexts = [];
            foreach ($field->helpText as $help) {
                $lang = (string) $help->attributes()['lang'] ?? '';
                $helpTexts[$lang] = (string) $help;
            }
            static::assertArrayHasKey('de-DE', $helpTexts, sprintf('Feld "%s" benötigt einen de-DE HelpText', $fieldName));
            static::assertArrayHasKey('en-GB', $helpTexts, sprintf('Feld "%s" benötigt einen en-GB HelpText', $fieldName));
            static::assertNotEmpty($helpTexts['de-DE'], sprintf('de-DE HelpText für "%s" darf nicht leer sein', $fieldName));
            static::assertNotEmpty($helpTexts['en-GB'], sprintf('en-GB HelpText für "%s" darf nicht leer sein', $fieldName));

            // DefaultValue prüfen
            static::assertTrue(isset($field->defaultValue), sprintf('Feld "%s" muss defaultValue besitzen', $fieldName));
            static::assertNotEmpty((string) $field->defaultValue, sprintf('defaultValue für "%s" darf nicht leer sein', $fieldName));
        }
    }

    public function testColorPickerFieldsHaveValidHexDefaults(): void
    {
        $colorFields = [
            'buttonBackgroundColor',
            'buttonHoverBackgroundColor',
            'buttonTextColor',
            'buttonHoverTextColor',
            'buttonBorderColor',
            'buttonHoverBorderColor',
            'cardBackgroundColor',
            'cardBorderColor',
            'cardAccentColor',
            'cardTextColor',
        ];

        foreach ($this->xml->xpath('//input-field[@type="colorpicker"]') as $field) {
            $name = (string) $field->name;
            static::assertContains($name, $colorFields, sprintf('Unerwartetes Colorpicker-Feld "%s"', $name));

            $default = (string) $field->defaultValue;
            static::assertMatchesRegularExpression(
                '/^#[0-9a-fA-F]{6}$/',
                $default,
                sprintf('Default-Wert für Colorpicker "%s" (%s) muss ein valider 6-stelliger Hex-Farbcode sein', $name, $default)
            );
        }
    }

    public function testBooleanFieldsHaveBooleanDefaults(): void
    {
        foreach ($this->xml->xpath('//input-field[@type="bool"]') as $field) {
            $name = (string) $field->name;
            $default = (string) $field->defaultValue;
            static::assertContains(
                $default,
                ['true', 'false', '1', '0'],
                sprintf('Boolean-Feld "%s" muss true oder false als defaultValue besitzen', $name)
            );
        }
    }

    public function testIntegerFieldsHavePositiveDefaults(): void
    {
        foreach ($this->xml->xpath('//input-field[@type="int"]') as $field) {
            $name = (string) $field->name;
            $default = (int) $field->defaultValue;
            static::assertGreaterThan(
                0,
                $default,
                sprintf('Integer-Feld "%s" muss einen positiven Default-Wert besitzen', $name)
            );
        }
    }
}
