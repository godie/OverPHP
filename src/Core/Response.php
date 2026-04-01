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
        private readonly mixed $content,
        private readonly int $statusCode = 200,
        private readonly array $headers = []
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

        $isJsonIntent = $this->isJsonContentType();

        if (is_array($this->content) || is_object($this->content) || is_bool($this->content) || is_int($this->content) || is_float($this->content) || $this->content === null) {
            $this->sendJson($this->content, $headersSent);
            return;
        }

        if (is_string($this->content) && $this->isJson($this->content)) {
            if (!$headersSent && !$isJsonIntent) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo $this->content;
            return;
        }

        if ($isJsonIntent) {
            $this->sendJson($this->content, $headersSent);
            return;
        }

        echo is_string($this->content) ? Security::escape($this->content) : (string) $this->content;
    }

    private function isJsonContentType(): bool
    {
        foreach ($this->headers as $name => $value) {
            if (strtolower($name) === 'content-type' && str_contains(strtolower($value), 'application/json')) {
                return true;
            }
        }

        if (function_exists('headers_list')) {
            foreach (headers_list() as $header) {
                if (stripos($header, 'Content-Type:') === 0 && stripos($header, 'application/json') !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function sendJson(mixed $data, bool $headersSent): void
    {
        if (!$headersSent) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $stats = Benchmark::stats();
        $output = $data;

        if ($stats !== null) {
            $output = [
                'data' => $data,
                '_performance' => $stats,
            ];
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
        if ($string === '') {
            return false;
        }

        // Prioridad: PHP 8.3+ (Extremadamente rápido y eficiente en memoria)
        if (function_exists('json_validate')) {
            return json_validate($string);
        }

        // Fallback para PHP < 8.3: Evitamos trim() para no duplicar memoria en strings grandes
        $len = strlen($string);
        $firstPos = 0;
        while ($firstPos < $len && ctype_space($string[$firstPos])) {
            $firstPos++;
        }

        if ($firstPos === $len) {
            return false;
        }

        $lastPos = $len - 1;
        while ($lastPos > $firstPos && ctype_space($string[$lastPos])) {
            $lastPos--;
        }

        $first = $string[$firstPos];
        $last = $string[$lastPos];

        // Solo intentamos decodificar si tiene estructura de objeto, array o es un escalar común (true, false, null, número, string entre comillas)
        $candidate = substr($string, $firstPos, $lastPos - $firstPos + 1);

        $isPossibleJson = ($first === '{' && $last === '}') ||
            ($first === '[' && $last === ']') ||
            ($first === '"' && $last === '"') ||
            in_array($candidate, ['true', 'false', 'null'], true) ||
            is_numeric($candidate);

        if ($isPossibleJson) {
            json_decode($candidate);
            return json_last_error() === JSON_ERROR_NONE;
        }

        return false;
    }
}
