<?php namespace Ewll\DBBundle\Repository;

use Ewll\DBBundle\DB\Client;

class Repository
{
    /** @var EntityConfig */
    protected $config;
    /** @var Client */
    protected $dbClient;
    /** @var Hydrator */
    protected $hydrator;
    /** @var string */
    protected $cipherkey;

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

    public function setCipherkey(string $cipherkey)
    {
        $this->cipherkey = $cipherkey;
    }

    public function findOneBy(array $params)
    {
        $item = $this->find(true, $params);

        return $item;
    }

    public function findBy(
        array $params,
        string $indexBy = null,
        int $page = null,
        int $itemsPerPage = null,
        array $sortBy = []
    ) {
        $items = $this->find(false, $params, $indexBy, $page, $itemsPerPage, $sortBy);

        return $items;
    }

    public function findById(int $id)
    {
        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        $item = $this->find(true, ['id' => $id]);

        return $item;
    }

    public function findAll(string $indexBy = null)
    {
        $items = $this->find(false, [], $indexBy);

        return $items;
    }

    public function create($item, $isAutoincrement = true)
    {
        $fields = [];
        $placeholders = [];
        $params = [];
        foreach ($this->config->fields as $fieldName => $type) {
            if ($isAutoincrement && $fieldName === 'id') {
                continue;
            }

            $value = $type->transformToStore($item->$fieldName, $this->getFieldTransformationOptions());
            $fields[] = $fieldName;
            if (null === $value) {
                $placeholders[] = 'NULL';
            } else {
                $placeholders[] = ":$fieldName";
                $params[$fieldName] = $value;
            }
        }

        $fieldsStr = '`' . implode('`,`', $fields) . '`';
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

    public function update($item, array $updateFields = null)
    {
        $params = ['id' => $item->id];
        $sets = [];
        foreach ($this->config->fields as $fieldName => $type) {
            if ($fieldName === 'id') {
                continue;
            }
            if (null !== $updateFields && !in_array($fieldName, $updateFields, true)) {
                continue;
            }
            $value = $type->transformToStore($item->$fieldName, $this->getFieldTransformationOptions());
            if (null === $value) {
                $sets[] = "{$fieldName} = NULL";
            } else {
                $sets[] = "`{$fieldName}` = :{$fieldName}";
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

    public function findByRelativeIndexed(array $list, $fieldName = null): array
    {
        $fieldName = $fieldName ?? "{$this->config->tableName}Id";
        $ids = [];
        foreach ($list as $item) {
            $id = $item->$fieldName;
            if (null !== $id) {
                $ids[] = $id;
            }
        }
        if (count($ids) === 0) {
            return [];
        }
        $ids = array_unique($ids);
        $elements = $this->findBy(['id' => $ids], 'id');

        return $elements;
    }

    public function clear()
    {
        $this->cache = [];
    }

    protected function getFieldTransformationOptions()
    {
        return [
            'cipherkey' => $this->cipherkey,
        ];
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

    private function find(
        bool $one,
        array $params,
        string $indexBy = null,
        int $page = null,
        int $itemsPerPage = null,
        array $sortBy = []
    ) {
        $prefix = 't1';
        $queryParams = [];
        $sqlCalcFoundRows = '';
        $limit = '';
        if (null !== $page) {
            $sqlCalcFoundRows = 'SQL_CALC_FOUND_ROWS';
            $offset = ($page - 1) * $itemsPerPage;
            $limit = "LIMIT $offset, $itemsPerPage";
        }
        $sql = <<<SQL
SELECT $sqlCalcFoundRows {$this->getSelectList($prefix)}
FROM {$this->config->tableName} $prefix
SQL;
        if (count($params) > 0) {
            $where = [];
            foreach ($params as $field => $value) {
                if (is_array($value)) {
                    $valueItemPlaceholders = [];
                    foreach ($value as $valueKey => $valueItem) {
                        $valueItemName = "{$field}_{$valueKey}";
                        $valueItemPlaceholders[] = ":$valueItemName";
                        $queryParams[$valueItemName] = $valueItem;
                    }
                    $valueItemPlaceholdersStr = implode(', ', $valueItemPlaceholders);
                    $where[] = "$prefix.$field IN ($valueItemPlaceholdersStr)";
                } else {
                    $queryParams[$field] = $value;
                    $where[] = "$prefix.$field = :$field";
                }
            }
            $whereStr = implode(' AND ', $where);
            $sql .= "\nWHERE $whereStr";
        }

        if (count($sortBy) > 0) {
            $sortByStrings = [];
            foreach ($sortBy as $item) {
                $sortByStrings[] = "$prefix.{$item['field']} {$item['method']}";
            }
            $sql .= "\nORDER BY ".implode(', ', $sortByStrings);
        }

        if (true === $one) {
            $sql .= "\nLIMIT 1";
        } else {
            $sql .= "\n$limit";
        }
        $statement = $this->dbClient->prepare($sql)->execute($queryParams);

        $transformationOptions = $this->getFieldTransformationOptions();
        if (true === $one) {
            $result = $this->hydrator->hydrateOne($this->config, $prefix, $statement, $transformationOptions);
            if (null !== $result) {
                $this->cache[$result->id] = $result;
            }
        } else {
            $result = $this->hydrator
                ->hydrateMany($this->config, $prefix, $statement, $transformationOptions, $indexBy);
            foreach ($result as $item) {
                $this->cache[$item->id] = $item;
            }
        }

        return $result;
    }
}
