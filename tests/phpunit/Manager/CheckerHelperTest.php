<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Tests\Manager;

use Keboola\SnowflakeDwhManager\Connection;
use Keboola\SnowflakeDwhManager\Manager\CheckerHelper;
use PHPUnit\Framework\TestCase;

class CheckerHelperTest extends TestCase
{
    /**
     * @param array<mixed> $expected
     * @param array<mixed> $grants
     * @dataProvider provideGrantsArrayToFilterByObjectType
     */
    public function testGetGrantsOfObjectTypeFromGrantsArray(
        array $expected,
        string $objectType,
        array $grants,
    ): void {
        $helper = new CheckerHelper();
        $actual = $helper->filterGrantsByObjectTypeGrantedOn($objectType, $grants);

        $this->assertSame($expected, $actual);
    }

    /**
     * @return array<mixed>
     */
    public function provideGrantsArrayToFilterByObjectType(): array
    {
        $grantsFromDb = [
            [
                'created_on' => '2018-06-19 18:49:36',
                'privilege' => 'USAGE',
                'granted_on' => 'DATABASE',
                'name' => 'TF2_DWH_MANAGER',
                'granted_to' => 'ROLE',
                'grantee_name' => 'TF2_DWH_MANAGER_MY-DWH-SCHEMA_ROLE_RW',
                'grant_option' => 'false',
                'granted_by' => 'TF2_DWH_MANAGER',
            ],
            [
                'created_on' => '2018-06-19 18:49:36',
                'privilege' => 'MODIFY',
                'granted_on' => 'SCHEMA',
                'name' => 'TF2_DWH_MANAGER."MY-DWH-SCHEMA"',
                'granted_to' => 'ROLE',
                'grantee_name' => 'TF2_DWH_MANAGER_MY-DWH-SCHEMA_ROLE_RW',
                'grant_option' => 'false',
                'granted_by' => 'TF2_DWH_MANAGER',
            ],
            [
                'created_on' => '2018-06-19 18:49:36',
                'privilege' => 'USAGE',
                'granted_on' => 'SCHEMA',
                'name' => 'TF2_DWH_MANAGER."MY-DWH-SCHEMA"',
                'granted_to' => 'ROLE',
                'grantee_name' => 'TF2_DWH_MANAGER_MY-DWH-SCHEMA_ROLE_RW',
                'grant_option' => 'false',
                'granted_by' => 'TF2_DWH_MANAGER',
            ],
            [
                'created_on' => '2018-06-20 09:50:41',
                'privilege' => 'USAGE',
                'granted_on' => 'WAREHOUSE',
                'name' => 'DEV',
                'granted_to' => 'ROLE',
                'grantee_name' => 'TF2_DWH_MANAGER_MY-DWH-SCHEMA_ROLE_RW',
                'grant_option' => 'false',
                'granted_by' => 'TF2_DWH_MANAGER',
            ],
        ];
        return [
            [
                [
                    [
                        'created_on' => '2018-06-20 09:50:41',
                        'privilege' => 'USAGE',
                        'granted_on' => 'WAREHOUSE',
                        'name' => 'DEV',
                        'granted_to' => 'ROLE',
                        'grantee_name' => 'TF2_DWH_MANAGER_MY-DWH-SCHEMA_ROLE_RW',
                        'grant_option' => 'false',
                        'granted_by' => 'TF2_DWH_MANAGER',
                    ],
                ],
                Connection::OBJECT_TYPE_WAREHOUSE,
                $grantsFromDb,
            ],
            [
                [
                    [
                        'created_on' => '2018-06-19 18:49:36',
                        'privilege' => 'USAGE',
                        'granted_on' => 'DATABASE',
                        'name' => 'TF2_DWH_MANAGER',
                        'granted_to' => 'ROLE',
                        'grantee_name' => 'TF2_DWH_MANAGER_MY-DWH-SCHEMA_ROLE_RW',
                        'grant_option' => 'false',
                        'granted_by' => 'TF2_DWH_MANAGER',
                    ],
                ],
                Connection::OBJECT_TYPE_DATABASE,
                $grantsFromDb,
            ],
        ];
    }

    /**
     * @param array<mixed> $expected
     * @param array<mixed> $grants
     *
     * @dataProvider provideGrantsArrayToMap
     */
    public function testMapGrantsToNames(array $expected, array $grants): void
    {
        $helper = new CheckerHelper();
        $actual = $helper->mapGrantsArrayToGrantedResourceNames($grants);

        $this->assertSame($expected, $actual);
    }

    /**
     * @return array<mixed>
     */
    public function provideGrantsArrayToMap(): array
    {
        $grantsFromDb = [
            [
                'created_on' => '2018-06-19 18:49:36',
                'privilege' => 'USAGE',
                'granted_on' => 'DATABASE',
                'name' => 'TF2_DWH_MANAGER',
                'granted_to' => 'ROLE',
                'grantee_name' => 'TF2_DWH_MANAGER_MY-DWH-SCHEMA_ROLE_RW',
                'grant_option' => 'false',
                'granted_by' => 'TF2_DWH_MANAGER',
            ],
            [
                'created_on' => '2018-06-19 18:49:36',
                'privilege' => 'MODIFY',
                'granted_on' => 'SCHEMA',
                'name' => 'TF2_DWH_MANAGER."MY-DWH-SCHEMA"',
                'granted_to' => 'ROLE',
                'grantee_name' => 'TF2_DWH_MANAGER_MY-DWH-SCHEMA_ROLE_RW',
                'grant_option' => 'false',
                'granted_by' => 'TF2_DWH_MANAGER',
            ],
            [
                'created_on' => '2018-06-19 18:49:36',
                'privilege' => 'USAGE',
                'granted_on' => 'SCHEMA',
                'name' => 'TF2_DWH_MANAGER."MY-DWH-SCHEMA"',
                'granted_to' => 'ROLE',
                'grantee_name' => 'TF2_DWH_MANAGER_MY-DWH-SCHEMA_ROLE_RW',
                'grant_option' => 'false',
                'granted_by' => 'TF2_DWH_MANAGER',
            ],
            [
                'created_on' => '2018-06-20 09:50:41',
                'privilege' => 'USAGE',
                'granted_on' => 'WAREHOUSE',
                'name' => 'DEV',
                'granted_to' => 'ROLE',
                'grantee_name' => 'TF2_DWH_MANAGER_MY-DWH-SCHEMA_ROLE_RW',
                'grant_option' => 'false',
                'granted_by' => 'TF2_DWH_MANAGER',
            ],
        ];
        return [
            [
                [
                    'TF2_DWH_MANAGER',
                    'TF2_DWH_MANAGER."MY-DWH-SCHEMA"',
                    'TF2_DWH_MANAGER."MY-DWH-SCHEMA"',
                    'DEV',
                ],
                $grantsFromDb,
            ],
        ];
    }

    /**
     * @dataProvider provideDataForStripQuotes
     */
    public function testStripQuotes(string $expected, string $input): void
    {
        $h = new CheckerHelper();

        $actual = $h->stripGlobalIdenitiferToUnquotedName($input);

        $this->assertSame($expected, $actual);
    }

    /**
     * @return array<mixed>
     */
    public function provideDataForStripQuotes(): array
    {
        return [
            [
                'my_schema',
                '"my_schema"',
            ],
            [
                'my_schema',
                'my_schema',
            ],
            [
                'my_schema',
                'some."my_schema"',
            ],
            [
                'my_schema',
                '"some"."my_schema"',
            ],
        ];
    }
}
