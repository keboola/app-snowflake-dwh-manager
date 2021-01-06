<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Connection;

interface Expr
{
    /**
     * @return mixed
     */
    public function getValue();
}
