<?php namespace Ewll\DBBundle\DB;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class CacheKeyCompiler
{
    public function compile(string $entityClass)
    {
        $entityKey = strtolower(preg_replace('/[^a-z]/i', '', $entityClass));
        $key = sprintf('ewll.entity.%s', $entityKey);

        return $key;
    }
}
