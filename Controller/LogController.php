<?php

namespace GoogleShoppingXml\Controller;

use GoogleShoppingXml\Model\GoogleshoppingxmlLog;
use GoogleShoppingXml\Model\GoogleshoppingxmlLogQuery;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;

class LogController extends BaseAdminController
{
    public function getLogAction()
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('GoogleShoppingXml'), AccessManager::CREATE)) {
            return $response;
        }

        $limit = $this->getRequest()->get('limit', 50);
        $offset = $this->getRequest()->get('offset', null);

        $query = GoogleshoppingxmlLogQuery::create()
            ->orderByCreatedAt('desc')
            ->orderById('desc')
            ->limit($limit);

        if (!empty($offset)) {
            $query->offset($offset);
        }

        $logCollection = $query->find();

        $logResults = [];

        /** @var GoogleshoppingxmlLog $log **/
        foreach ($logCollection as $log) {
            $logArray = [];
            $logArray['date'] = $log->getCreatedAt()->format('d/m/Y H:i:s');
            $logArray['feed_id'] = $log->getFeedId();
            $logArray['feed_label'] = $log->getGoogleshoppingxmlFeed()->getLabel();
            $logArray['level'] = $log->getLevel();
            $logArray['message'] = $log->getMessage();
            $logArray['help'] = $log->getHelp();
            $logArray['product_id'] = !empty($log->getProductSaleElements()) ? $log->getProductSaleElements()->getProductId() : null;
            $logArray['product_ref'] = !empty($log->getProductSaleElements()) ? $log->getProductSaleElements()->getProduct()->getRef() : null;

            $logResults[] = $logArray;
        }

        return $this->jsonResponse(json_encode($logResults));
    }
}
