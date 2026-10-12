<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Form;

use GoogleShoppingXml\Service\EditionLocale;
use GoogleShoppingXml\Service\ExclusionRepository;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Thelia\Form\BaseForm;

/**
 * The combinations of the edited product, each with a box to keep it out of the Google Shopping feeds.
 */
class ExclusionForm extends BaseForm
{
    public function __construct(
        private readonly ExclusionRepository $exclusionRepository,
        private readonly EditionLocale $editionLocale,
    ) {
    }

    protected function buildForm(): void
    {
        $productId = (int) ($this->request->attributes->get('productId') ?? $this->request->query->get('product_id') ?? 0);

        $choices = [];
        foreach ($this->exclusionRepository->combinationsOfProduct($productId, $this->editionLocale->of($this->request)) as $combination) {
            $label = '' === $combination['label'] ? $combination['reference'] : \sprintf('%s (%s)', $combination['reference'], $combination['label']);
            if (isset($choices[$label])) {
                $label = \sprintf('%s #%d', $label, $combination['id']);
            }
            $choices[$label] = $combination['id'];
        }

        $this->formBuilder->add('excluded_combinations', ChoiceType::class, [
            'required' => false,
            'multiple' => true,
            'expanded' => true,
            'label' => false,
            'choices' => $choices,
            'choice_translation_domain' => false,
        ]);
    }

    public static function getName(): string
    {
        return 'googleshoppingxml_exclusion';
    }
}
