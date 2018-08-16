<?php namespace Ewll\DBBundle\Repository;

use Ewll\DBBundle\DB\Statement;

class Hydrator
{
    public function hydrateOne(
        EntityConfig $entityConfig,
        string $prefix,
        Statement $statement,
        array $transformationOptions
    ) {
        $data = $statement->fetchArray();

        if (null === $data) {
            return null;
        }

        $item = $this->hydrate($entityConfig, $prefix, $data, $transformationOptions);

        return $item;
    }

    public function hydrateMany(
        EntityConfig $entityConfig,
        string $prefix,
        Statement $statement,
        array $transformationOptions,
        string $indexBy = null
    ) {
        $items = [];
        $data = $statement->fetchArrays();

        foreach ($data as $row) {
            $key = null === $indexBy ? count($items) : $row["{$prefix}_$indexBy"];
            $items[$key] = $this->hydrate($entityConfig, $prefix, $row, $transformationOptions);
        }

        return $items;
    }

    private function hydrate(EntityConfig $entityConfig, string $prefix, array $data, array $transformationOptions)
    {
        $item = new $entityConfig->class;

        foreach ($entityConfig->fields as $fieldName => $type) {
            $item->$fieldName = $type->transformToView($data["{$prefix}_$fieldName"], $transformationOptions);
        }

        return $item;
    }
}
