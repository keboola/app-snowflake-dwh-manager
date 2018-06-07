<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager;

use Couchbase\UserSettings;
use Keboola\Component\Config\BaseConfig;
use Keboola\SnowflakeDwhManager\Configuration\Schema;
use Keboola\SnowflakeDwhManager\Configuration\SchemaDefinition;
use Keboola\SnowflakeDwhManager\Configuration\User;
use Keboola\SnowflakeDwhManager\Configuration\UserDefinition;

class Config extends BaseConfig
{
    /**
     * @return string[]
     */
    public function getSnowflakeConnectionOptions(): array
    {
        return [
            'host' => $this->getValue(['parameters','master_host']),
            //'port' => $this->getValue(['parameters','master_port']),
            'user' => $this->getValue(['parameters','master_user']),
            'password' => $this->getValue(['parameters','master_password']),
            'database' => $this->getValue(['parameters','master_database']),
            'warehouse' => $this->getValue(['parameters','warehouse']),
            /*'tracing',
            'loginTimeout',
            'networkTimeout',
            'queryTimeout',*/
        ];
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
        return new Schema($this->getValue(['parameters', 'schema']), new SchemaDefinition());
    }

    public function getUser(): User
    {
        return new User($this->getValue(['parameters', 'user']), new UserDefinition());
    }
}
