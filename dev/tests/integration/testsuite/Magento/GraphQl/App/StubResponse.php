<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\App;

class StubResponse extends \Swoole\Http\Response
{
    private $response = '';

    /**
     * @param string $html
     */
    public function end($html = '')
    {
        $this->response = $html;
    }

    /**
     * @return string
     */
    public function getResponse()
    {
        return $this->response;
    }
}