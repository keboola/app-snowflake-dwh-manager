<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager;

use Couchbase\UserSettings;
use Keboola\Component\Config\BaseConfig;
use Keboola\Component\UserException;
use Keboola\SnowflakeDwhManager\Configuration\Schema;
use Keboola\SnowflakeDwhManager\Configuration\SchemaDefinition;
use Keboola\SnowflakeDwhManager\Configuration\User;
use Keboola\SnowflakeDwhManager\Configuration\UserDefinition;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Config extends BaseConfig
{
    /**
     * @inheritDoc
     */
    public function __construct(array $config, ?ConfigurationInterface $configDefinition = null)
    {
        /** @var array<string, array<string, mixed>> $config */
        $password = $config['parameters']['#master_password'] ?? '';
        $privateKey = $config['parameters']['#master_private_key'] ?? null;

        if (empty($password) && $privateKey === null) {
            throw new UserException('Either "password" or "privateKey" must be provided.');
        }

        if (!empty($password) && !empty($privateKey)) {
            throw new UserException('Both "password" and "privateKey" cannot be set at the same time.');
        }

        parent::__construct($config, $configDefinition);
    }

    /**
     * @return string[]
     */
    public function getSnowflakeConnectionOptions(): array
    {
        $connectionOptions = [
            'host' => $this->getValue(['parameters', 'master_host']),
            'user' => $this->getValue(['parameters', 'master_user']),
            'password' => $this->getValue(['parameters', '#master_password']),
            'privateKey' => $this->getValue(['parameters', '#master_private_key']),
            'database' => $this->getValue(['parameters', 'master_database']),
            'warehouse' => $this->getValue(['parameters', 'warehouse']),
        ];

        if (getenv('KBC_RUNID')) {
            $connectionOptions['runId'] = (string) getenv('KBC_RUNID');
        }

        return $connectionOptions;
    }

    public function isUserRow(): bool
    {
        return (bool) $this->getValue(['parameters', 'user'], false);
    }

    public function isSchemaRow(): bool
    {
        return (bool) $this->getValue(['parameters', 'business_schema'], false);
    }

    public function getSchema(): Schema
    {
        return new Schema($this->getValue(['parameters', 'business_schema']), new SchemaDefinition());
    }

    public function getUser(): User
    {
        return new User($this->getValue(['parameters', 'user']), new UserDefinition());
    }

    public function getWarehouse(): string
    {
        return $this->getValue(['parameters', 'warehouse']);
    }
    public function getDatabase(): string
    {
        return $this->getValue(['parameters', 'master_database']);
    }
}
