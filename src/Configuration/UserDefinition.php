<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Configuration;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class UserDefinition implements ConfigurationInterface
{
    public const PERMISSION_WRITE = 'write';
    public const PERMISSION_READ = 'read';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('user');
        $this->buildRootDefinition($treeBuilder);

        return $treeBuilder;
    }

    private function buildRootDefinition(TreeBuilder $treeBuilder): ArrayNodeDefinition
    {
        /** @var ArrayNodeDefinition $root */
        $root = $treeBuilder->getRootNode();

        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        // phpcs:disable Generic.Files.LineLength
        $root
            ->children()
                ->scalarNode('email')
                    ->cannotBeEmpty()
                    ->isRequired()
                ->end()
                ->arrayNode('business_schemas')
                    ->setDeprecated('"business_schemas" is deprecated, use "schemas" instead', '3.0.0')
                    ->scalarPrototype()
                    ->validate()
                        ->ifTrue(function ($value) {
                            return !SchemaDefinition::isSchemaNameValid($value);
                        })
                        ->thenInvalid('Schema name can only contain alphanumeric characters and underscores')
                    ->end()
                    ->end()
                ->end()
                ->arrayNode('schemas')
                    ->arrayPrototype()
                         ->children()
                            ->scalarNode('name')
                                ->validate()
                                    ->ifTrue(function ($name) {
                                        return !SchemaDefinition::isSchemaNameValid($name);
                                    })
                                    ->thenInvalid('Schema name can only contain alphanumeric characters and underscores')
                                ->end()
                            ->end()
                            ->scalarNode('permission')
                                ->validate()
                                    ->ifTrue(function ($permission) {
                                        return !self::isValidSchemaPermission($permission);
                                    })
                                    ->thenInvalid('Permission %s is not valid')
                                ->end()
                            ->end()
                        ->end()

                    ->end()
                ->end()
                ->integerNode('statement_timeout')
                    ->defaultValue(10800)
                ->end()
                ->booleanNode('disabled')
                    ->defaultFalse()
                ->end()
                ->booleanNode('reset_password')
                    ->defaultFalse()
                ->end()
            ->end()
        ->end()
        ;
        // @formatter:on
        // phpcs:enable
        return $root;
    }

    public function getRootDefinition(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('user');

        return $this->buildRootDefinition($treeBuilder);
    }

    public static function isValidSchemaPermission(mixed $permission): bool
    {
        return in_array($permission, [
            self::PERMISSION_READ,
            self::PERMISSION_WRITE,
        ]);
    }
}
