<?php

namespace App\Support;

use App\Enums\RotationRule;
use App\Enums\TemperatureClass;

/**
 * Tipe gudang yang dipilih saat registrasi site. Menentukan master data awal (dummy) yang dibuat
 * TenantMasterSeeder: zona & suhu, jenis lokasi, aturan rotasi item, lot/expired, jumlah owner, dsb.
 * Disimpan juga di master_warehouse.profile (JSON) untuk setup wizard / recommendation engine nanti.
 */
enum WarehouseType: string
{
    case GENERAL = 'GENERAL';
    case FMCG = 'FMCG';
    case COLD = 'COLD';
    case PHARMA = 'PHARMA';
    case ECOMMERCE = 'ECOMMERCE';
    case THREEPL = 'THREEPL';

    public function label(): string
    {
        return match ($this) {
            self::GENERAL => 'Gudang Umum / Distribusi',
            self::FMCG => 'Makanan & Minuman (FMCG)',
            self::COLD => 'Cold Storage (Chiller / Frozen)',
            self::PHARMA => 'Farmasi / Alat Kesehatan',
            self::ECOMMERCE => 'E-commerce / Fulfillment',
            self::THREEPL => '3PL (banyak pemilik barang)',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::GENERAL => 'building',
            self::FMCG => 'basket',
            self::COLD => 'snow',
            self::PHARMA => 'capsule',
            self::ECOMMERCE => 'bag-check',
            self::THREEPL => 'people',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::GENERAL => 'Rak pallet ambient, rotasi FIFO, tanpa lot/expired. Cocok untuk spare part, bahan bangunan, distribusi umum.',
            self::FMCG => 'Zona ambient + chiller, item ber-lot & expired, rotasi FEFO, minimum sisa umur kirim.',
            self::COLD => 'Zona chiller & frozen, semua item ber-expired, FEFO, kapasitas rak per pallet.',
            self::PHARMA => 'Suhu terkendali, lot & expired wajib, area QC/karantina, FEFO ketat, sisa umur minimum tinggi.',
            self::ECOMMERCE => 'Banyak pick face (shelving) untuk order kecil, rak reserve di belakang, FIFO.',
            self::THREEPL => 'Beberapa owner (klien) dalam satu gudang, zona terpisah per owner, supplier/customer per owner.',
        };
    }

    /** Profil gudang (disimpan di master_warehouse.profile). */
    public function profile(): array
    {
        return match ($this) {
            self::GENERAL => ['type' => $this->value, 'lot' => false, 'expiry' => false, 'rotation' => 'FIFO', 'temps' => ['AMBIENT'], 'pick_face' => true, 'owners' => 1, 'qc' => false],
            self::FMCG => ['type' => $this->value, 'lot' => true, 'expiry' => true, 'rotation' => 'FEFO', 'temps' => ['AMBIENT', 'CHILLED'], 'pick_face' => true, 'owners' => 1, 'qc' => false],
            self::COLD => ['type' => $this->value, 'lot' => true, 'expiry' => true, 'rotation' => 'FEFO', 'temps' => ['CHILLED', 'FROZEN'], 'pick_face' => false, 'owners' => 1, 'qc' => false],
            self::PHARMA => ['type' => $this->value, 'lot' => true, 'expiry' => true, 'rotation' => 'FEFO', 'temps' => ['AIR_CONDITIONED', 'CHILLED'], 'pick_face' => true, 'owners' => 1, 'qc' => true],
            self::ECOMMERCE => ['type' => $this->value, 'lot' => false, 'expiry' => false, 'rotation' => 'FIFO', 'temps' => ['AMBIENT'], 'pick_face' => true, 'owners' => 1, 'qc' => false],
            self::THREEPL => ['type' => $this->value, 'lot' => true, 'expiry' => false, 'rotation' => 'FIFO', 'temps' => ['AMBIENT'], 'pick_face' => true, 'owners' => 3, 'qc' => false],
        };
    }

    public function rotation(): RotationRule
    {
        return RotationRule::from($this->profile()['rotation']);
    }

    /** @return list<TemperatureClass> */
    public function temperatures(): array
    {
        return array_map(fn ($t) => TemperatureClass::from($t), $this->profile()['temps']);
    }
}
