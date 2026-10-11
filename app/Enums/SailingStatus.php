<?php

namespace App\Enums;

enum SailingStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
