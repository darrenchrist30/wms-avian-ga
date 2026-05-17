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
        if ($this->blok !== null && $this->grup !== null && $this->kolom !== null && $this->baris !== null) {
            return sprintf(
                '%s-%s-%s-%s',
                $this->blok,
                strtoupper((string) $this->grup),
                $this->kolom,
                $this->baris
            );
        }

        return (string) $this->code;
    }

    public function getPhysicalLabelAttribute(): string
    {
        if ($this->blok !== null && $this->grup !== null && $this->kolom !== null && $this->baris !== null) {
            $grup = strtoupper((string) $this->grup);

            return "Blok {$this->blok} - Grup {$grup} - Kolom {$this->kolom} - Baris {$this->baris}";
        }

        return (string) ($this->label ?: $this->code);
    }

    public function getPhysicalCapacityMaxAttribute(): int
    {
        return (int) $this->capacity_max;
    }

    public function getPhysicalCapacityUsedAttribute(): int
    {
        if ($this->blok !== null && $this->grup !== null && $this->kolom !== null && $this->baris !== null) {
            return Stock::where('cell_id', $this->id)
                ->where('quantity', '>', 0)
                ->whereIn('status', ['available', 'reserved'])
                ->count();
        }

        return (int) $this->capacity_used;
    }

    public function getPhysicalCapacityRemainingAttribute(): int
    {
        return max(0, $this->physical_capacity_max - $this->physical_capacity_used);
    }

    // Persentase utilisasi
    public function getUtilizationPercentAttribute(): float
    {
        if ($this->capacity_max === 0) return 0;

        $used = $this->isMspartCell()
            ? $this->physical_capacity_used
            : $this->capacity_used;

        return round($used / $this->capacity_max * 100, 1);
    }

    // Otomatis update status berdasarkan kapasitas
    public function updateStatus(): void
    {
        if ($this->status === 'blocked' || $this->status === 'reserved') return;

        $used = $this->isMspartCell()
            ? $this->physical_capacity_used
            : (int) $this->capacity_used;

        if ($this->isMspartCell() && (int) $this->capacity_used !== $used) {
            $this->capacity_used = $used;
        }

        if ($used === 0) {
            $this->status = 'available';
        } elseif ($used >= (int) $this->capacity_max) {
            $this->status = 'full';
        } else {
            $this->status = 'partial';
        }
        $this->save();
    }

    private function isMspartCell(): bool
    {
        return $this->blok !== null
            && $this->grup !== null
            && $this->kolom !== null
            && $this->baris !== null;
    }
}
