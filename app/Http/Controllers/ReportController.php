<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Reports\InboundDetailReport;
use App\Reports\InboundSummaryReport;
use App\Reports\InventoryReport;
use App\Reports\OutboundDetailReport;
use App\Reports\OutboundSummaryReport;
use App\Reports\Report;
use App\Support\Xlsx\XlsxWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Semua laporan berbasis App\Reports\Report (Inbound Detail, Inbound Summary, Inventory).
 *   index       : KPI + tabel (paginasi) + tab Pivot & Grafik (diolah di browser, public/js/report.js)
 *   dataset     : baris untuk pivot/grafik (kolom dimensi + ukuran saja, maks Report::DATASET_LIMIT)
 *   export      : Excel .xlsx, semua kolom atau ?columns[]=... terpilih (maks Report::EXPORT_LIMIT)
 *   exportPivot : hasil pivot dari browser -> Excel
 *   row         : detail satu baris (modal), bila laporan punya rowKey() (mis. Inventory -> kartu stok)
 * Route & hak akses per laporan: lihat helper $report di routes/web.php.
 */
class ReportController extends Controller
{
    private const REPORTS = [
        'detail' => InboundDetailReport::class,
        'summary' => InboundSummaryReport::class,
        'inventory' => InventoryReport::class,
        'out-detail' => OutboundDetailReport::class,
        'out-summary' => OutboundSummaryReport::class,
    ];

    public function index(Request $request, string $report): View
    {
        $r = $this->report($report);
        $f = $r->filtersFrom($request);
        $keys = array_keys($r->columns());

        $q = $r->query($f);
        if ($r->rowKey()) {
            $q->selectRaw($r->rowKey().' as `__row`');
        }
        $rows = $q->paginate(50)->withQueryString();
        $rows->setCollection($rows->getCollection()->map(fn ($row) => [...$r->present($row, $keys), '__row' => $row->__row ?? null]));

        return view('reports.index', [
            'report' => $r,
            'f' => $f,
            'rows' => $rows,
            'kpis' => $r->kpis($f),
            'columns' => $r->columns(),
        ]);
    }

    public function dataset(Request $request, string $report): JsonResponse
    {
        $r = $this->report($report);
        $f = $r->filtersFrom($request);
        $keys = collect($r->columns())->filter(fn ($c) => ($c['dim'] ?? false) || ($c['measure'] ?? false))->keys()->all();

        $data = $r->query($f, $keys)->limit(Report::DATASET_LIMIT + 1)->get();
        $truncated = $data->count() > Report::DATASET_LIMIT;

        return response()->json([
            'fields' => $keys,
            'rows' => $data->take(Report::DATASET_LIMIT)->map(fn ($row) => array_values($r->present($row, $keys)))->values(),
            'truncated' => $truncated,
            'limit' => Report::DATASET_LIMIT,
        ]);
    }

    public function export(Request $request, string $report): Response
    {
        $r = $this->report($report);
        $f = $r->filtersFrom($request);
        $cols = $r->columns();
        $keys = array_values(array_intersect((array) $request->input('columns', []), array_keys($cols))) ?: array_keys($cols);

        $q = $r->query($f, $keys);
        if ((clone $q)->reorder()->count() > Report::EXPORT_LIMIT) {
            throw new BusinessException('Data lebih dari '.number_format(Report::EXPORT_LIMIT).' baris. Persempit periode / filter lalu export lagi.');
        }

        return XlsxWriter::make($r->title())
            ->title([$r->title(), $r->filterSummary($f), 'Dicetak '.now()->format('d/m/Y H:i').' oleh '.$request->user()->name])
            ->columns(array_map(fn ($k) => ['label' => $cols[$k]['label'], 'type' => $cols[$k]['type']], $keys))
            ->rows($this->rowsFor($r, $q, $keys))
            ->download(str($r->title())->slug().'-'.now()->format('Ymd-His').'.xlsx');
    }

    /** Hasil pivot (dihitung di browser) -> Excel. */
    public function exportPivot(Request $request, string $report): Response
    {
        $r = $this->report($report);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'headers' => ['required', 'array', 'min:1', 'max:200'],
            'headers.*' => ['nullable', 'string', 'max:200'],
            'types' => ['required', 'array'],
            'types.*' => ['in:string,number,percent'],
            'rows' => ['present', 'array', 'max:5000'],
            'rows.*' => ['array'],
        ]);

        return XlsxWriter::make('Pivot')
            ->title([$r->title().' — Pivot', $data['title'], $r->filterSummary($r->filtersFrom($request)), 'Dicetak '.now()->format('d/m/Y H:i')])
            ->columns(array_map(fn ($h, $t) => ['label' => (string) $h, 'type' => $t], $data['headers'], array_pad($data['types'], count($data['headers']), 'string')))
            ->rows($data['rows'])
            ->download(str($r->title())->slug().'-pivot-'.now()->format('Ymd-His').'.xlsx');
    }

    /** Detail satu baris (HTML partial untuk modal). */
    public function row(Request $request, string $report): Response
    {
        $r = $this->report($report);
        abort_unless($r->rowKey(), 404);

        return response($r->rowDetail($request->integer('id')));
    }

    private function rowsFor(Report $r, $q, array $keys): \Generator
    {
        foreach ($q->lazy(1000) as $row) {
            yield array_values($r->present($row, $keys));
        }
    }

    private function report(string $key): Report
    {
        $class = self::REPORTS[$key] ?? abort(404);

        return app($class);
    }
}
