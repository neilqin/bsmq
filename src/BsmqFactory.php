<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace Neilqin\Bsmq;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Bsmq\Exception\InvalidBsmqProxyException;

class BsmqFactory
{
    /**
     * @var BsmqProxy[]
     */
    protected $proxies;

    public function __construct(ConfigInterface $config)
    {
        $bsmqConfig = $config->get('bsmq');

        foreach ($bsmqConfig as $poolName => $item) {
            $this->proxies[$poolName] = make(BsmqProxy::class, ['pool' => $poolName]);
        }
    }

    /**
     * @return \Bsmq|BsmqProxy
     */
    public function get(string $poolName)
    {
        $proxy = $this->proxies[$poolName] ?? null;
        if (! $proxy instanceof BsmqProxy) {
            throw new InvalidBsmqProxyException('Invalid bsmq proxy.');
        }

        return $proxy;
    }
}
