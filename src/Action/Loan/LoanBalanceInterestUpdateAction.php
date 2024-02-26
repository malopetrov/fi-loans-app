<?php

namespace App\Action\Loan;

use App\Domain\Loan\Service\LoanBalanceInterestUpdater;
use App\Renderer\JsonRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use App\Factory\LoggerFactory;
use Psr\Log\LoggerInterface;

final class LoanBalanceInterestUpdateAction
{
    private LoggerInterface $logger;
    
    public function __construct(private LoanBalanceInterestUpdater $loanBalanceInterestUpdater, private JsonRenderer $jsonRenderer, LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory
            ->addFileHandler('debt-update.log')
            ->createLogger();
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        
        $updateBalanceInterestLoan = $this->loanBalanceInterestUpdater->updateBalanceInterestLoan((array)$data);

        $this->logInfo('Update Balance/Interest', $request, (array) $updateBalanceInterestLoan);
        
        $updateBalanceInterestLoan->uuid = $this->logger->getName();
        
        $status = ($updateBalanceInterestLoan->success ? 200 : 400);

        return $this->renderer->json($response->withStatus($status), $updateBalanceInterestLoan);
    }

    private function logInfo(string $msg, ServerRequestInterface $request, array $extraInfo = []): void
    {
        $info = [];
        $info['method'] = $request->getMethod();
        $info['path'] = (string)$request->getUri()->getPath();
        $info['query'] = (string)$request->getUri()->getQuery();
        $info['headers'] = $request->getHeaders();
        $info['body'] = $request->getBody()->getContents();

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