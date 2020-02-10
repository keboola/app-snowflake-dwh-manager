<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Tests;

use Keboola\SnowflakeDwhManager\Config;
use Keboola\SnowflakeDwhManager\ConfigDefinition;
use Keboola\SnowflakeDwhManager\Configuration\Schema;
use Keboola\SnowflakeDwhManager\Configuration\User;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testGetUser(): void
    {
        $configData = [
            'parameters' => [
                'master_host' => 'host',
                'master_user' => 'user',
                '#master_password' => 'password',
                'master_database' => 'database',
                'warehouse' => 'warehouse',
                'user' => [
                    'email' => 'test@example.com',
                    'business_schemas' => [
                        'dwh1',
                        'dwh2',
                    ],
                ],
            ],
        ];
        $config = new Config($configData, new ConfigDefinition());

        $user = $config->getUser();

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('test@example.com', $user->getEmail());
        $this->assertFalse($user->isDisabled());
    }

    public function testGetSchema(): void
    {
        $configData = [
            'parameters' => [
                'master_host' => 'host',
                'master_user' => 'user',
                '#master_password' => 'password',
                'master_database' => 'database',
                'warehouse' => 'warehouse',
                'business_schema' => [
                    'schema_name' => 'dwh1',
                ],
            ],
        ];

        $config = new Config($configData, new ConfigDefinition());

        $schema = $config->getSchema();

        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertSame('dwh1', $schema->getName());
    }
}
