<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Owner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Customer (header) + alamat kirim (detail). */
class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = Customer::query()
            ->with('owner')
            ->withCount('addresses')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', '%'.$request->q.'%')
                ->orWhere('name', 'like', '%'.$request->q.'%')
                ->orWhere('city', 'like', '%'.$request->q.'%')))
            ->when($request->input('owner') === 'shared', fn ($q) => $q->whereNull('owner_id'))
            ->when(ctype_digit((string) $request->input('owner')), fn ($q) => $q->where('owner_id', $request->integer('owner')))
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('code')
            ->paginate(30)->withQueryString();

        return view('master.customers.index', [
            'customers' => $customers,
            'owners' => Owner::where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $customer = Customer::create($this->validated($request));

        return $this->ok("Customer {$customer->name} dibuat. Silakan tambahkan alamat kirim.", route('master.customers.show', $customer));
    }

    public function show(Customer $customer): View
    {
        $customer->load(['owner', 'addresses']);

        return view('master.customers.show', [
            'customer' => $customer,
            'owners' => Owner::where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $customer->update($this->validated($request, $customer));

        return $this->ok("Customer {$customer->name} diperbarui.");
    }

    // ---------------------------------------------------------------- alamat kirim (detail)

    public function storeAddress(Request $request, Customer $customer): JsonResponse
    {
        DB::transaction(function () use ($request, $customer) {
            $data = $this->validatedAddress($request);
            // alamat pertama otomatis jadi default
            $data['is_default'] = $data['is_default'] || ! $customer->addresses()->exists();
            $address = $customer->addresses()->create($data);
            $this->keepSingleDefault($customer, $address);
        });

        return $this->ok('Alamat kirim ditambahkan.');
    }

    public function updateAddress(Request $request, Customer $customer, CustomerAddress $address): JsonResponse
    {
        DB::transaction(function () use ($request, $customer, $address) {
            $address->update($this->validatedAddress($request));
            $this->keepSingleDefault($customer, $address);
        });

        return $this->ok('Alamat kirim diperbarui.');
    }

    public function destroyAddress(Customer $customer, CustomerAddress $address): JsonResponse
    {
        // Catatan: setelah modul outbound ada, alamat yang sudah dipakai order cukup dinonaktifkan.
        DB::transaction(function () use ($customer, $address) {
            $wasDefault = $address->is_default;
            $address->delete();
            if ($wasDefault) {
                $customer->addresses()->where('is_active', true)->first()?->update(['is_default' => true]);
            }
        });

        return $this->ok('Alamat kirim dihapus.');
    }

    /** Hanya satu alamat default per customer. */
    private function keepSingleDefault(Customer $customer, CustomerAddress $address): void
    {
        if ($address->is_default) {
            $customer->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
        }
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('master_customer', 'code')->ignore($customer)],
            'name' => ['required', 'string', 'max:255'],
            'owner_id' => ['nullable', 'exists:master_owner,id'],
            'tax_id' => ['nullable', 'string', 'max:30'],
            'pic' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ], [], ['tax_id' => 'NPWP', 'pic' => 'PIC', 'phone' => 'telepon', 'city' => 'kota']);
        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function validatedAddress(Request $request): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'pic' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ], [], ['label' => 'label', 'postal_code' => 'kode pos', 'pic' => 'PIC', 'phone' => 'telepon', 'city' => 'kota']);
        $data['is_default'] = $request->boolean('is_default');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
