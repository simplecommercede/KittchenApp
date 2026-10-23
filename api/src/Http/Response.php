<?php

declare(strict_types=1);

namespace Kitchen\Http;

final class Response extends \RuntimeException
{
    public function __construct(public readonly mixed $data, public readonly int $status = 200)
    {
        parent::__construct('HTTP response', $status);
    }
    public function send(): void
    {
        http_response_code($this->status);
        echo json_encode($this->data, JSON_UNESCAPED_UNICODE);
    }
}
