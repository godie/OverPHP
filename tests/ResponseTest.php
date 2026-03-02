<?php

declare(strict_types=1);

namespace OverPHP\Tests;

use OverPHP\Core\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testJsonResponse(): void
    {
        $response = Response::json(['success' => true]);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);
        $this->assertTrue($decoded['success']);
    }

    public function testRawResponse(): void
    {
        $response = Response::raw('raw content');

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertEquals('raw content', $output);
    }
}
