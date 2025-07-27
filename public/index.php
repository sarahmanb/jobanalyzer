<?php
// public/index.php - Application Entry Point

require_once __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;
use Slim\ResponseEmitter\ResponseEmitter;
use DI\Container;
use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;

// Load Environment Variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Create Container
$container = new Container();

// Database Configuration
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => $_ENV['DB_CONNECTION'],
    'host' => $_ENV['DB_HOST'],
    'port' => $_ENV['DB_PORT'],
    'database' => $_ENV['DB_DATABASE'],
    'username' => $_ENV['DB_USERNAME'],
    'password' => $_ENV['DB_PASSWORD'],
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

// Register Database in Container
$container->set('db', function() use ($capsule) {
    return $capsule;
});

// Register Twig
$container->set('view', function() {
    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../resources/views');
    $twig = new \Twig\Environment($loader, [
        'cache' => $_ENV['APP_ENV'] === 'production' ? __DIR__ . '/../storage/cache' : false,
        'debug' => $_ENV['APP_DEBUG'] === 'true',
    ]);
    
    // Add global variables
    $twig->addGlobal('app_name', $_ENV['APP_NAME']);
    $twig->addGlobal('app_url', $_ENV['APP_URL']);
    
    return $twig;
});

// Register Services
$container->set('jobAnalyzer', function() {
    return new \App\Services\JobAnalyzerService();
});

$container->set('pdfParser', function() {
    return new \App\Services\PDFParserService();
});

$container->set('aiAnalysis', function() {
    return new \App\Services\AIAnalysisService();
});

$container->set('reportGenerator', function() use ($container) {
    return new \App\Services\ReportGeneratorService($container->get('view'));
});

// Create App
AppFactory::setContainer($container);
$app = AppFactory::create();

// Add Error Middleware
$errorMiddleware = $app->addErrorMiddleware(
    $_ENV['APP_DEBUG'] === 'true',
    true,
    true
);

// Add Body Parsing Middleware
$app->addBodyParsingMiddleware();

// Add Routing Middleware
$app->addRoutingMiddleware();

// Register Routes
require_once __DIR__ . '/../routes/web.php';
require_once __DIR__ . '/../routes/api.php';

// Home Route
$app->get('/', function ($request, $response, $args) {
    return $response->withHeader('Location', '/dashboard')->withStatus(302);
});

// Run App
$serverRequestCreator = ServerRequestCreatorFactory::create();
$request = $serverRequestCreator->createServerRequestFromGlobals();

$response = $app->handle($request);
$responseEmitter = new ResponseEmitter();
$responseEmitter->emit($response);