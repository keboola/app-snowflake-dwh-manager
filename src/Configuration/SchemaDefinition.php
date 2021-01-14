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
                            return !self::isSchemaNameValid($value);
                        })
                        ->thenInvalid('Schema name can only contain alphanumeric characters and underscores')
                    ->end()
                    ->isRequired()
                ->end()
                ->integerNode('statement_timeout')
                    ->defaultValue(10800)
                ->end()
                ->booleanNode('reset_password')
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

    /**
     * @param mixed $value
     */
    public static function isSchemaNameValid($value): bool
    {
        return preg_match('~' . self::REGEX_SCHEMA_NAME . '~', $value) === 1;
    }
}
