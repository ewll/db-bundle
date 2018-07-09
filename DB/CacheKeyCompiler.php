<?php namespace Ewll\DBBundle\DB;

class CacheKeyCompiler
{
    public function compile(string $entityClass)
    {
        $dirHash = hash('md5', __DIR__);
        $entityKey = strtolower(preg_replace('/[^a-z]/i', '', $entityClass));
        $key = sprintf('ewll.%s.entity.%s', $dirHash, $entityKey);

        return $key;
    }
}
