<?php

use PHPUnit\Framework\TestCase;

class IbcTest extends TestCase
{
    protected $app;

    protected function setUp(): void
    {
        // Instantiate the app
        $settings = require dirname(__DIR__) . '/app/settings.php';
        $app = new \Slim\App($settings);

        // Set up dependencies
        require dirname(__DIR__) . '/app/dependencies.php';

        // Register middleware
        require dirname(__DIR__) . '/app/middleware.php';

        $this->app = $app;

        $this->container = $app->getContainer();
    }

    public function testBuildUrlNoQueryString(): void
    {
        $url = $this->container['utils']->build_url('https://example.com');
        $query = parse_url($url, PHP_URL_QUERY);
        $this->assertNull($query, 'build_url should not append query string in this instance');
    }

    public function testBuildUrlQueryString(): void
    {
        $url = $this->container['utils']->build_url('https://example.com', ['foo' => 'bar']);
        $query = parse_url($url, PHP_URL_QUERY);
        $this->assertNotNull($query, 'build_url should append a query string in this instance');
    }

    public function testBuildUrlOnlyOneQueryString(): void
    {
        $url = $this->container['utils']->build_url('https://example.com?endpoint=micropub', ['foo' => 'bar']);
        $query = parse_url($url, PHP_URL_QUERY);

        $this->assertFalse(strpos($query, '?'), 'URL already has query parameters. build_url should append query params with &');
    }

    public function testUtilsIsUrlAllowed(): void
    {
        $url = 'https://indiebookclub.biz/redirect1';
        $result = $this->container['utils']->is_url_allowed($url);
        $this->assertTrue($result, sprintf('%s should be an allowed url', $url));

        $url = 'https://dev.indiebookclub.biz/redirect2';
        $result = $this->container['utils']->is_url_allowed($url);
        $this->assertTrue($result, sprintf('%s should be an allowed url', $url));

        $url = '/redirect3';
        $result = $this->container['utils']->is_url_allowed($url);
        $this->assertTrue($result, sprintf('%s should be an allowed url', $url));

        $url = 'https://subdomain.indiebookclub.biz/';
        $result = $this->container['utils']->is_url_allowed($url);
        $this->assertFalse($result, sprintf('%s should not be an allowed url', $url));

        $url = 'https://example.com/';
        $result = $this->container['utils']->is_url_allowed($url);
        $this->assertFalse($result, sprintf('%s should not be an allowed url', $url));
    }

    public function testUtilsGetRedirect(): void
    {
        $url = '/redirect1';
        $result = $this->container['utils']->get_redirect($url);
        $this->assertEquals($url, $result);

        $url = 'https://indiebookclub.biz/redirect2';
        $result = $this->container['utils']->get_redirect($url);
        $this->assertEquals($url, $result);

        $url = 'https://dev.indiebookclub.biz/redirect3';
        $result = $this->container['utils']->get_redirect($url);
        $this->assertEquals($url, $result);

        $url = 'https://example.com/redirect4';
        $result = $this->container['utils']->get_redirect($url);
        $this->assertEquals('/', $result);

        $default = '/profile';
        $url = 'https://example.com/redirect4';
        $result = $this->container['utils']->get_redirect($url, $default);
        $this->assertEquals($default, $result);
    }

    public function testNormalizeSeparatedString(): void
    {
        $input = 'create,  draft,   delete';
        $result = $this->container['utils']->normalizeSeparatedString($input);
        $this->assertEquals('create,draft,delete', $result);

        $input = '  create  delete   profile  ';
        $result = $this->container['utils']->normalizeSeparatedString($input, ' ');
        $this->assertEquals('create delete profile', $result);
    }

    public function testHasScope(): void
    {
        $scopes = 'create profile';
        $result = $this->container['utils']->hasScope($scopes, 'profile');
        $this->assertTrue($result);

        $result = $this->container['utils']->hasScope($scopes, 'delete');
        $this->assertFalse($result);
    }
}

