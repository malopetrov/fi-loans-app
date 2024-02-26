<?php

namespace App\Middleware;

use Slim\Exception\HttpForbiddenException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\Uid\Uuid;
use Laminas\Config\Config;

final class DebtInformationMiddleware implements MiddlewareInterface
{
    public function __construct(private ResponseFactoryInterface $responseFactory, private Config $config) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        if (!($_SERVER['SSL_CLIENT_S_DN_O'] ?? null)) 
            || !(preg_match('/serialNumber\=(\d{9})/', $_SERVER['SSL_CLIENT_S_DN'], $m))
            || !in_array($m[1], $this->config->authorizedOrgNums->toArray(), true)
        ) {
            throw new HttpForbiddenException($request, 'Not allowed to access resource');
        }

        return $handler->handle($request);
    }
}