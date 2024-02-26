<?php

namespace App\Action\Loan;

use App\Domain\Loan\Service\LoanFinder;
use App\Renderer\JsonRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpForbiddenException;
use Laminas\Config\Config;
use App\Factory\LoggerFactory;
use Psr\Log\LoggerInterface;

final class LoanFinderAction
{
    private LoggerInterface $logger;

    public function __construct(private LoanFinder $loanFinder, private JsonRenderer $jsonRenderer, private Config $config, LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory
            ->addFileHandler('debt-information.log')
            ->createLogger();
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $financialInstitutionID = $request->getAttribute('financialInstitutionID');
        
        // VALIDATE financialInstitutionID (path attribute FI against conf)
        if ($financialInstitutionID != $this->config->financialInstitutionID) {
            throw new HttpForbiddenException($request, 'Not allowed to access FI resource.');
        }

        $customerHeaders = $request->getHeader('CustomerID');

        $debtInformation = $this->loanFinder->findLoans($customerHeaders, $financialInstitutionID);

        $this->logInfo('Get Customer Loans', $request);

        return $this->renderer->json($response, $debtInformation);
    }

    private function logInfo(string $msg, ServerRequestInterface $request, array $extraInfo = []): void
    {
        $info = [];
        $info['method'] = $request->getMethod();
        $info['url'] = (string)$request->getUri();
        $info['headers'] = $request->getHeaders();
        
        if ($_SERVER['SSL_CLIENT_S_DN_O'] ?? null) {
            $info['SSL_CLIENT_S_DN_O'] = $_SERVER['SSL_CLIENT_S_DN_O'] ?? "";
        }
        if ($_SERVER['SSL_CLIENT_S_DN']) ?? null) {
            $info['SSL_CLIENT_S_DN'] = $_SERVER['SSL_CLIENT_S_DN'] ?? "";
        }
        if ($_SERVER['REMOTE_ADDR'] ?? null) {
            $info['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? "";
        }

        if ($extraInfo) {
            $info = array_merge($info, $extraInfo);
        }

        $this->logger->info($msg, $info);
    }
}