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
        $treeBuilder = new TreeBuilder('business_schema');
        $this->buildRootDefinition($treeBuilder);

        return $treeBuilder;
    }

    private function buildRootDefinition(TreeBuilder $treeBuilder): ArrayNodeDefinition
    {
        /** @var ArrayNodeDefinition $root */
        $root = $treeBuilder->getRootNode();

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
                ->scalarNode('public_key')
                    ->defaultNull()
                ->end()
                ->booleanNode('reset_public_key')
                    ->defaultFalse()
                ->end()
            ->end()
        ->end()
        ;

        $root
            ->validate()
                /** @phpstan-ignore-next-line */
                ->ifTrue(fn ($v) => $v['reset_public_key'] === true && $v['public_key'] === null)
                ->thenInvalid('Cannot reset public key when key_pair is not set');
        // @formatter:on
        return $root;
    }

    public function getRootDefinition(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('business_schema');

        return $this->buildRootDefinition($treeBuilder);
    }

    public static function isSchemaNameValid(mixed $value): bool
    {
        return preg_match('~' . self::REGEX_SCHEMA_NAME . '~', $value) === 1;
    }
}
