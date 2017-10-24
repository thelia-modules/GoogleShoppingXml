<?php

namespace GoogleShoppingXml\Controller;

use GoogleShoppingXml\GoogleShoppingXml;
use GoogleShoppingXml\Model\GoogleshoppingxmlGoogleFieldAssociation;
use GoogleShoppingXml\Model\GoogleshoppingxmlGoogleFieldAssociationQuery;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;

class GoogleFieldAssociationController extends BaseAdminController
{
    const ASSO_TYPE_FIXED_VALUE = 1;
    const ASSO_TYPE_RELATED_TO_THELIA_ATTRIBUTE = 2;
    const ASSO_TYPE_RELATED_TO_THELIA_FEATURE = 3;

    // The following are already defined in the XML output file by the module and cannot be overwritten.
    const FIELDS_NATIVELY_DEFINED = array(
        'id', 'title', 'description', 'link', 'image_link', 'price', 'identifier_exists',
        'shipping', 'google_product_category', 'product_type'
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


    public function updateFieldAction()
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('GoogleShoppingXml'), AccessManager::UPDATE)) {
            return $response;
        }

        $message = null;

        try {
            $httpRequest = $this->getRequest();
            $fieldAssociation = GoogleshoppingxmlGoogleFieldAssociationQuery::create()
                ->findOneById($httpRequest->request->get('id'));
            if ($fieldAssociation != null) {
                $this->hydrateFieldAssociationObjectWithRequestContent($fieldAssociation, $this->getRequest());
                $fieldAssociation->save();
            } else {
                throw new \Exception($this->getTranslator()->trans('Unable to find the field association to update.', [], GoogleShoppingXml::DOMAIN_NAME));
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


    public function deleteFieldAction()
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('GoogleShoppingXml'), AccessManager::DELETE)) {
            return $response;
        }

        $message = null;

        try {
            $httpRequest = $this->getRequest();
            $fieldAssociation = GoogleshoppingxmlGoogleFieldAssociationQuery::create()
                ->findOneById($httpRequest->request->get('id_field_to_delete'));
            if ($fieldAssociation != null) {
                $fieldAssociation->delete();
            } else {
                throw new \Exception($this->getTranslator()->trans('Unable to find the field association to delete.', [], GoogleShoppingXml::DOMAIN_NAME));
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
            throw new \Exception($this->getTranslator()->trans('The Google attribute cannot be empty.', [], GoogleShoppingXml::DOMAIN_NAME));
        }

        // 'g:' is the beginning of the XML tag. If the user added it manually, remove it, it will be added afterwards.
        $prefix = 'g:';
        if (substr($googleAttribute, 0, strlen($prefix)) == $prefix) {
            $googleAttribute = substr($googleAttribute, strlen($prefix));
        }

        if (in_array($googleAttribute, self::FIELDS_NATIVELY_DEFINED)) {
            throw new \Exception($this->getTranslator()->trans(
                'The Google attribute "%name" cannot be redefined here as it is already defined natively by the module.',
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
                    throw new \Exception($this->getTranslator()->trans('The fixed value cannot be empty if you have chosen the "Fixed value" association type.', [], GoogleShoppingXml::DOMAIN_NAME));
                }
                $fieldAssociation->setFixedValue($fixedValue);
                break;
            case self::ASSO_TYPE_RELATED_TO_THELIA_ATTRIBUTE:
                $thelia_attribute_id = $request->get('thelia_attribute');
                if (empty($thelia_attribute_id)) {
                    throw new \Exception($this->getTranslator()->trans('The Thelia attribute cannot be empty if you have chosen the "Linked to a Thelia attribute" association type.', [], GoogleShoppingXml::DOMAIN_NAME));
                }
                $fieldAssociation->setIdRelatedAttribute($thelia_attribute_id);
                break;
            case self::ASSO_TYPE_RELATED_TO_THELIA_FEATURE:
                $thelia_feature_id = $request->get('thelia_feature');
                if (empty($thelia_feature_id)) {
                    throw new \Exception($this->getTranslator()->trans('The Thelia feature cannot be empty if you have chosen the "Linked to a Thelia feature" association type.', [], GoogleShoppingXml::DOMAIN_NAME));
                }
                $fieldAssociation->setIdRelatedFeature($thelia_feature_id);
                break;
            default:
                throw new \Exception($this->getTranslator()->trans('The chosen association type is unknown.', [], GoogleShoppingXml::DOMAIN_NAME));
        }

        $fieldAssociation->setAssociationType($associationType);
        return $fieldAssociation;
    }
}
