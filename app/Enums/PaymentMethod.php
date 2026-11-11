<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Card = 'card';
    case PaymentLink = 'payment_link';
    // Paid online by the guest (recorded automatically from the EasyKash webhook)
    case EasyKash = 'easykash';
}
