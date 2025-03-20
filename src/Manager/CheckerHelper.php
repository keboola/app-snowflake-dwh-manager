<?php

declare(strict_types=1);

namespace Keboola\SnowflakeDwhManager\Manager;

use Keboola\SnowflakeDwhManager\Connection\ExprString;

class CheckerHelper
{
    /**
     * @param array<mixed> $grants
     * @return array<mixed>
     */
    public function filterGrantsByObjectTypeGrantedOn(string $objectType, array $grants): array
    {
        return array_values(array_filter(
            $grants,
            function (array $grant) use ($objectType) {
                return $grant['granted_on'] === $objectType;
            },
        ));
    }

    /**
     * @param array<mixed> $grants
     * @return array<mixed>
     */
    public function mapGrantsArrayToGrantedResourceNames(array $grants): array
    {
        return array_map(function ($grant) {
            return $grant['name'];
        }, $grants);
    }

    public function stripGlobalIdenitiferToUnquotedName(string $value): string
    {
        return preg_replace('~^(?:"?[^"]+"?\.)*"([^"]+)"$~', '$1', $value);
    }
}
