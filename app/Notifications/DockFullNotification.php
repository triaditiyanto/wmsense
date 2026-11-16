<?php

namespace App\Notifications;

use App\Models\ReceiptHeader;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Semua lokasi receiving (dock) di gudang penuh -> minta admin cek storage / jalankan putaway.
 * Disimpan di tabel `notifications` (channel database), tampil di lonceng navbar.
 */
class DockFullNotification extends Notification
{
    /** @param list<array> $docks status kapasitas dock saat notifikasi dikirim */
    public function __construct(
        private ReceiptHeader $receipt,
        private User $sender,
        private array $docks,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $wh = $this->receipt->warehouse;

        return [
            'type' => 'dock_full',
            'level' => 'danger',
            'icon' => 'exclamation-triangle-fill',
            'title' => "Semua dock penuh di {$wh->code}",
            'message' => "{$this->sender->name} tidak bisa menaruh pallet baru untuk {$this->receipt->receipt_no}. "
                .'Mohon cek storage / percepat putaway. Dock: '.collect($this->docks)->pluck('label')->join(', '),
            'url' => route('master.locations.index', ['warehouse' => $wh->id]),
            'receipt_no' => $this->receipt->receipt_no,
            'sender' => $this->sender->name,
        ];
    }
}
