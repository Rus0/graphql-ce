<?php
/**
 * Public alias for the application entry point
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\State;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use Magento\Framework\ObjectManagerInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

require __DIR__ . '/app/bootstrap.php';
$objectManager = initObjectManager();

/** @var \Magento\GraphQl\Controller\GraphQl $frontController */
$frontController = $objectManager->get(\Magento\GraphQl\Controller\GraphQl::class);
$http = new Server('0.0.0.0', 9501);
$http->set([
    'worker_num' => 8,
    'max_request' => 100,
    'buffer_output_size' => 32 * 1024 *1024,
]);

/** @var \Magento\Framework\GraphQl\Schema\SchemaGeneratorInterface $schemaGenerator */
$schemaGenerator = $objectManager->get(\Magento\Framework\GraphQl\Schema\SchemaGeneratorInterface::class);
$schemaGenerator->generate();

$http->on('request', function (Request $request, Response $response) use ($objectManager, $frontController) {
    $httpRequest = makeMagentoRequest($request, $objectManager);
    /** @var \Magento\Framework\Webapi\Response $result */
    $result = $frontController->dispatch($httpRequest);
    $response->end($result->getContent());
});
$http->start();

/**
 * @return ObjectManagerInterface
 */
function initObjectManager(): ObjectManagerInterface
{
    $params = $_SERVER;
    $params[Bootstrap::INIT_PARAM_FILESYSTEM_DIR_PATHS] = array_replace_recursive(
        $params[Bootstrap::INIT_PARAM_FILESYSTEM_DIR_PATHS] ?? [],
        [
            DirectoryList::PUB => [DirectoryList::URL_PATH => ''],
            DirectoryList::MEDIA => [DirectoryList::URL_PATH => 'media'],
            DirectoryList::STATIC_VIEW => [DirectoryList::URL_PATH => 'static'],
            DirectoryList::UPLOAD => [DirectoryList::URL_PATH => 'media/upload'],
        ]
    );
    $bootstrap = Magento\Framework\App\Bootstrap::create(BP, $params);
    $om = $bootstrap->getObjectManager();
    $state = $om->get(State::class);
    $areaCode = 'graphql';
    $state->setAreaCode($areaCode);
    $om->configure($om->get(ConfigLoaderInterface::class)->load($areaCode));
    return $om;
}

/**
 * @param Request $request
 * @param ObjectManagerInterface $om
 * @return \Magento\Framework\App\Request\Http
 */
function makeMagentoRequest(
    Request $request,
    ObjectManagerInterface $om
): \Magento\Framework\App\Request\Http {
    /** @var  \Magento\Framework\App\Request\Http $httpRequest */
    $httpRequest = $om->create(\Magento\Framework\App\Request\Http::class);
    $httpRequest->setPathInfo($request->server['path_info']);
    $httpRequest->setHeaders(\Zend\Http\Headers::fromString('')->addHeaders($request->header));
    $httpRequest->setContent($request->rawContent());
    $httpRequest->setServer($om->create(\Zend\Stdlib\Parameters::class, $request->server));
    return $httpRequest;
}
