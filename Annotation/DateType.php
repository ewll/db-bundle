<?php namespace Ewll\DBBundle\Annotation;

use DateTime;

/** @Annotation */
class DateType extends TypeAbstract
{
    public function transformToView($value, array $options)
    {
        if (null === $value) {
            return null;
        }

        return empty($value) ? null : new DateTime($value);
    }

    public function transformToStore($value, array $options)
    {
        if (null === $value) {
            return null;
        }

        /** @var $value DateTime|null */
        return empty($value) ? null : $value->format('Y-m-d');
    }
}
