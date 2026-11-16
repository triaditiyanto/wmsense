<?php

namespace App\Models;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Task gudang (o_task_header), mis. putaway satu LPN. Lihat migration create_o_task_tables. */
class TaskHeader extends Model
{
    public const SOURCE_DESKTOP = 'DESKTOP';
    public const SOURCE_MOBILE = 'MOBILE';

    protected $table = 'o_task_header';

    protected $fillable = [
        'task_no', 'task_type', 'status', 'warehouse_id', 'lpn_id', 'from_location_id', 'suggested_location_id',
        'suggest_reason', 'to_location_id', 'source', 'created_by', 'completed_by', 'completed_at',
        'cancelled_by', 'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'task_type' => TaskType::class,
            'status' => TaskStatus::class,
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', TaskStatus::IN_PROGRESS);
    }

    public function details(): HasMany
    {
        return $this->hasMany(TaskDetail::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lpn(): BelongsTo
    {
        return $this->belongsTo(Lpn::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function suggestedLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'suggested_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
