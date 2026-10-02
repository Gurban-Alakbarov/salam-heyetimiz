<?php

/*
| Registration applications (IMPLEMENTATION_PLAN B9). Location is a latitude/longitude pair picked on an
| OpenStreetMap map in the app — no map provider is called server-side. The service-area box rejects
| obviously wrong pins (e.g. 0,0); default = Azerbaijan.
*/
return [
    'service_area' => [
        'lat_min' => (float) env('APPLICATIONS_LAT_MIN', 38.3),
        'lat_max' => (float) env('APPLICATIONS_LAT_MAX', 41.95),
        'lng_min' => (float) env('APPLICATIONS_LNG_MIN', 44.7),
        'lng_max' => (float) env('APPLICATIONS_LNG_MAX', 50.95),
    ],
];
