<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager;

use Keboola\Component\Config\BaseConfigDefinition;
use Keboola\SnowflakeDwhManager\Configuration\SchemaDefinition;
use Keboola\SnowflakeDwhManager\Configuration\UserDefinition;
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
                ->append((new SchemaDefinition())->getRootDefinition())
                ->append((new UserDefinition())->getRootDefinition())
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
        return $parametersNode;
    }
}
