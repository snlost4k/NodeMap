<?php

namespace App\Http\Controllers;

use App\Models\Node;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NodeApiController extends Controller
{
    public function index(Request $req)
    {
        // Filters: q (free text), country, city, continent, vendor, interface
        // We only have a single "address" column, so we parse via LIKEs.
        $q         = trim((string) $req->query('q', ''));
        $continent = trim((string) $req->query('continent', ''));
        $vendor    = trim((string) $req->query('vendor', ''));
        $iface     = trim((string) $req->query('interface', ''));
        $limit     = min((int)$req->query('limit', 5000), 10000);

        $query = Node::query()
            ->select('nodes.id', 'nodes.node_id', 'nodes.name', 'nodes.address', 'nodes.continent')
            ->with(['vendors:vendor_name', 'interfaces:interface_name', 'geo:id,lat,lng']);

        if ($q !== '') {
            // free-text against address or name
            $query->where(function($w) use ($q) {
                $w->where('address', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%");
            });
        }

        if ($continent !== '') {
            $query->where('continent', $continent);
        }

        if ($vendor !== '') {
            $query->whereExists(function($s) use ($vendor) {
                $s->select(DB::raw(1))
                  ->from('node_vendors as nv')
                  ->whereColumn('nv.id', 'nodes.id')
                  ->where('nv.vendor_name', $vendor);
            });
        }

        if ($iface !== '') {
            $query->whereExists(function($s) use ($iface) {
                $s->select(DB::raw(1))
                  ->from('node_interfaces as ni')
                  ->whereColumn('ni.id', 'nodes.id')
                  ->where('ni.interface_name', $iface);
            });
        }

        // return compact marker payload
        $nodes = $query->limit($limit)->get()->map(function($n) {
            return [
                'id'        => $n->id,
                'node_id'   => $n->node_id,
                'name'      => $n->name,
                'address'   => $n->address,
                'continent' => $n->continent,
                'vendors'   => $n->vendors->pluck('vendor_name')->values(),
                'interfaces'=> $n->interfaces->pluck('interface_name')->values(),
                'lat'       => optional($n->geo) ? (float) $n->geo->lat : null, // <-- cast
                'lng'       => optional($n->geo) ? (float) $n->geo->lng : null, // <-- cast
            ];
        });

        return response()->json(['data' => $nodes]);
    }

    public function show($id)
    {
        $n = Node::with(['vendors:vendor_name', 'interfaces:interface_name', 'geo:id,lat,lng'])->findOrFail((int)$id);

        return response()->json([
            'id'        => $n->id,
            'node_id'   => $n->node_id,
            'name'      => $n->name,
            'address'   => $n->address,
            'continent' => $n->continent,
            'vendors'   => $n->vendors->pluck('vendor_name')->values(),
            'interfaces'=> $n->interfaces->pluck('interface_name')->values(),
            'lat'       => optional($n->geo) ? (float) $n->geo->lat : null,
            'lng'       => optional($n->geo) ? (float) $n->geo->lng : null,
        ]);
    }
}
