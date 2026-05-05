<?php

namespace App\Log;

use Monolog\Logger;

class DatabaseLogger
{
    public function __invoke(array $config): Logger
    {
        return new Logger('database', [
            new CustomLogHandler(),
        ]);
    }
}
