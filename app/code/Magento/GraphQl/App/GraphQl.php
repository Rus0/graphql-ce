<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\App;

use Magento\Framework\App\Area;
use Magento\Framework\App\Console\Response as CliResponse;
use Magento\Framework\App\Request\Http;
use Magento\Framework\GraphQl\Schema\SchemaGeneratorInterface;
use Magento\Framework as Framework;
use Magento\GraphQl\Controller\GraphQl\Proxy as Conttroller;
use Magento\MessageQueue\Api\PoisonPillCompareInterface;
use Magento\MessageQueue\Api\PoisonPillReadInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Magento\Framework\AppInterface;
use Magento\Framework\App;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\State;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;

class GraphQl implements AppInterface
{
    /**
     * @var Conttroller
     */
    private $graphQl;

    /**
     * @var SchemaGeneratorInterface
     */
    private $schemaGenerator;

    /**
     * @var State
     */
    private $appState;

    /**
     * @var ConfigLoaderInterface
     */
    private $configLoader;

    /**
     * @var CliResponse
     */
    private $cliResponse;
    /**
     * @var PoisonPillReadInterface
     */
    private $poisonPillRead;
    /**
     * @var PoisonPillCompareInterface
     */
    private $poisonPillCompare;
    private $poisonPillVersion;

    /**
     * @var Server
     */
    private $http;

    /**
     * @var array
     */
    private $data;

    /**
     * GraphQl constructor.
     * @param State $appState
     * @param Conttroller $graphQl
     * @param SchemaGeneratorInterface $schemaGenerator
     * @param ConfigLoaderInterface $configLoader
     * @param CliResponse $cliResponse
     * @param PoisonPillReadInterface $poisonPillRead
     * @param PoisonPillCompareInterface $poisonPillCompare
     * @param array $data
     */
    public function __construct(
        State $appState,
        Conttroller $graphQl,
        SchemaGeneratorInterface $schemaGenerator,
        ConfigLoaderInterface $configLoader,
        CliResponse $cliResponse,
        PoisonPillReadInterface $poisonPillRead,
        PoisonPillCompareInterface $poisonPillCompare,
        $data = []
    )
    {
        $this->graphQl = $graphQl;
        $this->schemaGenerator = $schemaGenerator;
        $this->appState = $appState;
        $this->configLoader = $configLoader;
        $this->cliResponse = $cliResponse;
        $this->poisonPillRead = $poisonPillRead;
        $this->poisonPillCompare = $poisonPillCompare;
        $this->data = $data;
    }

    public function launch()
    {
        $this->init();
        $this->cliResponse->setCode(0);
        $this->cliResponse->terminateOnSend(false);
        return $this->cliResponse;
    }

    private function init():void
    {
        $this->appState->setAreaCode(Area::AREA_GRAPHQL);
        Framework\App\ObjectManager::getInstance()->configure($this->configLoader->load(Area::AREA_GRAPHQL));
        $this->schemaGenerator->generate();
    }

    public function listen():void
    {
        //TODO: need extend config
        $this->http = Framework\App\ObjectManager::getInstance()->create(
            Server::class,
            [
                'host' => '0.0.0.0',
                'port' => 9501,
                'mode' => SWOOLE_PROCESS,
                'sock_type' => SWOOLE_SOCK_TCP
            ]
        );
        $this->http->set([
            'worker_num' => 8,
            'max_request' => 10000,
            'buffer_output_size' => 32 * 1024 * 1024,
        ]);
        $this->poisonPillVersion = $this->poisonPillRead->getLatestVersion();
        $this->http->on('request', [$this, 'request']);
        echo 'GraphQl is running';
        $this->http->start();
    }

    public function request(Request $request, Response $response)
    {
        if (false === $this->poisonPillCompare->isLatestVersion($this->poisonPillVersion)) {
            $this->reset();
        }
        $httpRequest = $this->makeMagentoRequest($request);
        /** @var Framework\Webapi\Response $result */
        $result = $this->graphQl->dispatch($httpRequest);
        $response->end($result->getContent());
    }

    private function reset()
    {
        $factory = Framework\App\Bootstrap::createObjectManagerFactory($this->data['baseDir'], $_SERVER);
        Framework\App\ObjectManager::setInstance($factory->create($_SERVER));
        $this->http->reload();
    }

    /**
     * @param Request $request
     * @return Http
     */
    private function makeMagentoRequest(Request $request): Http {
        $httpRequest = Framework\App\ObjectManager::getInstance()->create(Http::class);
        $httpRequest->setPathInfo($request->server['path_info']);
        $httpRequest->setHeaders(\Zend\Http\Headers::fromString('')->addHeaders($request->header));
        $httpRequest->setContent($request->rawContent());
        $httpRequest->setServer(
            Framework\App\ObjectManager::getInstance()->create(
            \Zend\Stdlib\Parameters::class, $request->server
            )
        );
        return $httpRequest;
    }

    /**
     * {@inheritdoc}
     */
    public function catchException(App\Bootstrap $bootstrap, \Exception $exception)
    {
        return true;
    }
}