<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\App;

class StubRequest extends \Swoole\Http\Request
{
    /**
     * @var string
     */
    private $rawContent;

    /**
     * @var array
     */
    public $header;

    /**
     * @var array
     */
    public $server;

    /**
     * @param string $rawContent
     * @param array $header
     * @param array $server
     */
    public function __construct(string $rawContent, array $header, array $server)
    {
        $this->header = $header;
        $this->server = $server;
        $this->rawContent = $rawContent;
    }

    public function rawContent()
    {
        return $this->rawContent;
    }
}