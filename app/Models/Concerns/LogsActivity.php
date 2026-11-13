<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes created/updated/deleted rows to the audit log. Secrets and noisy
 * columns are never recorded (see $hiddenFromActivityLog / ActivityLog::NEVER_LOG).
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn (Model $model) => ActivityLog::record('created', $model, $model->activityChanges(true)));
        static::updated(function (Model $model) {
            $changes = $model->activityChanges(false);

            if ($changes !== []) {
                ActivityLog::record('updated', $model, $changes);
            }
        });
        static::deleted(fn (Model $model) => ActivityLog::record('deleted', $model));
    }

    protected function activityChanges(bool $isNew): array
    {
        $ignored = array_merge(ActivityLog::NEVER_LOG, $this->hiddenFromActivityLog ?? []);
        $changes = [];

        foreach ($isNew ? $this->getAttributes() : $this->getChanges() as $key => $value) {
            if (in_array($key, $ignored, true)) {
                continue;
            }

            $changes[$key] = $isNew
                ? ['new' => $value]
                : ['old' => $this->getOriginal($key), 'new' => $value];
        }

        return $changes;
    }
}
