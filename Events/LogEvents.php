<?php

namespace GoogleShoppingXml\Events;

class LogEvents
{
    const PROPEL_PRE_INSERT = "credit-note.pre.insert.";

    public static function preInsert($tableName)
    {
        return self::PROPEL_PRE_INSERT . $tableName;
    }
}