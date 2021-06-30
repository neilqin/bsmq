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

use Neilqin\Bsmq\Exception\InvalidBsmqConnectionException;
use Neilqin\Bsmq\Pool\PoolFactory;
use Hyperf\Utils\Context;
/**
 * 在该类上尽量使用当前类开放出来的方法
 * @mixin \Bsmq
 */
class Bsmq
{

    /**
     * @var PoolFactory
     */
    protected $factory;

    /**
     * @var string
     */
    protected $poolName = 'default';
    
    const DEFAULT_DELAY = 0; // no delay
    const DEFAULT_PRIORITY = 1024; // most urgent: 0, least urgent: 4294967295
    const DEFAULT_TTR = 60; // 1 minute

    public function __construct(PoolFactory $factory)
    {
        $this->factory = $factory;
    }

    public function __call($name, $arguments)
    {
        $hasContextConnection = Context::has($this->getContextKey());
        $connection = $this->getConnection($hasContextConnection);

        try {
            $connection = $connection->getConnection();
            // Execute the command with the arguments.
            $result = $connection->{$name}(...$arguments);
        } finally {
            if (! $hasContextConnection) {
                if ($this->shouldUseSameConnection($name)) {
                    if ($name=='useTube' && $tube = $arguments[0]) {
                        $connection->setTube($name,$tube);
                    }
                    // Should storage the connection to coroutine context, then use defer() to release the connection.
                    Context::set($this->getContextKey(), $connection);
                    defer(function () use ($connection) {
                        Context::set($this->getContextKey(), null);
                        $connection->release();
                    });
                } else {
                    // Release the connection after command executed.
                    $connection->release();
                }
            }
            
        }

        return $result;
    }
    
    /**
     * 放到数据到指定的tube
     * @param $tube                 消息队列通道
     * @param $urlLink              数据
     * @param int $priority         优先级，默认为1024
     * @param int $delaySecond      延迟多少秒
     * @param int $ttr
     * @return mixed
     */
    public function putInTube($tube,$urlLink, $priority=self::DEFAULT_PRIORITY, $delaySecond=self::DEFAULT_DELAY,$ttr=self::DEFAULT_TTR){
        $hasContextConnection = Context::has($this->getContextKey());
        $connection = $this->getConnection($hasContextConnection);
        try {
            $connection = $connection->getConnection();
            // Execute the command with the arguments.
            $result = $connection->putInTube($tube,$urlLink,$priority,$delaySecond,$ttr);
        } finally {
            // Release the connection after command executed.
            if (! $hasContextConnection) {
                // Release the connection after command executed.
                $connection->release();
            }
        }
        return $result;
    }
    
    /**
     * 从指定tube取一个job
     * @param $tube
     * @param null $timeout
     * @return mixed
     */
    public function reserveFromTube($tube, $timeout = null) {
        $hasContextConnection = Context::has($this->getContextKey());
        $connection = $this->getConnection($hasContextConnection);
        try {
            $connection = $connection->getConnection();
            // Execute the command with the arguments.
            $result = $connection->reserveFromTube($tube,$timeout);
        } finally {
            // Release the connection after command executed.
            if (! $hasContextConnection) {
                // Release the connection after command executed.
                $connection->release();
            }
        }
        return $result;
    }
    
    /**
     * 显示目前所有的tubes
     * @return mixed
     */
    public function listTubes() {
        $hasContextConnection = Context::has($this->getContextKey());
        $connection = $this->getConnection($hasContextConnection);
        try {
            $connection = $connection->getConnection();
            // Execute the command with the arguments.
            $result = $connection->listTubes();
        } finally {
            // Release the connection after command executed.
            if (! $hasContextConnection) {
                // Release the connection after command executed.
                $connection->release();
            }
        }
        return $result;
    }
    
    /**
     * 查看某个tube状态
     * @param $tube
     * @return mixed
     */
    public function statsTube($tube) {
        $hasContextConnection = Context::has($this->getContextKey());
        $connection = $this->getConnection($hasContextConnection);
        try {
            $connection = $connection->getConnection();
            // Execute the command with the arguments.
            $result = $connection->statsTube($tube);
        } finally {
            // Release the connection after command executed.
            if (! $hasContextConnection) {
                // Release the connection after command executed.
                $connection->release();
            }
        }
        return $result;
    }
    /**
     * Get a connection from coroutine context, or from bsmq connectio pool.
     * @param mixed $hasContextConnection
     */
    private function getConnection($hasContextConnection=false): BsmqConnection
    {
        $connection = null;
        if ($hasContextConnection) {
            $connection = Context::get($this->getContextKey());
        }
        if (! $connection instanceof BsmqConnection) {
            $pool = $this->factory->getPool($this->poolName);
            $connection = $pool->get();
        }
        if (! $connection instanceof BsmqConnection) {
            throw new InvalidBsmqConnectionException('The connection is not a valid BsmqConnection.');
        }
        return $connection;
    }

    /**
     * The key to identify the connection object in coroutine context.
     */
    private function getContextKey(): string
    {
        return sprintf('bsmq.connection.%s', $this->poolName);
    }
    /**
     * Define the commands that needs same connection to execute.
     * When these commands executed, the connection will storage to coroutine context.
     */
    private function shouldUseSameConnection(string $methodName): bool
    {
        return in_array($methodName, [
            'useTube',
            'watch',
            'watchOnly',
        ]);
    }
}
