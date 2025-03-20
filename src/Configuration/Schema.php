<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Configuration;

use Exception;
use Keboola\Component\Config\BaseConfig;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Schema extends BaseConfig
{
    /**
     * @param array<string> $config
     */
    public function __construct(
        array $config,
        ?ConfigurationInterface $configDefinition = null,
    ) {
        if (!$configDefinition instanceof SchemaDefinition) {
            throw new Exception(sprintf(
                'Config definition must be %s',
                SchemaDefinition::class,
            ));
        }
        parent::__construct($config, $configDefinition);
    }

    public function getName(): string
    {
        return $this->getValue(['schema_name']);
    }

    public function getStatementTimeout(): int
    {
        return (int) $this->getValue(['statement_timeout']);
    }

    public function isResetPassword(): bool
    {
        return (bool) $this->getValue(['reset_password']);
    }
}
