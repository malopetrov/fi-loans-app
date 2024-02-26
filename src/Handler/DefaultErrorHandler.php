<?php

namespace App\Handler;

use App\Factory\LoggerFactory;
use App\Renderer\JsonRenderer;
use DomainException;
use Fig\Http\Message\StatusCodeInterface;
use InvalidArgumentException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;
use Slim\Interfaces\ErrorHandlerInterface;
use App\Support\Validation\ValidationException;

use Throwable;
use DateTime;
use DateTimeZone;

/**
 * Default Error Renderer.
 */
final class DefaultErrorHandler implements ErrorHandlerInterface
{
    private LoggerInterface $logger;

    public function __construct(
        private JsonRenderer $jsonRenderer,
        private ResponseFactoryInterface $responseFactory,
        LoggerFactory $loggerFactory
    ) 
    {
        $this->logger = $loggerFactory
            ->addFileHandler('error.log')
            ->createLogger();
    }

    /**
     * Invoke.
     *
     * @param ServerRequestInterface $request The request
     * @param Throwable $exception The exception
     * @param bool $displayErrorDetails Show error details
     * @param bool $logErrors Log errors
     * @param bool $logErrorDetails Log error details
     *
     * @return ResponseInterface The response
     */
    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface {
        // Log error
        if ($logErrors) {
            $error = $this->getErrorDetails($exception, $logErrorDetails);
            $error['method'] = $request->getMethod();
            $error['url'] = (string)$request->getUri();
            $error['headers'] = $request->getHeaders();
            $error['body'] = json_decode($request->getBody()->getContents(),true);
            if ($_SERVER['SSL_CLIENT_S_DN_O'] ?? null) {
                $error['SSL_CLIENT_S_DN_O'] = $_SERVER['SSL_CLIENT_S_DN_O'] ?? "";
            }
            if ($_SERVER['SSL_CLIENT_S_DN'] ?? null) {
                $error['SSL_CLIENT_S_DN'] = $_SERVER['SSL_CLIENT_S_DN'] ?? "";
            }
            if ($_SERVER['REMOTE_ADDR'] ?? null) {
                $error['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? "";
            }

            $this->logger->error($exception->getMessage(), $error);
            $uuid = $this->logger->getName();
        }

        $response = $this->responseFactory->createResponse();
        // Render response
        $response = $this->jsonRenderer->json($response, [
            'uuid' => $uuid ?? "",
            'timestamp' => (new DateTime())->setTimezone(new DateTimeZone("UTC"))->format("Y-m-d\TH:i:sp"),
            'error' => $this->getErrorDetails($exception, $displayErrorDetails),
        ]);

        return $response->withStatus($this->getHttpStatusCode($exception));
    }

    /**
     * Get http status code.
     *
     * @param Throwable $exception The exception
     *
     * @return int The http code
     */
    private function getHttpStatusCode(Throwable $exception): int
    {
        // Detect status code
        $statusCode = StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR;

        if ($exception instanceof HttpException) {
            $statusCode = $exception->getCode();
        }

        if ($exception instanceof DomainException || $exception instanceof InvalidArgumentException) {
            // Bad request
            $statusCode = StatusCodeInterface::STATUS_BAD_REQUEST;
        }

        $file = basename($exception->getFile());
        if ($file === 'CallableResolver.php') {
            $statusCode = StatusCodeInterface::STATUS_NOT_FOUND;
        }

        return $statusCode;
    }

    /**
     * Get error message.
     *
     * @param Throwable $exception The error
     * @param bool $displayErrorDetails Display details
     *
     * @return array The error details
     */
    private function getErrorDetails(Throwable $exception, bool $displayErrorDetails): array
    {
        if ($displayErrorDetails === true) {
            $result = [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                //'previous' => $exception->getPrevious(),
                //'trace' => $exception->getTrace(),
            ];
        } else {
            $result = [
                'message' => $exception->getMessage(),
            ];
        }

        if ($exception->getCode() == 422) {
            $details = $exception->transform();
            if ($details) {
                $result['details'] = $details;
            }
        }

        return $result;
    }
}