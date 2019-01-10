<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Tests\Configuration;

use Keboola\SnowflakeDwhManager\Configuration\User;
use Keboola\SnowflakeDwhManager\Configuration\UserDefinition;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    /**
     * @dataProvider provideConfigsForGetReadOnlySchema
     */
    public function testGetReadOnlySchemas(array $expected, array $config): void
    {
        $user = new User($config, new UserDefinition());
        $this->assertEquals($expected, $user->getReadOnlySchemas());
    }

    /**
     * @return mixed[][]
     */
    public function provideConfigsForGetReadOnlySchema(): array
    {
        return [
            'multiple schemas legacy' => [
                ['dwh1', 'dwh2'],
                [
                    'email' => 'test@example.com',
                    'business_schemas' => [
                        'dwh1',
                        'dwh2',
                    ],
                ],
            ],
            'single schema legacy' => [
                ['dwh2'],
                [
                    'email' => 'test@example.com',
                    'business_schemas' => [
                        'dwh2',
                    ],
                ],
            ],
            'no schemas legacy' => [
                [],
                [
                    'email' => 'test@example.com',
                    'business_schemas' => [
                    ],
                ],
            ],
            'multiple schemas new' => [
                ['dwh1', 'dwh2'],
                [
                    'email' => 'test@example.com',
                    'schemas' => [
                        ['name' => 'dwh1', 'permission' => UserDefinition::PERMISSION_READ],
                        ['name' => 'dwh2', 'permission' => UserDefinition::PERMISSION_READ],
                    ],
                ],
            ],
            'single schema new' => [
                ['dwh2'],
                [
                    'email' => 'test@example.com',
                    'schemas' => [
                        ['name' => 'dwh2', 'permission' => UserDefinition::PERMISSION_READ],
                    ],
                ],
            ],
            'no schemas new' => [
                [],
                [
                    'email' => 'test@example.com',
                    'schemas' => [
                    ],
                ],
            ],
            'multiple disjunct schemas combined' => [
                ['dwh1', 'dwh2', 'dwh3', 'dwh5'],
                [
                    'email' => 'test@example.com',
                    'business_schemas' => [
                        'dwh1',
                        'dwh2',
                    ],
                    'schemas' => [
                        ['name' => 'dwh3', 'permission' => UserDefinition::PERMISSION_READ],
                        ['name' => 'dwh4', 'permission' => UserDefinition::PERMISSION_WRITE],
                        ['name' => 'dwh5', 'permission' => UserDefinition::PERMISSION_READ],
                    ],
                ],
            ],
            'overlapping same permissions' => [
                ['dwh2','dwh1'],
                [
                    'email' => 'test@example.com',
                    'business_schemas' => [
                        'dwh2',
                    ],
                    'schemas' => [
                        ['name' => 'dwh1', 'permission' => UserDefinition::PERMISSION_READ],
                        ['name' => 'dwh2', 'permission' => UserDefinition::PERMISSION_READ],
                        ['name' => 'dwh4', 'permission' => UserDefinition::PERMISSION_WRITE],
                    ],
                ],
            ],
            'no schemas combined' => [
                [],
                [
                    'email' => 'test@example.com',
                    'business_schemas' => [
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideConfigsGetWriteSchemas
     */
    public function testGetWriteSchemas(array $expected, array $config): void
    {
        $user = new User($config, new UserDefinition());
        $this->assertSame($expected, $user->getWriteSchemas());
    }

    /**
     * @return mixed[][]
     */
    public function provideConfigsGetWriteSchemas(): array
    {
        return [
            'multiple schemas' => [
                [
                    'dwh1',
                    'dwh2',
                ],
                [
                    'email' => 'test@example.com',
                    'business_schemas' => [
                        'dwh1',
                        'dwh2',
                    ],
                    'schemas' => [
                        ['name' => 'dwh1', 'permission' => UserDefinition::PERMISSION_WRITE],
                        ['name' => 'dwh2', 'permission' => UserDefinition::PERMISSION_WRITE],
                    ],
                ],
            ],
            'Does not show read permission schemas' => [
                [
                    'dwh1',
                ],
                [
                    'email' => 'test@example.com',
                    'business_schemas' => [
                        'dwh2',
                    ],
                    'schemas' => [
                        ['name' => 'dwh1', 'permission' => UserDefinition::PERMISSION_WRITE],
                        ['name' => 'dwh2', 'permission' => UserDefinition::PERMISSION_READ],
                    ],
                ],
            ],
            'no schemas' => [
                [],
                [
                    'email' => 'test@example.com',
                    'business_schemas' => [
                    ],
                    'schemas' => [
                    ],
                ],
            ],
        ];
    }
}
