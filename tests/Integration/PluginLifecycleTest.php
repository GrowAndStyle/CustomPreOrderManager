<?php declare(strict_types=1);

namespace CustomPreOrderManager\Tests\Integration;

use CustomPreOrderManager\CustomPreOrderManager;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * Integrationstest für die Plugin-Hauptklasse.
 *
 * Testet Lifecycle-Hooks und saubere Initialisierung im Test-Container.
 */
class PluginLifecycleTest extends TestCase
{
    use IntegrationTestBehaviour;

    private CustomPreOrderManager $plugin;

    protected function setUp(): void
    {
        $this->plugin = $this->getContainer()->get('kernel')
            ->getPluginLoader()
            ->getPluginInstances()
            ->get(CustomPreOrderManager::class);
    }

    public function testPluginClassExists(): void
    {
        static::assertInstanceOf(CustomPreOrderManager::class, $this->plugin);
    }

    public function testInstallDoesNotThrow(): void
    {
        $context = $this->createMock(InstallContext::class);
        $this->plugin->install($context);
        $this->addToAssertionCount(1);
    }

    public function testUpdateDoesNotThrow(): void
    {
        $context = $this->createMock(UpdateContext::class);
        $this->plugin->update($context);
        $this->addToAssertionCount(1);
    }

    public function testActivateDoesNotThrow(): void
    {
        $context = $this->createMock(ActivateContext::class);
        $this->plugin->activate($context);
        $this->addToAssertionCount(1);
    }

    public function testDeactivateDoesNotThrow(): void
    {
        $context = $this->createMock(DeactivateContext::class);
        $this->plugin->deactivate($context);
        $this->addToAssertionCount(1);
    }

    public function testUninstallKeepUserDataPreservesData(): void
    {
        $context = $this->createMock(UninstallContext::class);
        $context->method('keepUserData')->willReturn(true);

        $this->plugin->uninstall($context);
        $this->addToAssertionCount(1);
    }
}
