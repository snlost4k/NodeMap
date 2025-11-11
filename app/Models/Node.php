<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Node extends Model
{
    protected $table = 'nodes';
    protected $primaryKey = 'id';
    public $incrementing = false;     // id comes from file, not auto-increment
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = ['id','node_id','name','address','continent'];

    public function vendors()
    {
        // vendors(vendor_name PK) <-> node_vendors(id, vendor_name)
        return $this->belongsToMany(Vendor::class, 'node_vendors', 'id', 'vendor_name', 'id', 'vendor_name');
    }

    public function interfaces()
    {
        // interfaces(interface_name PK) <-> node_interfaces(id, interface_name)
        return $this->belongsToMany(InterfaceType::class, 'node_interfaces', 'id', 'interface_name', 'id', 'interface_name');
    }

    // optional: lat/lng from an auxiliary table (see Section 4)
    public function geo()
    {
        return $this->hasOne(\App\Models\NodeGeo::class, 'id', 'id');
    }
}
