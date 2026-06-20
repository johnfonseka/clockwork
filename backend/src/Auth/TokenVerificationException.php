<?php

declare(strict_types=1);

namespace Clockwork\Auth;

/** Raised when an Apple identity token cannot be verified. */
final class TokenVerificationException extends \RuntimeException
{
}
