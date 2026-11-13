<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Streams a query as CSV (chunked, so large exports don't exhaust memory) and audits the export. */
class CsvExport
{
    /**
     * @param  array<string, callable(mixed): mixed>  $columns  header => value resolver
     */
    public static function download(string $name, Builder $query, array $columns): StreamedResponse
    {
        ActivityLog::record('export', null, ['rows' => (clone $query)->count()], "Exported {$name}");

        return response()->streamDownload(function () use ($query, $columns) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows ₹ correctly
            fputcsv($out, array_keys($columns));

            $query->chunkById(500, function ($records) use ($out, $columns) {
                foreach ($records as $record) {
                    fputcsv($out, array_map(fn (callable $resolve) => self::safe($resolve($record)), $columns));
                }
            });

            fclose($out);
        }, $name.'-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralise spreadsheet formula injection (=, +, -, @ at the start of a cell). */
    protected static function safe(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($value) ? "'".$value : $value;
    }
}
