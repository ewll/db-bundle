<?php namespace Ewll\DBBundle\Annotation;

use DateTime;

/** @Annotation */
class TimestampType extends TypeAbstract
{
    public function transformToView($value)
    {
        if (null === $value) {
            return null;
        }

        return empty($value) ? null : new DateTime($value);
    }

    public function transformToStore($value)
    {
        if (null === $value) {
            return null;
        }

        /** @var $value DateTime|null */
        return empty($value) ? null : $value->format('Y-m-d H:i:s');
    }
}
