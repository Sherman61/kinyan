<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
require_app_rate_limit('vin_lookup', 30, 60 * 60);

header('Content-Type: application/json');

$vin = strtoupper(trim($_GET['vin'] ?? ''));
if (!preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Enter a valid 17-character VIN.']);
    exit;
}

$url = 'https://vpic.nhtsa.dot.gov/api/vehicles/DecodeVinValuesExtended/' . rawurlencode($vin) . '?format=json';
$context = stream_context_create([
    'http' => [
        'timeout' => 8,
        'header' => "User-Agent: Kinyan VIN lookup\r\nAccept: application/json\r\n",
    ],
]);
$raw = @file_get_contents($url, false, $context);
if ($raw === false) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'VIN lookup is temporarily unavailable. You can still fill the fields manually.']);
    exit;
}

$json = json_decode($raw, true);
$data = $json['Results'][0] ?? null;
if (!$data || empty($data['Make']) || empty($data['Model']) || empty($data['ModelYear'])) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'No vehicle details were found for that VIN.']);
    exit;
}

$body = normalize_body_type($data['BodyClass'] ?? '');
$fuel = normalize_fuel_type(($data['FuelTypePrimary'] ?? '') ?: ($data['ElectrificationLevel'] ?? ''));
$transmission = normalize_transmission($data['TransmissionStyle'] ?? '');
$engine = trim(implode(' ', array_filter([
    $data['EngineConfiguration'] ?? '',
    !empty($data['DisplacementL']) ? $data['DisplacementL'] . 'L' : '',
    $data['EngineModel'] ?? '',
])));

echo json_encode([
    'ok' => true,
    'year' => $data['ModelYear'] ?? '',
    'make' => ucwords(strtolower($data['Make'] ?? '')),
    'model' => ucwords(strtolower($data['Model'] ?? '')),
    'trim' => $data['Trim'] ?? '',
    'body_type' => $body,
    'fuel_type' => $fuel,
    'transmission' => $transmission,
    'drivetrain' => $data['DriveType'] ?? '',
    'engine' => $engine,
]);

function normalize_body_type(string $value): string
{
    $value = strtolower($value);
    return match (true) {
        str_contains($value, 'minivan') => 'Minivan',
        str_contains($value, 'sport utility'), str_contains($value, 'suv') => 'SUV',
        str_contains($value, 'pickup') => 'Pickup Truck',
        str_contains($value, 'hatchback') => 'Hatchback',
        str_contains($value, 'coupe') => 'Coupe',
        str_contains($value, 'convertible') => 'Convertible',
        str_contains($value, 'wagon') => 'Wagon',
        str_contains($value, 'van') => 'Van',
        str_contains($value, 'sedan') => 'Sedan',
        default => 'Other',
    };
}

function normalize_fuel_type(string $value): string
{
    $value = strtolower($value);
    return match (true) {
        str_contains($value, 'plug') => 'Plug-in Hybrid',
        str_contains($value, 'hybrid') => 'Hybrid',
        str_contains($value, 'electric') => 'Electric',
        str_contains($value, 'diesel') => 'Diesel',
        str_contains($value, 'gas') => 'Gasoline',
        default => 'Other',
    };
}

function normalize_transmission(string $value): string
{
    $value = strtolower($value);
    return match (true) {
        str_contains($value, 'manual') => 'Manual',
        str_contains($value, 'cvt') => 'CVT',
        str_contains($value, 'automatic') => 'Automatic',
        default => 'Other',
    };
}
