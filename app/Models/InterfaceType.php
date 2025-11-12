<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterfaceType extends Model
{
    protected $table = 'interfaces';
    protected $primaryKey = 'interface_name';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['interface_name'];
}
