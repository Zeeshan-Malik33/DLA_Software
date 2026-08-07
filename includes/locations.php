<?php
// Shared list used by both the Add/Edit Customer forms and the list-page filter.
// Extend freely as your customer base grows.
$COUNTRIES = [
    'Pakistan', 'Afghanistan', 'Australia', 'Norway', 'United States',
    'United Kingdom', 'Canada', 'Germany', 'France', 'United Arab Emirates', 'Other',
];

$CITIES_BY_COUNTRY = [
    'Pakistan'              => ['Lahore', 'Karachi', 'Islamabad', 'Peshawar', 'Rawalpindi'],
    'Afghanistan'           => ['Kabul', 'Herat', 'Kandahar', 'Mazar-i-Sharif'],
    'Australia'             => ['Sydney', 'Melbourne', 'Perth', 'Brisbane'],
    'Norway'                => ['Oslo', 'Bergen', 'Trondheim'],
    'United States'         => ['New York', 'Los Angeles', 'Chicago', 'Houston'],
    'United Kingdom'        => ['London', 'Manchester', 'Birmingham'],
    'Canada'                => ['Toronto', 'Vancouver', 'Montreal'],
    'Germany'               => ['Berlin', 'Munich', 'Hamburg'],
    'France'                => ['Paris', 'Lyon', 'Marseille'],
    'United Arab Emirates'  => ['Dubai', 'Abu Dhabi', 'Sharjah'],
];
