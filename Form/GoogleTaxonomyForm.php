<?php


namespace GoogleShoppingXml\Form;

use GoogleShoppingXml\GoogleShoppingXml;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Symfony\Component\Validator\Constraints;

class GoogleTaxonomyForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("thelia_category_id", "text", array(
                'required' => true,
                "constraints" => array(
                    new Constraints\NotBlank(),
                )
            ))
            ->add("google_category_id", "number", array(
                'required' => true,
                "constraints" => array(
                    new Constraints\NotBlank(),
                )
            ));
    }

    public function getName()
    {
        return "googleshoppingxml_taxonomy";
    }
}
