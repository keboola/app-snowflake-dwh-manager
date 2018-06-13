<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager;

use Keboola\Component\BaseComponent;
use Keboola\SnowflakeDwhManager\Manager\Checker;
use Keboola\SnowflakeDwhManager\Manager\Generator;

class Component extends BaseComponent
{
    public function run(): void
    {
        /** @var Config $config */
        $config = $this->getConfig();
        $connection = new Connection($config->getSnowflakeConnectionOptions());
        $database = $config->getValue(['parameters', 'master_database']);
        $connection->query(
            'USE DATABASE ' . $connection->quoteIdentifier($database)
        );
        $manager = new DwhManager(new Checker($connection), new Generator($connection), $this->getLogger());
        if ($config->isSchemaRow()) {
            $schema = $config->getSchema();
            if ($manager->checkSchema($schema)) {
                $manager->updateSchema($schema);
            } else {
                $manager->createSchemaAndRwUser($schema);
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
