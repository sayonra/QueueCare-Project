<?php

namespace App\Console\Commands;

use App\Services\NotificationOutbox;
use Illuminate\Console\Command;

class RetryNotifications extends Command
{
    protected $signature = 'notifications:retry {--limit=100}';

    protected $description = 'Deliver pending QueueCare notifications and retry transient failures';

    public function handle(NotificationOutbox $outbox): int
    {
        $count = $outbox->deliverDue((int) $this->option('limit'));
        $this->info("Processed {$count} notification(s).");

        return self::SUCCESS;
    }
}
