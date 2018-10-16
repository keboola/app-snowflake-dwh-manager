<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Tests\Manager;

use Keboola\Component\UserException;
use Keboola\SnowflakeDwhManager\Configuration\Schema;
use Keboola\SnowflakeDwhManager\Configuration\SchemaDefinition;
use Keboola\SnowflakeDwhManager\Configuration\User;
use Keboola\SnowflakeDwhManager\Configuration\UserDefinition;
use Keboola\SnowflakeDwhManager\Manager\NamingConventions;
use PHPUnit\Framework\TestCase;
use function str_repeat;

class NamingConventionsTest extends TestCase
{
    public function testCheckLengthValid(): void
    {
        $source = str_repeat('A', 44);
        $var = 'DWHM_TEST_' . str_repeat('X', 200) . '_' . $source;
        $message = 'Source must be maximum %s long';
        $namingConventions = new NamingConventions('DWHM_TEST');

        $namingConventions->checkLength($var, $source, $message);

        $this->assertTrue(true, 'An exception should not be thrown');
    }

    public function testCheckLengthInvalid(): void
    {
        $source = str_repeat('A', 50);
        $var = 'DWHM_TEST_' . str_repeat('X', 200) . '_' . $source;
        $message = 'Source must be maximum %s long';
        $namingConventions = new NamingConventions('DWHM_TEST');

        $this->expectException(UserException::class);
        $this->expectExceptionMessage('Source must be maximum 44 long');

        $namingConventions->checkLength($var, $source, $message);
    }

    public function testGetOwnSchemaNameFromUser(): void
    {
        $namingConventions = new NamingConventions('DWHM_TEST');

        $this->assertSame(
            'USER_EXAMPLE_COM',
            $namingConventions->getOwnSchemaNameFromUser($this->getUser())
        );
    }

    public function testGetRoRoleFromSchema(): void
    {
        $namingConventions = new NamingConventions('DWHM_TEST');

        $this->assertSame(
            'DWHM_TEST_MY_SCHEMA_NAME_RO',
            $namingConventions->getRoRoleFromSchema($this->getSchema())
        );
    }

    public function testGetRoRoleFromSchemaName(): void
    {
        $namingConventions = new NamingConventions('DWHM_TEST');

        $this->assertSame(
            'DWHM_TEST_MY_SCHEMA_NAME_RO',
            $namingConventions->getRoRoleFromSchemaName($this->getSchema()->getName())
        );
    }

    public function testGetRoleNameFromUser(): void
    {
        $namingConventions = new NamingConventions('DWHM_TEST');

        $this->assertSame(
            'DWHM_TEST_USER_EXAMPLE_COM',
            $namingConventions->getRoleNameFromUser($this->getUser())
        );
    }

    public function testGetRwRoleFromSchema(): void
    {
        $namingConventions = new NamingConventions('DWHM_TEST');

        $this->assertSame(
            'DWHM_TEST_MY_SCHEMA_NAME_RW',
            $namingConventions->getRwRoleFromSchema($this->getSchema())
        );
    }

    public function testGetRwUserFromSchema(): void
    {
        $namingConventions = new NamingConventions('DWHM_TEST');

        $this->assertSame(
            'DWHM_TEST_MY_SCHEMA_NAME',
            $namingConventions->getRwUserFromSchema($this->getSchema())
        );
    }

    public function testGetSchemaNameFromSchema(): void
    {
        $namingConventions = new NamingConventions('DWHM_TEST');

        $this->assertSame(
            'MY_SCHEMA_NAME',
            $namingConventions->getSchemaNameFromSchema($this->getSchema())
        );
    }

    public function testGetUsernameFromEmail(): void
    {
        $namingConventions = new NamingConventions('DWHM_TEST');

        $this->assertSame(
            'DWHM_TEST_USER_EXAMPLE_COM',
            $namingConventions->getUsernameFromEmail($this->getUser())
        );
    }

    public function testGetRwRoleFromSchemaName(): void
    {
        $namingConventions = new NamingConventions('DWHM_TEST');

        $this->assertSame(
            'DWHM_TEST_MY_SCHEMA_NAME1_RW',
            $namingConventions->getRwRoleFromSchemaName('my_schema_name1')
        );
    }

    /**
     * @dataProvider provideInputForSanitize
     */
    public function testSanitizeAsIdentifier(string $expected, string $input): void
    {
        $namingConventions = new NamingConventions('DWHM_TEST');
        $this->assertSame(
            $expected,
            $namingConventions->sanitizeAsIdentifier($input)
        );
    }

    /**
     * @return string[][]
     */
    public function provideInputForSanitize(): array
    {
        return [
            'will convert email' => [
                'user_example_com',
                'user@example.com',
            ],
            'will convert multiple invalid characters to one' => [
                'user_example_com',
                'user@@@@example@@@@@com',
            ],
            'will ignore case correctly' => [
                'user_example_com',
                'User@ExAmPlE.COM',
            ],
            'will ignore random characters' => [
                'test_test',
                'test_~ˇ^˘°,.-§¨)˛`˙´˝_test',
            ],
        ];
    }

    protected function getSchema(): Schema
    {
        return new Schema(
            [
                'schema_name' => 'my_schema_name',
            ],
            new SchemaDefinition()
        );
    }

    protected function getUser(): User
    {
        return new User(
            [
                'email' => 'user@example.com',
                'business_schemas' => ['my_schema_name2'],
                'disabled' => false,
            ],
            new UserDefinition()
        );
    }
}
