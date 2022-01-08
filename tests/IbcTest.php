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

	public function testBuildUrlNoQueryString()
	{
		$url = $this->container['utils']->build_url('https://example.com');
		$query = parse_url($url, PHP_URL_QUERY);
		$this->assertNull($query, 'build_url should not append query string in this instance');
	}

	public function testBuildUrlQueryString()
	{
		$url = $this->container['utils']->build_url('https://example.com', ['foo' => 'bar']);
		$query = parse_url($url, PHP_URL_QUERY);
		$this->assertNotNull($query, 'build_url should append a query string in this instance');
	}

	public function testBuildUrlOnlyOneQueryString()
	{
		$url = $this->container['utils']->build_url('https://example.com?endpoint=micropub', ['foo' => 'bar']);
		$query = parse_url($url, PHP_URL_QUERY);

		$this->assertFalse(strpos($query, '?'), 'URL already has query parameters. build_url should append query params with &');
	}
}

