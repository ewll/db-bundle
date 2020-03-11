<?php namespace Ewll\DBBundle\Repository;

use Ewll\DBBundle\DB\Client;
use Ewll\DBBundle\Exception\ExecuteException;
use Ewll\DBBundle\Query\QueryBuilder;
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
        $qb = new QueryBuilder($this);
        $qb
            ->setLimit(1)
            ->addConditions($params);

        return $this->find($qb);
    }

    public function findBy(
        array $params,
        string $indexBy = null,
        int $page = null,
        int $itemsPerPage = null,
        array $sortBy = []
    ) {
        $qb = new QueryBuilder($this);
        $qb
            ->addConditions($params)
            ->setSort($sortBy)
            ->setIndex($indexBy);
        if (null !== $page) {
            $qb->setPage($page, $itemsPerPage);
        }

        return $this->find($qb);
    }

    public function findById(int $id, bool $isForUpdate = false)
    {
        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        $qb = new QueryBuilder($this);
        $qb
            ->addConditions(['id' => $id])
            ->setLimit(1);
        if (true === $isForUpdate) {
            $qb->setFlag(QueryBuilder::FLAG_FOR_UPDATE);
        }

        return $this->find($qb);
    }

    public function findAll(string $indexBy = null)
    {
        $qb = new QueryBuilder($this);
        $qb->setIndex($indexBy);

        return $this->find($qb);
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

    public function findByRelativeIndexed(array $list, string $fieldName = null, bool $isForUpdate = false): array
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


        $qb = new QueryBuilder($this);
        $qb
            ->addConditions(['id' => $ids])
            ->setIndex('id');
        if (true === $isForUpdate) {
            $qb->setFlag(QueryBuilder::FLAG_FOR_UPDATE);
        }

        return $this->find($qb);
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

    public function getSelectArray($prefix, array $items = [])
    {
        $list = [];
        foreach ($this->config->fields as $fieldName => $type) {
            if (count($items) === 0 || in_array($fieldName, $items, true)) {
                $list[] = "$prefix.$fieldName as {$prefix}_$fieldName";
            }
        }

        return $list;
    }

    public function getEntityConfig(): EntityConfig
    {
        return $this->config;
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

    public function find(QueryBuilder $queryBuilder)
    {
        $prefix = $queryBuilder->getPrefix();
        $queryParams = [];
        $compiledConditions = [];
        if ($queryBuilder->hasConditions()) {
            $placeholderIncrement = 0;
            foreach ($queryBuilder->getConditions() as $field => $value) {
                if ($value instanceof FilterExpression) {//@TODO
                    $placeholderIncrement++;
                    $singleFilters = [
                        FilterExpression::ACTION_EQUAL,
                        FilterExpression::ACTION_NOT_EQUAL,
                        FilterExpression::ACTION_GREATER,
                        FilterExpression::ACTION_LESS
                    ];
                    $arrayFilters = [FilterExpression::ACTION_IN, FilterExpression::ACTION_NOT_IN];
                    $nullFilters = [FilterExpression::ACTION_IS_NULL, FilterExpression::ACTION_IS_NOT_NULL];
                    if (in_array($value->getAction(), $singleFilters, true)) {
                        $placeholder = "{$value->getParam1()}_{$placeholderIncrement}";
                        $queryParams[$placeholder] = $value->getParam2();
                        $compiledConditions[] = sprintf(
                            '%s.%s %s :%s',
                            $prefix,
                            $value->getParam1(),
                            $value->getAction(),
                            $placeholder
                        );
                    } elseif (in_array($value->getAction(), $arrayFilters, true)) {
                        $prePlaceholder = "{$value->getParam1()}_{$placeholderIncrement}";
                        $placeholders = [];
                        foreach ($value->getParam2() as $elKey => $elValue) {
                            $placeholder = "{$prePlaceholder}_$elKey";
                            $placeholders[] = ":{$placeholder}";
                            $queryParams[$placeholder] = $elValue;
                        }

                        $compiledConditions[] = sprintf(
                            '%s.%s %s (%s)',
                            $prefix,
                            $value->getParam1(),
                            $value->getAction(),
                            implode(',', $placeholders)
                        );
                    } elseif (in_array($value->getAction(), $nullFilters, true)) {
                        $param1 = $value->getParam1();
                        $compiledConditions[] = sprintf(
                            '%s.%s %s',
                            is_array($param1) ? $param1[0] : $prefix,
                            is_array($param1) ? $param1[1] : $param1,
                            $value->getAction(),
                        );
                    } else {
                        throw new RuntimeException('Unknown FilterExpression action');
                    }
                } elseif (is_array($value)) {
                    $valueItemPlaceholders = [];
                    foreach ($value as $valueKey => $valueItem) {
                        $valueItemName = "{$field}_{$valueKey}";
                        $valueItemPlaceholders[] = ":$valueItemName";
                        $queryParams[$valueItemName] = $valueItem;
                    }
                    $valueItemPlaceholdersStr = implode(', ', $valueItemPlaceholders);
                    $compiledConditions[] = "$prefix.$field IN ($valueItemPlaceholdersStr)";
                } else {
                    $queryParams[$field] = $value;
                    $compiledConditions[] = "$prefix.$field = :$field";
                }
            }
        }
        $compiledSort = [];
        if ($queryBuilder->hasSort()) {
            foreach ($queryBuilder->getSort() as $item) {
                switch ($item['type']) {
                    case self::SORT_TYPE_SIMPLE:
                        $sortExpression = "$prefix.{$item['field']}";
                        break;
                    case self::SORT_TYPE_EXPRESSION:
                        $sortExpression = str_replace('{prefix}', $prefix, $item['expression']);
                        break;
                    default:
                        throw new RuntimeException('Unknown sort type.');
                }
                $compiledSort[] = "$sortExpression {$item['method']}";
            }
        }

        $sql = 'SELECT';
        if ($queryBuilder->hasFlag(QueryBuilder::FLAG_CALC_ROWS)) {
            $sql .= ' SQL_CALC_FOUND_ROWS';
        }
        $sql .= ' ' . implode(', ', $queryBuilder->getSelectionItems());
        $sql .= "\nFROM {$queryBuilder->getTableName()} {$prefix}";
        foreach ($queryBuilder->getJoins() as $join) {
            $sql .= "\n{$join[3]} JOIN {$join[0]} {$join[1]} ON {$join[2]}";
        }
        if ($queryBuilder->hasConditions()) {
            $sql .= "\nWHERE " . implode(' AND ', $compiledConditions);
        }
        if ($queryBuilder->hasSort()) {
            $sql .= "\nORDER BY " . implode(', ', $compiledSort);
        }
        if (null !== $queryBuilder->getLimit()) {
            $sql .= "\nLIMIT " . $queryBuilder->getLimit();
        }
        if (null !== $queryBuilder->getOffset()) {
            $sql .= "\nOFFSET " . $queryBuilder->getOffset();
        }
        if ($queryBuilder->hasFlag(QueryBuilder::FLAG_FOR_UPDATE)) {
            $sql .= "\nFOR UPDATE";
        }
//        if ($queryBuilder->getTableName() === 'product') {
//            var_dump($queryParams);
//            exit($sql);
//        }
        $statement = $this->dbClient->prepare($sql)->execute($queryParams);

        $transformationOptions = $this->getFieldTransformationOptions();
        if (1 === $queryBuilder->getLimit()) {
            $result = $this->hydrator->hydrateOne($this->config, $prefix, $statement, $transformationOptions);
            if (null !== $result) {
                $this->cache[$result->id] = $result;
            }
        } else {
            $result = $this->hydrator
                ->hydrateMany($this->config, $prefix, $statement, $transformationOptions, $queryBuilder->getIndex());
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
