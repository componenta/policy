<?php

declare(strict_types=1);

$autoloadFiles = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
];

foreach ($autoloadFiles as $autoloadFile) {
    if (is_file($autoloadFile)) {
        require $autoloadFile;

        spl_autoload_register(static function (string $class): void {
            $prefix = 'Componenta\\Policy\\Tests\\';

            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

            if (is_file($file)) {
                require $file;
            }
        });

        return;
    }
}

throw new RuntimeException('Unable to locate Composer autoload.php for componenta/policy tests.');