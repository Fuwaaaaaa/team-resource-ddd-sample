<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Reports\ProjectStatusReportRenderer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function projectStatusPdf(
        string $id,
        Request $request,
        ProjectStatusReportRenderer $renderer,
    ): Response {
        $request->validate([
            'referenceDate' => 'nullable|date_format:Y-m-d',
        ]);

        try {
            $binary = $renderer->render($id, $request->query('referenceDate'));
        } catch (\RuntimeException $e) {
            abort(404, $e->getMessage());
        }

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="project-status-%s.pdf"', $id),
            'Content-Length' => (string) strlen($binary),
        ]);
    }
}
