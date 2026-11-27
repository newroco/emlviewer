<?php

declare(strict_types=1);

namespace OCA\EmlViewer\Tests\Integration\Controller;

use OCA\EmlViewer\AppInfo\Application;
use OCA\EmlViewer\Controller\PageController;
use OCP\AppFramework\App;
use OCP\Server;
use PHPUnit\Framework\TestCase;


/**
 * This test shows how to make a small Integration Test. Query your class
 * directly from the container, only pass in mocks if needed and run your tests
 * against the database
 */
class AppTest extends TestCase
{

    private $container;

    public function setUp(): void
    {
        parent::setUp();
        $app = new App('emlviewer');
        $this->container = $app->getContainer();
    }

    public function testAppInstalled()
    {
        $appManager = $this->container->get('OCP\App\IAppManager');
        $this->assertTrue($appManager->isInstalled('emlviewer'));
        $this->assertInstanceOf(PageController::class, $this->container->get(PageController::class));
    }

    public function testApplicationBootUsesAvailableNextcloudApis(): void
    {
        $context = $this->createMock(\OCP\AppFramework\Bootstrap\IBootContext::class);
        $context->expects(self::exactly(2))->method('injectFn')->willReturnCallback(function (callable $callback) {
            $arguments = array_map(fn ($parameter) => Server::get($parameter->getType()->getName()),
                (new \ReflectionFunction($callback))->getParameters());
            $callback(...$arguments);
        });
        (new Application())->boot($context);
    }

}
