<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbound:
 *   o_wave_header            : wave = kumpulan shipment yang dialokasikan & dipick bersama (GO = 1 shipment, PLAN = banyak)
 *   o_delivery_order_header  : DO / Surat Jalan = beberapa shipment yang berangkat dalam satu kendaraan
 *   o_shipment_header/detail : dokumen pesanan keluar (shipment entry)
 *   o_allocation             : hasil alokasi per baris stok (= baris pick list), status OPEN -> PICKED / SHORT / CANCELLED
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('o_wave_header', function (Blueprint $table) {
            $table->id();
            $table->string('wave_no', 30)->unique();
            $table->foreignId('warehouse_id')->constrained('master_warehouse')->restrictOnDelete();
            $table->string('wave_type', 10)->default('PLAN');       // GO (1 shipment, dari shipment entry) | PLAN (wave planning)
            $table->string('allocation_rule', 10)->nullable();      // App\Enums\RotationRule; null = ikut shipment / item
            $table->string('status', 20)->default('PLANNED');       // App\Enums\WaveStatus
            $table->foreignId('staging_location_id')->nullable()->constrained('master_location')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('allocated_at')->nullable();
            $table->timestamp('picking_started_at')->nullable();
            $table->timestamp('picked_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['warehouse_id', 'status']);
        });

        Schema::create('o_delivery_order_header', function (Blueprint $table) {
            $table->id();
            $table->string('do_no', 30)->unique();
            $table->foreignId('warehouse_id')->constrained('master_warehouse')->restrictOnDelete();
            $table->string('status', 20)->default('DRAFT');          // App\Enums\DeliveryOrderStatus
            $table->date('ship_date')->nullable();
            $table->string('vehicle_no', 20)->nullable();
            $table->string('driver_name', 100)->nullable();
            $table->string('driver_phone', 30)->nullable();
            $table->string('carrier', 100)->nullable();              // ekspedisi / armada sendiri
            $table->text('notes')->nullable();
            $table->unsignedInteger('print_count')->default(0);
            $table->timestamp('last_printed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['warehouse_id', 'status']);
        });

        Schema::create('o_shipment_header', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_no', 30)->unique();
            $table->foreignId('warehouse_id')->constrained('master_warehouse')->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('master_owner')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('master_customer')->restrictOnDelete();
            $table->foreignId('customer_address_id')->nullable()->constrained('master_customer_address')->nullOnDelete();
            // snapshot alamat kirim (master bisa berubah, dokumen tidak)
            $table->string('ship_to_name', 150)->nullable();
            $table->text('ship_to_address')->nullable();
            $table->string('reference_no', 50)->nullable();          // no. SO / PO customer
            $table->date('order_date')->nullable();
            $table->date('expected_ship_date')->nullable();
            $table->string('allocation_rule', 10)->nullable();       // App\Enums\RotationRule; null = ikut item
            $table->unsignedTinyInteger('priority')->default(5);     // 1 = paling penting
            $table->string('status', 20)->default('DRAFT');          // App\Enums\ShipmentStatus
            $table->foreignId('wave_id')->nullable()->constrained('o_wave_header')->nullOnDelete();
            $table->foreignId('delivery_order_id')->nullable()->constrained('o_delivery_order_header')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['warehouse_id', 'status']);
            $table->index(['owner_id', 'status']);
            $table->index('reference_no');
            $table->index('expected_ship_date');
        });

        Schema::create('o_shipment_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_header_id')->constrained('o_shipment_header')->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->foreignId('item_id')->constrained('master_item')->restrictOnDelete();
            $table->foreignId('uom_id')->constrained('master_uom')->restrictOnDelete();
            $table->decimal('qty_ordered', 18, 4);
            $table->decimal('conversion_factor', 18, 6);
            $table->decimal('qty_ordered_base', 18, 4);
            $table->decimal('qty_allocated_base', 18, 4)->default(0);
            $table->decimal('qty_picked_base', 18, 4)->default(0);
            $table->decimal('qty_shipped_base', 18, 4)->default(0);
            $table->string('lot_no', 50)->nullable();                // batasan: hanya ambil lot ini (opsional)
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['shipment_header_id', 'line_no']);
            $table->index('item_id');
        });

        Schema::create('o_allocation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wave_id')->constrained('o_wave_header')->restrictOnDelete();
            $table->foreignId('shipment_header_id')->constrained('o_shipment_header')->restrictOnDelete();
            $table->foreignId('shipment_detail_id')->constrained('o_shipment_detail')->restrictOnDelete();
            $table->foreignId('inventory_id')->constrained('o_inventory')->restrictOnDelete(); // baris stok sumber
            $table->foreignId('location_id')->constrained('master_location')->restrictOnDelete();
            $table->foreignId('lpn_id')->nullable()->constrained('master_lpn')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('master_item')->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('master_owner')->restrictOnDelete();
            $table->string('lot_no', 50)->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('qty_allocated_base', 18, 4);
            $table->decimal('qty_picked_base', 18, 4)->default(0);
            $table->string('status', 20)->default('OPEN');           // App\Enums\AllocationStatus
            $table->string('rule', 10)->nullable();                  // aturan yang dipakai saat alokasi (FIFO/FEFO/...)
            $table->foreignId('staging_location_id')->nullable()->constrained('master_location')->nullOnDelete();
            $table->foreignId('picked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('picked_at')->nullable();
            $table->string('pick_source', 10)->nullable();           // DESKTOP | MOBILE
            $table->string('short_reason')->nullable();
            $table->timestamps();
            $table->index(['wave_id', 'status']);
            $table->index(['shipment_header_id', 'status']);
            $table->index('inventory_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('o_allocation');
        Schema::dropIfExists('o_shipment_detail');
        Schema::dropIfExists('o_shipment_header');
        Schema::dropIfExists('o_delivery_order_header');
        Schema::dropIfExists('o_wave_header');
    }
};
