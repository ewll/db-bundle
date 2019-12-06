<?php namespace Ewll\DBBundle\Repository;

use Ewll\DBBundle\DB\Client;
use LogicException;
use RuntimeException;

class Repository
{
    const SORT_TYPE_SIMPLE = 1;
    const SORT_TYPE_EXPRESSION = 2;

    const FOR_UPDATE = true;

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

    public function findById(int $id, bool $isForUpdate = false)
    {
        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        $item = $this->find(true, ['id' => $id], null, null, null, [], $isForUpdate);

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

    protected function getSelectArray($prefix)
    {
        $list = [];
        foreach ($this->config->fields as $fieldName => $type) {
            $list[] = "$prefix.$fieldName as {$prefix}_$fieldName";
        }

        return $list;
    }

    protected function getSelectList($prefix)
    {
        return implode(', ', $this->getSelectArray($prefix));
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
        array $sortBy = [],
        bool $isForUpdate = false
    ) {
        $prefix = 't1';
        $sqlData = [
            'calcRows' => false,
            'limit' => null,
            'selectionItems' => $this->getSelectArray($prefix),
            'tableName' => $this->config->tableName,
            'prefix' => $prefix,
            'where' => [],
            'sortBy' => [],
            'isForUpdate' => $isForUpdate,
        ];
        $queryParams = [];
        if ($one) {
            $sqlData['limit'] = '1';
        } elseif (null !== $page) {
            $sqlData['calcRows'] = true;
            $offset = ($page - 1) * $itemsPerPage;
            $sqlData['limit'] = "$offset, $itemsPerPage";

            if (count($sortBy) > 0) {
                foreach ($sortBy as $item) {
                    switch ($item['type']) {
                        case self::SORT_TYPE_SIMPLE:
                            $sortExpression = "$prefix.{$item['field']}";
                            break;
                        case self::SORT_TYPE_EXPRESSION:
                            $sortExpression = str_replace('{prefix}', $sqlData['prefix'], $item['expression']);
                            break;
                        default:
                            throw new RuntimeException('Unknown sort type.');
                    }
                    $sqlData['sortBy'][] = "$sortExpression {$item['method']}";
                }
            }
        }
        if (count($params) > 0) {
            foreach ($params as $field => $value) {
                if ($value instanceof FilterExpression) {//@TODO
                    $queryParams[$value->getParam1()] = $value->getParam2();
                    $sqlData['where'][] = sprintf(
                        '%s.%s %s :%s',
                        $prefix,
                        $value->getParam1(),
                        $value->getAction(),
                        $value->getParam1()
                    );
                } elseif (is_array($value)) {
                    $valueItemPlaceholders = [];
                    foreach ($value as $valueKey => $valueItem) {
                        $valueItemName = "{$field}_{$valueKey}";
                        $valueItemPlaceholders[] = ":$valueItemName";
                        $queryParams[$valueItemName] = $valueItem;
                    }
                    $valueItemPlaceholdersStr = implode(', ', $valueItemPlaceholders);
                    $sqlData['where'][] = "$prefix.$field IN ($valueItemPlaceholdersStr)";
                } else {
                    $queryParams[$field] = $value;
                    $sqlData['where'][] = "$prefix.$field = :$field";
                }
            }
        }

        $sql = 'SELECT';
        if ($sqlData['calcRows']) {
            $sql .= ' SQL_CALC_FOUND_ROWS';
        }
        $sql .= ' ' . implode(', ', $sqlData['selectionItems']);
        $sql .= "\nFROM {$sqlData['tableName']} {$sqlData['prefix']}";
        if (count($sqlData['where']) > 0) {
            $sql .= "\nWHERE " . implode(' AND ', $sqlData['where']);
        }
        if (count($sqlData['sortBy']) > 0) {
            $sql .= "\nORDER BY " . implode(', ', $sqlData['sortBy']);
        }
        if (null !== $sqlData['limit']) {
            $sql .= "\nLIMIT " . $sqlData['limit'];
        }
        if (true !== $sqlData['isForUpdate']) {
            $sql .= "\nFOR UPDATE";
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

    public function delete($item, $force = false)
    {
        if (true === $force) {
            $params = ['id' => $item->id];
            $this->dbClient->prepare(<<<SQL
DELETE FROM {$this->config->tableName} WHERE id = :id
SQL
            )->execute($params);
        } else {
            throw new RuntimeException('Not realised');
        }
    }
}
