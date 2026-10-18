<?php

declare(strict_types=1);

namespace OCA\EmlViewer\Tests\Unit\Controller;

use OCA\EmlViewer\Controller\PageController;
use OCA\EmlViewer\Storage\AuthorStorage;
use OCP\IRequest;
use OCP\ITempManager;
use OCP\IURLGenerator;
use OCP\Share\IManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class PageControllerTest extends TestCase {
    protected function controller(string $body, string $contentType = 'text/plain', ?string $addressHeaders = null): PageController {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')->willReturnCallback(fn ($name, $default = null) =>
            $name === 'eml_file' ? '/sample.eml' : $default);
        $storage = $this->createMock(AuthorStorage::class);
        $storage->expects(self::once())->method('emlFileContent')->with('/sample.eml')->willReturn(
            ($addressHeaders ?? "From: Alice <alice@example.com>\r\nTo: Bob <bob@example.com>\r\n")
            . "Subject: Test\r\nMIME-Version: 1.0\r\nContent-Type: $contentType; charset=UTF-8\r\n\r\n$body"
        );
        $urls = $this->createStub(IURLGenerator::class);
        $urls->method('linkTo')->willReturnCallback(fn ($app, $file) => '/apps/' . $app . '/' . $file);
        return new PageController('emlviewer', $request, $storage,
            $this->createStub(IManager::class), new NullLogger(),
            $urls, \OCP\Server::get(ITempManager::class),
            $this->createStub(\OCP\ISession::class));
    }

    public function testPreviewDoesNotUseRemovedNonceApi(): void {
        $response = $this->controller('Grüße & <b>plain text</b>')->emlPrint();
        self::assertSame('emlcontent', $response->getTemplateName());
        self::assertStringContainsString('Grüße &amp; &lt;b&gt;', $response->getParams()['htmlContent']);
        self::assertSame('', $response->getParams()['date']);
    }

    public function testHtmlQuotesAndUnicodeArePreserved(): void {
        $response = $this->controller('<p title="Grüße">Schöne Grüße</p>', 'text/html')->emlPrint();
        self::assertStringContainsString('title="Grüße"', $response->getParams()['htmlContent']);
    }

    public function testPrintingEmptyHtmlDoesNotDereferenceMissingBody(): void {
        $response = $this->controller('<html><head><title>Empty</title></head><body></body></html>', 'text/html')->emlPrint(true);
        self::assertSame('printcontent', $response->getTemplateName());
        self::assertStringContainsString('alice@example.com', $response->render());
    }

    public function testPdfUsesNextcloudTemporaryStorage(): void {
        $response = $this->controller('Grüße from a plain text email')->pdfPrint();
        self::assertSame(200, $response->getStatus());
        self::assertStringStartsWith('%PDF-', $response->render());
    }

    public function testActiveEmailHtmlIsRemovedBeforePrinting(): void {
        $response = $this->controller('<p onclick="alert(1)">Safe</p><script>alert(2)</script><iframe src="/settings"></iframe>', 'text/html')->emlPrint();
        $html = $response->getParams()['htmlContent'];
        self::assertStringContainsString('<p>Safe</p>', $html);
        self::assertStringNotContainsString('onclick', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<iframe', $html);
    }

    private function encodedAddressHeaders(): string {
        return "From: =?utf-8?Q?Pr=C3=BCfabsender?= <sender@example.test>\r\n"
            . "To: Testperson Eins <recipient-one@example.test>, Testperson Zwei <recipient-two@example.test>,\r\n"
            . "\t=?utf-8?B?UHLDvGZlbXBmw6RuZ2VyIERyZWk=?= <recipient-three@example.test>\r\n"
            . "Cc: =?iso-8859-1?Q?Pr=FCfkopie?= <copy@example.test>, other@example.test\r\n";
    }

    public function testAllAddressNamesAreDecodedWithoutDroppingRecipients(): void {
        $params = $this->controller('Body', addressHeaders: $this->encodedAddressHeaders())->emlPrint()->getParams();
        self::assertSame('Prüfabsender <sender@example.test>', $params['from']);
        self::assertSame('Testperson Eins <recipient-one@example.test>, Testperson Zwei <recipient-two@example.test>, Prüfempfänger Drei <recipient-three@example.test>', $params['to']);
        self::assertSame('Prüfkopie <copy@example.test>, other@example.test', $params['cc']);
    }

    public function testPrintAndPdfHeaderHtmlContainsDecodedNames(): void {
        $response = $this->controller('Body', addressHeaders: $this->encodedAddressHeaders())->emlPrint(true);
        $html = $response->render();
        $document = new \DOMDocument();
        $document->loadHTML($html, LIBXML_NONET);
        $text = $document->textContent;
        self::assertStringContainsString('Prüfabsender', $text);
        self::assertStringContainsString('Prüfempfänger Drei', $text);
        self::assertStringContainsString('Prüfkopie', $text);
        self::assertStringNotContainsString('=?', $text);
        foreach (['recipient-one', 'recipient-two', 'recipient-three'] as $recipient) {
            self::assertStringContainsString($recipient . '@example.test', $html);
        }
    }

    public function testQuotedNamesAndAddressHtmlAreEscapedInPreview(): void {
        $response = $this->controller('Body', addressHeaders: "To: \"Testperson, Vier\" <recipient-four@example.test>,\r\n"
            . "\t\"<b>Testperson</b> & Co\" <recipient-three@example.test>\r\n")->emlPrint();
        self::assertSame('"Testperson, Vier" <recipient-four@example.test>, "<b>Testperson</b> & Co" <recipient-three@example.test>', $response->getParams()['to']);
        $html = $response->render();
        self::assertStringContainsString('&lt;b&gt;Testperson&lt;/b&gt; &amp; Co', $html);
        self::assertStringNotContainsString('<b>Testperson</b>', $html);
        self::assertSame('', $response->getParams()['from']);
        self::assertSame('', $response->getParams()['cc']);
    }

    public function testPrintPageDoesNotLoadNextcloudLayoutOrTheme(): void {
        $response = $this->controller('End of message')->emlPrint(true);
        self::assertInstanceOf(\OCP\AppFramework\Http\StandaloneTemplateResponse::class, $response);
        self::assertSame('', $response->getRenderAs());
        $html = $response->render();
        self::assertSame(1, substr_count(strtolower($html), '<!doctype html>'));
        self::assertStringContainsString('/apps/emlviewer/css/emlviewer-print.css', $html);
        self::assertStringContainsString('/apps/emlviewer/js/emlviewer-print.js', $html);
        self::assertStringNotContainsString('layout-base', $html);
        self::assertStringNotContainsString('app-public', $html);
        self::assertStringContainsString('End of message', $html);
    }

    public function testPdfHtmlHasNoBrowserPrintControlsOrExternalStylesheets(): void {
        $controller = $this->controller('End of message');
        $params = $controller->emlPrint(true)->getParams();
        $headers = new \OCP\AppFramework\Http\TemplateResponse('emlviewer', 'email_headers', $params, '');
        $html = (new \ReflectionMethod(PageController::class, 'getPrintableHtml'))
            ->invoke($controller, $params['htmlContent'], $headers->render());
        self::assertStringContainsString('alice@example.com', $html);
        self::assertStringContainsString('End of message', $html);
        self::assertStringNotContainsString('printButton', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<link', $html);
    }
}
