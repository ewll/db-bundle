<?php namespace Ewll\DBBundle\Repository;

use Ewll\DBBundle\DB\Client;

//@TODO cache
class Repository
{
    /** @var EntityConfig */
    protected $config;
    /** @var Client */
    protected $dbClient;
    /** @var Hydrator */
    protected $hydrator;

    private $cache = [];

    public function setDbClient(Client $client)
    {
        $this->dbClient = $client;
    }

    public function setEntityConfig(EntityConfig $config)
    {
        $this->config = $config;
    }

    public function setHydrator(Hydrator $hydrator)
    {
        $this->hydrator = $hydrator;
    }

    public function findOneBy(array $params)
    {
        $prefix = 't1';
        $where = [];
        foreach ($params as $field => $value) {
            $where[] = "$prefix.$field = :$field";
        }
        $whereStr = implode(' AND ', $where);
        $statement = $this->dbClient->prepare(<<<SQL
SELECT {$this->getSelectList($prefix)}
FROM {$this->config->tableName} $prefix
WHERE $whereStr
LIMIT 1
SQL
        )->execute($params);

        $item = $this->hydrator->hydrateOne($this->config, $prefix, $statement);

        return $item;
    }

    public function findBy(array $params, string $indexBy = null)
    {
        $prefix = 't1';
        $where = [];
        foreach ($params as $field => $value) {
            $where[] = "$prefix.$field = :$field";
        }
        $whereStr = implode(' AND ', $where);
        $statement = $this->dbClient->prepare(<<<SQL
SELECT {$this->getSelectList($prefix)}
FROM {$this->config->tableName} $prefix
WHERE $whereStr
SQL
        )->execute($params);

        $items = $this->hydrator->hydrateMany($this->config, $prefix, $statement, $indexBy);

        return $items;
    }

    public function findById(int $id)
    {
        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        $item = $this->findOneBy(['id' => $id]);

        if (null !== $item) {
            $this->cache[$id] = $item;
        }

        return $item;
    }

    public function create($item)
    {
        $fields = [];
        $placeholders = [];
        $params = [];
        foreach ($this->config->fields as $fieldName => $type) {
            if ($fieldName === 'id') {
                continue;
            }

            $value = $type->transformToStore($item->$fieldName);
            $fields[] = $fieldName;
            if (null === $value) {
                $placeholders[] = 'NULL';
            } else {
                $placeholders[] = ":$fieldName";
                $params[$fieldName] = $value;
            }
        }

        $fieldsStr = implode(', ', $fields);
        $placeholdersStr = implode(', ', $placeholders);
        $this->dbClient->prepare(<<<SQL
INSERT INTO {$this->config->tableName}
    ($fieldsStr)
VALUES
    ($placeholdersStr)
SQL
        )->execute($params);

        $item->id = (int)$this->dbClient->lastInsertId();
    }

    public function update($item)
    {
        $params = ['id' => $item->id];
        $sets = [];
        foreach ($this->config->fields as $fieldName => $type) {
            if ($fieldName === 'id') {
                continue;
            }
            $value = $type->transformToStore($item->$fieldName);
            if (null === $value) {
                $sets[] = "{$fieldName} = NULL";
            } else {
                $sets[] = "{$fieldName} = :{$fieldName}";
                $params[$fieldName] = $value;
            }
        }

        $setsStr = implode(', ', $sets);
        $this->dbClient->prepare(<<<SQL
UPDATE {$this->config->tableName}
SET $setsStr
WHERE id = :id
SQL
        )->execute($params);
    }

    public function getFoundRows()
    {
        $statement = $this->dbClient->prepare(<<<SQL
SELECT FOUND_ROWS()
SQL
        )->execute();
            $num = $statement->fetchColumn();

            return $num;
    }

    protected function getSelectList($prefix)
    {
        $list = [];
        foreach ($this->config->fields as $fieldName => $type) {
            $list[] = "$prefix.$fieldName as {$prefix}_$fieldName";
        }

        return implode(', ', $list);
    }

    protected function getGroupByList($prefix)
    {
        $list = [];
        foreach ($this->config->fields as $fieldName => $type) {
            $list[] = "$prefix.$fieldName";
        }

        return implode(', ', $list);
    }
}
