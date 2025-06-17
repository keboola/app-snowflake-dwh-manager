<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Tests;

use Keboola\SnowflakeDwhManager\ConfigDefinition;
use Keboola\SnowflakeDwhManager\Configuration\UserDefinition;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConfigDefinitionTest extends TestCase
{
    /**
     * @param array<string, string|array<mixed>> $expected
     * @param array<string, string|array<mixed>> $configurationData
     *
     * @dataProvider provideValidConfigs
     */
    public function testValidConfigs(array $expected, array $configurationData): void
    {
        $definition = new ConfigDefinition();
        $processor = new Processor();
        $processedConfiguration = $processor->processConfiguration(
            $definition,
            [$configurationData],
        );
        $this->assertSame($expected, $processedConfiguration);
    }

    /**
     * @return mixed[][]
     */
    public function provideValidConfigs(): array
    {
        return [
            'user' => [
                [
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
                            'schemas' => [],
                            'statement_timeout' => 10800,
                            'disabled' => false,
                            'reset_password' => false,
                            'reset_mfa' => false,
                            'person_type' => false,
                        ],
                        '#master_private_key' => null,
                    ],
                ],
                [
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
                ],
            ],
            'user with reset password' => [
                [
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
                            'reset_password' => true,
                            'schemas' => [],
                            'statement_timeout' => 10800,
                            'disabled' => false,
                            'reset_mfa' => false,
                            'person_type' => false,
                        ],
                        '#master_private_key' => null,
                    ],
                ],
                [
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
                            'reset_password' => true,
                        ],
                    ],
                ],
            ],
            'user with write schema' => [
                [
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
                            'schemas' => [
                                ['name' => 'dwh3', 'permission' => UserDefinition::PERMISSION_WRITE],
                            ],
                            'statement_timeout' => 10800,
                            'disabled' => false,
                            'reset_password' => false,
                            'reset_mfa' => false,
                            'person_type' => false,
                        ],
                        '#master_private_key' => null,
                    ],
                ],
                [
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
                            'schemas' => [
                                ['name' => 'dwh3', 'permission' => UserDefinition::PERMISSION_WRITE],
                            ],
                        ],
                    ],
                ],
            ],
            'user with statement timeout' => [
                [
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
                            'statement_timeout' => 123,
                            'schemas' => [],
                            'disabled' => false,
                            'reset_password' => false,
                            'reset_mfa' => false,
                            'person_type' => false,
                        ],
                        '#master_private_key' => null,
                    ],
                ],
                [
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
                            'statement_timeout' => 123,
                        ],
                    ],
                ],
            ],
            'schema' => [
                [
                    'parameters' => [
                        'master_host' => 'host',
                        'master_user' => 'user',
                        '#master_password' => 'password',
                        'master_database' => 'database',
                        'warehouse' => 'warehouse',
                        'business_schema' => [
                            'schema_name' => 'dwh1',
                            'statement_timeout' => 10800,
                            'reset_password' => false,
                            'public_key' => null,
                        ],
                        '#master_private_key' => null,
                    ],
                ],
                [
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
                ],
            ],
            'schema with user statement timeout' => [
                [
                    'parameters' => [
                        'master_host' => 'host',
                        'master_user' => 'user',
                        '#master_password' => 'password',
                        'master_database' => 'database',
                        'warehouse' => 'warehouse',
                        'business_schema' => [
                            'schema_name' => 'dwh1',
                            'statement_timeout' => 123,
                            'reset_password' => false,
                            'public_key' => null,
                        ],
                        '#master_private_key' => null,
                    ],
                ],
                [
                    'parameters' => [
                        'master_host' => 'host',
                        'master_user' => 'user',
                        '#master_password' => 'password',
                        'master_database' => 'database',
                        'warehouse' => 'warehouse',
                        'business_schema' => [
                            'schema_name' => 'dwh1',
                            'statement_timeout' => 123,
                        ],
                    ],
                ],
            ],
            'schema with reset password' => [
                [
                    'parameters' => [
                        'master_host' => 'host',
                        'master_user' => 'user',
                        '#master_password' => 'password',
                        'master_database' => 'database',
                        'warehouse' => 'warehouse',
                        'business_schema' => [
                            'schema_name' => 'dwh1',
                            'reset_password' => true,
                            'statement_timeout' => 10800,
                            'public_key' => null,
                        ],
                        '#master_private_key' => null,
                    ],
                ],
                [
                    'parameters' => [
                        'master_host' => 'host',
                        'master_user' => 'user',
                        '#master_password' => 'password',
                        'master_database' => 'database',
                        'warehouse' => 'warehouse',
                        'business_schema' => [
                            'schema_name' => 'dwh1',
                            'reset_password' => true,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, array<mixed>> $configurationData
     * @dataProvider provideInvalidConfigs
     */
    public function testInvalidConfigs(
        string $expectedException,
        string $expectedExceptionMessage,
        array $configurationData,
    ): void {
        $definition = new ConfigDefinition();
        $processor = new Processor();
        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedExceptionMessage);
        $processor->processConfiguration(
            $definition,
            [$configurationData],
        );
    }

    /**
     * @return mixed[][]
     */
    public function provideInvalidConfigs(): array
    {
        return [
            'both user and schema' => [
                InvalidConfigurationException::class,
                'The "user" and "business_schema" options are mutually exclusive.',
                [
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
                        'business_schema' => [
                            'schema_name' => 'dwh1',
                        ],
                    ],
                ],
            ],
            'neither user or schema' => [
                InvalidConfigurationException::class,
                'Either "user" or "business_schema" must be present.',
                [
                    'parameters' => [
                        'master_host' => 'host',
                        'master_user' => 'user',
                        '#master_password' => 'password',
                        'master_database' => 'database',
                        'warehouse' => 'warehouse',
                    ],
                ],
            ],
            'invalid schema name' => [
                InvalidConfigurationException::class,
                'Invalid configuration for path "root.parameters.business_schema.schema_name": '
                . 'Schema name can only contain alphanumeric characters and underscores',
                [
                    'parameters' => [
                        'master_host' => 'host',
                        'master_user' => 'user',
                        '#master_password' => 'password',
                        'master_database' => 'database',
                        'warehouse' => 'warehouse',
                        'business_schema' => [
                            'schema_name' => 'dwh-1',
                        ],
                    ],
                ],
            ],
            'invalid schema name in user' => [
                InvalidConfigurationException::class,
                'Schema name can only contain alphanumeric characters and underscores',
                [
                    'parameters' => [
                        'master_host' => 'host',
                        'master_user' => 'user',
                        '#master_password' => 'password',
                        'master_database' => 'database',
                        'warehouse' => 'warehouse',
                        'user' => [
                            'email' => 'test@example.com',
                            'business_schemas' => [
                                'dwh-1',
                                'dwh-2',
                            ],
                        ],
                    ],
                ],
            ],
            'invalid write schema name in user' => [
                InvalidConfigurationException::class,
                'Schema name can only contain alphanumeric characters and underscores',
                [
                    'parameters' => [
                        'master_host' => 'host',
                        'master_user' => 'user',
                        '#master_password' => 'password',
                        'master_database' => 'database',
                        'warehouse' => 'warehouse',
                        'user' => [
                            'email' => 'test@example.com',
                            'schemas' => [
                                [
                                    'name' => 'dwh-1',
                                    'permission' => UserDefinition::PERMISSION_READ,
                                ],
                                [
                                    'name' => 'dwh-2',
                                    'permission' => UserDefinition::PERMISSION_WRITE,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'invalid write schema permission in user' => [
                InvalidConfigurationException::class,
                'Permission 37 is not valid',
                [
                    'parameters' => [
                        'master_host' => 'host',
                        'master_user' => 'user',
                        '#master_password' => 'password',
                        'master_database' => 'database',
                        'warehouse' => 'warehouse',
                        'user' => [
                            'email' => 'test@example.com',
                            'schemas' => [
                                [
                                    'name' => 'dwh1',
                                    'permission' => UserDefinition::PERMISSION_READ,
                                ],
                                [
                                    'name' => 'dwh2',
                                    'permission' => 37,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'empty master_host' => [
                InvalidConfigurationException::class,
                'The path "root.parameters.master_host" cannot contain an empty value',
                [
                    'parameters' => [
                        'master_host' => '',
                        'master_user' => 'user',
                        '#master_password' => 'password',
                        'master_database' => 'database',
                        'warehouse' => 'warehouse',
                        'user' => [
                            'email' => 'test@example.com',
                        ],
                    ],
                ],
            ],
            'empty master_user' => [
                InvalidConfigurationException::class,
                'The path "root.parameters.master_user" cannot contain an empty value',
                [
                    'parameters' => [
                        'master_host' => 'host',
                        'master_user' => '',
                        '#master_password' => 'password',
                        'master_database' => 'database',
                        'warehouse' => 'warehouse',
                        'user' => [
                            'email' => 'test@example.com',
                        ],
                    ],
                ],
            ],
            'empty master_database' => [
                InvalidConfigurationException::class,
                'The path "root.parameters.master_database" cannot contain an empty value',
                [
                    'parameters' => [
                        'master_host' => 'host',
                        'master_user' => 'user',
                        '#master_password' => 'password',
                        'master_database' => '',
                        'warehouse' => 'warehouse',
                        'user' => [
                            'email' => 'test@example.com',
                        ],
                    ],
                ],
            ],
            'empty warehouse' => [
                InvalidConfigurationException::class,
                'The path "root.parameters.warehouse" cannot contain an empty value',
                [
                    'parameters' => [
                        'master_host' => 'host',
                        'master_user' => 'user',
                        '#master_password' => 'password',
                        'master_database' => 'database',
                        'warehouse' => '',
                        'user' => [
                            'email' => 'test@example.com',
                        ],
                    ],
                ],
            ],
            'empty user.email' => [
                InvalidConfigurationException::class,
                'The path "root.parameters.user.email" cannot contain an empty value',
                [
                    'parameters' => [
                        'master_host' => 'host',
                        'master_user' => 'user',
                        '#master_password' => 'password',
                        'master_database' => 'database',
                        'warehouse' => 'warehouse',
                        'user' => [
                            'email' => '',
                        ],
                    ],
                ],
            ],
        ];
    }
}
