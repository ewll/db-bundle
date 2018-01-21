<?php namespace Ewll\DBBundle\Exception;

use RuntimeException;

/**
 * Shard not found exception
 */
class ShardNotFoundException extends RuntimeException implements ShardExceptionInterface, DBExceptionInterface
{

}
