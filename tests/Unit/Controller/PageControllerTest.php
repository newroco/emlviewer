<?php

declare(strict_types=1);

namespace OCA\EmlViewer\Tests\Unit\Controller;

use OCA\EmlViewer\Controller\PageController;
use OCA\EmlViewer\Storage\AuthorStorage;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Share\IManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class PageControllerTest extends TestCase
{
    private IRequest&MockObject $request;

    private AuthorStorage&MockObject $storage;

    private IManager&MockObject $shareManager;

    private LoggerInterface&MockObject $logger;

    private IURLGenerator&MockObject $urlGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = $this->createMock(IRequest::class);
        $this->storage = $this->createMock(AuthorStorage::class);
        $this->shareManager = $this->createMock(IManager::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->urlGenerator = $this->createMock(IURLGenerator::class);
    }

    public function testGetRawHeadersFormatsParserHeaders(): void
    {
        $message = new class {
            public function getRawHeaders(): array
            {
                return [
                    ['From', 'Alice <alice@example.com>'],
                    ['Subject', 'Hello'],
                    ['X-Trace', "abc\r\n\tcontinued"],
                ];
            }
        };

        $controller = $this->getMockBuilder(PageController::class)
            ->setConstructorArgs([
                'emlviewer',
                $this->request,
                $this->storage,
                $this->shareManager,
                $this->logger,
                $this->urlGenerator,
            ])
            ->onlyMethods(['getMessage'])
            ->getMock();
        $controller->method('getMessage')->willReturn($message);

        $this->assertSame(
            "From: Alice <alice@example.com>\nSubject: Hello\nX-Trace: abc\n\tcontinued",
            $controller->getRawHeaders()
        );
    }

    public function testEmlPrintProvidesRawHeadersToPreviewTemplate(): void
    {
        $message = new class {
            public function getHeaderValue(string $name): string
            {
                return match (strtolower($name)) {
                    'from' => 'Alice <alice@example.com>',
                    'to' => 'Bob <bob@example.com>',
                    'date' => 'Fri, 18 Sep 2026 08:00:00 +0000',
                    'subject' => 'Hello',
                    default => '',
                };
            }

            public function getHeader(string $name): ?object
            {
                return match (strtolower($name)) {
                    'to' => new class {
                        public function getRawValue(): string
                        {
                            return 'Bob <bob@example.com>';
                        }
                    },
                    'cc' => new class {
                        public function getRawValue(): string
                        {
                            return 'Carol <carol@example.com>';
                        }
                    },
                    default => null,
                };
            }

            public function getTextContent(): string
            {
                return 'Plain text';
            }

            public function getAllAttachmentParts(): array
            {
                return [];
            }
        };

        $controller = $this->getMockBuilder(PageController::class)
            ->setConstructorArgs([
                'emlviewer',
                $this->request,
                $this->storage,
                $this->shareManager,
                $this->logger,
                $this->urlGenerator,
            ])
            ->onlyMethods(['getMessage', 'getEmailHTMLContent', 'getRawHeaders'])
            ->getMock();

        $controller->method('getMessage')->willReturn($message);
        $controller->method('getEmailHTMLContent')->willReturn('<p>Hello</p>');
        $controller->method('getRawHeaders')->willReturn("From: Alice <alice@example.com>\nX-Unsafe: <script>alert(1)</script>");
        $this->urlGenerator->method('linkToRoute')->willReturnCallback(static fn (string $route, array $params = []): string => '/index.php/' . $route . '?' . http_build_query($params));

        $response = $controller->emlPrint();
        $params = $response->getParams();

        $this->assertSame("From: Alice <alice@example.com>\nX-Unsafe: <script>alert(1)</script>", $params['rawHeaders']);
        $this->assertSame('Hello', $params['subject']);
    }

    public function testEmlContentTemplateEscapesRawHeaders(): void
    {
        $output = $this->renderTemplate($this->templatePath('emlcontent.php'), [
            'from' => 'Alice <alice@example.com>',
            'to' => 'Bob <bob@example.com>',
            'cc' => '',
            'date' => 'today',
            'subject' => 'Hello',
            'attachments' => [],
            'urlAttachment' => '/attachment',
            'urlPdf' => '/pdf',
            'urlPrinter' => '/print',
            'textContent' => '',
            'rawHeaders' => "X-Unsafe: <script>alert(1)</script>\nX-Test: value",
            'htmlContent' => '<p>Hello</p>',
        ]);

        $this->assertStringContainsString('toggle-raw-headers', $output);
        $this->assertStringContainsString('aria-controls="emlviewer-raw-headers"', $output);
        $this->assertStringContainsString('aria-expanded="false"', $output);
        $this->assertStringContainsString('id="emlviewer-raw-headers"', $output);
        $this->assertStringContainsString('aria-labelledby="emlviewer-raw-headers-label"', $output);
        $this->assertStringContainsString('id="emlviewer-raw-headers-label"', $output);
        $this->assertStringContainsString('hidden', $output);
        $this->assertStringContainsString('Show full headers', $output);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $output);
    }

    public function testPrinterHeadersTemplateDoesNotRenderRawHeaders(): void
    {
        $output = $this->renderTemplate($this->templatePath('email_headers.php'), [
            'nonce' => 'nonce',
            'from' => 'Alice <alice@example.com>',
            'to' => 'Bob <bob@example.com>',
            'cc' => '',
            'date' => 'today',
            'subject' => 'Hello',
            'rawHeaders' => "X-Unsafe: <script>alert(1)</script>",
        ]);

        $this->assertStringNotContainsString('Full headers:', $output);
        $this->assertStringNotContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $output);
    }

    private function createController(): PageController
    {
        $this->urlGenerator->method('linkToRoute')
            ->willReturnCallback(static fn (string $route, array $params = []): string => '/index.php/' . $route . '?' . http_build_query($params));

        return new PageController(
            'emlviewer',
            $this->request,
            $this->storage,
            $this->shareManager,
            $this->logger,
            $this->urlGenerator
        );
    }

    private function renderTemplate(string $path, array $params): string
    {
        $_ = $params;

        ob_start();
        include $path;

        return (string)ob_get_clean();
    }

    private function templatePath(string $templateName): string
    {
        return dirname(__DIR__, 3) . '/templates/' . $templateName;
    }
}
