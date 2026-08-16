<?php

declare(strict_types=1);

namespace AsterMD\VrioClient\Exception;

use Exception;

/**
 * Base exception for every error raised by this package.
 *
 * Extends \Exception so existing `catch (\Exception $e)` blocks keep working.
 *
 * @package AsterMD\VrioClient
 */
class VrioException extends Exception
{
}
