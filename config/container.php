<?php

use App\Factory\LoggerFactory;
use App\Handler\DefaultErrorHandler;

use Cake\Database\Connection;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Middleware\ErrorMiddleware;
use Laminas\Config\Config;
use Tuupola\Middleware\HttpBasicAuthentication;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputOption;

return [
    'settings' => function () {
        return require __DIR__ . '/settings.php';
    },
    
    HttpBasicAuthentication::class => function (ContainerInterface $container) {
        $settings = $container->get('settings')['basic-auth-debt-update'];
        return new HttpBasicAuthentication([
            "path" => "/debt-updates/v1",
            "ignore" => ["/debt-updates/v1/hi"],
            "realm" => "Protected",
            "users" => [
                $settings['user'] => $settings['pass'],
            ],
        ]);
    },
    
    ResponseFactoryInterface::class => function (ContainerInterface $container) {
        return $container->get(App::class)->getResponseFactory();
    },
    
    // The Slim RouterParser
    RouteParserInterface::class => function (ContainerInterface $container) {
        return $container->get(App::class)->getRouteCollector()->getRouteParser();
    },
    
    // The logger factory
    LoggerFactory::class => function (ContainerInterface $container) {
        return new LoggerFactory($container->get('settings')['logger']);
    },
    
    App::class => function (ContainerInterface $container) {
        AppFactory::setContainer($container);
        
        return AppFactory::create();
    },

    ErrorMiddleware::class => function (ContainerInterface $container) {
        $app = $container->get(App::class);
        $settings = $container->get('settings')['error'];

        $logger = $container->get(LoggerFactory::class)
            ->addFileHandler('error.log')
            ->createLogger();
            
        $errorMiddleware = new ErrorMiddleware(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            (bool)$settings['display_error_details'],
            (bool)$settings['log_errors'],
            (bool)$settings['log_error_details'],
            $logger
        );

        $errorMiddleware->setDefaultErrorHandler($container->get(DefaultErrorHandler::class));

        return $errorMiddleware;
    },

    Connection::class => function (ContainerInterface $container) {
        return new Connection($container->get('settings')['db']);
    },

    PDO::class => function (ContainerInterface $container) {
        $driver = $container->get(Connection::class)->getDriver();

        $class = new ReflectionClass($driver);
        $method = $class->getMethod('getPdo');
        $method->setAccessible(true);

        return $method->invoke($driver);
    },
    
    Config::class => function (ContainerInterface $container) {
        $datapath = $container->get('settings')['path']['data'];
        $file_loans_name = $container->get('settings')['file_loans_name'];
        $file_ssns_name = $container->get('settings')['file_ssns_name'];
        $authorizedOrgNums = $container->get('settings')['authorizedOrgNums'];
        $providerID = $container->get('settings')['providerID'];
        $financialInstitutionID = $container->get('settings')['financialInstitutionID'];
        $dic_url = $container->get('settings')['dic_url'];
        $dic_path = $container->get('settings')['dic_path'];
        $dic_cert_full_path_file = $container->get('settings')['dic_cert_full_path_file'];
        $dic_cert_password = $container->get('settings')['dic_cert_password'];
        
        return new Config([
            'datapath' => $datapath, 
            'file_loans_name' => $file_loans_name, 
            'file_ssns_name' => $file_ssns_name, 
            'authorizedOrgNums' => (array)$authorizedOrgNums,
            'providerID' => $providerID,
            'financialInstitutionID' => $financialInstitutionID,
            'dic_url' => $dic_url,
            'dic_path' => $dic_path,
            'dic_cert_full_path_file' => $dic_cert_full_path_file,
            'dic_cert_password' => $dic_cert_password
        ]);
    },
    
    Application::class => function (ContainerInterface $container) {
        $application = new Application();

        $application->getDefinition()->addOption(
            new InputOption('--env', '-e', InputOption::VALUE_REQUIRED, 'The Environment name.', 'dev')
        );

        foreach ($container->get('settings')['commands'] as $class) {
            $application->add($container->get($class));
        }

        return $application;
    },
    
];