<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Configuration;

use Exception;
use Keboola\Component\Config\BaseConfig;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class User extends BaseConfig
{
    public function __construct(
        array $config,
        ?ConfigurationInterface $configDefinition = null
    ) {
        if (!$configDefinition instanceof UserDefinition) {
            throw new Exception(sprintf(
                'Config definition must be %s',
                UserDefinition::class
            ));
        }
        parent::__construct($config, $configDefinition);
    }

    public function getEmail(): string
    {
        return $this->getValue(['email']);
    }

    /**
     * @return string[]
     */
    public function getSchemas(): array
    {
        return $this->getValue(['business_schemas']);
    }

    public function isDisabled(): bool
    {
        return (bool) $this->getValue(['disabled']);
    }
}
