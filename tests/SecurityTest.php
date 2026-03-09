<?php

declare(strict_types=1);

namespace OverPHP\Tests;

use OverPHP\Core\Security;
use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase
{
    public function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function testGenerateAndValidateCsrfToken(): void
    {
        $token = Security::generateCsrfToken();
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        $this->assertTrue(Security::validateCsrfToken($token));
    }

    public function testValidateInvalidCsrfToken(): void
    {
        Security::generateCsrfToken();
        $this->assertFalse(Security::validateCsrfToken('invalid-token'));
        $this->assertFalse(Security::validateCsrfToken(null));
    }

    public function testEscapeHtml(): void
    {
        $input = '<script>alert("xss")</script>';
        $expected = '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;';
        $this->assertEquals($expected, Security::escape($input));
    }

    public function testJsonEncodeEscapesTags(): void
    {
        $data = ['tag' => '<script>'];
        $json = Security::jsonEncode($data);
        $this->assertStringContainsString('\u003Cscript\u003E', $json);
    }

    public function testCsrfEnabledDisabled(): void
    {
        Security::setCsrfEnabled(true);
        $this->assertTrue(Security::isCsrfEnabled());
        Security::setCsrfEnabled(false);
        $this->assertFalse(Security::isCsrfEnabled());
        Security::setCsrfEnabled(true); // reset
    }

    public function testGenerateCsrfTokenReusesExistingToken(): void
    {
        $token1 = Security::generateCsrfToken();
        $token2 = Security::generateCsrfToken();
        $this->assertEquals($token1, $token2);
    }
}
