<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Configuration;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class SchemaDefinition implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder();
        $this->buildRootDefinition($treeBuilder);

        return $treeBuilder;
    }

    private function buildRootDefinition(TreeBuilder $treeBuilder): ArrayNodeDefinition
    {
        $root = $treeBuilder->root('business_schema');

        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        $root
            ->children()
                ->scalarNode('schema_name')
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
