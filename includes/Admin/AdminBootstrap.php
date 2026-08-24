<?php

declare(strict_types=1);

namespace CoffeePOS\Admin;

use CoffeePOS\Core\Environment;
use CoffeePOS\Infrastructure\Database\Migrator;
final class AdminBootstrap
{
    public function __construct(?Environment $environment = null, ?Migrator $migrator = null)
    {
    }

    public function register(): void
    {
    }
}
