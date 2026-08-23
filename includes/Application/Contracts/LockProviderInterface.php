<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

interface LockProviderInterface
{
    /**
     * @return mixed
     */
    public function synchronized(string $key, callable $callback);
}
