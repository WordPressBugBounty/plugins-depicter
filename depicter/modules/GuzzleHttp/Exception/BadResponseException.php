<?php

declare (strict_types=1);
namespace Depicter\GuzzleHttp\Exception;

/**
 * Exception thrown for HTTP responses with 4xx or 5xx status codes.
 */
class BadResponseException extends ResponseException
{
}