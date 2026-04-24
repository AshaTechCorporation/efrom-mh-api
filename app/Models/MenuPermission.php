<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuPermission extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'menu_permissions';

    protected $fillable = [
        'permission_id',
        'menu_id',
        'create',
        'view_own',
        'view_all',
        'edit_own',
        'edit_all',
        'delete_own',
        'delete_all',
        'view',
        'edit',
        'save',
        'delete',
        'create_by',
        'update_by',
    ];

    protected $casts = [
        'permission_id' => 'integer',
        'menu_id' => 'integer',
        'create' => 'integer',
        'view_own' => 'integer',
        'view_all' => 'integer',
        'edit_own' => 'integer',
        'edit_all' => 'integer',
        'delete_own' => 'integer',
        'delete_all' => 'integer',
        'view' => 'integer',
        'edit' => 'integer',
        'save' => 'integer',
        'delete' => 'integer',
    ];

    public function permission()
    {
        return $this->belongsTo(Permission::class);
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }
}
