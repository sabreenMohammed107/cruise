<?php

namespace App\Enums;

enum OnlinePaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    // Signed as paid, but for another amount than we asked: not recorded, the team checks it
    case AmountMismatch = 'amount_mismatch';
}
