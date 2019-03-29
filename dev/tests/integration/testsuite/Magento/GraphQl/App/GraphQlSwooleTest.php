<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\App;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Symfony\Component\Process\Process;

/**
 * Tests the dispatch method in the GraphQl Controller class using a simple product query
 *
 * @magentoAppArea graphql
 * @magentoDbIsolation disabled
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GraphQlSwooleTest extends \PHPUnit\Framework\TestCase
{
    const CONTENT_TYPE = 'application/json';

    /** @var \Magento\Framework\ObjectManagerInterface */
    private $objectManager;

    /** @var Process */
    private static $process;

    public static function setUpBeforeClass()
    {
        self::$process = self::startEntryPoint();
        do {
            sleep(5);
        } while (
            self::$process->isRunning()
            && (!self::$process->getOutput() || self::$process->getOutput() !== 'GraphQl is running' . PHP_EOL)
        );
        parent::setUpBeforeClass();
    }

    protected function setUp() : void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        parent::setUp();
    }

    public static function tearDownAfterClass()
    {
        self::$process->stop();
        parent::tearDownAfterClass();
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple_multistore.php
     */
    public function testPoisonPill():void
    {
        //send graphQl request
        $store = 'fixturestore';
        $query = '{"query":"{\n  products(filter: {sku: {eq: \"simple\"}}, pageSize: 1, currentPage: 1) {\n    total_count\n    items {\n      name\n      sku\n    }\n  }\n}\n","variables":null,"operationName":null}';
        $expected = '{"data":{"products":{"total_count":1,"items":[{"name":"StoreTitle","sku":"simple"}]}}}';
        $output = $this->request($store, $query);
        $this->assertEquals($expected, $output);
    }

    /**
     * @return Process
     */
    private static function startEntryPoint()
    {
        /** @var Process $process */
        $process = Bootstrap::getObjectManager()->create(
            Process::class, ['commandline' => [PHP_BINARY,  'bin/graphql']]
        );
        $process->setTimeout(60);
        $process->setEnv($_ENV + get_defined_constants(true)['user']);
        $process->start(function($type, $data) use ($process) {
            if (!$process->isSuccessful()) {
                echo $process->getErrorOutput() ?: $process->getOutput();
            }
        });
        return $process;
    }

    public function request($store, $data)
    {
        /** @var \Magento\TestFramework\Helper\Curl $curl */
        $curl = $this->objectManager->create(\Magento\TestFramework\Helper\Curl::class);
        $curl->setHeaders(['content_type' => self::CONTENT_TYPE, 'store' => $store]);
        $curl->post('localhost:9501/graphQl', $data);

        $body = $curl->getBody();
        $this->assertEquals('200', $curl->getStatus());
        return $body;
    }
}
