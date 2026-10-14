<?php

declare (strict_types=1);
namespace Depicter\GuzzleHttp\Exception;

use Depicter\Psr\Http\Client\NetworkExceptionInterface;
use Depicter\Psr\Http\Message\RequestInterface;
/**
 * Base exception for transfer failures without a response.
 */
class NetworkException extends TransferException implements NetworkExceptionInterface
{
    public function __construct(string $message, RequestInterface $request, ?\Throwable $previous = null)
    {
        parent::__construct($message, $request, 0, $previous);
    }
}