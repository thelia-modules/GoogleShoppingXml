<?php

namespace GoogleShoppingXml\Controller;

use Thelia\Controller\Front\BaseFrontController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Thelia\Exception\ImageException;
use Thelia\Model\ProductImageQuery;

class ImageController extends BaseFrontController
{
    public function getImage(int $imageId)
    {
        $productImage = ProductImageQuery::create()->findPk($imageId);

        if (!$productImage) {
            throw new ImageException("Image does not exists.");
        }

        $sourceDir = THELIA_ROOT . "local/media/images/product/" . $productImage->getFile();

        if (!file_exists($sourceDir)) {
            throw new ImageException("Source image file does not exists.");
        }

        return new BinaryFileResponse($sourceDir);
    }
}