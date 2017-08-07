<?php

declare(strict_types=1);
namespace Mandrill\Exception;

/**
 * Non-empty pools cannot be deleted.
 */
class InvalidDeleteNonEmptyPoolException extends InvalidException
{
}
