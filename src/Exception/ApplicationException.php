<?php

declare(strict_types=1);

namespace Keboola\Juicer\Exception;

use Exception;
use Throwable;

class ApplicationException extends Exception
{
    protected array $data;

    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, array $data = [])
    {
        $this->data = $data;
        parent::__construct($message, $code, $previous);
    }

    public function getData(): array
    {
        return $this->data;
    }
}
