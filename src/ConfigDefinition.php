<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager;

use Keboola\Component\Config\BaseConfigDefinition;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class ConfigDefinition extends BaseConfigDefinition
{
    protected function getParametersDefinition(): ArrayNodeDefinition
    {
        $parametersNode = parent::getParametersDefinition();
        $parametersNode->isRequired();
        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        $parametersNode
            ->children()
                ->scalarNode('master_host')
                    ->isRequired()
                ->end()
                ->scalarNode('master_user')
                    ->isRequired()
                ->end()
                ->scalarNode('master_password')
                    ->isRequired()
                ->end()
                ->scalarNode('master_database')
                    ->isRequired()
                ->end()
                ->scalarNode('warehouse')
                    ->isRequired()
                ->end()
                ->arrayNode('business_schema')
                    ->children()
                        ->scalarNode('schema_name')
                            ->isRequired()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('user')
                    ->children()
                        ->scalarNode('email')
                            ->isRequired()
                        ->end()
                        ->arrayNode('business_schemes')
                            ->scalarPrototype()
                        ->end()
                        ->end()
                        ->booleanNode('disabled')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(function ($v) {
                    return isset($v['user']) && isset($v['business_schema']);
                })
                ->thenInvalid('The "user" and "business_schema" options are mutually exclusive.')
            ->end()
            ->validate()
                ->ifTrue(function ($v) {
                    return !isset($v['user']) && !isset($v['business_schema']);
                })
                ->thenInvalid('Either "user" or "business_schema" must be present.')
            ->end()
        ;
        // @formatter:on
        // host, user, password, database
        return $parametersNode;
    }
}
