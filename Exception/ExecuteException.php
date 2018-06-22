<?php namespace Ewll\DBBundle\Exception;

/**
 * Execute exception
 */
class ExecuteException extends DBException
{
    const DEADLOCK_CODE = '40001';
    const TABLE_OR_VIEW_NOT_FOUND_CODE = '42S02';
}
