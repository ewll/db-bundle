<?php namespace Ewll\DBBundle\Form\ChoiceList\Loader;

use Ewll\DBBundle\Query\QueryBuilder;
use Ewll\DBBundle\Repository\FilterExpression;
use Ewll\DBBundle\Repository\Repository;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class EntityChoiceLoader implements ChoiceLoaderInterface
{
    const PLACEHOLDER = '{:}';

    /** @var ArrayChoiceList */
    private $choiceList;

    private $repository;
    private $compileText;
    private $conditions;
    private $fetchingFields;

    public function __construct(
        Repository $repository,
        callable $compileText,
        array $conditions = [],
        array $fetchingFields = ['id']
    ) {
        $this->repository = $repository;
        $this->compileText = $compileText;
        $this->conditions = $conditions;
        $this->fetchingFields = $fetchingFields;
    }

    /** {@inheritdoc} */
    public function loadChoiceList(callable $value = null)
    {
        if (null !== $this->choiceList) {
            return $this->choiceList;
        }

        $qb = new QueryBuilder($this->repository, QueryBuilder::DEFAULT_PREFIX, $this->fetchingFields);
        $qb
            ->addConditions($this->conditions)
            ->addCondition(new FilterExpression(FilterExpression::ACTION_EQUAL, 'isDeleted', 0));
        $items = $this->repository->find($qb);

        $choices = [];
        foreach ($items as $item) {
            $text = ($this->compileText)($item);
            $choices[$text] = $item->id;
        }

        return $this->choiceList = new ArrayChoiceList($choices, $value);
    }

    /** {@inheritdoc} */
    public function loadChoicesForValues(array $values, callable $value = null)
    {
        // Optimize
        if (empty($values)) {
            return [];
        }

        return $this->loadChoiceList($value)->getChoicesForValues($values);
    }

    /** {@inheritdoc} */
    public function loadValuesForChoices(array $choices, callable $value = null)
    {
        // Optimize
        if (empty($choices)) {
            return [];
        }

        return $this->loadChoiceList($value)->getValuesForChoices($choices);
    }
}
