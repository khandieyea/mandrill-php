<?php

declare(strict_types=1);
namespace Mandrill\Exception;

use GuzzleHttp\Exception\RequestException;

class HttpException extends RequestException implements Exception
{
}
