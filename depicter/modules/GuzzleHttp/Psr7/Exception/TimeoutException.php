<?php

declare (strict_types=1);
namespace Depicter\GuzzleHttp\Psr7\Exception;

use RuntimeException;
/**
 * Exception thrown when a stream operation times out.
 */
class TimeoutException extends RuntimeException
{
}