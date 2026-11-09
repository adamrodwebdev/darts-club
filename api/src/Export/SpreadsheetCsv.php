<?php

declare(strict_types=1);

namespace App\Export;

/**
 * Fichier CSV pour un tableur : BOM UTF-8 (accents lus par Excel), point-virgule, et chaque cellule
 * neutralisée contre l'injection de formules (=, +, -, @, tabulation).
 */
final class SpreadsheetCsv
{
    /** @param iterable<list<string>> $rows en-tête compris */
    public static function of(iterable $rows): string
    {
        $out = fopen('php://temp', 'r+');
        \assert(false !== $out);
        fwrite($out, "\u{FEFF}");
        foreach ($rows as $row) {
            fputcsv($out, array_map(self::cell(...), $row), ';', escape: '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    private static function cell(string $value): string
    {
        return '' !== $value && \in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($value)
            ? "'".$value
            : $value;
    }
}
