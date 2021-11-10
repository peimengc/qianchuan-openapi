<?php


namespace Peimengc\QianchuanOpenapi;


use Throwable;

class ResponseContentsException extends \Exception
{
    public function __construct($contents, $code = 0, Throwable $previous = null)
    {
        parent::__construct($contents, $code, $previous);
    }
}