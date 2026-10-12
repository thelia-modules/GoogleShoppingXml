<?php

declare(strict_types=1);

namespace Thelia\Log;

if (!class_exists(Tlog::class, false)) {
    /**
     * Stands in for the Thelia logger when the unit tests run without the Thelia kernel.
     */
    final class Tlog
    {
        /** @var list<string> */
        public static array $warnings = [];

        public static function getInstance(): self
        {
            return new self();
        }

        public function warning(string $message): void
        {
            self::$warnings[] = $message;
        }
    }
}
