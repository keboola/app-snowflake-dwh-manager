<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Configuration;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class SchemaDefinition implements ConfigurationInterface
{
    public const REGEX_SCHEMA_NAME = '^[_a-zA-Z0-9]+$';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder();
        $this->buildRootDefinition($treeBuilder);

        return $treeBuilder;
    }

    private function buildRootDefinition(TreeBuilder $treeBuilder): ArrayNodeDefinition
    {
        /** @var ArrayNodeDefinition $root */
        $root = $treeBuilder->root('business_schema');

        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        $root
            ->children()
                ->scalarNode('schema_name')
                    ->validate()
                        ->ifTrue(function ($value) {
                            return preg_match('~' . SchemaDefinition::REGEX_SCHEMA_NAME . '~', $value) !== 1;
                        })
                        ->thenInvalid('Schema name can only contain alphanumeric characters and underscore')
                    ->end()
                    ->isRequired()
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
