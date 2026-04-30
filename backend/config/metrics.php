<?php

declare(strict_types=1);

return [
    /*
     * Prometheus / OpenMetrics scraping 用 token。
     *
     * 未設定 (空文字列) のときは GET /api/metrics は 404 を返す。
     * production では env で乱数を設定し、 Prometheus 側で
     *   bearer_token: '<value>'
     * として scrape config に書く。
     *
     * Datadog OpenMetrics integration からも同じ token で読める。
     */
    'token' => env('METRICS_TOKEN', ''),
];
