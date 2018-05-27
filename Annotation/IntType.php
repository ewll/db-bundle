<?php namespace Ewll\DBBundle\Annotation;

/** @Annotation */
class IntType extends TypeAbstract
{
    public function transformToView($value)
    {
        if (null === $value) {
            return null;
        }

        return (int) $value;
    }
}
