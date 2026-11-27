<?php

declare(strict_types=1);

namespace OCA\EmlViewer\Tests\Unit\Storage;

use OCA\EmlViewer\Storage\AuthorStorage;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class AuthorStorageTest extends TestCase {
    public function testAnonymousRequestHasNoPrivateFileAccess(): void {
        $storage = new AuthorStorage($this->createStub(IRootFolder::class), $this->createStub(IUserSession::class));
        $this->expectException(NotPermittedException::class);
        $storage->emlFileContent('/private.eml');
    }
}
