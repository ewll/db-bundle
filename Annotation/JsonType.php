<?php namespace Ewll\DBBundle\Annotation;

/** @Annotation */
class JsonType extends TypeAbstract
{
    public function transformToView($value)
    {
        if (null === $value) {
            return null;
        }

        return json_decode($value, true);
    }

    public function transformToStore($value)
    {
        if (null === $value) {
            return null;
        }

        return json_encode($value);
    }
}
