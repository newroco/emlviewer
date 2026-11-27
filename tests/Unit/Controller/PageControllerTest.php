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
        $urls->method('linkToRoute')->willReturnCallback(fn ($route, $params = []) => '/' . $route . '?' . http_build_query($params));
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

    private function inlineEmail(string $html, array $attachments): PageController {
        $body = "--inline-boundary\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n$html\r\n";
        foreach ($attachments as [$id, $filename, $type, $content]) {
            $body .= "--inline-boundary\r\nContent-Type: $type; name=\"$filename\"\r\n"
                . "Content-Disposition: inline; filename=\"$filename\"\r\nContent-ID: <$id>\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($content), 76, "\r\n");
        }
        return $this->controller($body . "--inline-boundary--\r\n", 'multipart/related; boundary="inline-boundary"');
    }

    private function png(int $red, int $green): string {
        $image = imagecreatetruecolor(2, 2);
        imagefill($image, 0, 0, imagecolorallocate($image, $red, $green, 0));
        ob_start();
        imagepng($image);
        return ob_get_clean();
    }

    private function imageSources(string $html): array {
        if ($html === '') {
            return [];
        }
        $document = new \DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        return array_map(fn ($image) => $image->getAttribute('src'), iterator_to_array($document->getElementsByTagName('img')));
    }

    public function testInlineContentIdsMatchCompleteUrlsWithoutChangingText(): void {
        $red = $this->png(255, 0);
        $green = $this->png(0, 255);
        $html = $this->inlineEmail('<p>Grüße: cid:image1 and cid:image10.</p>'
            . '<img src="cid:image1" alt="cid:image1"><img src="cid:image10">', [
                ['image1', 'red.png', 'image/png', $red],
                ['image10', 'green.png', 'image/png', $green],
            ])->emlPrint()->getParams()['htmlContent'];
        self::assertSame(['data:image/png;base64,' . base64_encode($red), 'data:image/png;base64,' . base64_encode($green)], $this->imageSources($html));
        self::assertStringContainsString('Grüße: cid:image1 and cid:image10.', $html);
        self::assertStringContainsString('alt="cid:image1"', $html);
    }

    public function testInlineFilenameFallbackMatchesCompleteUrls(): void {
        $red = $this->png(255, 0);
        $green = $this->png(0, 255);
        $html = $this->inlineEmail('<img src="cid:image1"><img src="cid:image10">', [
            ['other-first', 'image1', 'image/png', $red],
            ['other-second', 'image10', 'image/png', $green],
        ])->emlPrint()->getParams()['htmlContent'];
        self::assertSame(['data:image/png;base64,' . base64_encode($red), 'data:image/png;base64,' . base64_encode($green)], $this->imageSources($html));
    }

    public function testInlineContentIdTakesPriorityOverAnotherAttachmentsFilename(): void {
        $red = $this->png(255, 0);
        $green = $this->png(0, 255);
        $html = $this->inlineEmail('<img src="cid:logo">', [
            ['other', 'logo', 'image/png', $red],
            ['logo', 'green.png', 'image/png', $green],
        ])->emlPrint()->getParams()['htmlContent'];
        self::assertSame(['data:image/png;base64,' . base64_encode($green)], $this->imageSources($html));
    }

    public function testInlineContentIdsDecodeUrlEscapesAndHtmlEntities(): void {
        $png = $this->png(255, 0);
        $html = $this->inlineEmail('<img src=CiD:logo%2Fpart+id%40example.test><img src="cid:a&amp;b@example.test">', [
            ['logo/part+id@example.test', 'encoded.png', 'image/png', $png],
            ['a&b@example.test', 'entity.png', 'image/png', $png],
        ])->emlPrint()->getParams()['htmlContent'];
        self::assertSame(array_fill(0, 2, 'data:image/png;base64,' . base64_encode($png)), $this->imageSources($html));
    }

    public function testInlineAttachmentLinkPreservesItsQueryParameters(): void {
        $html = $this->inlineEmail('<a href="cid:document">Attachment</a>', [
            ['document', 'sample.bin', 'application/octet-stream', 'Synthetic attachment'],
        ])->emlPrint()->getParams()['htmlContent'];
        $document = new \DOMDocument();
        $document->loadHTML($html, LIBXML_NONET);
        self::assertSame('/emlviewer.page.attachment?eml_file=%2Fsample.eml&att=0',
            $document->getElementsByTagName('a')->item(0)->getAttribute('href'));
    }

    public function testWebpIsConvertedToPngForPreviewPrintAndPdf(): void {
        if (!function_exists('imagewebp')) {
            self::markTestSkipped('GD WebP support is not available.');
        }
        $image = imagecreatetruecolor(2, 2);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 0, 0, 127));
        imagesetpixel($image, 1, 1, imagecolorallocatealpha($image, 0, 255, 0, 0));
        ob_start();
        imagewebp($image);
        $controller = $this->inlineEmail('<img src="cid:webp-image">', [
            ['webp-image', 'sample.webp', 'image/webp', ob_get_clean()],
        ]);
        $html = $controller->emlPrint()->getParams()['htmlContent'];
        $sources = $this->imageSources($html);
        self::assertCount(1, $sources);
        self::assertStringStartsWith('data:image/png;base64,', $sources[0]);
        $png = base64_decode(substr($sources[0], strlen('data:image/png;base64,')), true);
        self::assertSame('image/png', getimagesizefromstring($png)['mime']);
        $converted = imagecreatefromstring($png);
        self::assertSame(2, imagesx($converted));
        self::assertSame(127, imagecolorsforindex($converted, imagecolorat($converted, 0, 0))['alpha']);
        self::assertStringContainsString($sources[0], $controller->emlPrint(true)->render());
        $pdf = $controller->pdfPrint();
        self::assertSame(200, $pdf->getStatus());
        self::assertStringStartsWith('%PDF-', $pdf->render());
        self::assertMatchesRegularExpression('~/Subtype\s*/Image\b~', $pdf->render());
    }

    public function testInvalidWebpDoesNotBreakRenderingOrBypassSanitization(): void {
        $controller = $this->inlineEmail('<img src="cid:invalid" onerror="alert(1)"><p>Safe</p>', [
            ['invalid', 'invalid.webp', 'image/webp', '<svg onload="alert(2)"></svg>'],
        ]);
        $html = $controller->emlPrint()->getParams()['htmlContent'];
        self::assertSame([], $this->imageSources($html));
        self::assertStringContainsString('<p>Safe</p>', $html);
        self::assertStringNotContainsString('onerror', $html);
        self::assertSame(200, $controller->pdfPrint()->getStatus());
    }
}
