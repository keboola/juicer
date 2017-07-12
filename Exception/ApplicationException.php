<?php

namespace Keboola\Juicer\Exception;

class ApplicationException extends \Exception
{
    /**
     * @var array
     */
    protected $data;

    public function __construct($message = "", $code = 0, \Exception $previous = null, $data = [])
    {
        $this->data = $data;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }
}
