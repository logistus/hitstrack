<?php

namespace Database\Seeders;

use App\Models\LinkRotator;
use App\Models\LinkTracker;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LinkStatsLoadSeeder extends Seeder
{
    private const TRACKER_ROWS = 12000;

    private const ROTATOR_ROWS = 8000;

    private const CHUNK_SIZE = 1000;

    /**
     * Seed link tracker and rotator stats for local performance testing.
     */
    public function run(): void
    {
        $user = User::query()->first()
            ?? User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $trackers = $this->trackersFor($user);
        $rotator = $this->rotatorFor($user, $trackers);

        $this->seedTrackerStats($trackers);
        $this->seedRotatorStats($rotator, $trackers);
    }

    private function trackersFor(User $user)
    {
        $trackers = LinkTracker::query()
            ->where('user_id', $user->id)
            ->limit(5)
            ->get();

        if ($trackers->isNotEmpty()) {
            return $trackers;
        }

        return collect(range(1, 5))
            ->map(fn (int $index) => LinkTracker::create([
                'user_id' => $user->id,
                'target_url' => "https://example.com/offer-{$index}",
                'tracker_slug' => Str::random(8),
                'tracker_name' => "Load test tracker {$index}",
            ]));
    }

    private function rotatorFor(User $user, $trackers): LinkRotator
    {
        $rotator = LinkRotator::query()
            ->where('user_id', $user->id)
            ->first();

        if (! $rotator) {
            $rotator = LinkRotator::create([
                'user_id' => $user->id,
                'rotator_slug' => Str::random(8),
                'rotation_type' => 'round_robin',
            ]);
        }

        foreach ($trackers->values() as $index => $tracker) {
            $rotator->trackers()->syncWithoutDetaching([
                $tracker->id => [
                    'weight' => 1,
                    'order_column' => $index + 1,
                ],
            ]);
        }

        return $rotator;
    }

    private function seedTrackerStats($trackers): void
    {
        $rows = [];

        for ($i = 0; $i < self::TRACKER_ROWS; $i++) {
            $tracker = $trackers->random();
            $createdAt = $this->randomCreatedAt();

            $rows[] = [
                'tracker_id' => $tracker->id,
                'rotator_id' => null,
                ...$this->clientPayload(),
                'created_at' => $createdAt,
            ];

            if (count($rows) >= self::CHUNK_SIZE) {
                DB::table('tracker_stats')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('tracker_stats')->insert($rows);
        }
    }

    private function seedRotatorStats(LinkRotator $rotator, $trackers): void
    {
        $rotatorRows = [];
        $trackerRows = [];

        for ($i = 0; $i < self::ROTATOR_ROWS; $i++) {
            $tracker = $trackers->random();
            $createdAt = $this->randomCreatedAt();
            $payload = $this->clientPayload();

            $rotatorRows[] = [
                'rotator_id' => $rotator->id,
                'tracker_id' => $tracker->id,
                ...$payload,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            $trackerRows[] = [
                'tracker_id' => $tracker->id,
                'rotator_id' => $rotator->id,
                ...$payload,
                'created_at' => $createdAt,
            ];

            if (count($rotatorRows) >= self::CHUNK_SIZE) {
                DB::table('rotator_stats')->insert($rotatorRows);
                DB::table('tracker_stats')->insert($trackerRows);
                $rotatorRows = [];
                $trackerRows = [];
            }
        }

        if ($rotatorRows !== []) {
            DB::table('rotator_stats')->insert($rotatorRows);
            DB::table('tracker_stats')->insert($trackerRows);
        }
    }

    private function clientPayload(): array
    {
        $referrers = [
            'hungryforhits.com',
            'hitsconnect.com',
            'hitsandlistcafe.com',
            'harvesttraffic.com',
            'trafficadbar.com',
            'leadsleap.com',
            'easyhits4u.com',
            'legacyhits.com',
            null,
        ];

        $devices = ['desktop', 'mobile', 'tablet'];
        $operatingSystems = ['Windows', 'macOS', 'iOS', 'Android', 'Linux'];
        $browsers = ['Chrome', 'Firefox', 'Safari', 'Edge', 'Opera'];
        $countries = ['US', 'TR', 'GB', 'CA', 'DE', 'AU', null];

        return [
            'ref_url' => $referrers[array_rand($referrers)],
            'ip_address' => $this->randomIp(),
            'device_type' => $devices[array_rand($devices)],
            'operating_system' => $operatingSystems[array_rand($operatingSystems)],
            'browser' => $browsers[array_rand($browsers)],
            'country_code' => $countries[array_rand($countries)],
        ];
    }

    private function randomCreatedAt(): string
    {
        return now()
            ->subDays(random_int(0, 59))
            ->setTime(random_int(0, 23), random_int(0, 59), random_int(0, 59))
            ->toDateTimeString();
    }

    private function randomIp(): string
    {
        return sprintf(
            '%d.%d.%d.%d',
            random_int(11, 223),
            random_int(0, 255),
            random_int(0, 255),
            random_int(1, 254),
        );
    }
}
