<?php namespace Ewll\DBBundle\Command;

use Doctrine\Common\Annotations\Reader;
use Ewll\DBBundle\Annotation\AnnotationInterface;
use Ewll\DBBundle\DB\CacheKeyCompiler;
use Ewll\DBBundle\Repository\EntityConfig;
use ReflectionClass;
use Symfony\Component\Cache\Simple\FilesystemCache;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EntityCacheCommand extends Command
{
    private $annotationReader;
    private $entityDir;
    private $cacheKeyCompiler;
    private $cacheDir;

    public function __construct(
        Reader $annotationReader,
        string $projectDir,
        CacheKeyCompiler $cacheKeyCompiler,
        string $cacheDir
    ) {
        parent::__construct();
        $this->annotationReader = $annotationReader;
        $this->entityDir = implode(DIRECTORY_SEPARATOR, [$projectDir, 'src', 'Entity']);
        $this->cacheKeyCompiler = $cacheKeyCompiler;
        $this->cacheDir = $cacheDir;
    }

    protected function configure()
    {
        $this
            ->setName('ewll:db:entity-cache');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cacheDir = implode(DIRECTORY_SEPARATOR, [$this->cacheDir, 'Ewll', 'EntityCache']);
        $fileSystemCache = new FilesystemCache('', 0, $cacheDir);

        $files = glob("$this->entityDir/*.php");
        foreach ($files as $file) {
            preg_match('/([a-z]+)\.php/i', $file, $matches);
            $tableName = lcfirst($matches[1]);
            $className = "\App\Entity\\$matches[1]";
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
            $fileSystemCache->set($cacheKey, $entityConfig);
        }
    }
}
