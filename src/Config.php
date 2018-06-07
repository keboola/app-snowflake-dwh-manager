<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager;

use Keboola\Component\Config\BaseConfig;

class Config extends BaseConfig
{
    // @todo implement your custom getters
    public function getFoo() : string
    {
        return $this->getValue(['parameters', 'foo']);
    }
}
