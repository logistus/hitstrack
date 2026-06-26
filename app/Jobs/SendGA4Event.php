<?php

namespace App\Jobs;

use App\Services\GA4Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendGA4Event implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 10;

    public function __construct(
        private readonly string $eventType,  // 'tracker_click' | 'rotator_click'
        private readonly array  $clickData,
    ) {}

    public function handle(GA4Service $ga4): void
    {
        match ($this->eventType) {
            'tracker_click' => $ga4->sendTrackerClick($this->clickData),
            'rotator_click' => $ga4->sendRotatorClick($this->clickData),
            default         => null,
        };
    }
}
