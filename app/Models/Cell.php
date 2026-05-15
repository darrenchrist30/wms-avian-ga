<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cell extends Model
{
    use SoftDeletes, Auditable;

    public function getAuditLabel(): string
    {
        return 'Sel ' . $this->code;
    }

    protected $fillable = [
        'rack_id', 'dominant_category_id', 'code', 'label',
        'level', 'column', 'blok', 'grup', 'kolom', 'baris',
        'capacity_max', 'capacity_used',
        'zone_category', 'qr_code', 'status', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function rack()
    {
        return $this->belongsTo(Rack::class);
    }

    public function dominantCategory()
    {
        return $this->belongsTo(ItemCategory::class, 'dominant_category_id');
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function gaRecommendationDetails()
    {
        return $this->hasMany(GaRecommendationDetail::class);
    }

    public function putAwayConfirmations()
    {
        return $this->hasMany(PutAwayConfirmation::class);
    }

    public function deadstockNotifications()
    {
        return $this->hasMany(DeadstockNotification::class);
    }

    // Sisa kapasitas (dua nama alias agar kompatibel)
    public function getRemainingCapacityAttribute(): int
    {
        return $this->capacity_max - $this->capacity_used;
    }

    public function getCapacityRemainingAttribute(): int
    {
        return $this->capacity_max - $this->capacity_used;
    }

    public function getPhysicalCodeAttribute(): string
    {
        if ($this->blok !== null && $this->grup !== null && $this->kolom !== null) {
            return sprintf('%s-%s-K%s', $this->blok, strtoupper((string) $this->grup), $this->kolom);
        }

        return (string) $this->code;
    }

    public function getPhysicalLabelAttribute(): string
    {
        if ($this->blok !== null && $this->grup !== null && $this->kolom !== null) {
            $grup = strtoupper((string) $this->grup);
            $barisRak = strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ', $grup);
            $barisRak = $barisRak === false ? null : $barisRak + 1;

            return "Blok {$this->blok} - Grup {$grup} - Baris {$barisRak} - Kolom {$this->kolom}";
        }

        return (string) ($this->label ?: $this->code);
    }

    public function getPhysicalCapacityMaxAttribute(): int
    {
        if ($this->blok === null || $this->grup === null || $this->kolom === null) {
            return (int) $this->capacity_max;
        }

        return max(1, (int) static::where('is_active', true)
            ->where('blok', $this->blok)
            ->where('grup', strtoupper((string) $this->grup))
            ->where('kolom', $this->kolom)
            ->max('capacity_max'));
    }

    public function getPhysicalCapacityUsedAttribute(): int
    {
        if ($this->blok === null || $this->grup === null || $this->kolom === null) {
            return (int) $this->capacity_used;
        }

        $cellIds = static::where('is_active', true)
            ->where('blok', $this->blok)
            ->where('grup', strtoupper((string) $this->grup))
            ->where('kolom', $this->kolom)
            ->pluck('id');

        return Stock::whereIn('cell_id', $cellIds)
            ->where('quantity', '>', 0)
            ->whereIn('status', ['available', 'reserved'])
            ->count();
    }

    public function getPhysicalCapacityRemainingAttribute(): int
    {
        return max(0, $this->physical_capacity_max - $this->physical_capacity_used);
    }

    // Persentase utilisasi
    public function getUtilizationPercentAttribute(): float
    {
        if ($this->capacity_max === 0) return 0;
        return round($this->capacity_used / $this->capacity_max * 100, 1);
    }

    // Otomatis update status berdasarkan kapasitas
    public function updateStatus(): void
    {
        if ($this->status === 'blocked' || $this->status === 'reserved') return;

        if ($this->capacity_used === 0) {
            $this->status = 'available';
        } elseif ($this->capacity_used >= $this->capacity_max) {
            $this->status = 'full';
        } else {
            $this->status = 'partial';
        }
        $this->save();
    }
}
