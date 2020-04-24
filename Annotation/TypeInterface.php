<?php namespace Ewll\DBBundle\Annotation;

interface TypeInterface
{
    public function transformToView($value, array $options);
    public function transformToStore($value, array $options);
}
