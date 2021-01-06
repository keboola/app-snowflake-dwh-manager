<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Connection;

class ExprInt implements Expr
{
    /** @var int */
    private $value;

    public function __construct(
        int $value
    ) {
        $this->value = $value;
    }

    public function getValue(): int
    {
        return $this->value;
    }
}
