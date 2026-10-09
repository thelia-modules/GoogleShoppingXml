<?php

namespace GoogleShoppingXml\Tools;

class GtinChecker
{
    public function isValidGtin($gtin)
    {
        if (!ctype_digit((string) $gtin)) {
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

    /**
     * The weights run from the right of the code without its check digit: 3 for the last digit, then 1, 3...
     * (GS1). Counted from the left, an EAN-13 (twelve digits before the check digit) got the weights of the
     * other lengths swapped and most valid codes were refused.
     */
    protected function gtinCheckSum($code)
    {
        $total = 0;

        foreach (array_reverse(str_split($code)) as $i => $c) {
            $total += 0 === $i % 2 ? 3 * (int) $c : (int) $c;
        }
        $checkDigit = (10 - ($total % 10)) % 10;
        return $checkDigit;
    }
}
