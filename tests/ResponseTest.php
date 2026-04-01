<?php

declare(strict_types=1);

namespace OverPHP\Tests;

use OverPHP\Core\Response;
use OverPHP\Core\Benchmark;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    protected function setUp(): void
    {
        Benchmark::start(false); // Ensure benchmark is disabled by default
    }

    public function testJsonResponse(): void
    {
        \OverPHP\Core\Benchmark::start(false);
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

   /**
     * Test para verificar la preservación de objetos JsonSerializable y los Benchmarks.
     */
    public function testJsonSerializablePreservationWithBenchmarks(): void
    {
        // Activamos los benchmarks de OverPHP
        \OverPHP\Core\Benchmark::start(true);

        $data = new class implements \JsonSerializable {
            public function jsonSerialize(): mixed {
                return ['serialized' => true];
            }
        };

        $response = Response::json($data);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);

        // Verificamos que la data se serializó correctamente
        $this->assertArrayHasKey('data', $decoded);
        $this->assertEquals(['serialized' => true], $decoded['data']);
        
        // Verificamos que los benchmarks están presentes en la respuesta
        $this->assertArrayHasKey('_performance', $decoded);

        // Limpiamos el estado del benchmark
        \OverPHP\Core\Benchmark::start(false);
    }

    /**
     * Test para verificar que isJson maneja correctamente varios tipos de espacios en blanco.
     */
    public function testIsJsonWithVariousWhitespace(): void
    {
        // Caso 1: JSON Válido con espacios
        $jsonData = '   {"key": "value"}   ';
        $response = Response::json($jsonData); 

        ob_start();
        $response->send();
        $output = ob_get_clean();

        // Si isJson detectó que ya era JSON, debería enviarlo tal cual (o mínimamente procesado)
        $this->assertEquals($jsonData, $output);

        // Caso 2: String que no es JSON con espacios
        $invalidJson = '   not json   ';
        // Usamos el constructor directamente para ver cómo lo maneja el send()
        $response2 = new Response($invalidJson); 
        
        ob_start();
        $response2->send();
        $output2 = ob_get_clean();

        $this->assertEquals('   not json   ', $output2);
    }

    /**
     * Test específico para espacios en blanco complejos (tabs, saltos de línea) al inicio.
     */
    public function testIsJsonWithLeadingWhitespaceOnly(): void
    {
        $jsonData = "\n\r\t [1,2,3]";
        $response = Response::json($jsonData);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        // Verificamos que la función isJson fue capaz de ver a través de los caracteres especiales
        $this->assertEquals($jsonData, $output);
    }

    /**
     * Test para verificar que los escalares se serializan a JSON cuando el intent es JSON
     */
    public function testScalarSerializationWithJsonIntent(): void
    {
        // En CLI header() no funciona para headers_list(), así que usamos el header del Response object
        // para probar la misma lógica interna de detección de JSON intent.

        $response = new Response(true, 200, ['Content-Type' => 'application/json']);
        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertEquals('true', $output);

        $response2 = new Response(null, 200, ['Content-Type' => 'application/json']);
        ob_start();
        $response2->send();
        $output2 = ob_get_clean();

        $this->assertEquals('null', $output2);
    }
}
