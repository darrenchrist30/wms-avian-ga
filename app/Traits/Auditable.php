<?php

namespace App\Traits;

use App\Models\AuditLog;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            AuditLog::record('created', $model, null, $model->getAuditAttributes());
        });

        static::updated(function ($model) {
            $changed = $model->getChangedAuditAttributes();
            if (!empty($changed)) {
                AuditLog::record('updated', $model, $model->getOriginalAuditAttributes(), $changed);
            }
        });

        static::deleted(function ($model) {
            AuditLog::record('deleted', $model, $model->getAuditAttributes(), null);
        });
    }

    public function getAuditLabel(): string
    {
        return $this->name ?? $this->code ?? $this->sku ?? $this->do_number ?? '#' . $this->getKey();
    }

    protected function getAuditAttributes(): array
    {
        $hidden = array_merge($this->hidden ?? [], ['password', 'remember_token']);
        return collect($this->getAttributes())->except($hidden)->toArray();
    }

    protected function getOriginalAuditAttributes(): array
    {
        $hidden = array_merge($this->hidden ?? [], ['password', 'remember_token']);
        return collect($this->getOriginal())->except($hidden)->toArray();
    }

    protected function getChangedAuditAttributes(): array
    {
        $hidden = array_merge($this->hidden ?? [], ['password', 'remember_token']);
        return collect($this->getDirty())->except($hidden)->toArray();
    }
}
