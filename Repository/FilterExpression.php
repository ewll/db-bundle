<?php namespace Ewll\DBBundle\Repository;

class FilterExpression
{
    const ACTION_EQUAL = '=';
    const ACTION_NOT_EQUAL = '<>';
    const ACTION_IN = 'IN';
    const ACTION_NOT_IN = 'NOT IN';
    const ACTION_IS_NULL = 'IS NULL';
    const ACTION_IS_NOT_NULL = 'IS NOT NULL';

    private $action;
    private $param1;
    private $param2;

    public function __construct(string $action, $param1, $param2 = null)//@TODO
    {
        $this->action = $action;
        $this->param1 = $param1;
        $this->param2 = $param2;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getParam1()
    {
        return $this->param1;
    }

    public function getParam2()
    {
        return $this->param2;
    }
}
