<?php

namespace App\Console\Commands;

use App\Actions\Listings\ExpireListing;
use App\Actions\Listings\SendListingFreshnessReminder;
use App\Enums\ReminderStage;
use App\Models\Listing;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Hourly freshness pass (docs/13-freshness-and-notifications.md): first reminder, second
 * reminder and automatic pause. Every pass is idempotent (flags on the row, status change)
 * and a listing receives at most one email per run.
 */
#[Signature('avytra:listings:process-freshness {--dry-run : List what would happen without sending or changing anything}')]
#[Description('Send availability reminders and pause the published listings nobody confirmed in time.')]
class ProcessListingFreshness extends Command
{
    private const CHUNK = 200;

    public function handle(SendListingFreshnessReminder $sendReminder, ExpireListing $expire): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run: nothing is sent or changed.');
        }

        $reminded = [];

        foreach (ReminderStage::cases() as $stage) {
            $reminded[$stage->value] = $this->process(
                Listing::query()->dueForReminder($stage),
                "{$stage->value} reminder",
                fn (Listing $listing) => $sendReminder->handle($listing, $stage),
                $dryRun,
            );
        }

        $expired = $this->process(
            Listing::query()->dueForExpiration(),
            'pause',
            fn (Listing $listing) => $expire->handle($listing),
            $dryRun,
        );

        $this->info(sprintf(
            '%s: first reminders %d, second reminders %d, paused %d.',
            $dryRun ? 'Would process' : 'Processed',
            $reminded[ReminderStage::First->value],
            $reminded[ReminderStage::Second->value],
            $expired,
        ));

        return self::SUCCESS;
    }

    /**
     * @param  Builder<Listing>  $query
     * @param  callable(Listing): mixed  $action
     */
    private function process(Builder $query, string $label, callable $action, bool $dryRun): int
    {
        $count = 0;

        $query->with('business.owner')->chunkById(self::CHUNK, function (Collection $listings) use ($label, $action, $dryRun, &$count): void {
            foreach ($listings as $listing) {
                $count++;

                if ($dryRun) {
                    $this->line(sprintf('[%s] #%d %s (last confirmed %s)', $label, $listing->id, $listing->title, $listing->last_confirmed_at?->toDateString()));

                    continue;
                }

                $action($listing);
            }
        });

        return $count;
    }
}
