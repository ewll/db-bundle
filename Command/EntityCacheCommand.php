<?php namespace Ewll\DBBundle\Command;

use Doctrine\Common\Annotations\Reader;
use Ewll\DBBundle\Annotation\AnnotationInterface;
use Ewll\DBBundle\Cache\Cachier;
use Ewll\DBBundle\DB\CacheKeyCompiler;
use Ewll\DBBundle\Repository\EntityConfig;
use ReflectionClass;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EntityCacheCommand extends Command
{
    private $container;
    private $annotationReader;
    private $cacheKeyCompiler;
    private $cachier;
    private $bundles;
    private $projectDir;

    public function __construct(
        ContainerInterface $container,
        Reader $annotationReader,
        CacheKeyCompiler $cacheKeyCompiler,
        Cachier $cachier,
        array $bundles,
        string $projectDir
    ) {
        parent::__construct();
        $this->container = $container;
        $this->annotationReader = $annotationReader;
        $this->cacheKeyCompiler = $cacheKeyCompiler;
        $this->cachier = $cachier;
        $this->bundles = $bundles;
        $this->projectDir = $projectDir;
    }

    protected function configure()
    {
        $this
            ->setName('ewll:db:entity-cache');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $entityDirs = [];
        $entityDirs[] = [
            'dir' => implode(DIRECTORY_SEPARATOR, [$this->projectDir, 'src', 'Entity']),
            'namespace' => 'App',
        ];
        foreach ($this->bundles as $bundle) {
            $bundle = $this->container->get('kernel')->getBundle($bundle);
            $entityDirs[] = [
                'dir' => implode(DIRECTORY_SEPARATOR, [$bundle->getPath(), 'Entity']),
                'namespace' => $bundle->getNamespace(),
            ];
        }
        foreach ($entityDirs as $entityDir) {
            $this->handleDir($entityDir);
        }

        return 0;
    }

    private function handleDir(array $entityDir)
    {
        $files = glob("{$entityDir['dir']}/*.php");
        foreach ($files as $file) {
            preg_match('/([a-z]+)\.php/i', $file, $matches);
            $tableName = lcfirst($matches[1]);
            $className = implode('\\', ['', $entityDir['namespace'], 'Entity', $matches[1]]);
            $reflectionClass = new ReflectionClass($className);
            $fields = [];
            $reflectionProperties = $reflectionClass->getProperties();
            foreach ($reflectionProperties as $reflectionProperty) {
                $propertyAnnotations = $this->annotationReader->getPropertyAnnotations($reflectionProperty);
                foreach ($propertyAnnotations as $propertyAnnotation) {
                    if ($propertyAnnotation instanceof AnnotationInterface) {
                        $fields[$reflectionProperty->getName()] = serialize($propertyAnnotation);
                        break;
                    }
                }
            }
            $cacheKey = $this->cacheKeyCompiler->compile($className);
            $entityConfig = new EntityConfig($className, $tableName, $fields);
            $this->cachier->set($cacheKey, $entityConfig);
        }
    }
}
