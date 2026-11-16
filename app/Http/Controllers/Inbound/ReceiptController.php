<?php

namespace App\Http\Controllers\Inbound;

use App\Enums\ReceiptStatus;
use App\Enums\ReceiptType;
use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Models\ReceiptHeader;
use App\Models\Warehouse;
use App\Services\ReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Header dokumen receipt. Detail ada di ReceiptDetailController. */
class ReceiptController extends Controller
{
    public function __construct(private ReceiptService $service)
    {
    }

    public function index(Request $request): View
    {
        $receipts = ReceiptHeader::query()
            ->with(['warehouse', 'owner', 'supplier', 'customer'])
            ->withCount('details')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = $request->input('q');
                $query->where(fn ($w) => $w->where('receipt_no', 'like', "%{$q}%")
                    ->orWhere('reference_no', 'like', "%{$q}%")
                    // whereHas = cari berdasarkan kolom di tabel relasi (subquery EXISTS)
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%")));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouse_id', $request->integer('warehouse_id')))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('inbound.receipts.index', [
            'receipts' => $receipts,
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $receipt = $this->service->createHeader($this->validated($request), $request->user());

        // Setelah header dibuat, langsung buka halaman dokumen untuk input detail
        return $this->ok(
            "Receipt {$receipt->receipt_no} dibuat. Silakan tambahkan item.",
            route('inbound.receipts.show', $receipt),
        );
    }

    public function show(ReceiptHeader $receipt): View
    {
        $receipt->load(['warehouse', 'owner', 'supplier', 'customer', 'createdBy', 'confirmedBy', 'cancelledBy', 'details.item', 'details.uom']);

        return view('inbound.receipts.show', [
            'receipt' => $receipt,
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, ReceiptHeader $receipt): JsonResponse
    {
        $this->service->updateHeader($receipt, $this->validated($request), $request->user());

        return $this->ok('Header dokumen berhasil diperbarui.');
    }

    public function confirm(Request $request, ReceiptHeader $receipt): JsonResponse
    {
        $this->service->confirm($receipt, $request->user());

        return $this->ok("Receipt {$receipt->receipt_no} dikonfirmasi dan siap diterima di gudang.");
    }

    public function cancel(Request $request, ReceiptHeader $receipt): JsonResponse
    {
        $this->service->cancel($receipt, $request->user());

        return $this->ok("Receipt {$receipt->receipt_no} dibatalkan.");
    }

    private function validated(Request $request): array
    {
        $ownerId = $request->integer('owner_id');
        // Supplier/customer harus aktif DAN milik owner dokumen (atau milik bersama / owner_id null)
        $partnerOf = fn (string $table) => Rule::exists($table, 'id')->where(fn ($q) => $q
            ->where('is_active', true)
            ->where(fn ($w) => $w->whereNull('owner_id')->orWhere('owner_id', $ownerId)));

        $data = $request->validate([
            'warehouse_id' => ['required', Rule::exists('master_warehouse', 'id')->where('is_active', true)],
            'owner_id' => ['required', Rule::exists('master_owner', 'id')->where('is_active', true)],
            'receipt_type' => ['required', Rule::enum(ReceiptType::class)],
            'reference_no' => ['nullable', 'string', 'max:50'],
            // Pembelian wajib supplier, retur customer wajib customer
            'supplier_id' => ['nullable', 'required_if:receipt_type,PURCHASE', $partnerOf('master_supplier')],
            'customer_id' => ['nullable', 'required_if:receipt_type,RETURN', $partnerOf('master_customer')],
            'expected_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ], [
            'supplier_id.required_if' => 'Supplier wajib dipilih untuk receipt pembelian.',
            'customer_id.required_if' => 'Customer wajib dipilih untuk retur customer.',
            'supplier_id.exists' => 'Supplier tidak aktif atau bukan milik owner ini.',
            'customer_id.exists' => 'Customer tidak aktif atau bukan milik owner ini.',
        ], [
            'receipt_type' => 'tipe receipt',
            'reference_no' => 'no. referensi',
            'supplier_id' => 'supplier',
            'customer_id' => 'customer',
            'expected_date' => 'tanggal kedatangan',
        ]);

        // Simpan hanya pengirim yang sesuai tipe
        if (ReceiptType::from($data['receipt_type'])->usesCustomer()) {
            $data['supplier_id'] = null;
        } else {
            $data['customer_id'] = null;
        }

        return $data;
    }

    private function formOptions(): array
    {
        return [
            'warehouses' => Warehouse::where('is_active', true)->orderBy('code')->get(),
            'owners' => Owner::where('is_active', true)->orderBy('code')->get(),
            'types' => ReceiptType::cases(),
            'statuses' => ReceiptStatus::cases(),
        ];
    }
}
