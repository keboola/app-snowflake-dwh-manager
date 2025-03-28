<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Manager;

use Keboola\Component\UserException;
use Keboola\SnowflakeDwhManager\Configuration\Schema;
use Keboola\SnowflakeDwhManager\Configuration\User;

class NamingConventions
{
    private const SUFFIX_ROLE_RO = '_RO';
    private const SUFFIX_ROLE_RW = '_RW';

    private string $uniquePrefix;

    public function __construct(
        string $uniquePrefix,
    ) {
        $this->uniquePrefix = $uniquePrefix;
    }

    public function getOwnSchemaNameFromUser(User $user): string
    {
        $schemaName = $this->sanitizeAsIdentifier($user->getEmail());
        $this->checkLength($schemaName, $user->getEmail(), 'Maximum email length is %s characters');
        return strtoupper($schemaName);
    }

    public function getRoRoleFromSchemaName(string $schemaName): string
    {
        $role = $this->uniquePrefix . '_' . $schemaName . self::SUFFIX_ROLE_RO;
        $this->checkLength($role, $schemaName, 'Maximum schema name length is %s characters');
        return strtoupper($role);
    }

    public function getRwRoleFromSchemaName(string $schemaName): string
    {
        $role = $this->uniquePrefix . '_' . $schemaName . self::SUFFIX_ROLE_RW;
        $this->checkLength($role, $schemaName, 'Maximum schema name length is %s characters');
        return strtoupper($role);
    }

    public function getRoRoleFromSchema(Schema $schema): string
    {
        return $this->getRoRoleFromSchemaName($schema->getName());
    }

    public function getRoleNameFromUser(User $user): string
    {
        $role = $this->uniquePrefix . '_' . $this->sanitizeAsIdentifier($user->getEmail());
        $this->checkLength($role, $user->getEmail(), 'Maximum email length is %s characters');
        return strtoupper($role);
    }

    public function getRwRoleFromSchema(Schema $schema): string
    {
        $schemaName = $schema->getName();
        return $this->getRwRoleFromSchemaName($schemaName);
    }

    public function getRwUserFromSchema(Schema $schema): string
    {
        $user = $this->uniquePrefix . '_' . $schema->getName();
        $this->checkLength($user, $schema->getName(), 'Maximum schema name is %s characters');
        return strtoupper($user);
    }

    public function getSchemaNameFromSchema(Schema $schema): string
    {
        $schemaName = $schema->getName();
        $this->checkLength($schemaName, $schema->getName(), 'Maximum schema name length is %s characters');
        return strtoupper($schemaName);
    }

    public function getUsernameFromEmail(User $user): string
    {
        $username = $this->uniquePrefix . '_' . $this->sanitizeAsIdentifier($user->getEmail());
        $this->checkLength($username, $user->getEmail(), 'Maximum email length is %s characters');
        return strtoupper($username);
    }

    public function sanitizeAsIdentifier(string $string): string
    {
        return (string) preg_replace('~[^a-z0-9]+~', '_', strtolower($string));
    }

    public function checkLength(string $var, string $source, string $message): void
    {
        if (strlen($var) > 255) {
            $sourceMaxLength = 255 - (strlen($var) - strlen($source));
            throw new UserException(sprintf($message, $sourceMaxLength));
        }
    }
}
