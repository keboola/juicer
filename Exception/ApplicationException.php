<?php

namespace Keboola\Juicer\Exception;

class ApplicationException extends \Exception
{
    protected array $data;

    public function __construct(string $message = "", int $code = 0, \Exception $previous = null, array $data = [])
    {
        $this->data = $data;
        parent::__construct($message, $code, $previous);
    }

    public function getData(): array
    {
        return $this->data;
    }
}
