<?php namespace Ewll\DBBundle\DB;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class CacheKeyCompiler
{
    private $projectCacheDir;

    public function __construct(string $projectCacheDir)
    {
        $this->projectCacheDir = $projectCacheDir;
    }

    public function compile(string $entityClass)
    {
        $dirHash = hash('md5', $this->projectCacheDir);
        $entityKey = strtolower(preg_replace('/[^a-z]/i', '', $entityClass));
        $key = sprintf('ewll.%s.entity.%s', $dirHash, $entityKey);

        return $key;
    }
}
