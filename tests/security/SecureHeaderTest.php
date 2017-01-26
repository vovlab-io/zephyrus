<?php namespace Zephyrus\Tests;

use PHPUnit\Framework\TestCase;
use Zephyrus\Security\ContentSecurityPolicy;
use Zephyrus\Security\SecureHeader;

class SecureHeaderTest extends TestCase
{
    public function testFrameOptions()
    {
        $header = new SecureHeader();
        $header->setFrameOptions("SAMEORIGIN");
        self::assertEquals("SAMEORIGIN", $header->getFrameOptions());
        $header->send();
        self::assertTrue(in_array('X-Frame-Options: SAMEORIGIN', xdebug_get_headers()));
    }

    public function testXssProtection()
    {
        $header = new SecureHeader();
        $header->setXssProtection("1; mode=block");
        self::assertEquals("1; mode=block", $header->getXssProtection());
        $header->send();
        self::assertTrue(in_array('X-XSS-Protection: 1; mode=block', xdebug_get_headers()));
    }

    public function testContentTypeOptions()
    {
        $header = new SecureHeader();
        $header->setContentTypeOptions("nosniff");
        self::assertEquals("nosniff", $header->getContentTypeOptions());
        $header->send();
        self::assertTrue(in_array('X-Content-Type-Options: nosniff', xdebug_get_headers()));
    }

    public function testStrictTransport()
    {
        $header = new SecureHeader();
        $header->setStrictTransportSecurity("max-age=16070400; includeSubDomains");
        self::assertEquals("max-age=16070400; includeSubDomains", $header->getStrictTransportSecurity());
        $header->send();
        self::assertTrue(in_array('Strict-Transport-Security: max-age=16070400; includeSubDomains', xdebug_get_headers()));
    }

    public function testContentSecurityPolicy()
    {
        $csp = new ContentSecurityPolicy();
        $csp->setDefaultSources([ContentSecurityPolicy::SELF]);
        $header = new SecureHeader();
        $header->setContentSecurityPolicy($csp);
        self::assertEquals([ContentSecurityPolicy::SELF], $header->getContentSecurityPolicy()->getAllHeader()['default-src']);
        $header->send();
        self::assertTrue(in_array("Content-Security-Policy: default-src 'self';reflected-xss block;", xdebug_get_headers()));
    }
}