<?php

use Slim\App;
use Slim\Middleware\ErrorMiddleware;
use Tuupola\Middleware\HttpBasicAuthentication;

return function (App $app) {
    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();
    $app->add(HttpBasicAuthentication::class);
    $app->add(ErrorMiddleware::class);
};
