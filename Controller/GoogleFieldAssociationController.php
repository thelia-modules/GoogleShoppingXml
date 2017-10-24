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
            $field_association = new GoogleshoppingxmlGoogleFieldAssociation();
            $this->hydrateFieldAssociationObjectWithRequestContent($field_association, $this->getRequest());
            $field_association->save();
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
            $http_request = $this->getRequest();
            $field_association = GoogleshoppingxmlGoogleFieldAssociationQuery::create()
                ->findOneById($http_request->request->get('id'));
            if ($field_association != null) {
                $this->hydrateFieldAssociationObjectWithRequestContent($field_association, $this->getRequest());
                $field_association->save();
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
            $http_request = $this->getRequest();
            $field_association = GoogleshoppingxmlGoogleFieldAssociationQuery::create()
                ->findOneById($http_request->request->get('id_field_to_delete'));
            if ($field_association != null) {
                $field_association->delete();
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
     * @param Request $http_request
     * @param GoogleshoppingxmlGoogleFieldAssociation $field_association
     * @return GoogleshoppingxmlGoogleFieldAssociation
     */
    protected function hydrateFieldAssociationObjectWithRequestContent(&$field_association, $http_request)
    {
        $request = $http_request->request;

        // ********   Google field   ********

        $google_attribute = $request->get('google_attribute');

        if (empty($google_attribute)) {
            throw new \Exception($this->getTranslator()->trans('The Google attribute cannot be empty.', [], GoogleShoppingXml::DOMAIN_NAME));
        }

        // 'g:' is the beginning of the XML tag. If the user added it manually, remove it, it will be added afterwards.
        $prefix = 'g:';
        if (substr($google_attribute, 0, strlen($prefix)) == $prefix) {
            $google_attribute = substr($google_attribute, strlen($prefix));
        }

        if (in_array($google_attribute, self::FIELDS_NATIVELY_DEFINED)) {
            throw new \Exception($this->getTranslator()->trans(
                'The Google attribute "%name" cannot be redefined here as it is already defined natively by the module.',
                array('%name' => $google_attribute),
                GoogleShoppingXml::DOMAIN_NAME
            ));
        }

        $field_association->setGoogleField($google_attribute);


        // ********   Association type   *********

        $association_type = $request->get('association_type');

        switch ($association_type) {
            case self::ASSO_TYPE_FIXED_VALUE:
                $fixed_value = $request->get('fixed_value');
                if (empty($fixed_value)) {
                    throw new \Exception($this->getTranslator()->trans('The fixed value cannot be empty if you have chosen the "Fixed value" association type.', [], GoogleShoppingXml::DOMAIN_NAME));
                }
                $field_association->setFixedValue($fixed_value);
                break;
            case self::ASSO_TYPE_RELATED_TO_THELIA_ATTRIBUTE:
                $thelia_attribute_id = $request->get('thelia_attribute');
                if (empty($thelia_attribute_id)) {
                    throw new \Exception($this->getTranslator()->trans('The Thelia attribute cannot be empty if you have chosen the "Linked to a Thelia attribute" association type.', [], GoogleShoppingXml::DOMAIN_NAME));
                }
                $field_association->setIdRelatedAttribute($thelia_attribute_id);
                break;
            case self::ASSO_TYPE_RELATED_TO_THELIA_FEATURE:
                $thelia_feature_id = $request->get('thelia_feature');
                if (empty($thelia_feature_id)) {
                    throw new \Exception($this->getTranslator()->trans('The Thelia feature cannot be empty if you have chosen the "Linked to a Thelia feature" association type.', [], GoogleShoppingXml::DOMAIN_NAME));
                }
                $field_association->setIdRelatedFeature($thelia_feature_id);
                break;
            default:
                throw new \Exception($this->getTranslator()->trans('The chosen association type is unknown.', [], GoogleShoppingXml::DOMAIN_NAME));
        }

        $field_association->setAssociationType($association_type);
        return $field_association;
    }
}
