<?php

namespace App\Support\Validation;

use DomainException;
use Throwable;

final class ValidationException extends DomainException
{
    private $errors;

    public function __construct(
        string $message, 
        array $errors = [], 
        int $code = 422, 
        Throwable $previous = null
    ){
        parent::__construct($message, $code, $previous);

        $this->errors = $errors;
        
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
    
    public function transform(): array
    {
        $error = [];

        $errors = $this->getErrors();
        if ($errors) {
            $error = $this->addErrors([], $errors);
        }

        return $error;
    }

    private function addErrors(array $result, array $errors, string $path = ''): array
    {
        foreach ($errors as $field => $error) {
            $oldPath = $path;
            $path .= ($path === '' ? '' : '.') . $field;
            $result = $this->addSubErrors($result, $error, $path);
            $path = $oldPath;
        }

        return $result;
    }

    private function addSubErrors(array $result, array $error, string $path = ''): array
    {
        foreach ($error as $field2 => $errorMessage) {
            if (is_array($errorMessage)) {
                $result = $this->addErrors($result, [$field2 => $errorMessage], $path);
            } else {
                $result[] = [
                    'message' => $errorMessage,
                    'field' => $path,
                ];
            }
        }

        return $result;
    }
}