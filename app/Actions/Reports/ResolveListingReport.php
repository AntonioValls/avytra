<?php

namespace App\Actions\Reports;

use App\Enums\ListingReportStatus;
use App\Models\ListingReport;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use InvalidArgumentException;

/**
 * Closes a report as resolved (the complaint was justified) or dismissed. Whatever the
 * superadmin did with the listing itself goes through the listing Actions, which keep
 * their own trail; this one only records the decision on the report.
 */
class ResolveListingReport
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(ListingReport $report, User $actor, ListingReportStatus $outcome, ?string $notes = null): ListingReport
    {
        throw_if($outcome->isOpen(), new InvalidArgumentException('A report is closed as resolved or dismissed, never reopened as open.'));
        throw_unless($report->isOpen(), new InvalidArgumentException('The report is already closed.'));

        $report->status = $outcome;
        $report->resolved_by_user_id = $actor->getKey();
        $report->resolved_at = now();
        $report->resolution_notes = filled($notes) ? trim((string) $notes) : null;
        $report->save();

        $this->audit->log(
            action: 'listing_report.'.$outcome->value,
            subject: $report,
            actor: $actor,
            changes: ['after' => ['status' => $outcome->value, 'resolution_notes' => $report->resolution_notes]],
        );

        return $report;
    }
}
