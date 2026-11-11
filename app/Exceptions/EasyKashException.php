<?php

namespace App\Exceptions;

use RuntimeException;

/** EasyKash could not open a checkout (not configured, unreachable, or refused the request). */
class EasyKashException extends RuntimeException {}
