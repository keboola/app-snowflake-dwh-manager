<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager;

use Keboola\Component\BaseComponent;
use Keboola\Component\UserException;
use Keboola\SnowflakeDwhManager\Manager\Checker;
use Keboola\SnowflakeDwhManager\Manager\CheckerHelper;
use Keboola\SnowflakeDwhManager\Manager\NamingConventions;
use Psr\Log\NullLogger;

class Component extends BaseComponent
{
    public function run(): void
    {
        /** @var Config $config */
        $config = $this->getConfig();
        try {
            $connection = new Connection(new NullLogger(), $config->getSnowflakeConnectionOptions());
        } catch (\Keboola\Db\Import\Exception $e) {
            throw new UserException('Cannot connect to Snowflake, check your credentials.', 0, $e);
        }
        $manager = new DwhManager(
            new Checker(new CheckerHelper(), $connection),
            $connection,
            $this->getLogger(),
            new NamingConventions($config->getDatabase()),
            $config->getWarehouse(),
            $config->getDatabase()
        );
        if ($config->isSchemaRow()) {
            $manager->checkSchema($config->getSchema());
        } elseif ($config->isUserRow()) {
            $manager->checkUser($config->getUser());
        }
    }

    protected function getConfigClass(): string
    {
        return Config::class;
    }

    protected function getConfigDefinitionClass(): string
    {
        return ConfigDefinition::class;
    }
}
