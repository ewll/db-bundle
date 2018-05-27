<?php namespace Ewll\DBBundle\Repository;

use Ewll\DBBundle\Annotation\TypeAbstract;

class EntityConfig
{
    public $class;
    public $tableName;
    /** @var TypeAbstract[] */
    public $fields = [];

    public function __construct(string $class, string $tableName, array $fields)
    {
        $this->class = $class;
        $this->tableName = $tableName;
        $this->fields = $fields;
    }

    public function __wakeup()
    {
        $fields = [];
        foreach ($this->fields as $fieldName => $type) {
            $fields[$fieldName] = unserialize($type);
        }
        $this->fields = $fields;
    }
}
