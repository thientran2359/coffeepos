<?php

declare(strict_types=1);

namespace CoffeePOS\Infrastructure\Templates;

final class TemplateLoader
{
    private string $templatePath;

    private static array $context = [];

    public function __construct(?string $templatePath = null)
    {
        $this->templatePath = $templatePath ?? COFFEEPOS_PATH . 'templates';
    }

    public function prepare(string $screen, array $data = []): ?string
    {
        $templateFile = $this->resolve($screen);

        if ($templateFile === null) {
            return null;
        }

        self::$context = $data;

        return $templateFile;
    }

    public function resolve(string $screen): ?string
    {
        $normalizedScreen = sanitize_key($screen);
        $templateFile = $this->templatePath . DIRECTORY_SEPARATOR . $normalizedScreen . DIRECTORY_SEPARATOR . 'index.php';

        if (! is_readable($templateFile)) {
            return null;
        }

        return $templateFile;
    }

    public static function context(): array
    {
        return self::$context;
    }
}
