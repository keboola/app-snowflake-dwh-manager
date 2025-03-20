<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Tests\Configuration;

use Keboola\SnowflakeDwhManager\Configuration\SchemaDefinition;
use PHPUnit\Framework\TestCase;

class SchemaDefinitionTest extends TestCase
{
    /**
     * @dataProvider provideSchemaNames
     */
    public function testSchemaNameValidation(bool $expected, string $schemaName): void
    {
        $this->assertSame(
            $expected,
            SchemaDefinition::isSchemaNameValid($schemaName),
        );
    }

    /**
     * @return mixed[][]
     */
    public function provideSchemaNames(): array
    {
        return [
            'valid schema with underscores' => [
                true,
                'my_schema_123',
            ],
            'empty schema is invalid' => [
                false,
                '',
            ],
            'invalid schema with hyphen' => [
                false,
                'my-schema_123',
            ],
            'invalid schema with invalid character $' => [
                false,
                'my_schema$',
            ],
            'invalid schema with invalid character "ř"' => [
                false,
                'my_schemař',
            ],
        ];
    }
}
