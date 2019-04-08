<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\App;

use Magento\GraphQl\App\State\Comparator;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use Magento\MessageQueue\Api\PoisonPillCompareInterface;

/**
 * Tests the dispatch method in the GraphQl Controller class using a simple product query
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GraphQlStateTest extends \PHPUnit\Framework\TestCase
{
    private const CONTENT_TYPE = 'application/json';

    /** @var \Magento\Framework\ObjectManagerInterface */
    private $objectManager;
    /**
     * @var GraphQl
     */
    private $graphQlApp;

    /** @var StubResponse|MockObject */
    private $responseMock;

    /** @var PoisonPillCompareInterface|MockObject */
    private $poisonPillCompareMock;

    /** @var Comparator */
    private $comparator;

    private const FIXTURES = [
        '/Magento/Catalog/_files/categories.php',
        '/Magento/Catalog/_files/multiple_mixed_products.php'
    ];

    /**
     * Apply fixtures only once for all tests
     */
    public static function setUpBeforeClass()
    {
        $fixtures = self::FIXTURES;
        array_walk($fixtures, function ($fixture) {
            global $fixtureBaseDir;
            require $fixtureBaseDir . $fixture;
        });

        parent::setUpBeforeClass();
    }

    /**
     * Rollback applied fixtures
     */
    public static function tearDownAfterClass()
    {
        $fixtures = self::FIXTURES;
        array_walk($fixtures, function ($fixture) {
            global $fixtureBaseDir;
            require $fixtureBaseDir . str_replace('.php', '_rollback.php', $fixture);
        });

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();

        $this->poisonPillCompareMock = $this->getMockBuilder(PoisonPillCompareInterface::class)
            ->setMethods(['isLatestVersion'])
            ->getMock();
        $this->poisonPillCompareMock->expects($this->any())->method('isLatestVersion')->willReturn(true);

        $this->graphQlApp = $this->objectManager->create(
            GraphQl::class,
            ['poisonPillCompare' => $this->poisonPillCompareMock]
        );

        $this->comparator = $this->objectManager->create(Comparator::class);

        // register Request and Response mocks for case when Swoole extension is not installed
        $this->getMockBuilder(\Swoole\Http\Request::class)->getMock();
        $this->getMockBuilder(\Swoole\Http\Response::class)->getMock();
        $this->responseMock = new StubResponse();

        // emulate poison pill version
        $obj = new \ReflectionObject($this->graphQlApp);
        $property = $obj->getProperty('poisonPillVersion');
        $property->setAccessible(true);
        $property->setValue($this->graphQlApp, 0);

        $this->graphQlApp->launch();

        parent::setUp();
    }

    /**
     * @dataProvider queryDataProvider
     * @param string $query
     * @param string $expected
     * @throws \Exception
     */
    public function testState($query, $expected): void
    {
        $queryData = json_decode($query, true);
        $operationName = $queryData['operationName'];

        $output1 = $this->request($query, $operationName, true);
        $this->assertContains($expected, $output1);

        $output2 = $this->request($query, $operationName);
        $this->assertContains($expected, $output2);
        $this->assertEquals($output1, $output2);
    }

    /**
     * @param string $query
     * @param string $operationName
     * @param bool $firstRequest
     * @return string
     * @throws \Exception
     */
    private function request($query, $operationName, $firstRequest = false): string
    {
        $this->comparator->rememberObjectsStateBefore($firstRequest);

        $request = new StubRequest(
            $query,
            [
                'content_type' => self::CONTENT_TYPE,
            ],
            [
                'path_info' => '/index.php/graphql',
            ]
        );

        $this->graphQlApp->request($request, $this->responseMock);

        $this->comparator->rememberObjectsStateAfter($firstRequest);
        $result = $this->comparator->compare($operationName);

        $this->assertEmpty(
            $result,
            \sprintf(
                '%s objects changed state during request. Details: %s',
                count($result),
                var_export($result, true)
            )
        );


        return $this->responseMock->getResponse();
    }

    public function queryDataProvider(): array
    {
        return [
            'Get Navigation Menu by category_id' => [
                '{"query":"query navigationMenu($id: Int!) {\n  category(id: $id) {\n    id\n    name\n    product_count\n    path\n    children {\n      id\n      name\n      position\n      level\n      url_key\n      url_path\n      product_count\n      children_count\n      path\n      productImagePreview: products(pageSize: 1) {\n        items {\n          small_image {\n  label\n url\n          }\n        }\n      }\n    }\n  }\n}","variables":{"id":4},"operationName":"navigationMenu"}',
                '"id":4,"name":"Category 1.1","product_count":2,'
            ],

            'Get Product Search by product_name' => [
                '{"query":"query productDetailByName($name: String, $onServer: Boolean!) {\n    products(filter: { name: { eq: $name } }) {\n        items {\n  id\n  sku\n  name\n  ... on ConfigurableProduct {\n    configurable_options {\n        attribute_code\n        attribute_id\n        id\n        label\n        values {\n  default_label\n  label\n  store_label\n  use_default_value\n  value_index\n        }\n    }\n    variants {\n        product {\n  #fashion_color\n  #fashion_size\n  id\n  media_gallery_entries {\n    disabled\n    file\n    label\n    position\n  }\n  sku\n  stock_status\n        }\n    }\n  }\n  meta_title @include(if: $onServer)\n  meta_keyword @include(if: $onServer)\n  meta_description @include(if: $onServer)\n        }\n    }\n}","variables":{"name":"Configurable Product","onServer":false},"operationName":"productDetailByName"}',
                '"sku":"configurable","name":"Configurable Product"'
            ],

            'Get List of Products by category_id' => [
                '{"query":"query category($id: Int!, $currentPage: Int, $pageSize: Int) {\n  category(id: $id) {\n    product_count\n    description\n    url_key\n    name\n    id\n    breadcrumbs {\n      category_name\n      category_url_key\n      __typename\n    }\n    products(pageSize: $pageSize, currentPage: $currentPage) {\n      total_count\n      items {\n        id\n        name\n        # small_image\n        # short_description\n        url_key\n        special_price\n        special_from_date\n        special_to_date\n        price {\n          regularPrice {\n            amount {\n              value\n              currency\n              __typename\n            }\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n","variables":{"id":4,"currentPage":1,"pageSize":12},"operationName":"category"}',
                '"url_key":"category-1-1","name":"Category 1.1"'
            ],

            'Get Simple Product Details by name' => [
                '{"query":"query productDetail($name: String, $onServer: Boolean!) {\n    productDetail: products(filter: { name: { eq: $name } }) {\n        items {\n            sku\n            name\n            price {\n                regularPrice {\n                    amount {\n                        currency\n                        value\n                    }\n                }\n            }\n            description {html}\n            media_gallery_entries {\n                label\n                position\n                disabled\n                file\n            }\n            ... on ConfigurableProduct {\n                configurable_options {\n                    attribute_code\n                    attribute_id\n                    id\n                    label\n                    values {\n                        default_label\n                        label\n                        store_label\n                        use_default_value\n                        value_index\n                    }\n                }\n                variants {\n                    product {\n                        id\n                        media_gallery_entries {\n                            disabled\n                            file\n                            label\n                            position\n                        }\n                        sku\n                        stock_status\n                    }\n                }\n            }\n            meta_title @include(if: $onServer)\n            # Yes, Products have `meta_keyword` and\n            # everything else has `meta_keywords`.\n            meta_keyword @include(if: $onServer)\n            meta_description @include(if: $onServer)\n        }\n    }\n}","variables":{"name":"Simple Product1","onServer":false},"operationName":"productDetail"}',
                '"sku":"simple1","name":"Simple Product1"'
            ],

            'Get Url Info by url_key' => [
                '{"query":"query resolveUrl($urlKey: String!) {\n    urlResolver(url: $urlKey) {\n        type\n        id\n    }\n}","variables":{"urlKey":"no-route"},"operationName":"resolveUrl"}',
                '"type":"CMS_PAGE","id":1'
            ],
        ];
    }
}
