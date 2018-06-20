<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Tests;

use Keboola\SnowflakeDwhManager\ConfigDefinition;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConfigDefinitionTest extends TestCase
{
    /**
     * @dataProvider provideValidConfigs
     */
    public function testValidConfigs(array $expected, array $configurationData): void
    {
        $definition = new ConfigDefinition();
        $processor = new Processor();
        $processedConfiguration = $processor->processConfiguration(
            $definition,
            [$configurationData]
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
                        'master_password' => 'password',
                        'master_database' => 'database',
                        'warehouse' => 'warehouse',
                        'user' => [
                            'email' => 'test@example.com',
                            'business_schemas' => [
                                'dwh1',
                                'dwh2',
                            ],
                            'disabled' => false,
                        ],
                    ],
                ],
                [
                    'parameters' => [
                        'master_host' => 'host',
                        'master_user' => 'user',
                        'master_password' => 'password',
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
            'schema' => [
                [
                    'parameters' => [
                        'master_host' => 'host',
                        'master_user' => 'user',
                        'master_password' => 'password',
                        'master_database' => 'database',
                        'warehouse' => 'warehouse',
                        'business_schema' => [
                            'schema_name' => 'dwh1',
                        ],
                    ],
                ],
                [
                    'parameters' => [
                        'master_host' => 'host',
                        'master_user' => 'user',
                        'master_password' => 'password',
                        'master_database' => 'database',
                        'warehouse' => 'warehouse',
                        'business_schema' => [
                            'schema_name' => 'dwh1',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideInvalidConfigs
     */
    public function testInvalidConfigs(
        string $expectedException,
        string $expectedExceptionMessage,
        array $configurationData
    ): void {
        $definition = new ConfigDefinition();
        $processor = new Processor();
        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedExceptionMessage);
        $processedConfiguration = $processor->processConfiguration(
            $definition,
            [$configurationData]
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
                        'master_password' => 'password',
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
                        'master_password' => 'password',
                        'master_database' => 'database',
                        'warehouse' => 'warehouse',
                    ],
                ],
            ],
        ];
    }
}
