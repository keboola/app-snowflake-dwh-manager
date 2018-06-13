<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Manager;

use Keboola\SnowflakeDwhManager\Connection;

class Generator
{
    /** @var Connection */
    private $connection;

    public function __construct(
        Connection $connection
    ) {
        $this->connection = $connection;
    }
}
