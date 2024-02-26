<?php

namespace App\Action\Loan;

use App\Domain\Loan\Service\LoanGetAll;
use App\Renderer\JsonRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpForbiddenException;
use Laminas\Config\Config;
use App\Factory\LoggerFactory;
use Psr\Log\LoggerInterface;

final class LoanGetAllAction
{
    private LoggerInterface $logger;

    public function __construct(private LoanGetAll $loanGetAll, private JsonRenderer $jsonRenderer, private Config $config, LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory
            ->addFileHandler('debt-information.log')
            ->createLogger();
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $financialInstitutionID = $request->getAttribute('financialInstitutionID');

        // VALIDATE financialInstitutionID (path attribute FI against conf)
        if ($financialInstitutionID && ($financialInstitutionID != $this->config->financialInstitutionID)) {
            throw new HttpForbiddenException($request, 'Not allowed to access FI resource.');
        }
        
        $params = $request->getQueryParams();
        if (isset($params['page'])) {
            $page = $params['page'];
        } else {
            $page = "0";
        }

        $debtInformationsString = $this->loanGetAll->getAllLoans($financialInstitutionID, $page);
        
        $fromPattern = $this->config->datapath . "/" . $this->config->file_loans_name."_latest_page_*.json";
        $loanFiles = glob($fromPattern);
        $pages = count($loanFiles);

        $info = [];
        $info['responseBodySize'] = mb_strlen($debtInformationsString);
        $info['responseHeader_numberOfPages'] = $pages;        
        $this->logInfo('Get All Loans', $request, $info);
        
        $response->getBody()->write((string)$debtInformationsString);
        if ($pages > 1) {
            return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withHeader('numberOfPages', $pages)
                    ->withStatus(200);
        }

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
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
        if ($_SERVER['SSL_CLIENT_S_DN'] ?? null) {
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