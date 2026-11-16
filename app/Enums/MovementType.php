<?php

namespace App\Enums;

/**
 * Jenis baris ledger inventory_movements. Satu baris movement selalu mengenai
 * satu baris inventory; transaksi yang memindahkan stok (putaway, move, pick)
 * menulis pasangan *_OUT dan *_IN.
 */
enum MovementType: string
{
    case RECEIVE = 'RECEIVE';           // on_hand + di lokasi receiving
    case RECEIVE_CANCEL = 'RECEIVE_CANCEL'; // pembatalan baris terima (on_hand -)
    case PUTAWAY_OUT = 'PUTAWAY_OUT';   // on_hand - dari staging
    case PUTAWAY_IN = 'PUTAWAY_IN';     // on_hand + di lokasi tujuan
    case MOVE_OUT = 'MOVE_OUT';
    case MOVE_IN = 'MOVE_IN';
    case REPLENISH_OUT = 'REPLENISH_OUT';
    case REPLENISH_IN = 'REPLENISH_IN';
    case ALLOCATE = 'ALLOCATE';         // allocated +
    case DEALLOCATE = 'DEALLOCATE';     // allocated -
    case PICK_OUT = 'PICK_OUT';         // on_hand - & allocated - di lokasi asal
    case PICK_IN = 'PICK_IN';           // on_hand + di staging outbound
    case SHIP = 'SHIP';                 // on_hand - (stok keluar gudang)
    case HOLD = 'HOLD';                 // hold +
    case RELEASE = 'RELEASE';           // hold -
    case STATUS_OUT = 'STATUS_OUT';     // pindah status kualitas (baris lama)
    case STATUS_IN = 'STATUS_IN';       // pindah status kualitas (baris baru)
    case ADJUST_IN = 'ADJUST_IN';       // koreksi plus (stock opname, temuan)
    case ADJUST_OUT = 'ADJUST_OUT';     // koreksi minus

    public function label(): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $this->value)));
    }
}
