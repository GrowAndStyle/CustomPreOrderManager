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
        $this->viewsPath = dirname(__DIR__, 3) . '/src/Resources/views/storefront';
        $this->snippetPath = dirname(__DIR__, 3) . '/src/Resources/snippet';
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

    public function testDeliveryInformationTemplateDeclaresDesign2026CardElements(): void
    {
        $templatePath = $this->viewsPath . '/component/delivery-information.html.twig';
        static::assertFileExists($templatePath);

        $content = (string) file_get_contents($templatePath);

        // Design 2026 Card-Elemente
        static::assertStringContainsString('preorder-delivery-card', $content);
        static::assertStringContainsString('preorder-card-icon-wrapper', $content);
        static::assertStringContainsString('preorder-card-icon', $content);
        static::assertStringContainsString('preorder-card-body', $content);
        static::assertStringContainsString('preorder-pill-badge', $content);
        static::assertStringContainsString('preorder-pulse-dot', $content);
        static::assertStringContainsString('preorder-notice-icon', $content);
    }

    public function testBaseTemplateInjectsCardCssVariables(): void
    {
        $baseTemplatePath = $this->viewsPath . '/base.html.twig';
        static::assertFileExists($baseTemplatePath);

        $content = (string) file_get_contents($baseTemplatePath);

        // Prüfung der dynamischen CSS-Custom-Properties für die Card
        static::assertStringContainsString('--custom-preorder-card-radius', $content);
        static::assertStringContainsString('--custom-preorder-card-bg', $content);
        static::assertStringContainsString('--custom-preorder-card-border', $content);
        static::assertStringContainsString('--custom-preorder-card-accent', $content);
        static::assertStringContainsString('--custom-preorder-card-color', $content);
        static::assertStringContainsString('--custom-preorder-card-shadow', $content);
        static::assertStringContainsString('--custom-preorder-card-bg-render', $content);
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
        $deSnippet = $this->snippetPath . '/de_DE/storefront.de-DE.json';
        $enSnippet = $this->snippetPath . '/en_GB/storefront.en-GB.json';

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

    public function testProductCardActionTemplateMaintainsGridSymmetryWithoutInfoBox(): void
    {
        $templatePath = $this->viewsPath . '/component/product/card/action.html.twig';
        static::assertFileExists($templatePath);

        $content = (string) file_get_contents($templatePath);

        static::assertStringContainsString('btn-preorder', $content);
        // Um 100% horizontale Symmetrie im Grid zu sichern, darf keine Info-Box über dem Button liegen
        static::assertStringNotContainsString('product-card-preorder-info', $content);
    }



    public function testLineItemLabelTemplateContainsStructuredInfoAndNoEmojis(): void
    {
        $templatePath = $this->viewsPath . '/component/line-item/element/label.html.twig';
        static::assertFileExists($templatePath);

        $content = (string) file_get_contents($templatePath);

        static::assertStringContainsString('line-item-preorder-info', $content);
        static::assertStringContainsString('line-item-preorder-pill', $content);
        static::assertStringContainsString('line-item-preorder-status', $content);
        static::assertStringContainsString('line-item-preorder-notice', $content);
        static::assertStringContainsString('preorder-pulse-dot', $content);

        // Altes Emoji darf keinesfalls mehr im Template vorkommen
        static::assertStringNotContainsString('📅', $content);
    }

    public function testOffcanvasCartTemplateContainsMixedCartNotice(): void
    {
        $templatePath = $this->viewsPath . '/component/checkout/offcanvas-cart.html.twig';
        static::assertFileExists($templatePath);

        $content = (string) file_get_contents($templatePath);

        // Offcanvas-Cart bindet das Partial per sw_include ein
        static::assertStringContainsString('mixed-cart-notice', $content);
        static::assertStringContainsString('sw_include', $content);
        static::assertStringContainsString('component/preorder/mixed-cart-notice.html.twig', $content);

        // Die eigentliche Logik liegt jetzt im Partial
        $partialPath = $this->viewsPath . '/component/preorder/mixed-cart-notice.html.twig';
        static::assertFileExists($partialPath);

        $partialContent = (string) file_get_contents($partialPath);
        static::assertStringContainsString('mixed-cart-notice-headline', $partialContent);
        static::assertStringContainsString('mixed-cart-notice-body', $partialContent);
        static::assertStringContainsString('enableMixedCartNotice', $partialContent);
        static::assertStringContainsString('mixedCartNoticeMode', $partialContent);
    }

    public function testCheckoutCartTemplateUsesCorrectBlockName(): void
    {
        $templatePath = $this->viewsPath . '/page/checkout/cart/index.html.twig';
        static::assertFileExists($templatePath);

        $content = (string) file_get_contents($templatePath);

        // Block-Name gemäß Shopware 6.5 Quellcode
        static::assertStringContainsString('page_checkout_cart', $content);
        static::assertStringNotContainsString('page_checkout_cart_container', $content);
        static::assertStringContainsString('sw_include', $content);
        static::assertStringContainsString('component/preorder/mixed-cart-notice.html.twig', $content);
    }

    public function testCheckoutConfirmTemplateUsesCorrectBlockName(): void
    {
        $templatePath = $this->viewsPath . '/page/checkout/confirm/index.html.twig';
        static::assertFileExists($templatePath);

        $content = (string) file_get_contents($templatePath);

        // Block-Name gemäß Shopware 6.5 Quellcode
        static::assertStringContainsString('page_checkout_confirm', $content);
        static::assertStringNotContainsString('page_checkout_confirm_container', $content);
        static::assertStringContainsString('sw_include', $content);
        static::assertStringContainsString('component/preorder/mixed-cart-notice.html.twig', $content);
    }

    public function testAccountOrderHistoryTemplatesDisplayPreOrderStatus(): void
    {
        $orderDetailItemPath = $this->viewsPath . '/page/account/order-history/order-detail-list-item.html.twig';
        $orderItemPath = $this->viewsPath . '/page/account/order-history/order-item.html.twig';

        static::assertFileExists($orderDetailItemPath);
        static::assertFileExists($orderItemPath);

        $detailContent = (string) file_get_contents($orderDetailItemPath);
        static::assertStringContainsString('lineItem.payload.isPreOrder', $detailContent);
        static::assertStringContainsString('line-item-preorder-info', $detailContent);

        $overviewContent = (string) file_get_contents($orderItemPath);
        static::assertStringContainsString('order-overview-preorder-badge', $overviewContent);
    }

    public function testScarcityPartialContainsFullLogic(): void
    {
        $partialPath = $this->viewsPath . '/component/preorder/scarcity-badge.html.twig';
        static::assertFileExists($partialPath);

        $content = (string) file_get_contents($partialPath);

        // Gesamte Scarcity-Logik liegt im Partial (Single Source of Truth, ADR-010)
        static::assertStringContainsString('lowStockThreshold', $content);
        static::assertStringContainsString('custom_preorder_inbound_stock', $content);
        static::assertStringContainsString('custom_preorder_sold_count', $content);
        static::assertStringContainsString('custom-preorder.badge.urgentFewLeft', $content);
    }

    public function testScarcityPartialSupportsCardVariant(): void
    {
        $partialPath = $this->viewsPath . '/component/preorder/scarcity-badge.html.twig';
        $content = (string) file_get_contents($partialPath);

        // Card-Variante: SVG-Icon + preorder-scarcity-line Klasse
        static::assertStringContainsString('preorder-scarcity-line', $content);
        static::assertStringContainsString('preorder-scarcity-icon', $content);
        static::assertStringContainsString('preorder-scarcity-text', $content);
        static::assertStringContainsString("variant|default('card') == 'card'", $content);
    }

    public function testScarcityPartialSupportsListingVariant(): void
    {
        $partialPath = $this->viewsPath . '/component/preorder/scarcity-badge.html.twig';
        $content = (string) file_get_contents($partialPath);

        // Listing-Variante: kompakter Badge ohne Icon
        static::assertStringContainsString('preorder-scarcity-listing', $content);
    }

    public function testDeliveryCardIncludesScarcityPartial(): void
    {
        $templatePath = $this->viewsPath . '/component/delivery-information.html.twig';
        $content = (string) file_get_contents($templatePath);

        // Scarcity in Delivery-Card integriert (ADR-010)
        static::assertStringContainsString('scarcityDisplayMode', $content);
        static::assertStringContainsString('sw_include', $content);
        static::assertStringContainsString('component/preorder/scarcity-badge.html.twig', $content);
        static::assertStringContainsString("variant: 'card'", $content);
    }

    public function testListingBadgesStrippedToMinimumAfterAdr011(): void
    {
        // Nach ADR-011: badges.html.twig delegiert Overlays an box-standard.html.twig.
        // Keine Overlay-Logik mehr in badges.html.twig.
        $templatePath = $this->viewsPath . '/component/product/card/badges.html.twig';
        static::assertFileExists($templatePath);

        $content = (string) file_get_contents($templatePath);

        static::assertStringNotContainsString('preorder-date-banner', $content, 'Date-Banner darf nicht mehr in badges.html.twig stehen (ADR-011)');
        static::assertStringNotContainsString('preorder-scarcity-listing', $content, 'Scarcity-Overlay darf nicht mehr in badges.html.twig stehen (ADR-011)');
        static::assertStringNotContainsString('custom_preorder_inbound_stock', $content, 'Scarcity-Logik darf nicht mehr in badges.html.twig stehen (ADR-011)');
    }

    public function testBoxStandardContainsListingOverlaysAdr011(): void
    {
        // ADR-011: Overlays liegen in box-standard.html.twig, block component_product_box_image.
        // Verifizierter Block gegen Shopware v6.5.8.19 (offiziell).
        $templatePath = $this->viewsPath . '/component/product/card/box-standard.html.twig';
        static::assertFileExists($templatePath, 'box-standard.html.twig muss nach ADR-011 vorhanden sein');

        $content = (string) file_get_contents($templatePath);

        // Korrekter Block-Name (verifiziert v6.5.8.19)
        static::assertStringContainsString('component_product_box_image', $content);

        // Korrekter Containing Block
        static::assertStringContainsString('preorder-image-overlay-root', $content);

        // enableListingBadge-Gate
        static::assertStringContainsString('enableListingBadge', $content);

        // Date-Banner komplett
        static::assertStringContainsString('product-preorder-date-banner', $content);
        static::assertStringContainsString('preorder-banner-prefix', $content);
        static::assertStringContainsString('preorder-banner-date', $content);

        // Scarcity-Badge: listing-spezifische Snippet-Keys (entkoppelt von badge.urgentFewLeft)
        static::assertStringContainsString('preorder-scarcity-listing', $content);
        static::assertStringContainsString('scarcity-listing-prefix', $content);
        static::assertStringContainsString('scarcity-listing-count', $content);
        static::assertStringContainsString('custom-preorder.listing.scarcityPrefix', $content);
        static::assertStringContainsString('custom-preorder.listing.scarcityCount', $content);
        static::assertStringContainsString('custom_preorder_inbound_stock', $content);
        static::assertStringContainsString("'listing_only'", $content);
        static::assertStringContainsString("'everywhere'", $content);

        // urgentFewLeft darf nicht mehr im Listing-Overlay stehen (Card/PDP-only)
        static::assertStringNotContainsString(
            'custom-preorder.badge.urgentFewLeft',
            $content,
            'urgentFewLeft ist Card/PDP-only — Listing nutzt listing.scarcityPrefix/Count'
        );
    }

    public function testBaseScssHasCorrectOverlayPositioningAdr011(): void
    {
        // ADR-011: Kein Pixel-Hack, korrekter Containing Block, explizite top/bottom-Werte,
        // border-radius + overflow: hidden für sauberes Frosted-Glass-Clipping
        $scssPath = dirname(__DIR__, 3) . '/src/Resources/app/storefront/src/scss/base.scss';
        $content = (string) file_get_contents($scssPath);

        // Containing Block vorhanden
        static::assertStringContainsString('preorder-image-overlay-root', $content);
        static::assertStringContainsString('position: relative', $content);

        // Pixel-Hack entfernt
        static::assertStringNotContainsString('top: calc(var(--bs-card-spacer-y', $content, 'Pixel-Hack darf nach ADR-011 nicht mehr im SCSS stehen');
        static::assertStringNotContainsString('translateY(-100%)', $content, 'transform-Hack darf nach ADR-011 nicht mehr im SCSS stehen');

        // border-radius: Date-Banner unten abgerundet, Scarcity oben abgerundet
        static::assertStringContainsString('border-radius: 0 0 8px 8px', $content, 'Date-Banner muss border-radius 0 0 8px 8px besitzen');
        static::assertStringContainsString('border-radius: 8px 8px 0 0', $content, 'Scarcity-Badge muss border-radius 8px 8px 0 0 besitzen');

        // overflow: hidden zwingend für backdrop-filter-Clipping an Rundungen
        $dateBannerSection = substr($content, strpos($content, '.product-preorder-date-banner'), 600);
        static::assertStringContainsString('overflow: hidden', $dateBannerSection, 'Date-Banner muss overflow: hidden für border-radius-Clipping besitzen');

        $scarcitySection = substr($content, strpos($content, '.preorder-scarcity-listing {'), 600);
        static::assertStringContainsString('overflow: hidden', $scarcitySection, 'Scarcity-Badge muss overflow: hidden für border-radius-Clipping besitzen');
    }

    public function testBadgesTemplateSupressesDiscountBadgeForPreorder(): void
    {
        // Rabatt-% Badge wird unterdrückt wenn Preorder-Overlays aktiv.
        // Verifiziert gegen Shopware 6.5.8.19: Block component_product_badges_discount.
        $badgesPath = $this->viewsPath . '/component/product/card/badges.html.twig';
        $content = (string) file_get_contents($badgesPath);

        // Override des korrekten 6.5.x Blocks vorhanden
        static::assertStringContainsString('component_product_badges_discount', $content);

        // Preorder-Logik korrekt verknüpft
        static::assertStringContainsString('custom_preorder_active', $content);
        static::assertStringContainsString('enableListingBadge', $content);
        static::assertStringContainsString('hasStock', $content);

        // Fallback: parent() wird gerufen wenn kein Preorder-Overlay aktiv
        static::assertStringContainsString('{{ parent() }}', $content);
    }

    public function testBuyWidgetTemplatesRemovedAfterCardIntegration(): void
    {
        // Buy-Widget Templates nach ADR-010 gelöscht — Badge wanderte in Delivery-Card
        $componentPath = $this->viewsPath . '/component/buy-widget/buy-widget.html.twig';
        $pagePath = $this->viewsPath . '/page/product-detail/buy-widget.html.twig';

        static::assertFileDoesNotExist($componentPath, 'component/buy-widget/buy-widget.html.twig muss nach ADR-010 gelöscht sein');
        static::assertFileDoesNotExist($pagePath, 'page/product-detail/buy-widget.html.twig muss nach ADR-010 gelöscht sein');
    }

    public function testScarcityPartialUsesCorrectConfigKey(): void
    {
        // Sicherstellen, dass der alte Config-Key nicht mehr referenziert wird
        $partialPath = $this->viewsPath . '/component/preorder/scarcity-badge.html.twig';
        $content = (string) file_get_contents($partialPath);

        static::assertStringNotContainsString('enableScarcityCounter', $content, 'Alter Config-Key enableScarcityCounter darf nicht mehr im Partial stehen');
    }
}

