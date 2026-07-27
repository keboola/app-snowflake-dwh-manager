<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Tests;

use Keboola\Component\UserException;
use Keboola\SnowflakeDwhManager\Connection;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ConnectionTest extends TestCase
{
    private function getConnectionMockThatThrowsOnQuery(string $queryExceptionMessage): Connection
    {
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['query'])
            ->getMock();
        $connection->method('query')->willThrowException(new RuntimeException($queryExceptionMessage));

        return $connection;
    }

    public function testAlterUserReclassifiesInvalidEmailErrorAsUserException(): void
    {
        $connection = $this->getConnectionMockThatThrowsOnQuery(
            'Error "odbc_prepare(): SQL error: Invalid email address(es): [not-a-valid-email]., ' .
            'SQL state 22023 in SQLPrepare" while executing query "ALTER USER IF EXISTS ..."',
        );

        $this->expectException(UserException::class);
        $this->expectExceptionMessageMatches(
            '/Invalid email address "not-a-valid-email" configured for Snowflake user "some_user"/',
        );

        $connection->alterUser('some_user', ['email' => 'not-a-valid-email']);
    }

    public function testCreateUserReclassifiesInvalidEmailErrorAsUserException(): void
    {
        $connection = $this->getConnectionMockThatThrowsOnQuery(
            'Error "odbc_prepare(): SQL error: Invalid email address(es): [not-a-valid-email]., ' .
            'SQL state 22023 in SQLPrepare" while executing query "CREATE USER IF NOT EXISTS ..."',
        );

        $this->expectException(UserException::class);
        $this->expectExceptionMessageMatches(
            '/Invalid email address "not-a-valid-email" configured for Snowflake user "some_user"/',
        );

        $connection->createUser('some_user', null, null, 'PERSON', ['email' => 'not-a-valid-email']);
    }

    public function testAlterUserDoesNotReclassifyUnrelatedQueryErrorsEvenWithEmailOption(): void
    {
        $connection = $this->getConnectionMockThatThrowsOnQuery(
            'Error "Insufficient privileges to operate on user \'SOME_USER\'." ' .
            'while executing query "ALTER USER IF EXISTS ..."',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Error "Insufficient privileges to operate on user \'SOME_USER\'." ' .
            'while executing query "ALTER USER IF EXISTS ..."',
        );

        $connection->alterUser('some_user', ['email' => 'valid@example.com']);
    }

    public function testAlterUserDoesNotReclassifyQueryErrorsWhenNoEmailOptionIsSet(): void
    {
        $connection = $this->getConnectionMockThatThrowsOnQuery(
            'Error "SQL error: Invalid email address(es): [not-a-valid-email]., SQL state 22023 in SQLPrepare" ' .
            'while executing query "ALTER USER IF EXISTS ..."',
        );

        $this->expectException(RuntimeException::class);

        $connection->alterUser('some_user', ['default_role' => 'some_role']);
    }
}
