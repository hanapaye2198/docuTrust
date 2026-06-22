<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class CscApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $responseBody
     */
    public function __construct(
        private readonly string $endpoint,
        private readonly int $httpStatus,
        private readonly array $responseBody,
        ?Throwable $previous = null,
    ) {
        parent::__construct($this->buildMessage(), $httpStatus, $previous);
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * @return array<string, mixed>
     */
    public function getResponseBody(): array
    {
        return $this->responseBody;
    }

    private function buildMessage(): string
    {
        $error = $this->responseBody['error_description']
            ?? $this->responseBody['error']
            ?? 'unknown error';

        return sprintf(
            'CSC API error on %s: HTTP %d — %s',
            $this->endpoint,
            $this->httpStatus,
            is_scalar($error) ? (string) $error : 'unknown error',
        );
    }
}
