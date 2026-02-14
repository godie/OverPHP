<?php

declare(strict_types=1);

namespace OverPHP\Controllers;

final class HelloController
{
    public function index(): array
    {
        return [
            'success' => true,
            'message' => 'Welcome to OverPHP',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        ];
    }
}
