<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use Auditable;
    protected $fillable = ['name', 'slug', 'description'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function hasPermission(string $slug): bool
    {
        return $this->permissions->contains('slug', $slug);
    }
}
