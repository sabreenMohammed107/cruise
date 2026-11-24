<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV files Excel opens directly: UTF-8 with a byte order mark (so Arabic shows correctly), and text that
 * Excel would run as a formula (starting with = + - @) prefixed with a quote. Customers export and reports.
 */
final class Csv
{
    /**
     * @param  list<string>  $headers
     * @param  callable(callable(array): void): void  $rows  receives a writer; call it once per row
     */
    public static function download(string $filename, array $headers, callable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            $rows(function (array $row) use ($out) {
                fputcsv($out, array_map([self::class, 'cell'], $row));
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Text starting with = + - @ would run as a formula in Excel; prefix it with a quote. Numbers stay as they are. */
    public static function cell(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true) && ! is_numeric($value) ? "'".$value : $value;
    }
}
