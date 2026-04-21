<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Import\ImportAllocationsHandler;
use App\Application\Import\ImportMembersHandler;
use App\Application\Import\ImportProjectsHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function members(Request $request, ImportMembersHandler $handler): JsonResponse
    {
        $csv = $this->readCsv($request);
        $report = $handler->handle($csv);

        return response()->json(['data' => $report->toArray()]);
    }

    public function projects(Request $request, ImportProjectsHandler $handler): JsonResponse
    {
        $csv = $this->readCsv($request);
        $report = $handler->handle($csv);

        return response()->json(['data' => $report->toArray()]);
    }

    public function allocations(Request $request, ImportAllocationsHandler $handler): JsonResponse
    {
        $csv = $this->readCsv($request);
        $report = $handler->handle($csv);

        return response()->json(['data' => $report->toArray()]);
    }

    private function readCsv(Request $request): string
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120', // 5MB
        ]);

        $file = $request->file('file');

        return (string) file_get_contents($file->getRealPath());
    }
}
