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
    public function getReadOnlySchemas(): array
    {
        $legacyReadOnlySchemaNames = $this->getValue(['business_schemas']);
        $newReadOnlySchemaNames = $this->getSchemaNamesByPermission(UserDefinition::PERMISSION_READ);
        return array_unique(array_merge(
            array_values($legacyReadOnlySchemaNames),
            array_values($newReadOnlySchemaNames)
        ));
    }

    /**
     * @return string[]
     */
    public function getWriteSchemas(): array
    {
        return $this->getSchemaNamesByPermission(UserDefinition::PERMISSION_WRITE);
    }

    /**
     * @return string[]
     */
    private function getSchemaNamesByPermission(string $permission): array
    {
        return $this->mapSchemaConfigToSchemaNames(
            array_filter(
                $this->getValue(['schemas']),
                function (array $schema) use ($permission) {
                    return $schema['permission'] === $permission;
                }
            )
        );
    }

    /**
     * @param string[][] $schemas
     * @return string[]
     */
    private function mapSchemaConfigToSchemaNames(array $schemas): array
    {
        return array_map(function (array $schema): string {
            return $schema['name'];
        }, $schemas);
    }

    public function isDisabled(): bool
    {
        return (bool) $this->getValue(['disabled']);
    }
}
