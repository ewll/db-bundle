<?php namespace Ewll\DBBundle\Migration;

use Exception;
use Ewll\DBBundle\DB\Client;
use Ewll\DBBundle\Exception\ExecuteException;

class MigrationManager
{
    private const TABLE_OR_VIEW_NOT_FOUND_ERROR_CODE = '42S02';

    /** @var Client|null Default DB client */
    private $defaultDbClient;
    /** @var string */
    private $migrationsDir;

    public function __construct(Client $defaultDbClient = null, string $projectDir)
    {
        $this->defaultDbClient = $defaultDbClient;
        $this->migrationsDir = implode(DIRECTORY_SEPARATOR, [$projectDir, 'src', 'Migration']);
    }

    public function getMigrationsInfo()
    {
        if (null === $this->defaultDbClient) {
            throw new Exception('Default DB client is undefined');
        }

        if (!is_dir($this->migrationsDir)) {
            throw new Exception("Migrations directory not exists: {$this->migrationsDir}");
        }

        $migrations = [];
        $files = glob("$this->migrationsDir/Migration*.php");
        foreach ($files as $file) {
            preg_match('/(Migration(\d+))\.php/', $file, $matches);
            $name = $matches[2];
            $className = "\App\Migration\\$matches[1]";
            require_once $file;
            /** @var MigrationInterface $migration */
            $migration = new $className;
            $migrations[$name] = [
                'name' => $name,
                'file' => $file,
                'className' => $className,
                'description' => $migration->getDescription(),
                'migrated' => false,
            ];
        }

        try {
            $migratedMigrations = $this->getMigrations();
            foreach ($migratedMigrations as $migratedMigration) {
                $migrations[$migratedMigration['name']]['migrated'] = true;
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
INSERT INTO migrations
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
DELETE FROM migrations WHERE name = ?
SQL
        )->execute([$name]);
    }

    private function getMigrations()
    {
        $statement = $this->defaultDbClient->prepare(<<<SQL
SELECT name
FROM migrations
SQL
        )->execute();

        $migrations = $statement->fetchArrays();

        return $migrations;
    }

    private function createMigrationsTable()
    {
        $this->defaultDbClient->exec(<<<SQL
CREATE TABLE `migrations` (
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
