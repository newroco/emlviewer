<?php

declare(strict_types=1);

namespace OCA\EmlViewer\Tests\Unit\Controller;

use OCA\EmlViewer\Controller\PageController;
use OCA\EmlViewer\Storage\AuthorStorage;
use OCP\AppFramework\PublicShareController;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotPermittedException;
use OCP\IRequest;
use OCP\ISession;
use OCP\ITempManager;
use OCP\IURLGenerator;
use OCP\Share\IManager;
use OCP\Share\IShare;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class SharedEmailTest extends TestCase {
    private function controller(?string $passwordHash = null, ?string $sessionHash = null, bool $download = true, ?string $folderPath = null): PageController {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')->willReturnCallback(fn ($name, $default = null) =>
            ['eml_file' => $folderPath ?? '/sample.eml', 'share_token' => 'test-token'][$name] ?? $default);
        $file = $this->createStub(File::class);
        $file->method('getExtension')->willReturn('eml');
        $file->method('isReadable')->willReturn(true);
        $file->method('getContent')->willReturn("Subject: Shared email\r\n\r\nBody");
        $share = $this->createStub(IShare::class);
        if ($folderPath !== null) {
            $folder = $this->createMock(Folder::class);
            $folder->expects(self::never())->method('get');
            $share->method('getNode')->willReturn($folder);
        } else {
            $share->method('getNode')->willReturn($file);
        }
        $share->method('getShareType')->willReturn(IShare::TYPE_LINK);
        $share->method('canSeeContent')->willReturn(true);
        $share->method('canDownload')->willReturn($download);
        $share->method('isPasswordProtected')->willReturn($passwordHash !== null);
        $share->method('getPassword')->willReturn($passwordHash);
        $manager = $this->createStub(IManager::class);
        $manager->method('shareApiEnabled')->willReturn(true);
        $manager->method('shareApiAllowLinks')->willReturn(true);
        $manager->method('getShareByToken')->willReturn($share);
        $session = $this->createStub(ISession::class);
        $session->method('get')->willReturnCallback(fn ($key) =>
            $key === PublicShareController::DAV_AUTHENTICATED_FRONTEND
                ? json_encode($sessionHash === null ? [] : ['test-token' => $sessionHash]) : null);
        $urls = $this->createStub(IURLGenerator::class);
        $urls->method('linkToRoute')->willReturnCallback(fn ($route, $params = []) => '/' . $route . '?' . http_build_query($params));
        return new PageController('emlviewer', $request, $this->createStub(AuthorStorage::class), $manager,
            new NullLogger(), $urls, \OCP\Server::get(ITempManager::class), $session);
    }

    public function testAllExportLinksKeepTheShareToken(): void {
        $response = $this->controller()->emlPrint();
        self::assertSame('emlcontent', $response->getTemplateName());
        foreach (['urlPdf', 'urlPrinter', 'urlAttachment'] as $key) {
            self::assertStringContainsString('share_token=test-token', $response->getParams()[$key]);
        }
    }

    public function testPasswordProtectedShareRequiresAuthentication(): void {
        self::assertSame(403, $this->controller('password-hash')->emlPrint()->getStatus());
    }

    public function testAuthenticatedShareIsReadable(): void {
        self::assertSame('Shared email', $this->controller('password-hash', 'password-hash')->getMessage()->getHeaderValue('Subject'));
    }

    public function testChangingTheSharePasswordInvalidatesAuthentication(): void {
        $this->expectException(NotPermittedException::class);
        $this->controller('new-hash', 'old-hash')->getMessage();
    }

    public function testDownloadRestrictedShareCannotBeExported(): void {
        self::assertSame(403, $this->controller(download: false)->pdfPrint()->getStatus());
    }

    public function testPathCannotEscapeTheSharedFolder(): void {
        $this->expectException(NotPermittedException::class);
        $this->controller(folderPath: '/../private.eml')->getMessage();
    }
}
