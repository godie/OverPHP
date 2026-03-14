<?php

declare(strict_types=1);

namespace OverPHP\Core;

/**
 * Minimal Response object to avoid over-engineering while providing flexibility.
 */
final class Response
{
    private bool $isRaw = false;

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
        $instance = new self($content, $statusCode, $headers);
        $instance->isRaw = true;
        return $instance;
    }

    public function send(): void
    {
        $headersSent = headers_sent();

        if (!$headersSent) {
            http_response_code($this->statusCode);

            foreach ($this->headers as $name => $value) {
                header("$name: $value");
            }
        }

        if ($this->isRaw) {
            echo (string) $this->content;
            return;
        }

        if (is_array($this->content) || is_object($this->content)) {
            $this->sendJson($this->content, $headersSent);
            return;
        }

        if (is_string($this->content) && $this->isJson($this->content)) {
            if (!$headersSent) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo $this->content;
            return;
        }

        echo is_string($this->content) ? Security::escape($this->content) : (string) $this->content;
    }

    private function sendJson(mixed $data, bool $headersSent): void
    {
        if (!$headersSent) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $stats = Benchmark::stats();
        $output = $data;

        if ($stats !== null) {
            $output = (array) $data;
            $output['_performance'] = $stats;
        }

        try {
            echo Security::jsonEncode($output);
        } catch (\JsonException $e) {
            if (!headers_sent()) {
                http_response_code(500);
            }
            echo json_encode(['success' => false, 'error' => 'Internal Server Error']);
        }
    }

    private function isJson(string $string): bool
    {
        $len = strlen($string);
        if ($len === 0) {
            return false;
        }

        // Find first non-whitespace character without ltrim()
        $first = '';
        $firstPos = 0;
        for ($i = 0; $i < $len; $i++) {
            $char = $string[$i];
            if ($char !== ' ' && $char !== "\n" && $char !== "\r" && $char !== "\t") {
                $first = $char;
                $firstPos = $i;
                break;
            }
        }

        if ($first !== '{' && $first !== '[') {
            return false;
        }

        if (function_exists('json_validate')) {
            return json_validate($string);
        }

        // Find last non-whitespace character without rtrim()
        $last = '';
        for ($i = $len - 1; $i >= $firstPos; $i--) {
            $char = $string[$i];
            if ($char !== ' ' && $char !== "\n" && $char !== "\r" && $char !== "\t") {
                $last = $char;
                break;
            }
        }

        if (($first === '{' && $last === '}') || ($first === '[' && $last === ']')) {
            json_decode($string);
            return json_last_error() === JSON_ERROR_NONE;
        }

        return false;
    }
}
