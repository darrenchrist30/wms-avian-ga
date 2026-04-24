<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = [
        'code',
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'erp_vendor_id',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function inboundOrders()
    {
        return $this->hasMany(InboundOrder::class);
    }
}
