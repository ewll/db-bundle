<?php namespace Ewll\DBBundle\Form\ChoiceList\Loader;

use Ewll\DBBundle\Query\QueryBuilder;
use Ewll\DBBundle\Repository\Repository;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class EntityChoiceLoader implements ChoiceLoaderInterface
{
    const PLACEHOLDER = '{:}';

    /** @var ArrayChoiceList */
    private $choiceList;

    private $translator;
    private $repository;
    private $placeholder;
    private $translationDomain;
    private $conditions;

    public function __construct(
        TranslatorInterface $translator,
        Repository $repository,
        string $placeholder = null,
        string $translationDomain = null,
        array $conditions = []
    ) {
        $this->translator = $translator;
        $this->repository = $repository;
        $this->placeholder = $placeholder;
        $this->translationDomain = $translationDomain;
        $this->conditions = $conditions;
    }

    /** {@inheritdoc} */
    public function loadChoiceList($value = null)
    {
        if (null !== $this->choiceList) {
            return $this->choiceList;
        }

        $qb = new QueryBuilder($this->repository, QueryBuilder::DEFAULT_PREFIX, ['id']);
        $qb
            ->addConditions($this->conditions);
        $items = $this->repository->find($qb);

        $choices = [];
        foreach ($items as $item) {
            if (null === $this->placeholder) {
                $text = $item->id;
            } else {
                $placeholder = str_replace(self::PLACEHOLDER, $item->id, $this->placeholder);
                $text = $this->translator->trans($placeholder, [], $this->translationDomain);
            }
            $choices[$text] = $item->id;
        }

        return $this->choiceList = new ArrayChoiceList($choices, $value);
    }

    /** {@inheritdoc} */
    public function loadChoicesForValues(array $values, $value = null)
    {
        // Optimize
        if (empty($values)) {
            return [];
        }

        return $this->loadChoiceList($value)->getChoicesForValues($values);
    }

    /** {@inheritdoc} */
    public function loadValuesForChoices(array $choices, $value = null)
    {
        // Optimize
        if (empty($choices)) {
            return [];
        }

        return $this->loadChoiceList($value)->getValuesForChoices($choices);
    }
}
