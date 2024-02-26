<?php

namespace App\Action;

use App\Exception\NotFoundException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use Laminas\Config\Config;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\ClientException;

final class HiAction
{    
    public function __construct(private Config $config) {}

    public function __invoke(
        ServerRequestInterface $request, 
        ResponseInterface $response
    ): ResponseInterface 
    {
        $text = "Hi";
        if ($_SERVER['SSL_CLIENT_S_DN_O'] ?? null) {
            $text .= ', '.$_SERVER['SSL_CLIENT_S_DN_O'];
         
            if ($_SERVER['SSL_CLIENT_S_DN'] ?? null) {
                if (preg_match('/serialNumber\=(\d{9})/', $_SERVER['SSL_CLIENT_S_DN'], $m)) {
                    $finantialInstitutionId = $m[1];
                    $text .= " (".$finantialInstitutionId.")";
                }
            }
        } else {
            $text .= ' guest!';
        }
        
        $response->getBody()->write($text);
        
        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus(200);
    }
}