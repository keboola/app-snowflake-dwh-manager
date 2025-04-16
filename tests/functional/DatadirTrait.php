<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\DatadirTests;

use Exception;
use Keboola\DatadirTests\DatadirTestSpecification;
use Keboola\SnowflakeDwhManager\Config;
use Keboola\SnowflakeDwhManager\ConfigDefinition;
use Keboola\SnowflakeDwhManager\Configuration\Schema;
use Keboola\SnowflakeDwhManager\Configuration\User;
use Keboola\SnowflakeDwhManager\Connection;
use Keboola\SnowflakeDwhManager\Manager\NamingConventions;
use Monolog\Logger;
use RandomLib\Factory;
use Symfony\Component\Process\Process;

trait DatadirTrait
{
    /**
     * @param array<mixed> $userConfig
     */
    private function getConfigFromConfigArray(array $userConfig): Config
    {
        return new Config($userConfig, new ConfigDefinition());
    }

    private function getConnectionForConfig(Config $config): Connection
    {
        return new Connection($config->getSnowflakeConnectionOptions());
    }

    /**
     * @param arra<mixed> $config
     */
    private function runAppWithConfig(array $config): Process
    {
        self::$logger->log(Logger::DEBUG, $this->getDataSetAsString());

        $specification = new DatadirTestSpecification(null, 0, null, null, null);

        $tempDatadir = $this->getTempDatadir($specification);

        file_put_contents($tempDatadir->getTmpFolder() . '/config.json', json_encode($config));

        $process = $this->runScript($tempDatadir->getTmpFolder());

        $this->assertMatchesSpecification($specification, $process, $tempDatadir->getTmpFolder());

        self::$logger->log(Logger::DEBUG, $process->getErrorOutput());

        return $process;
    }

    private static function dropCreatedSchema(Connection $connection, string $prefix, Schema $schema): void
    {
        $namingConventions = new NamingConventions($prefix);
        $connection->query(
            'DROP SCHEMA IF EXISTS '
            . $connection->quoteIdentifier($namingConventions->getSchemaNameFromSchema($schema)),
        );
        $connection->query(
            'DROP ROLE IF EXISTS '
            . $connection->quoteIdentifier($namingConventions->getRwRoleFromSchema($schema)),
        );
        $connection->query(
            'DROP ROLE IF EXISTS '
            . $connection->quoteIdentifier($namingConventions->getRoRoleFromSchema($schema)),
        );
        $connection->query(
            'DROP USER IF EXISTS '
            . $connection->quoteIdentifier($namingConventions->getRwUserFromSchema($schema)),
        );
        self::$logger->log(Logger::DEBUG, sprintf('Dropped schema "%s"' . PHP_EOL, $schema->getName()));
    }

    private static function dropCreatedUser(Connection $connection, string $prefix, User $user): void
    {
        $namingConventions = new NamingConventions($prefix);

        $connection->query(
            'DROP SCHEMA IF EXISTS '
            . $connection->quoteIdentifier($namingConventions->getOwnSchemaNameFromUser($user)),
        );
        $connection->query(
            'DROP ROLE IF EXISTS '
            . $connection->quoteIdentifier($namingConventions->getRoleNameFromUser($user)),
        );
        $connection->query(
            'DROP USER IF EXISTS '
            . $connection->quoteIdentifier($namingConventions->getUsernameFromEmail($user)),
        );
        self::$logger->log(Logger::DEBUG, sprintf('Dropped user "%s"' . PHP_EOL, $user->getEmail()));
    }

    /**
     * @param mixed[] $userConfig
     */
    private function getConnectionForUserFromUserConfig(array $userConfig): Connection
    {
        $config = $this->getConfigFromConfigArray($userConfig);
        if (!$config->isUserRow()) {
            throw new Exception('This is not a user config');
        }

        // get master connection to change the password
        $connection = $this->getConnectionForConfig($config);

        // change the password to known one
        $user1Username = $this->namingConventions->getUsernameFromEmail($config->getUser());
        $randomLibFactory = new Factory();
        $userNewPassword = $randomLibFactory
            ->getMediumStrengthGenerator()
            ->generateString(30);
        $connection->alterUser($user1Username, ['password' => $userNewPassword]);

        // alter the original config to use new user and password
        $loginAsUser1Config = $userConfig;
        $loginAsUser1Config['parameters']['master_user'] = $user1Username;
        $loginAsUser1Config['parameters']['#master_password'] = $userNewPassword;

        // force destructor to disconnect
        unset($connection);

        // get connection as the user
        $config = new Config($loginAsUser1Config, new ConfigDefinition());
        return $this->getConnectionForConfig($config);
    }
}
