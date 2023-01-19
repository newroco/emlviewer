<?php

namespace OCA\EmlViewer\Storage;

use Exception;
use \OCP\Files\NotFoundException;
use \OCP\Storage\StorageException;


class AuthorStorage
{
    private $storage;

    public function __construct($myStorage)
    {
        $this->storage = $myStorage;
    }

    public function emlFileContent($filePath)
    {
        try {
            $file = $this->storage->get($filePath);
            if ($file) {
                return $file->getContent();
            }
        } catch (Exception $e) {
            if (get_class($e) === "OCP\Files\NotFoundException") throw new NotFoundException('Could not find file: ' . $filePath);
            throw $e;
        }
    }
}