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

    public function testIsJsonWithVariousWhitespace(): void
    {
        $jsonData = '   {"key": "value"}   ';
        $response = Response::json($jsonData); // This will trigger isJson in send()

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertEquals($jsonData, $output);

        $invalidJson = '   not json   ';
        $response2 = new Response($invalidJson); // Using constructor to test escape logic
        ob_start();
        $response2->send();
        $output2 = ob_get_clean();

        $this->assertEquals('   not json   ', $output2);
    }

    public function testIsJsonWithLeadingWhitespaceOnly(): void
    {
        $jsonData = "\n\r\t [1,2,3]";
        $response = Response::json($jsonData);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertEquals($jsonData, $output);
    }
}
