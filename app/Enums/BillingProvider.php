<?php

namespace App\Enums;

enum BillingProvider: string
{
    case Local = 'local';
    case Stripe = 'stripe';
    case Paddle = 'paddle';
    case LemonSqueezy = 'lemon_squeezy';
}
