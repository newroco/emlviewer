<?php

namespace OCA\EmlViewer\Storage;

use Exception;
use OCP\IUserSession;
use OCP\Files\IRootFolder;
use OCP\Files\Folder;
use OCP\Files\File;
use OCP\Files\NotPermittedException;
use OCP\Files\NotFoundException;


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
            if ($this->storage === null) {
                throw new NotPermittedException('No authenticated user storage available');
            }

            $file = $this->storage?->get($filePath);
            if ($file instanceof File && strtolower($file->getExtension()) === 'eml' && $file->isReadable()) {
                return $file->getContent();
            }
            throw new NotPermittedException('Only readable .eml files can be previewed');
        } catch (Exception $e) {
            if ($e instanceof NotFoundException) {
                throw new NotFoundException('Could not find file: ' . $filePath);
            }
            throw $e;
        }

        throw new NotFoundException('Could not find file: ' . $filePath);
    }
}
