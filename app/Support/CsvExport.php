<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams large CSV exports in chunks and neutralises spreadsheet formula
 * injection (cells starting with = + - @ tab or CR are prefixed with a quote).
 */
final class CsvExport
{
    /**
     * @param  list<string>  $headers
     * @param  callable(mixed): list<mixed>  $row
     */
    public static function stream(string $filename, array $headers, Builder $query, callable $row): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $query, $row) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for spreadsheet apps
            fputcsv($out, $headers, escape: '');
            $query->chunkById(1000, function ($items) use ($out, $row) {
                foreach ($items as $item) {
                    fputcsv($out, array_map([self::class, 'safe'], $row($item)), escape: '');
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    public static function safe(mixed $value): string
    {
        $value = $value instanceof \BackedEnum ? (string) $value->value : (string) ($value ?? '');

        // Plain numbers (including negatives) are safe and must stay numeric.
        if (preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
