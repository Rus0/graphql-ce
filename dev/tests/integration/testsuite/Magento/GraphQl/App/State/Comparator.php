<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\App\State;
/**
 * Compare object state between requests
 */
class Comparator
{
    /**
     * @var Collector
     */
    private $collector;

    /** @var array */
    private $objectsStateBefore;

    /**
     * @var array
     */
    private $objectsStateAfter;

    /**
     * @var array
     */
    private $blacklist;

    /**
     * @var array
     */
    private $whitelist;

    /**
     * @param Collector $collector
     */
    public function __construct(Collector $collector)
    {
        $this->collector = $collector;
    }

    /**
     * Remember shared object state on first request
     *
     * @param bool $firstRequest
     * @throws \Exception
     */
    public function rememberObjectsStateBefore($firstRequest): void
    {
        if ($firstRequest) {
            $this->objectsStateBefore = $this->collector->getSharedObjects();
        }
    }

    /**
     * @param bool $firstRequest
     * @throws \Exception
     */
    public function rememberObjectsStateAfter($firstRequest): void
    {
        $this->objectsStateAfter = $this->collector->getSharedObjects();
        if ($firstRequest) {
            // on the end of first request add objects to init object state pool
            $this->objectsStateBefore = array_merge($this->objectsStateAfter, $this->objectsStateBefore);
        }
    }

    /**
     * @param string $operationName
     * @return array
     */
    public function compare($operationName): array
    {
        $compareResults = [];
        $blacklist = $this->getBlackList($operationName);
        $whitelist = $this->getWhiteList();
        $whitelistParentClasses = $whitelist['*'] ?? [];

        foreach ($this->objectsStateAfter as $typeName => $propertiesAfter) {
            if (\in_array($typeName, $blacklist, true)) {
                continue;
            }

            if (isset($whitelist[$typeName])) {
                $propertiesAfter = $this->filterProperties($propertiesAfter, $whitelist[$typeName]);
            }
            foreach ($whitelistParentClasses as $parentClass => $excludeProperties) {
                if (is_subclass_of($typeName, $parentClass)) {
                    $propertiesAfter = $this->filterProperties($propertiesAfter, $excludeProperties);
                }
            }

            $objectState = [];
            if (!isset($this->objectsStateBefore[$typeName])) {
                $compareResults[$typeName] = 'new object appeared after first request';
            } else {
                $before = $this->objectsStateBefore[$typeName];
                foreach ($propertiesAfter as $propertyName => $propertyValue) {
                    $result = $this->checkValues($before[$propertyName] ?? null, $propertyValue);
                    if ($result) {
                        $objectState[$propertyName] = $result;
                    }
                }
            }

            if ($objectState) {
                $compareResults[$typeName] = $objectState;
            }

        }

        return $compareResults;
    }

    /**
     * @param array $properties
     * @param array $excludeProperties
     * @return array
     */
    private function filterProperties($properties, $excludeProperties): array
    {
        return array_filter($properties, function ($propertyName) use ($excludeProperties) {
            return !\in_array($propertyName, $excludeProperties);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * @param string $operationName
     * @return array
     */
    private function getBlackList($operationName): array
    {
        if ($this->blacklist === null) {
            $this->blacklist = include __DIR__ . '/../../_files/state-black-list.php';
        }

        return array_merge($this->blacklist['*'], $this->blacklist[$operationName] ?? []);

    }

    /**
     * @return array
     */
    private function getWhiteList(): array
    {
        if ($this->whitelist === null) {
            $this->whitelist = include __DIR__ . '/../../_files/state-white-list.php';
        }

        return $this->whitelist;
    }

    /**
     * @param mixed $type
     * @return array
     */
    private function formatValue($type): array
    {
        $type = \is_array($type) ? $type : [$type];
        $data = [];
        foreach ($type as $k => $v) {
            if (\is_object($v)) {
                $v = \get_class($v);
            } elseif (\is_array($v)) {
                $v = $this->formatValue($v);
            }
            $data[$k] = $v;
        }

        return $data;
    }

    /**
     * @param mixed $before
     * @param mixed $after
     * @return array
     */
    private function checkValues($before, $after): array
    {
        $result = [];

        $typeBefore = gettype($before);
        $typeAfter = gettype($after);

        if ($typeBefore !== $typeAfter) {
            if ($before === null) {
                // skip: assume, that "null" => "type" used for lazy loading
                return $result;
            }
            $result['before'] = $this->formatValue($before);
            $result['after'] = $this->formatValue($after);

            return $result;
        }

        switch ($typeBefore) {
            case 'boolean':
            case 'integer':
            case 'double':
            case 'string':
                if ($before !== $after) {
                    $result['before'] = $before;
                    $result['after'] = $after;
                }
                break;
            case 'array':
                if (count($before) !== count($after) || $before != $after) {
                    $result['before'] = $this->formatValue($before);
                    $result['after'] = $this->formatValue($after);
                }
                break;
            case 'object':
                if ($before != $after) {
                    $result['before'] = \get_class($before);
                    $result['after'] = \get_class($after);
                }
                break;
        }

        return $result;
    }
}