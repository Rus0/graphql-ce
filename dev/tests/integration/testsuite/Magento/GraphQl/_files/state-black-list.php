<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

return [
    'navigationMenu' => [
        Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\ExtractDataFromCategoryTree::class,
        Magento\Customer\Model\Session::class,
        Magento\Framework\GraphQl\Query\Fields::class,
        Magento\Framework\Session\Generic::class,
        Magento\Framework\Url::class,
    ],
    'productDetailByName' => [
        Magento\Customer\Model\Session::class,
        Magento\Framework\GraphQl\Query\Fields::class,
        Magento\Framework\Session\Generic::class,
        Magento\Store\Model\GroupRepository::class,
    ],
    'category' => [
        Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\ExtractDataFromCategoryTree::class,
        Magento\Framework\GraphQl\Query\Fields::class,
    ],
    'productDetail' => [
        Magento\Framework\GraphQl\Query\Fields::class,
    ],
    'resolveUrl' => [
        Magento\Framework\GraphQl\Query\Fields::class,
    ],
    '*' => [
        Magento\Framework\Webapi\Response::class,
        Magento\TestFramework\App\Filesystem::class,
        Magento\TestFramework\Interception\PluginList::class,
        //memory leak, wrong sql, potential issues
        Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Deferred\Product::class,
        Magento\ConfigurableProductGraphQl\Model\Variant\Collection::class,
        Magento\ConfigurableProductGraphQl\Model\Options\Collection::class,
        Magento\Framework\Url\QueryParamsResolver::class,
        Magento\Framework\Event\Config\Data::class,
    ],
    '' => [
    ],

];