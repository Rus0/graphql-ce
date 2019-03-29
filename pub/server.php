<?php
/**
 * Public alias for the application entry point
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
require __DIR__ . '/../app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
/** @var \Magento\GraphQl\App\GraphQl $app */
$app = $bootstrap->createApplication(\Magento\GraphQl\App\GraphQl::class, ['data' => ['baseDir' => BP]]);
$bootstrap->run($app);
$app->listen();
