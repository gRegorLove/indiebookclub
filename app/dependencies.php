<?php
/**
 * Set up the application's dependencies and container
 */

declare(strict_types=1);

use App\Controller\{
    AuthController,
    IbcController,
    PageController,
    UsersController
};
use App\Helper\Utils;
use App\Model\{
    Book,
    Entry,
    User
};
use Monolog\{
    ErrorHandler,
    Logger,
    Handler\RotatingFileHandler,
    Handler\StreamHandler
};
use Psr\Http\{
    Message\ResponseInterface,
    Message\ServerRequestInterface
};
use Slim\{
    Http\Environment,
    Http\Uri,
    Views\Twig,
    Views\TwigExtension
};
use Twig\TwigFunction;

ORM::configure('mysql:host=' . $_ENV['IBC_DB_HOST'] . ';dbname=' . $_ENV['IBC_DB_NAME']);
ORM::configure('username', $_ENV['IBC_DB_USERNAME']);
ORM::configure('password', $_ENV['IBC_DB_PASSWORD']);
ORM::configure('driver_options', [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4']);

$container = $app->getContainer();

# Logging
$log_dir = dirname(__DIR__) . '/logs/'; # default log location
if ($_ENV['LOG_DIR']) {
    # custom log location
    $log_dir = $_ENV['LOG_DIR'];
}

$container['logger'] = function($c) use ($log_dir) {
    $logger = new Logger($_ENV['LOG_NAME']);
    $logger->pushHandler(
        new StreamHandler(
            $log_dir . sprintf('%s.log', $_ENV['LOG_NAME']),
            Logger::DEBUG,
            $bubble = true,
            0666
        )
    );

    return $logger;
};

$container['php_logger'] = function($c) use ($log_dir) {
    $logger = new Logger('php-' . $_ENV['LOG_NAME']);
    $logger->pushHandler(
        new RotatingFileHandler(
            $log_dir . sprintf('php-%s.log', $_ENV['LOG_NAME']),
            14,
            Logger::DEBUG,
            $bubble = true,
            0666
        )
    );

    $handler = new ErrorHandler($logger);
    $handler->registerErrorHandler([], true);
    $handler->registerExceptionHandler();
    $handler->registerFatalHandler();

    return $logger;
};

$container['utils'] = function ($c) {
    return new Utils($c->get('router'));
};

# Views
$container['view'] = function ($c) {
    $settings = $c->get('settings');

    $cache = $settings['theme']['twig_cache_path'] ?? false;
    $auto_reload = true;

    $twig = new Twig(
        $settings['theme']['twig_path'],
        compact('cache', 'auto_reload')
    );

    # Instantiate and add Slim-specific extension
    $router = $c->get('router');
    $uri = Uri::createFromEnvironment(new Environment($_SERVER));
    $twig->addExtension(new TwigExtension($router, $uri));

    $utils = $c->get('utils');

    $environment = $twig->getEnvironment();

    $environment->addFunction(
        new TwigFunction('getenv', function ($key) {
            return $_ENV[$key] ?? '';
        })
    );

    $environment->addFunction(
        new TwigFunction('session', function ($key) use ($utils) {
            return $utils->session($key);
        })
    );

    $environment->addFunction(
        new TwigFunction('debug', function($debug = null) {
            if (!is_null($debug)) {
                echo sprintf('<details><summary>Debugging</summary> <pre>%s</pre></details>',
                    print_r($debug, true)
                );
            }
        }, ['is_safe' => ['html']])
    );

    $environment->addFunction(
        /**
         * @author Aaron Parecki, https://aaronparecki.com
         * @copyright 2014 Aaron Parecki
         * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
         */
        new TwigFunction('get_entry_date', function(string $date, int $tz_offset = 0) {
            $dt = new DateTime($date);

            if ($tz_offset > 0) {
                $dt->add(new DateInterval('PT' . $tz_offset . 'S'));
            } elseif ($tz_offset < 0) {
                $dt->sub(new DateInterval('PT' . abs($tz_offset) . 'S'));
            }

            $tz = ($tz_offset < 0 ? '-' : '+') . sprintf('%02d%02d', abs($tz_offset/60/60), ($tz_offset/60)%60);
            return new DateTime($dt->format('Y-m-d H:i:s') . $tz);
        })
    );

    return $twig;
};

# Not Found handler
$container['notFoundHandler'] = function ($c) {
    return function (ServerRequestInterface $request, ResponseInterface $response) use ($c) {
        $response = $response->withStatus(404);
        return $c->view->render($response, 'pages/404.twig');
    };
};

# Default error handler
$container['errorHandler'] = function ($c) {
    return function (ServerRequestInterface $request, ResponseInterface $response, Exception $exception) use ($c) {
        $c->logger->error($exception->getMessage());

        $message = 'ENVIRONMENT:' . PHP_EOL . ($_ENV['APP_ENV'] ?? 'unspecified');
        $message .= PHP_EOL . PHP_EOL . 'TYPE:' . PHP_EOL . get_class($exception);
        $message .= PHP_EOL . PHP_EOL . 'MESSAGE:' . PHP_EOL . $exception->getMessage();
        $message .= PHP_EOL . PHP_EOL . 'URI:' . PHP_EOL . print_r($request->getUri(), true);
        $message .= PHP_EOL . PHP_EOL . 'METHOD:' . PHP_EOL . $request->getMethod();
        $message .= PHP_EOL . PHP_EOL . 'REQUEST BODY:' . PHP_EOL . print_r($request->getParsedBody(), true);
        $message .= PHP_EOL . PHP_EOL . 'STACK TRACE:' . PHP_EOL . $exception->getTraceAsString();
        $c->utils->notify_admin($message, 'indiebookclub internal server error');
        unset($message);

        $response = $response->withStatus(500);
        return $c->view->render($response, 'pages/500.twig');
    };
};

# PHP error handler
$container['phpErrorHandler'] = function ($c) {
    return function (ServerRequestInterface $request, ResponseInterface $response, $exception) use ($c) {
        $c->php_logger->error($exception->getMessage());

        $message = 'ENVIRONMENT:' . PHP_EOL . ($_ENV['APP_ENV'] ?? 'unspecified');
        $message .= PHP_EOL . PHP_EOL . 'TYPE:' . PHP_EOL . get_class($exception);
        $message .= PHP_EOL . PHP_EOL . 'MESSAGE:' . PHP_EOL . $exception->getMessage();
        $message .= PHP_EOL . PHP_EOL . 'URI:' . PHP_EOL . print_r($request->getUri(), true);
        $message .= PHP_EOL . PHP_EOL . 'METHOD:' . PHP_EOL . $request->getMethod();
        $message .= PHP_EOL . PHP_EOL . 'REQUEST BODY:' . PHP_EOL . print_r($request->getParsedBody(), true);
        $message .= PHP_EOL . PHP_EOL . 'STACK TRACE:' . PHP_EOL . $exception->getTraceAsString();
        $c->utils->notify_admin($message, 'indiebookclub PHP error');
        unset($message);

        $response = $response->withStatus(500);
        return $c->view->render($response, 'pages/500.twig');
    };
};

# Controllers
$container['AuthController'] = function ($c) {
    return new AuthController($c);
};

$container['PageController'] = function ($c) {
    return new PageController($c);
};

$container['IbcController'] = function ($c) {
    return new IbcController($c);
};

$container['UsersController'] = function ($c) {
    return new UsersController($c);
};

# Models
$container['Book'] = function ($c) {
    return new Book();
};

$container['Entry'] = function ($c) {
    return new Entry();
};

$container['User'] = function ($c) {
    return new User();
};

