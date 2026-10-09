<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Service;

use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Lang;

/**
 * The language of the labels shown in the back-office: the edit language of the administrator,
 * else the language of the request.
 */
final readonly class EditionLocale
{
    public function of(?Request $request): string
    {
        if (null === $request) {
            return (string) Lang::getDefaultLanguage()->getLocale();
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        $editionLang = $session instanceof Session ? $session->getAdminEditionLang() : null;

        return $editionLang instanceof Lang ? (string) $editionLang->getLocale() : $request->getLocale();
    }
}
