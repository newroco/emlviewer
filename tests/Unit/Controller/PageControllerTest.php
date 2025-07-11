<?php

namespace OCA\EmlViewer\Tests\Unit\Controller;

use Test\TestCase;

use OCP\AppFramework\Http\TemplateResponse;

use OCA\EmlViewer\Controller\PageController;


class PageControllerTest extends TestCase
{
    private $controller;
    private $userId = 'john';

    public function setUp():void
    {
        $request = $this->getMockBuilder('OCP\IRequest')->getMock();

        $this->controller = new PageController(
            'emlviewer', $request, $this->userId
        );
    }

    public function testIndex()
    {
        $result = $this->controller->index();

        $this->assertEquals('index', $result->getTemplateName());
        $this->assertTrue($result instanceof TemplateResponse);
    }

}
