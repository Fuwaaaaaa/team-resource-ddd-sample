<?php

declare(strict_types=1);

namespace App\Application\Import\DTOs;

/**
 * バルクインポート結果レポート。
 *
 * 成功件数と、失敗した行 (1-indexed、ヘッダは 1 行目) の一覧。
 * 部分成功を許容する想定なので、1 件失敗しても他の行は処理される。
 */
final class ImportReportDto
{
    /**
     * @param  int  $imported  取り込みに成功した行数
     * @param  array<int, array{line:int,error:string,raw?:array<string,string>}>  $failures
     */
    public function __construct(
        public readonly int $imported,
        public readonly array $failures,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'imported' => $this->imported,
            'failureCount' => count($this->failures),
            'failures' => $this->failures,
        ];
    }
}
