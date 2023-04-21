<?php

namespace GoogleShoppingXml\Controller;

use Thelia\Controller\Front\BaseFrontController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Thelia\Exception\ImageException;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;


class ImageController extends BaseFrontController
{
    public function getImage($imageFile)
    {
        // Liste des extensions acceptables
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'svg'];

        // Récupère l'extension du fichier
        $extension = pathinfo($imageFile, PATHINFO_EXTENSION);

        // Vérifie si l'extension est dans la liste des extensions acceptables
        if (!in_array(strtolower($extension), $allowedExtensions)) {
            throw new ImageException(sprintf("The image extension file %s is not allowed.", $extension));
        }

        $subdir = "product";

        $cacheDir = THELIA_ROOT . "web/cache/images/" . $subdir . "/" . $imageFile;
        $sourceDir = THELIA_ROOT . "local/media/images/" . $subdir . "/" . $imageFile;


        $imagePath = $cacheDir;
        if (!file_exists($cacheDir)) {
            if (!file_exists($sourceDir)) {
                throw new ImageException(sprintf("Source image file %s does not exists.", $sourceDir));
            }
            $imagePath = $sourceDir;
        }

        return new BinaryFileResponse($imagePath);
    }

}