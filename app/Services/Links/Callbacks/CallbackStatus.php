<?php

namespace App\Services\Links\Callbacks;

enum CallbackStatus: string
{
    case Failed = 'failed';
    case Pending = 'pending';
    case Sent = 'sent';
}
