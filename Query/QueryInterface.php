<?php namespace Ewll\DBBundle\Query;

/**
 * Query interface
 */
interface QueryInterface
{
    /**
     * Returns statement
     *
     * @return string
     */
    public function getStatement(): string;

    /**
     * Returns input parameters
     *
     * @return array
     */
    public function getParameters(): array;
}
