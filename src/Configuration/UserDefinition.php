<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Configuration;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class UserDefinition implements ConfigurationInterface
{
    public const PERMISSION_READWRITE = 'readwrite';
    public const PERMISSION_READ = 'read';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder();
        $this->buildRootDefinition($treeBuilder);

        return $treeBuilder;
    }

    private function buildRootDefinition(TreeBuilder $treeBuilder): ArrayNodeDefinition
    {
        /** @var ArrayNodeDefinition $root */
        $root = $treeBuilder->root('user');

        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        // phpcs:disable Generic.Files.LineLength
        $root
            ->children()
                ->scalarNode('email')
                    ->isRequired()
                ->end()
                ->arrayNode('business_schemas')
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
                ->booleanNode('disabled')
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
        $treeBuilder = new TreeBuilder();
        return $this->buildRootDefinition($treeBuilder);
    }

    /**
     * @param mixed $permission
     * @return bool
     */
    public static function isValidSchemaPermission($permission): bool
    {
        return in_array($permission, [
            self::PERMISSION_READ,
            self::PERMISSION_READWRITE,
        ]);
    }
}
