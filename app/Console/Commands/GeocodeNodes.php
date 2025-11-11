<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\NodeGeo;

class GeocodeNodes extends Command
{
    protected $signature = 'nodes:geocode {--limit=2500}';
    protected $description = 'Geocode node addresses using OpenCage API and store lat/lng in node_geos table';

    public function handle()
    {
        $apiKey = env('OPENCAGE_KEY');
        if (!$apiKey) {
            $this->error('Missing OPENCAGE_KEY in .env');
            return 1;
        }

        $limit = (int)$this->option('limit');
        $existing = NodeGeo::pluck('id')->all();

        $nodes = DB::table('nodes')
            ->select('id','address')
            ->whereNotNull('address')
            ->whereNotIn('id', $existing)
            ->limit($limit)
            ->get();

        if ($nodes->isEmpty()) {
            $this->info('No nodes left to geocode.');
            return 0;
        }

        $this->info("Geocoding {$nodes->count()} addresses via OpenCage…");

        $count = 0;
        foreach ($nodes as $n) {
            $query = urlencode($n->address);
            $url = "https://api.opencagedata.com/geocode/v1/json?q={$query}&key={$apiKey}&limit=1";
            $res = Http::timeout(20)->get($url);

            if (!$res->successful()) {
                $this->warn("{$n->id}: failed ({$res->status()})");
                sleep(1);
                continue;
            }

            $body = $res->json();
            $lat = $body['results'][0]['geometry']['lat'] ?? null;
            $lng = $body['results'][0]['geometry']['lng'] ?? null;

            if ($lat && $lng) {
                NodeGeo::updateOrCreate(['id' => $n->id], ['lat' => $lat, 'lng' => $lng]);
                $this->line("✓ {$n->id} geocoded ({$lat}, {$lng})");
            } else {
                $this->warn("{$n->id}: no result");
            }

            $count++;
            sleep(1); // obey 1 req/sec free-tier limit
        }

        $this->info("Done. Geocoded {$count} new nodes.");
        return 0;
    }
}
