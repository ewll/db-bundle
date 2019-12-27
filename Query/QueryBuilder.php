<?php namespace Ewll\DBBundle\Query;

use Ewll\DBBundle\Repository\FilterExpression;
use Ewll\DBBundle\Repository\Repository;

class QueryBuilder
{
    const FLAG_CALC_ROWS = 1;
    const FLAG_FOR_UPDATE = 2;

    const DEFAULT_PREFIX = 't1';

    /** @var array */
    private $flags = [];
    /** @var array */
    private $selectionItems = [];
    /** @var array */
    private $from;
    /** @var array */
    private $joins = [];
    /** @var array */
    private $conditions = [];
    /** @var array */
    private $sort = [];
    /** @var int|null */
    private $limit;
    /** @var int|null */
    private $offset;
    /** @var string|null */
    private $index;

    public function __construct(Repository $repository, string $prefix = self::DEFAULT_PREFIX)
    {
        $this->from = [$repository->getEntityConfig()->tableName, $prefix];
        $this->selectionItems = $repository->getSelectArray($prefix);
    }

    public function setFlag(int $flag): self
    {
        $this->flags[] = $flag;
        return $this;
    }

    public function hasFlag(int $flag): bool
    {
        return in_array($flag, $this->flags, true);
    }

    public function getSelectionItems(): array
    {
        return $this->selectionItems;
    }

    public function getTableName(): string
    {
        return $this->from[0];
    }

    public function getPrefix(): string
    {
        return $this->from[1];
    }

    public function addJoin(string $tableName, string $prefix, string $condition, string $type = 'INNER'): self
    {
        $this->joins[] = [$tableName, $prefix, $condition, $type];
        return $this;
    }

    public function getJoins(): array
    {
        return $this->joins;
    }

    public function hasConditions(): bool
    {
        return count($this->conditions) > 0;
    }

    public function addCondition(FilterExpression $condition)
    {
        $this->conditions[] = $condition;
        return $this;
    }

    public function addConditions(array $conditions): self
    {
        $this->conditions = array_merge($this->conditions, $conditions);
        return $this;
    }

    public function getConditions(): array
    {
        return $this->conditions;
    }

    public function setSort(array $sort): self
    {
        $this->sort = $sort;
        return $this;
    }

    public function hasSort(): bool
    {
        return count($this->sort) > 0;
    }

    public function getSort(): array
    {
        return $this->sort;
    }

    public function setPage(int $page, int $itemsPerPage): self
    {
        $this->offset = ($page - 1) * $itemsPerPage;
        $this->limit = $itemsPerPage;
        return $this;
    }

    public function setLimit(int $limit = null): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function setOffset(int $offset = null): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }

    public function setIndex(string $index = null): self
    {
        $this->index = $index;
        return $this;
    }

    public function getIndex(): ?string
    {
        return $this->index;
    }
}
