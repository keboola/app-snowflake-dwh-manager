<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager;

use Keboola\Component\BaseComponent;
use Keboola\SnowflakeDwhManager\Manager\Checker;
use Keboola\SnowflakeDwhManager\Manager\CheckerHelper;

class Component extends BaseComponent
{
    public function run(): void
    {
        /** @var Config $config */
        $config = $this->getConfig();
        $connection = new Connection($this->getLogger(), $config->getSnowflakeConnectionOptions());
        $database = $config->getValue(['parameters', 'master_database']);
        $prefix = 'dwhm_' . $database;
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
