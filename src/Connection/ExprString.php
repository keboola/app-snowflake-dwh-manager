<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Connection;

class ExprString implements Expr
{
    private string $value;

    public function __construct(
        string $value,
    ) {
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
