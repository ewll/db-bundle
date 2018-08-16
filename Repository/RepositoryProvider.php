<?php namespace Ewll\DBBundle\Repository;

use Ewll\DBBundle\DB\CacheKeyCompiler;
use Ewll\DBBundle\DB\Client;
use Symfony\Component\Cache\Simple\FilesystemCache;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RepositoryProvider
{
    private $defaultDbClient;
    private $container;
    private $repositories;
    /** @var Repository[] */
    private $cache = [];
    private $cacher;
    private $hydrator;

    private $entityConfigs = [];
    private $cacheKeyCompiler;

    public function __construct(
        ContainerInterface $container,
        iterable $repositories,
        Client $defaultDbClient,
        Hydrator $hydrator,
        CacheKeyCompiler $cacheKeyCompiler
    ) {
        $this->defaultDbClient = $defaultDbClient;
        $this->container = $container;
        $this->repositories = $repositories;
        $this->cacher = new FilesystemCache();
        $this->hydrator = $hydrator;
        $this->cacheKeyCompiler = $cacheKeyCompiler;
    }

    public function get(string $entityClass): Repository
    {
        $repositoryClassName = substr(strrchr($entityClass, '\\'), 1).'Repository';
        if (isset($this->cache[$repositoryClassName])) {
            return $this->cache[$repositoryClassName];
        }

        $repository = null;
        foreach ($this->repositories as $service) {
            if ($repositoryClassName === substr(strrchr(get_class($service), '\\'), 1)) {
                $repository = $service;
                break;
            }
        }

        if (null === $repository) {
            $repository = $this->container->get('ewll.db.repository');
        }
        $repository->setDbClient($this->defaultDbClient);
        $repository->setEntityConfig($this->getEntityConfig($entityClass));
        $repository->setHydrator($this->hydrator);
        $repository->setCipherkey($this->defaultDbClient->getCipherkey());

        $this->cache[$repositoryClassName] = $repository;

        return $repository;
    }

    public function clear()
    {
        foreach ($this->cache as $repository) {
            $repository->clear();
        }
    }

    private function getEntityConfig(string $entityClass): EntityConfig
    {
        $key = $this->cacheKeyCompiler->compile($entityClass);

        if (isset($this->entityConfigs[$key])) {
            return $this->entityConfigs[$key];
        }

        $entityConfig = $this->cacher->get($key);

        $this->entityConfigs[$key] = $entityConfig;

        return $entityConfig;
    }
}
