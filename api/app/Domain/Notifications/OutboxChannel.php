<?php

namespace App\Domain\Notifications;

enum OutboxChannel: string
{
    case SMS = 'SMS';
    case EMAIL = 'EMAIL';
    case DMS = 'DMS';
}
