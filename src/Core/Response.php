<?php

declare(strict_types=1);

namespace OverPHP\Core;

/**
 * Minimal Response object to avoid over-engineering while providing flexibility.
 */
final class Response
{
    public function __construct(
        private mixed $content,
        private int $statusCode = 200,
        private array $headers = []
    ) {}

    public static function json(mixed $data, int $statusCode = 200): self
    {
        return new self($data, $statusCode, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function raw(string $content, int $statusCode = 200, array $headers = []): self
    {
        return new self($content, $statusCode, $headers);
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("\$name: \$value");
        }

        if (is_array($this->content) || is_object($this->content)) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }

            $output = (array) $this->content;
            $stats = Benchmark::stats();
            if ($stats !== null) {
                $output['_performance'] = $stats;
            }

            try {
                echo Security::jsonEncode($output);
            } catch (\JsonException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Internal Server Error']);
            }
            return;
        }

        echo $this->content;
    }
}
