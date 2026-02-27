<?php

namespace OCA\EmlViewer\Storage;

use Exception;
use OCP\IUserSession;
use OCP\Files\IRootFolder;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\Storage\StorageException;


class AuthorStorage
{
    private ?Folder $storage = null;

    public function __construct(
        IRootFolder $rootFolder,
        IUserSession $userSession,
    ) {
        $userId = $userSession->getUser()?->getUID();
        if ($userId) {
            $this->storage = $rootFolder->getUserFolder($userId);
        }
    }

    public function emlFileContent(string $filePath): string
    {
        try {
            $file = $this->storage?->get($filePath);
            if ($file) {
                return $file->getContent();
            }
        } catch (Exception $e) {
            if (get_class($e) === "OCP\Files\NotFoundException") throw new NotFoundException('Could not find file: ' . $filePath);
            throw $e;
        }
    }
}
