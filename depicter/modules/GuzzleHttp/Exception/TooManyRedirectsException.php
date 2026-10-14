<?php

declare (strict_types=1);
namespace Depicter\GuzzleHttp\Exception;

/**
 * Exception thrown when redirect middleware reaches the redirect limit.
 */
class TooManyRedirectsException extends ResponseException
{
}