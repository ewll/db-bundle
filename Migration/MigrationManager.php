<?php namespace Ewll\DBBundle\Migration;

use Exception;
use Ewll\DBBundle\DB\Client;
use Ewll\DBBundle\Exception\ExecuteException;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MigrationManager
{
    private const TABLE_OR_VIEW_NOT_FOUND_ERROR_CODE = '42S02';

    private $container;
    /** @var Client|null Default DB client */
    private $defaultDbClient;
    private $bundles;
    private $projectDir;

    public function __construct(
        ContainerInterface $container,
        Client $defaultDbClient = null,
        array $bundles,
        string $projectDir
    ) {
        $this->container = $container;
        $this->defaultDbClient = $defaultDbClient;
        $this->bundles = $bundles;
        $this->projectDir = $projectDir;
    }

    public function getMigrationsInfo()
    {
        if (null === $this->defaultDbClient) {
            throw new Exception('Default DB client is undefined');
        }

        $bundleMigrationDirs = [];
        $bundleMigrationDirs[] = [
            'dir' => implode(DIRECTORY_SEPARATOR, [$this->projectDir, 'src', 'Migration']),
            'namespace' => 'App',
        ];
        foreach ($this->bundles as $bundle) {
            $bundle = $this->container->get('kernel')->getBundle($bundle);
            $bundleMigrationDirs[] = [
                'dir' => implode(DIRECTORY_SEPARATOR, [$bundle->getPath(), 'Migration']),
                'namespace' => $bundle->getNamespace(),
            ];
        }
        $files = [];
        foreach ($bundleMigrationDirs as $bundleMigrationDir) {
            if (is_dir($bundleMigrationDir['dir'])) {
                $dirFiles = glob("{$bundleMigrationDir['dir']}/Migration*.php");
                foreach ($dirFiles as $dirFile) {
                    $files[] = [
                        'name' => $dirFile,
                        'namespace' => $bundleMigrationDir['namespace'],
                    ];
                }
            }
        }

        $migrations = [];
        foreach ($files as $file) {
            preg_match('/(Migration(\d+))\.php/', $file['name'], $matches);
            $name = $matches[2];
            $className = '\\' . $file['namespace'] . '\Migration\\' . $matches[1];
            require_once $file['name'];
            /** @var MigrationInterface $migration */
            $migration = new $className;
            $migrations[$name] = [
                'name' => $name,
                'file' => $file['name'],
                'className' => $className,
                'description' => $migration->getDescription(),
                'migrated' => false,
            ];
        }

        try {
            $migratedMigrations = $this->getMigrations();
            foreach ($migratedMigrations as $migratedMigration) {
                if (isset($migrations[$migratedMigration['name']])) {
                    $migrations[$migratedMigration['name']]['migrated'] = true;
                }
            }
        } catch (ExecuteException $e) {
            if ($e->getPrevious()->getCode() === self::TABLE_OR_VIEW_NOT_FOUND_ERROR_CODE) {
                $this->createMigrationsTable();
            } else {
                throw $e;
            }
        }

        return $migrations;
    }

    public function up(array $migrations, string $name)
    {
        $migrationInfo = $this->getMigrationInfoByList($migrations, $name);
        if ($migrationInfo['migrated']) {
            throw new Exception("Migration $name already applied");
        }

        $migration = $this->getMigrationObject($migrationInfo);
        $sql = $migration->up();
        $this->defaultDbClient->exec($sql);
        $this->defaultDbClient->prepare(<<<SQL
INSERT INTO migration
    (name, description)
VALUES
    (?, ?)
SQL
        )->execute([$name, $migrationInfo['description']]);
    }

    public function down(array $migrations, string $name)
    {
        $migrationInfo = $this->getMigrationInfoByList($migrations, $name);
        if (!$migrationInfo['migrated']) {
            throw new Exception("Migration $name not applied");
        }

        $migration = $this->getMigrationObject($migrationInfo);
        $sql = $migration->down();
        $this->defaultDbClient->exec($sql);
        $this->defaultDbClient->prepare(<<<SQL
DELETE FROM migration WHERE name = ?
SQL
        )->execute([$name]);
    }

    private function getMigrations()
    {
        $statement = $this->defaultDbClient->prepare(<<<SQL
SELECT name
FROM migration
SQL
        )->execute();

        $migrations = $statement->fetchArrays();

        return $migrations;
    }

    private function createMigrationsTable()
    {
        $this->defaultDbClient->exec(<<<SQL
CREATE TABLE `migration` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(64) NOT NULL,
    `description` VARCHAR(250) NOT NULL,
    `created_ts` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `name` (`name`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB
SQL
        );
    }

    private function getMigrationInfoByList(array $migrations, string $name)
    {
        if (!isset($migrations[$name])) {
            throw new Exception("Migration $name not found");
        }

        return $migrations[$name];
    }

    private function getMigrationObject(array $migrationInfo): MigrationInterface
    {
        require_once $migrationInfo['file'];
        $migration = new $migrationInfo['className'];

        return $migration;
    }
}
