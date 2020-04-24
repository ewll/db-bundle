<?php namespace Ewll\DBBundle\Annotation;

abstract class RelationTypeAbstract implements RelationTypeInterface
{
    public $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }
}
