<?php

declare(strict_types=1);
namespace Mandrill\Exception;

/**
 * A dedicated IP cannot be provisioned while another request is pending.
 */
class LimitIPProvisionException extends LimitException
{
}
