<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NodeGeo extends Model
{
    protected $table = 'node_geos';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $fillable = ['id','lat','lng'];
    public $timestamps = false;
    protected $casts = ['lat' => 'float', 'lng' => 'float'];
}
