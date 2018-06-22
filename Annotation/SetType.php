<?php namespace Ewll\DBBundle\Annotation;

/** @Annotation */
class SetType extends TypeAbstract
{
    public function transformToView($value)
    {
        if (null === $value) {
            return null;
        }

        if ('' === $value) {
            return [];
        }

        return explode(',', $value);
    }

    public function transformToStore($value)
    {
        if (null === $value) {
            return null;
        }

        return implode(',', $value);
    }
}
