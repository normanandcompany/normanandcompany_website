<?php

declare(strict_types=1);

final class PrintfulException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $category = 'api_error',
        private readonly ?int $httpStatus = null,
        private readonly ?array $response = null
    ) {
        parent::__construct($message, $httpStatus ?? 0);
    }

    public function category(): string
    {
        return $this->category;
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function response(): ?array
    {
        return $this->response;
    }

    public function isNotFound(): bool
    {
        return $this->httpStatus === 404;
    }
}
