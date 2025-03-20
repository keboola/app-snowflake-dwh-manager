<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Connection;

interface Expr
{
    public function getValue(): mixed;
}
