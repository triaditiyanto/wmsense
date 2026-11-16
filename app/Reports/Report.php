<?php

namespace App\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * Dasar laporan: satu definisi dipakai untuk tabel, pivot/grafik (dataset JSON) dan export Excel.
 *
 * columns(): key => [
 *     'label'   => judul kolom,
 *     'type'    => string | number | percent | date | datetime,
 *     'sql'     => ekspresi SELECT (alias = key),
 *     'default' => tampil di tabel & export "kolom terpilih" secara default,
 *     'dim'     => bisa jadi baris/kolom pivot,
 *     'measure' => bisa jadi nilai pivot (angka),
 *     'agg'     => agregasi default di pivot (sum | avg | ...),
 *     'map'     => fn ($v) => label (mis. kode enum -> teks),
 * ]
 * Tambah laporan baru: buat subclass, daftarkan di ReportController::REPORTS, route (helper $report di routes/web.php),
 * module detail & panduan.
 */
abstract class Report
{
    /** Batas baris untuk pivot/grafik (diolah di browser) dan export. */
    public const DATASET_LIMIT = 20000;
    public const EXPORT_LIMIT = 50000;

    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    /** Kode module detail untuk hak akses & panduan. */
    abstract public function code(): string;

    abstract public function columns(): array;

    /** Filter tambahan selain periode: name => [label, [value => text]]. */
    abstract public function filters(): array;

    /** Query dasar (FROM + JOIN + WHERE dari filter), tanpa SELECT. */
    abstract protected function base(array $f): Builder;

    /** KPI ringkasan di atas laporan: list of [label, value, icon]. */
    abstract public function kpis(array $f): array;

    /** Label kolom tanggal yang dipakai filter periode. */
    abstract public function periodLabel(): string;

    /** Konfigurasi awal pivot/grafik. */
    abstract public function preset(): array;

    /** Prefix nama route laporan, mis. 'inbound.report-detail' (-> .index, .dataset, .export, .export-pivot, .row). */
    abstract public function routeBase(): string;

    public function route(string $name, array $params = []): string
    {
        return route($this->routeBase().'.'.$name, $params);
    }

    /** Label breadcrumb di depan judul. */
    public function section(): string
    {
        return 'Laporan';
    }

    /** false = laporan snapshot (mis. stok saat ini), tanpa filter periode. */
    public function hasPeriod(): bool
    {
        return true;
    }

    /** Ekspresi SQL yang dicari kotak "Cari" (kosong = tanpa kotak cari). */
    public function searchColumns(): array
    {
        return [];
    }

    public function searchPlaceholder(): string
    {
        return 'Cari...';
    }

    /** Ekspresi SQL id baris; bila terisi, baris tabel bisa diklik -> rowDetail() di modal. */
    public function rowKey(): ?string
    {
        return null;
    }

    /** HTML detail satu baris (dipanggil bila rowKey() terisi). */
    public function rowDetail(int $id): string
    {
        return '';
    }

    /** Filter dari request; periode default 30 hari terakhir. */
    public function filtersFrom(Request $request): array
    {
        $f = $this->hasPeriod() ? [
            'from' => $request->input('from', now()->subDays(29)->format('Y-m-d')),
            'to' => $request->input('to', now()->format('Y-m-d')),
        ] : [];
        foreach (array_keys($this->filters()) as $name) {
            $f[$name] = $request->input($name);
        }
        $f['q'] = $this->searchColumns() ? trim((string) $request->input('q')) : '';

        return $f;
    }

    /** base() + kotak cari. Dipakai tabel, dataset, export & KPI. */
    protected function filtered(array $f): Builder
    {
        $q = $this->base($f);
        if (($f['q'] ?? '') !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $f['q']).'%';
            $q->where(function ($w) use ($like) {
                foreach ($this->searchColumns() as $col) {
                    $w->orWhereRaw($col.' LIKE ?', [$like]);
                }
            });
        }

        return $q;
    }

    /** Query lengkap dengan kolom terpilih (alias = key kolom). */
    public function query(array $f, ?array $keys = null): Builder
    {
        $cols = $this->columns();
        $keys = $keys ? array_values(array_intersect($keys, array_keys($cols))) : array_keys($cols);

        $q = $this->filtered($f);
        foreach ($keys as $k) {
            $q->selectRaw($cols[$k]['sql'].' as `'.$k.'`');
        }

        return $q;
    }

    /** Ubah nilai mentah sesuai 'map' kolom (mis. PURCHASE -> Pembelian). */
    public function present(object|array $row, array $keys): array
    {
        $cols = $this->columns();
        $row = (array) $row;
        $out = [];
        foreach ($keys as $k) {
            $v = $row[$k] ?? null;
            $out[$k] = isset($cols[$k]['map']) && $v !== null ? ($cols[$k]['map'])($v) : $v;
        }

        return $out;
    }

    public function defaultColumns(): array
    {
        return array_keys(array_filter($this->columns(), fn ($c) => $c['default'] ?? false));
    }

    /** Kolom untuk JS (tanpa sql/map). */
    public function columnsForClient(): array
    {
        return collect($this->columns())->map(fn ($c, $k) => [
            'key' => $k, 'label' => $c['label'], 'type' => $c['type'],
            'dim' => (bool) ($c['dim'] ?? false), 'measure' => (bool) ($c['measure'] ?? false),
            'agg' => $c['agg'] ?? 'sum', 'default' => (bool) ($c['default'] ?? false),
        ])->values()->all();
    }

    /** Teks filter aktif untuk judul export. */
    public function filterSummary(array $f): string
    {
        $parts = $this->hasPeriod()
            ? [$this->periodLabel().': '.date('d/m/Y', strtotime($f['from'])).' - '.date('d/m/Y', strtotime($f['to']))]
            : ['Posisi per '.now()->format('d/m/Y H:i')];
        if (($f['q'] ?? '') !== '') {
            $parts[] = 'Cari: '.$f['q'];
        }
        foreach ($this->filters() as $name => [$label, $options]) {
            if (($f[$name] ?? '') !== '' && $f[$name] !== null) {
                $parts[] = $label.': '.($options[$f[$name]] ?? $f[$name]);
            }
        }

        return implode(' · ', $parts);
    }
}
