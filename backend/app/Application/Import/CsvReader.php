<?php

declare(strict_types=1);

namespace App\Application\Import;

use InvalidArgumentException;

/**
 * UTF-8 / UTF-8 BOM を許容するシンプルな CSV リーダー。
 *
 * - 1 行目はヘッダー（列名のマップを作る）
 * - ヘッダ中のコラム名は case-sensitive
 * - セル値はトリム + 空文字列 → null 変換しない（null 化は呼び出し側で）
 */
final class CsvReader
{
    /**
     * @return \Generator<int, array<string, string>> 1 ファイル行ごとにヘッダで添字化した連想配列を yield。
     *                                                key は行番号 (1-indexed、ヘッダは含まない先頭行 = 2)
     */
    public static function rows(string $csv): \Generator
    {
        // BOM を除去
        if (str_starts_with($csv, "\xEF\xBB\xBF")) {
            $csv = substr($csv, 3);
        }

        $lines = preg_split("/\r\n|\n|\r/", $csv) ?: [];
        if (count($lines) === 0 || trim($lines[0]) === '') {
            throw new InvalidArgumentException('CSV is empty or missing header.');
        }

        $header = str_getcsv((string) array_shift($lines), ',', '"', '\\');
        $header = array_map(fn ($h) => trim((string) $h), $header);

        $lineNo = 2; // header は 1 行目
        foreach ($lines as $line) {
            if (trim($line) === '') {
                $lineNo++;

                continue;
            }
            $cols = str_getcsv($line, ',', '"', '\\');
            $assoc = [];
            foreach ($header as $i => $name) {
                $assoc[$name] = isset($cols[$i]) ? trim((string) $cols[$i]) : '';
            }
            yield $lineNo => $assoc;
            $lineNo++;
        }
    }
}
