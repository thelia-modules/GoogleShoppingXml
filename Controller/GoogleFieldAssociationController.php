<?php

namespace GoogleShoppingXml\Controller;

use GoogleShoppingXml\Form\CompatibilitySqlForm;
use GoogleShoppingXml\GoogleShoppingXml;
use GoogleShoppingXml\Model\GoogleshoppingxmlGoogleFieldAssociation;
use GoogleShoppingXml\Model\GoogleshoppingxmlGoogleFieldAssociationQuery;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;

class GoogleFieldAssociationController extends BaseAdminController
{
    const ASSO_TYPE_FIXED_VALUE = 1;
    const ASSO_TYPE_RELATED_TO_THELIA_ATTRIBUTE = 2;
    const ASSO_TYPE_RELATED_TO_THELIA_FEATURE = 3;

    // The following are already defined in the XML output file by the module and cannot be overwritten.
    const FIELDS_NATIVELY_DEFINED = array(
        'id', 'title', 'description', 'link', 'image_link', 'availability', 'price', 'sale_price', 'identifier_exists',
        'shipping', 'google_product_category', 'product_type', 'gtin', 'identifier_exists', 'item_group_id', 'shipping_weight'
    );

    const GOOGLE_FIELD_LIST = array(
        'id', 'title', 'description', 'link', 'image_link', 'mobile_link', 'additionnal_image_link', 'availability',
        'availability_date', 'expiration_date', 'price', 'sale_price', 'sale_price_effective_date',
        'unit_pricing_measure', 'unit_pricing_base_measure', 'installment', 'loyalty_points', 'google_product_category',
        'identifier_exists', 'product_type', 'brand', 'gtin', 'mpn', 'identifier_exists', 'condition', 'adult',
        'multipack', 'is_bundle', 'energy_efficiency_class', 'age_group', 'color', 'gender', 'material', 'pattern',
        'size', 'size_type', 'size_system', 'item_group_id', 'adwords_redirect', 'custom_label_0', 'custom_label_1',
        'custom_label_2', 'custom_label_3', 'custom_label_4', 'promotion_id', 'shipping', 'included_destination',
        'excluded_destination', 'shipping_label', 'shipping_weight', 'shipping_length', 'shipping_width',
        'shipping_height', 'min_handling_time', 'max_handling_time', 'tax', 'shipping_weight'
    );

