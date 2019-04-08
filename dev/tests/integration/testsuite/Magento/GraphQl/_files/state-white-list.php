<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

return [
    // check all child classes
    '*' => [
        Magento\Framework\DataObject::class => ['_underscoreCache'],
        Magento\Eav\Model\Entity\AbstractEntity::class => ['_attributesByTable', '_attributesByCode', '_staticAttributes'],
        Magento\Framework\Model\ResourceModel\Db\AbstractDb::class => ['_tables'],
    ],
    Magento\Framework\ObjectManager\ConfigInterface::class => ['_mergedArguments'],
    Magento\Framework\ObjectManager\DefinitionInterface::class => ['_definitions'],
    Magento\Framework\App\Cache\Type\FrontendPool::class => ['_instances'],
    Magento\Framework\GraphQl\Schema\Type\TypeRegistry::class => ['types'],
    Magento\Framework\Filesystem::class => ['readInstances', 'writeInstances'],
    Magento\Framework\EntityManager\TypeResolver::class => [
        'typeMapping'
    ],
    Magento\Framework\App\View\Deployment\Version::class => [
        'cachedValue' // deployment version of static files
    ],
    Magento\Framework\View\Design\Fallback\RulePool::class => ['rules'],
    Magento\Framework\View\Asset\Minification::class => ['configCache'], // depends on mode
    Magento\Eav\Model\Config::class => ['attributeProto', 'attributesPerSet', 'attributes', '_objects', '_references'], // risky?
    Magento\Framework\Api\ExtensionAttributesFactory::class => ['classInterfaceMap'],
    Magento\Catalog\Model\ResourceModel\Category::class => ['_isActiveAttributeId'],
    Magento\Eav\Model\ResourceModel\Entity\Type::class => ['additionalAttributeTables'],
    Magento\Framework\Reflection\MethodsMap::class => ['serviceInterfaceMethodsMap'],
    Magento\Framework\EntityManager\Sequence\SequenceRegistry::class => ['registry'],
    Magento\Framework\EntityManager\MetadataPool::class => ['registry'],
    Magento\Framework\App\Config\ScopeCodeResolver::class => ['resolvedScopeCodes'],
//    ::class => [''],


];