<?php declare(strict_types=1);

namespace CustomPreOrderManager\Tests\Unit\Storefront;

use PHPUnit\Framework\TestCase;

/**
 * Unit-Tests für das Storefront Delivery-Information Template und die Clean-Button CTA Architektur.
 *
 * Verifiziert nach Enterprise Tier-1 Standard (ADR-007):
 * - Semantische 2-Ebenen-Hierarchie (Headline mit Termin + dezent gestaltete Subline mit Hinweistext)
 * - Abwesenheit von unruhigen SVG-Icons in allen Button-Templates
 * - Rechtssichere Formulierung "Voraussichtlich lieferbar ab %date%"
 */
class DeliveryInformationTemplateTest extends TestCase
{
    private string $viewsPath;
    private string $snippetPath;

    protected function setUp(): void
    {
        $this->viewsPath = dirname(__DIR__, 2) . '/src/Resources/views/storefront';
        $this->snippetPath = dirname(__DIR__, 2) . '/src/Resources/snippet';
    }

    public function testDeliveryInformationTemplateDeclaresTwoLevelHierarchy(): void
    {
        $templatePath = $this->viewsPath . '/component/delivery-information.html.twig';
        static::assertFileExists($templatePath);

        $content = (string) file_get_contents($templatePath);

        // Prüfung der semantischen CSS-Klassen
        static::assertStringContainsString('preorder-delivery-information', $content);
        static::assertStringContainsString('preorder-delivery-headline', $content);
        static::assertStringContainsString('preorder-delivery-status', $content);
        static::assertStringContainsString('preorder-delivery-notice', $content);
        static::assertStringContainsString('delivery-status-indicator is-preorder', $content);

        // Prüfung der 2-Ebenen Bedingung: Hinweistext ergänzt das Datum als Subline
        static::assertStringContainsString('{% if releaseDate and releaseText %}', $content);

        // Prüfung des Snippet-Aufrufs mit Datumsparameter
        static::assertStringContainsString('"custom-preorder.badge.availableFrom"|trans({\'%date%\': releaseDate|date(\'d.m.Y\')})', $content);
    }

    public function testPreOrderButtonsDoNotContainSvgCalendarIcons(): void
    {
        $buttonTemplates = [
            'PDP Buy-Widget' => $this->viewsPath . '/page/product-detail/buy-widget-form.html.twig',
            'Component Buy-Widget' => $this->viewsPath . '/component/buy-widget/buy-widget-form.html.twig',
            'Listing-Card Action' => $this->viewsPath . '/component/product/card/action.html.twig',
        ];

        foreach ($buttonTemplates as $name => $path) {
            static::assertFileExists($path, sprintf('Template "%s" nicht gefunden', $name));
            $content = (string) file_get_contents($path);

            // Button-Klasse muss vorhanden sein
            static::assertStringContainsString('btn-preorder', $content, sprintf('Template "%s" muss btn-preorder Klasse enthalten', $name));

            // Kalender-Icon darf nicht mehr im Button gerendert werden (Design 2026)
            static::assertStringNotContainsString("{% sw_icon 'calendar'", $content, sprintf('Template "%s" darf kein calendar-Icon im Button enthalten', $name));
        }
    }

    public function testSnippetsContainVoraussichtlichWording(): void
    {
        $deSnippet = $this->snippetPath . '/custom-preorder.de-DE.json';
        $enSnippet = $this->snippetPath . '/custom-preorder.en-GB.json';

        static::assertFileExists($deSnippet);
        static::assertFileExists($enSnippet);

        $deData = json_decode((string) file_get_contents($deSnippet), true);
        $enData = json_decode((string) file_get_contents($enSnippet), true);

        $deAvailableFrom = $deData['custom-preorder']['badge']['availableFrom'] ?? '';
        $enAvailableFrom = $enData['custom-preorder']['badge']['availableFrom'] ?? '';

        // Juristisch rechtssichere Formulierung nach ADR-007
        static::assertSame('Voraussichtlich lieferbar ab %date%', $deAvailableFrom);
        static::assertSame('Expected to be available from %date%', $enAvailableFrom);
    }
}
