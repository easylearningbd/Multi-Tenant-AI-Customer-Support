<?php

namespace App\Enums;

enum SupportTicketSenderType: string
{
    case SUBSCRIBER = 'subscriber';
    case STAFF = 'staff';
}
