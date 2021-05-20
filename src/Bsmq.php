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
namespace Neil\Bsmq;

use Neil\Bsmq\Exception\InvalidBsmqConnectionException;
use Neil\Bsmq\Pool\PoolFactory;

/**
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

    public function __construct(PoolFactory $factory)
    {
        $this->factory = $factory;
    }

    public function __call($name, $arguments)
    {

        $connection = $this->getConnection();

        try {
            $connection = $connection->getConnection();
            // Execute the command with the arguments.
            $result = $connection->{$name}(...$arguments);
        } finally {
            // Release the connection after command executed.
            $connection->release();
        }

        return $result;
    }
    

    /**
     * Get a connection from coroutine context, or from bsmq connectio pool.
     * @param mixed $hasContextConnection
     */
    private function getConnection(): BsmqConnection
    {
        $connection = null;
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
}
