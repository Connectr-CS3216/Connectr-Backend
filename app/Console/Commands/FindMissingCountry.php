<?php

namespace App\Console\Commands;

use App\Models\Place;
use Illuminate\Console\Command;

class FindMissingCountry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'place:missingcountry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export Places without country to a json file';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $mapboxSecret = env('MAPBOX_API_SECRET', '');
        $places = Place::where('country', '')->get();
        foreach ($places as $place) {
            try {
                $data = $this->getGeoLocation($place->lat, $place->long, $mapboxSecret);
                $country = $data['features'][0]['place_name'];
                $place->country = $country;
                $place->save();
            } catch (\Exception $e) {
                error_log($e);
            }
        }
    }

    private function getGeoLocation($lat, $long, $secret)
    {
        $service_url = "https://api.mapbox.com/geocoding/v5/mapbox.places/$long,$lat.json?types=country&access_token=$secret";
        $curl = curl_init($service_url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        $curl_response = curl_exec($curl);
        curl_close($curl);
        return json_decode($curl_response, true);
    }
}
