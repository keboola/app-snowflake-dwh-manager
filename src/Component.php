<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager;

use Keboola\Component\BaseComponent;
use Keboola\Db\Import\Snowflake\Connection;

class Component extends BaseComponent
{
    public function run(): void
    {
        /** @var Config $config */
        $config = $this->getConfig();
        $connection = new Connection($config->getSnowflakeConnectionOptions());
        $manager = new DwhManager($connection);
        if ($config->isSchemaRow()) {
            $schema = $config->getSchema();
            if ($manager->schemaSetupCorrectly($schema)) {
                $manager->updateSchema($schema);
            } else {
                $manager->createSchema($schema);
            }
        } elseif ($config->isUserRow()) {
            $user = $config->getUser();
            if ($manager->userSetupCorrectly($user)) {
                $manager->updateUser($user);
            } else {
                $manager->createUser($user);
            }
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
