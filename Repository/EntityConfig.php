<?php namespace Ewll\DBBundle\Repository;

use Ewll\DBBundle\Annotation\RelationTypeAbstract;
use Ewll\DBBundle\Annotation\TypeInterface;

class EntityConfig
{
    public $class;
    public $tableName;
    /** @var TypeInterface[] */
    public $fields = [];
    /** @var RelationTypeAbstract[] */
    public $relations;

    public function __construct(string $class, string $tableName, array $fields, array $relations)
    {
        $this->class = $class;
        $this->tableName = $tableName;
        $this->fields = $fields;
        $this->relations = $relations;
    }

    public function __wakeup()
    {
        $fields = [];
        foreach ($this->fields as $fieldName => $type) {
            $fields[$fieldName] = unserialize($type);
        }
        $this->fields = $fields;

        $relations = [];
        foreach ($this->relations as $relationName => $type) {
            $relations[$relationName] = unserialize($type);
        }
        $this->relations = $relations;
    }
}
