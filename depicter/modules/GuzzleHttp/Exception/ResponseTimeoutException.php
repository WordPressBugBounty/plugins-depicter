<?php

declare (strict_types=1);
namespace Depicter\GuzzleHttp\Exception;

/**
 * Exception thrown when a transfer times out after response headers are
 * received.
 */
class ResponseTimeoutException extends ResponseTransferException
{
}