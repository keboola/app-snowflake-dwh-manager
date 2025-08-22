<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager;

use Keboola\Component\BaseComponent;
use Keboola\Component\UserException;
use Keboola\Db\Import\Exception;
use Keboola\SnowflakeDwhManager\Manager\Checker;
use Keboola\SnowflakeDwhManager\Manager\CheckerHelper;
use Keboola\SnowflakeDwhManager\Manager\NamingConventions;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Component extends BaseComponent
{
    private Connection $connection;
    private DwhManager $manager;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);

        /** @var Config $config */
        $config = $this->getConfig();
        try {
            $this->connection = new Connection($config->getSnowflakeConnectionOptions());
        } catch (Exception $e) {
            throw new UserException('Cannot connect to Snowflake, check your credentials.', 0, $e);
        }

        $prefix = $config->getDatabase();
        $this->manager = new DwhManager(
            new Checker(new CheckerHelper(), $this->connection),
            $this->connection,
            $this->getLogger(),
            new NamingConventions($prefix),
            $config->getWarehouse(),
            $config->getDatabase(),
        );
    }

    protected function run(): void
    {
        /** @var Config $config */
        $config = $this->getConfig();

        if ($config->isSchemaRow()) {
            $this->manager->checkSchema($config->getSchema());
        } elseif ($config->isUserRow()) {
            $this->manager->checkUser($config->getUser());
        }
    }

    /**
     * @return array<string, string>
     */
    protected function getSyncActions(): array
    {
        return [
            'enrollMFA' => 'handleEnrollMFA',
            'resetPassword' => 'handleResetPassword',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function handleEnrollMFA(): array
    {
        /** @var Config $config */
        $config = $this->getConfig();

        $this->manager->ensureUserResetMFA($config->getUser());

        return [
            'action' => 'enrollMFA',
            'status' => 'success',
            'message' => 'MFA enrollment handled successfully.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function handleResetPassword(): array
    {
        /** @var Config $config */
        $config = $this->getConfig();
        $userOrSchema = $config->isUserRow() ? $config->getUser() : $config->getSchema();

        $password = $this->manager->resetPasswordAndRetrieveResetUrl($userOrSchema);

        return [
            'action' => 'resetPassword',
            'status' => 'success',
            'message' => sprintf('Password reset URL: "%s" generated successfully.', $password),
        ];
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
