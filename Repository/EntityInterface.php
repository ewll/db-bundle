<?php namespace Ewll\DBBundle\Repository;

interface EntityInterface
{
    public function getTableData();
    public static function getTableName();
    public static function getTablePrefix();
    public static function getTableFields();
}
