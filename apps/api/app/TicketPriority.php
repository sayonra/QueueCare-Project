<?php

namespace App;

enum TicketPriority: string
{
    case Emergency = 'emergency';
    case Accessibility = 'accessibility';
    case Scheduled = 'scheduled';
    case Standard = 'standard';
    case Restored = 'restored';
}
