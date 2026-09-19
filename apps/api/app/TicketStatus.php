<?php

namespace App;

enum TicketStatus: string
{
    case Reserved = 'reserved';
    case Waiting = 'waiting';
    case Called = 'called';
    case Serving = 'serving';
    case Skipped = 'skipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** @return array<int, self> */
    public static function active(): array
    {
        return [self::Reserved, self::Waiting, self::Called, self::Serving, self::Skipped];
    }
}
