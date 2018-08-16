<?php namespace Ewll\DBBundle\Annotation;

/** @Annotation */
class BoolType extends TypeAbstract
{
    public function transformToView($value, array $options)
    {
        if (null === $value) {
            return null;
        }

        return (bool) $value;
    }

    public function transformToStore($value, array $options)
    {
        if (null === $value) {
            return null;
        }

        return $value ? 1 : 0;
    }
}
