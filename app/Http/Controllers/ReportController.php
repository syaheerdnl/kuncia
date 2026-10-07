<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Reports\LandlordStats;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $year = $this->year($request);

        return Inertia::render('reports/index', [
            'year' => $year,
            'years' => range((int) now()->year, (int) now()->year - 4),
            'report' => $this->stats($request)->incomeByProperty($year),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $year = $this->year($request);
        $report = $this->stats($request)->incomeByProperty($year);

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['Month', ...$report['properties'], 'Total (RM)']);
            foreach ($report['rows'] as $row) {
                $values = array_map(fn ($p) => number_format((float) $row[$p], 2, '.', ''), $report['properties']);
                fputcsv($out, [$row['month'], ...$values, number_format(array_sum(array_map('floatval', $values)), 2, '.', '')]);
            }
            fputcsv($out, ['Total', ...array_map(fn ($v) => number_format($v, 2, '.', ''), array_values($report['totals'])), number_format(array_sum($report['totals']), 2, '.', '')]);
            fclose($out);
        }, "sewahub-income-{$year}.csv", ['Content-Type' => 'text/csv']);
    }

    private function year(Request $request): int
    {
        $year = $request->integer('year', (int) now()->year);

        return max(2000, min($year, (int) now()->year));
    }

    private function stats(Request $request): LandlordStats
    {
        /** @var User $user */
        $user = $request->user();

        return new LandlordStats($user);
    }
}
