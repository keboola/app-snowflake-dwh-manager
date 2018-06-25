<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Connection;

class Expr
{
    /** @var string */
    private $value;

    public function __construct(
        string $value
    ) {
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
