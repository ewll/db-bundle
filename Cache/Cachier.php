<?php namespace Ewll\DBBundle\Cache;

use Psr\Cache\InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class Cachier
{
    /** @var FilesystemAdapter */
    private $fileSystemCache;

    public function __construct(string $projectCacheDir)
    {
        $cacheDir = implode(DIRECTORY_SEPARATOR, [$projectCacheDir, 'Ewll', 'EntityCache']);
        $this->fileSystemCache = new FilesystemAdapter('', 0, $cacheDir);
    }

    /** @throws InvalidArgumentException */
    public function get(string $key)
    {
        $data = $this->fileSystemCache->get(
            $key,
            function () use ($key) {
                throw new RuntimeException("Cache $key must be exists here");
            });

        return $data;
    }

    /** @throws InvalidArgumentException */
    public function set(string $key, $data)
    {
        $this->fileSystemCache->delete($key);
        $this->fileSystemCache->get($key, function () use ($data) {
            return $data;
        });
    }

}