    public function addFieldAction()
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('GoogleShoppingXml'), AccessManager::CREATE)) {
            return $response;
        }

        $message = null;

        try {
            $fieldAssociation = new GoogleshoppingxmlGoogleFieldAssociation();
            $this->hydrateFieldAssociationObjectWithRequestContent($fieldAssociation, $this->getRequest());
            $fieldAssociation->save();
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        $redirectParameters = array(
            'module_code' => 'GoogleShoppingXml',
            'current_tab' => 'advanced'
        );

        if (!empty($message)) {
            $redirectParameters['error_message_advanced_tab'] = $message;
        }

        return $this->generateRedirectFromRoute("admin.module.configure", array(), $redirectParameters);
    }


    public function updateFieldAction(Request $httpRequest)
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('GoogleShoppingXml'), AccessManager::UPDATE)) {
            return $response;
        }

        $message = null;

        try {
            $fieldAssociation = GoogleshoppingxmlGoogleFieldAssociationQuery::create()
                ->findOneById($httpRequest->request->get('id'));
            
            if ($fieldAssociation != null) {
                $this->hydrateFieldAssociationObjectWithRequestContent($fieldAssociation, $httpRequest);
                $fieldAssociation->save();
            } else {
                throw new \Exception(Translator::getInstance()->trans('Unable to find the field association to update.', [], GoogleShoppingXml::DOMAIN_NAME));
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        $redirectParameters = array(
            'module_code' => 'GoogleShoppingXml',
            'current_tab' => 'advanced'
        );

        if (!empty($message)) {
            $redirectParameters['error_message_advanced_tab'] = $message;
        }

        return $this->generateRedirectFromRoute("admin.module.configure", array(), $redirectParameters);
    }


    public function deleteFieldAction(Request $httpRequest)
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('GoogleShoppingXml'), AccessManager::DELETE)) {
            return $response;
        }

        $message = null;

        try {
            $fieldAssociation = GoogleshoppingxmlGoogleFieldAssociationQuery::create()
                ->findOneById($httpRequest->request->get('id_field_to_delete'));
            if ($fieldAssociation != null) {
                $fieldAssociation->delete();
            } else {
                throw new \Exception(Translator::getInstance()->trans('Unable to find the field association to delete.', [], GoogleShoppingXml::DOMAIN_NAME));
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        $redirectParameters = array(
            'module_code' => 'GoogleShoppingXml',
            'current_tab' => 'advanced'
        );

        if (!empty($message)) {
            $redirectParameters['error_message_advanced_tab'] = $message;
        }

        return $this->generateRedirectFromRoute("admin.module.configure", array(), $redirectParameters);
    }


    /**
     * @param Request $httpRequest
     * @param GoogleshoppingxmlGoogleFieldAssociation $fieldAssociation
     * @return GoogleshoppingxmlGoogleFieldAssociation
     */
    protected function hydrateFieldAssociationObjectWithRequestContent(&$fieldAssociation, $httpRequest)
    {
        $request = $httpRequest->request;

        // ********   Google field   ********

        $googleAttribute = $request->get('google_attribute');

        if (empty($googleAttribute)) {
            throw new \Exception(Translator::getInstance()->trans('The Google attribute cannot be empty.', [], GoogleShoppingXml::DOMAIN_NAME));
        }

        $googleAttribute = strtolower($googleAttribute);

        // 'g:' is the beginning of the XML tag. If the user added it manually, remove it, it will be added afterwards.

        $prefix = 'g:';
        if (substr($googleAttribute, 0, strlen($prefix)) == $prefix) {
            $googleAttribute = substr($googleAttribute, strlen($prefix));
        }

        if (in_array($googleAttribute, self::FIELDS_NATIVELY_DEFINED)) {
            throw new \Exception(Translator::getInstance()->trans(
                'The Google attribute "%name" cannot be redefined here as it is already defined by the module.',
                array('%name' => $googleAttribute),
                GoogleShoppingXml::DOMAIN_NAME
            ));
        }

        $fieldAssociation->setGoogleField($googleAttribute);


        // ********   Association type   *********

        $associationType = $request->get('association_type');

        switch ($associationType) {
            case self::ASSO_TYPE_FIXED_VALUE:
                $fixedValue = $request->get('fixed_value');
                if (empty($fixedValue)) {
                    throw new \Exception(Translator::getInstance()->trans('The fixed value cannot be empty if you have chosen the "Fixed value" association type.', [], GoogleShoppingXml::DOMAIN_NAME));
                }
                $fieldAssociation->setFixedValue($fixedValue);
                break;
            case self::ASSO_TYPE_RELATED_TO_THELIA_ATTRIBUTE:
                $thelia_attribute_id = $request->get('thelia_attribute');
                if (empty($thelia_attribute_id)) {
                    throw new \Exception(Translator::getInstance()->trans('The Thelia attribute cannot be empty if you have chosen the "Linked to a Thelia attribute" association type.', [], GoogleShoppingXml::DOMAIN_NAME));
                }
                $fieldAssociation->setIdRelatedAttribute($thelia_attribute_id);
                break;
            case self::ASSO_TYPE_RELATED_TO_THELIA_FEATURE:
                $thelia_feature_id = $request->get('thelia_feature');
                if (empty($thelia_feature_id)) {
                    throw new \Exception(Translator::getInstance()->trans('The Thelia feature cannot be empty if you have chosen the "Linked to a Thelia feature" association type.', [], GoogleShoppingXml::DOMAIN_NAME));
                }
                $fieldAssociation->setIdRelatedFeature($thelia_feature_id);
                break;
            default:
                throw new \Exception(Translator::getInstance()->trans('The chosen association type is unknown.', [], GoogleShoppingXml::DOMAIN_NAME));
        }

        $fieldAssociation->setAssociationType($associationType);
        return $fieldAssociation;
    }

    public function setEanRuleAction(Request $httpRequest)
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('GoogleShoppingXml'), AccessManager::UPDATE)) {
            return $response;
        }

        $ruleArray = [
            FeedXmlController::EAN_RULE_ALL,
            FeedXmlController::EAN_RULE_CHECK_FLEXIBLE,
            FeedXmlController::EAN_RULE_CHECK_STRICT,
            FeedXmlController::EAN_RULE_NONE
        ];

        $gtinRule = $httpRequest->request->get('gtin_rule');
        if ($gtinRule != null && in_array($gtinRule, $ruleArray)) {
            GoogleShoppingXml::setConfigValue("ean_rule", $gtinRule);
        }

        $redirectParameters = array(
            'module_code' => 'GoogleShoppingXml',
            'current_tab' => 'advanced'
        );

        if (!empty($message)) {
            $redirectParameters['error_message_advanced_tab'] = $message;
        }

        return $this->generateRedirectFromRoute("admin.module.configure", array(), $redirectParameters);
    }

    public function setCompatibilitySqlAction(ParserContext $parserContext)
    {
        $form = $this->createForm(CompatibilitySqlForm::getName());

        try {
            $compatibilityForm = $this->validateForm($form);

            GoogleShoppingXml::setConfigValue(GoogleShoppingXml::ENABLE_SQL_8_COMPATIBILITY, $compatibilityForm->get('enable_optimisation')->getData());

            return $this->generateSuccessRedirect($form);
        }catch (\Exception $exception) {
            Tlog::getInstance()->error($exception->getMessage());

            $form->setErrorMessage($exception->getMessage());

            $parserContext
                ->addForm($form)
                ->setGeneralError($exception->getMessage())
            ;

            return $this->generateErrorRedirect($form);
        }
    }
}
