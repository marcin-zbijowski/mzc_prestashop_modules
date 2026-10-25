<?php

declare(strict_types=1);

namespace Mzc\Brevo\Api;

/** Brevo could not be reached: DNS, TLS, timeout, connection reset. Always worth a retry. */
final class TransportException extends \RuntimeException
{
}
