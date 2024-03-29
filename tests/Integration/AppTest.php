<?php

namespace OCA\EmlViewer\Tests\Integration\Controller;

use OCP\AppFramework\App;
use Test\TestCase;


/**
 * This test shows how to make a small Integration Test. Query your class
 * directly from the container, only pass in mocks if needed and run your tests
 * against the database
 */
class AppTest extends TestCase
{

    private $container;

    public function setUp()
    {
        parent::setUp();
        $app = new App('emlviewer');
        $this->container = $app->getContainer();
    }

    public function testAppInstalled()
    {
        $appManager = $this->container->get('OCP\App\IAppManager');
        $this->assertTrue($appManager->isInstalled('emlviewer'));
    }

}
