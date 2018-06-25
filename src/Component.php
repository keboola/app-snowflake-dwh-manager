<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager;

use Keboola\Component\BaseComponent;
use Keboola\SnowflakeDwhManager\Manager\Checker;
use Keboola\SnowflakeDwhManager\Manager\CheckerHelper;
use Psr\Log\NullLogger;

class Component extends BaseComponent
{
    public function run(): void
    {
        /** @var Config $config */
        $config = $this->getConfig();
        $connection = new Connection(new NullLogger(), $config->getSnowflakeConnectionOptions());
        $prefix = 'dwhm_' . $config->getDatabase();
        $manager = new DwhManager(
            $prefix,
            new Checker(new CheckerHelper(), $connection),
            $connection,
            $this->getLogger(),
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
