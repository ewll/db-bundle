<?php namespace Ewll\DBBundle\Repository;

use Ewll\DBBundle\Annotation\ManyToMany;
use Ewll\DBBundle\Annotation\ManyToOne;
use Ewll\DBBundle\DB\Client;
use Ewll\DBBundle\Exception\ExecuteException;
use Ewll\DBBundle\Query\QueryBuilder;
use LogicException;
use RuntimeException;
use Symfony\Component\Inflector\Inflector;
use Symfony\Component\PropertyAccess\PropertyPath;

class Repository
{
    const SORT_TYPE_SIMPLE = 1;
    const SORT_TYPE_EXPRESSION = 2;

    const FOR_UPDATE = true;

    /** @var EntityConfig */
    protected $config;
    /** @var RepositoryProvider */
    protected $repositoryProvider;
    /** @var Client */
    protected $dbClient;
    /** @var Hydrator */
    protected $hydrator;
    /** @var string */
    protected $cipherkey;

    private $cache = [];

    public function setRepositoryProvider(RepositoryProvider $repositoryProvider): void
    {
        $this->repositoryProvider = $repositoryProvider;
    }

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
//        $elements = $this->findBy(['id' => $ids], 'id');


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
            $phInc = 0;
            foreach ($queryBuilder->getConditions() as $field => $value) {
                $phInc++;
                $this->handleCondition($queryBuilder, $field, $value, $phInc, $queryParams, $compiledConditions);
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
            $item->isDeleted = 1;
            $this->update($item, ['isDeleted']);
        }
    }

    private function handleCondition(
        QueryBuilder $queryBuilder,
        string $field,
        $condition,
        int $phInc,
        array &$queryParams,
        array &$compiledConditions
    ) {
        $mainPrefix = $queryBuilder->getPrefix();
        $fieldPath = $condition instanceof FilterExpression ? $condition->getParam1() : $field;
        if ($fieldPath instanceof PropertyPath) {
            //@TODO max elements checking
            $fieldName = $fieldPath->getElement(0);
        } else {
            $fieldName = $fieldPath;
        }
        if (is_array($fieldName)) {//@TODO Crud DbSource
            $prefix = $mainPrefix;
        } else {
            $isRelationFieldType = array_key_exists($fieldName, $this->config->relations);
            if ($isRelationFieldType) {//@TODO Двойные джойны по двум фильтрам
                $prefix = 't' . (count($queryBuilder->getJoins()) + 2);
                $relationConfig = $this->config->relations[$fieldName];
                $relationRepository = $this->repositoryProvider->get($relationConfig->config['RelationClassName']);
                $relationTableName = $relationRepository->getEntityConfig()->tableName;
                if ($relationConfig instanceof ManyToMany) {
                    $joinCondition = sprintf('%s.id = %s.%sId', $mainPrefix, $prefix, $this->config->tableName);
                    $fieldName = Inflector::singularize($fieldName);
                } elseif ($relationConfig instanceof ManyToOne) {
                    $joinCondition = sprintf('%s.%sId = %s.id', $mainPrefix, $fieldName, $prefix);
                    $fieldName = $fieldPath->getElement(1);
                } else {
                    throw new RuntimeException('TODO');
                }
                $queryBuilder
                    ->addJoin($relationTableName, $prefix, $joinCondition, 'LEFT');
            } else {
                $prefix = $mainPrefix;
            }
        }
        if ($condition instanceof FilterExpression) {
            $singleFilters = [
                FilterExpression::ACTION_EQUAL,
                FilterExpression::ACTION_NOT_EQUAL,
                FilterExpression::ACTION_GREATER,
                FilterExpression::ACTION_LESS
            ];
            $arrayFilters = [FilterExpression::ACTION_IN, FilterExpression::ACTION_NOT_IN];
            $nullFilters = [FilterExpression::ACTION_IS_NULL, FilterExpression::ACTION_IS_NOT_NULL];
            if (in_array($condition->getAction(), $singleFilters, true)) {
                $placeholder = is_array($fieldName)
                    ? "{$fieldName[0]}{$fieldName[1]}_{$phInc}"
                    : "{$fieldName}_{$phInc}";
                $queryParams[$placeholder] = $condition->getParam2();
                $compiledConditions[] = sprintf(
                    '%s.%s %s :%s',
                    is_array($fieldName) ? $fieldName[0] : $prefix,
                    is_array($fieldName) ? $fieldName[1] : $fieldName,
                    $condition->getAction(),
                    $placeholder
                );
            } elseif (in_array($condition->getAction(), $arrayFilters, true)) {
                $prePlaceholder = is_array($fieldName)
                    ? "{$fieldName[0]}{$fieldName[1]}_{$phInc}"
                    : "{$fieldName}_{$phInc}";
                $placeholders = [];
                foreach ($condition->getParam2() as $elKey => $elValue) {
                    $placeholder = "{$prePlaceholder}_$elKey";
                    $placeholders[] = ":{$placeholder}";
                    $queryParams[$placeholder] = $elValue;
                }

                $compiledConditions[] = sprintf(
                    '%s.%s %s (%s)',
                    is_array($fieldName) ? $fieldName[0] : $prefix,
                    is_array($fieldName) ? $fieldName[1] : $fieldName,
                    $condition->getAction(),
                    implode(',', $placeholders)
                );
            } elseif (in_array($condition->getAction(), $nullFilters, true)) {
                $compiledConditions[] = sprintf(
                    '%s.%s %s',
                    is_array($fieldName) ? $fieldName[0] : $prefix,
                    is_array($fieldName) ? $fieldName[1] : $fieldName,
                    $condition->getAction(),
                );
            } else {
                throw new RuntimeException('Unknown FilterExpression action');
            }
        } elseif (is_array($condition)) {
            $conditionItemPlaceholders = [];
            foreach ($condition as $conditionKey => $conditionItem) {
                $conditionItemName = "{$fieldName}_{$conditionKey}";
                $conditionItemPlaceholders[] = ":$conditionItemName";
                $queryParams[$conditionItemName] = $conditionItem;
            }
            $conditionItemPlaceholdersStr = implode(', ', $conditionItemPlaceholders);
            $compiledConditions[] = "$prefix.$fieldName IN ($conditionItemPlaceholdersStr)";
        } else {
            $queryParams[$fieldName] = $condition;
            $compiledConditions[] = "$prefix.$fieldName = :$fieldName";
        }
    }
}
