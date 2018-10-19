<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Configuration;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class UserDefinition implements ConfigurationInterface
{
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
                        ->thenInvalid('Schema name can only contain alphanumeric characters and underscore')
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
        return $root;
    }

    public function getRootDefinition(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder();
        return $this->buildRootDefinition($treeBuilder);
    }
}
