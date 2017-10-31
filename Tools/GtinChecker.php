<?php

namespace GoogleShoppingXml\Tools;

class GtinChecker
{
    public function isValidGtin($gtin)
    {
        if (!is_numeric($gtin)) {
            return false;
        } elseif (!in_array(strlen($gtin), array(8, 12, 13, 14, 18))) {
            return false;
        }

        return $this->isGtinChecksumValid($gtin);
    }

    protected function isGtinChecksumValid($code)
    {
        $lastPart = substr($code, -1);
        $checkSum = $this->gtinCheckSum(substr($code, 0, strlen($code)-1));
        return $lastPart == $checkSum;
    }

    protected function gtinCheckSum($code)
    {
        $total = 0;

        $codeArray = str_split($code);
        foreach (array_values($codeArray) as $i => $c) {
            if ($i % 2 == 1) {
                $total = $total + $c;
            } else {
                $total = $total + (3*$c);
            }
        }
        $checkDigit = (10 - ($total % 10)) % 10;
        return $checkDigit;
    }
}
