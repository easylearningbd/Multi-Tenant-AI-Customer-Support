<?php

namespace App\Enums;

enum UsageLedgerStatus: string
{
    case RESERVED = 'reserved';
    case COMMITTED = 'committed';
    case RELEASED = 'released';
}
