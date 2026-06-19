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
$details = vin_detail_groups($data, $body, $fuel, $transmission, $engine);
$additionalDetails = vin_additional_detail_groups($data);

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
    'details' => $details,
    'additional_details' => $additionalDetails,
    'history_note' => 'This VIN decoder does not confirm current mileage, last-sale mileage, title brands, liens, accidents, ownership history, theft records, or open recalls. Ask the seller for records and consider a title/history report before buying.',
]);

function vin_detail_groups(array $data, string $body, string $fuel, string $transmission, string $engine): array
{
    $get = fn(string $key): string => clean_vin_value($data[$key] ?? '');
    $groups = [
        [
            'title' => 'Vehicle identity',
            'items' => [
                ['Year', $get('ModelYear')],
                ['Make', ucwords(strtolower($get('Make')))],
                ['Model', ucwords(strtolower($get('Model')))],
                ['Trim', $get('Trim')],
                ['Series', $get('Series')],
                ['Vehicle type', $get('VehicleType')],
                ['Body class', $get('BodyClass') ?: $body],
                ['VIN descriptor', $get('VehicleDescriptor')],
            ],
        ],
        [
            'title' => 'Engine and drivetrain',
            'items' => [
                ['Engine', $engine],
                ['Cylinders', $get('EngineCylinders')],
                ['Displacement', $get('DisplacementL') ? $get('DisplacementL') . 'L' : ''],
                ['Horsepower', $get('EngineHP')],
                ['Engine manufacturer', $get('EngineManufacturer')],
                ['Fuel type', $fuel],
                ['Secondary fuel', $get('FuelTypeSecondary') ? normalize_fuel_type($get('FuelTypeSecondary')) : ''],
                ['Electrification', $get('ElectrificationLevel')],
                ['Transmission', $transmission],
                ['Drive type', $get('DriveType')],
                ['Turbo', $get('Turbo')],
            ],
        ],
        [
            'title' => 'Size and body',
            'items' => [
                ['Doors', $get('Doors')],
                ['Seats', $get('Seats')],
                ['Seat rows', $get('SeatRows')],
                ['Cab type', $get('BodyCabType')],
                ['Wheelbase', $get('WheelBaseShort') ? $get('WheelBaseShort') . ' in' : ''],
                ['Gross weight rating', trim($get('GVWR') . ($get('GVWR_to') ? ' to ' . $get('GVWR_to') : ''))],
                ['Trailer body type', $get('TrailerBodyType')],
            ],
        ],
        [
            'title' => 'Manufacturing',
            'items' => [
                ['Manufacturer', $get('Manufacturer')],
                ['Plant city', $get('PlantCity')],
                ['Plant state', $get('PlantState')],
                ['Plant country', $get('PlantCountry')],
                ['Destination market', $get('DestinationMarket')],
            ],
        ],
        [
            'title' => 'Safety and equipment decoded from VIN',
            'items' => [
                ['Seat belts', $get('SeatBeltsAll')],
                ['Front airbags', $get('AirBagLocFront')],
                ['Side airbags', $get('AirBagLocSide')],
                ['Curtain airbags', $get('AirBagLocCurtain')],
                ['Knee airbags', $get('AirBagLocKnee')],
                ['ABS', $get('ABS')],
                ['Electronic stability control', $get('ESC')],
                ['Traction control', $get('TractionControl')],
                ['Backup camera', $get('BackupCamera')],
                ['Blind spot monitoring', $get('BlindSpotMon')],
                ['Lane keep system', $get('LaneKeepSystem')],
                ['Forward collision warning', $get('ForwardCollisionWarning')],
                ['Adaptive cruise control', $get('AdaptiveCruiseControl')],
                ['TPMS', $get('TPMS')],
            ],
        ],
    ];

    return array_values(array_filter(array_map(function (array $group): array {
        $group['items'] = array_values(array_filter($group['items'], fn(array $item): bool => $item[1] !== ''));
        return $group;
    }, $groups), fn(array $group): bool => !empty($group['items'])));
}

function vin_additional_detail_groups(array $data): array
{
    $get = fn(string $key): string => clean_vin_value($data[$key] ?? '');
    $range = function (string $fromKey, string $toKey, string $unit = '') use ($get): string {
        $from = $get($fromKey);
        $to = $get($toKey);
        if ($from === '' && $to === '') return '';
        $value = $from !== '' && $to !== '' && $from !== $to ? $from . ' to ' . $to : ($from ?: $to);
        return $value . $unit;
    };

    $groups = [
        ['title' => 'EV and battery', 'items' => [
            ['Battery type', $get('BatteryType')],
            ['Battery capacity', $range('BatteryKWh', 'BatteryKWh_to', ' kWh')],
            ['Battery voltage', $range('BatteryV', 'BatteryV_to', ' V')],
            ['Battery current', $range('BatteryA', 'BatteryA_to', ' A')],
            ['Cells per module', $get('BatteryCells')],
            ['Modules per pack', $get('BatteryModules')],
            ['Battery packs', $get('BatteryPacks')],
            ['EV drive unit', $get('EVDriveUnit')],
            ['Charger level', $get('ChargerLevel')],
            ['Charger power', $get('ChargerPowerKW') ? $get('ChargerPowerKW') . ' kW' : ''],
            ['Other battery information', $get('BatteryInfo')],
        ]],
        ['title' => 'Dimensions and weight', 'items' => [
            ['Curb weight', $get('CurbWeightLB') ? $get('CurbWeightLB') . ' lb' : ''],
            ['Wheelbase', $range('WheelBaseShort', 'WheelBaseLong', ' in')],
            ['Track width', $get('TrackWidth') ? $get('TrackWidth') . ' in' : ''],
            ['Gross vehicle weight rating', $range('GVWR', 'GVWR_to')],
            ['Gross combined weight rating', $range('GCWR', 'GCWR_to')],
            ['Bed length', $get('BedLengthIN') ? $get('BedLengthIN') . ' in' : ''],
            ['Number of wheels', $get('Wheels')],
            ['Front wheel size', $get('WheelSizeFront') ? $get('WheelSizeFront') . ' in' : ''],
            ['Rear wheel size', $get('WheelSizeRear') ? $get('WheelSizeRear') . ' in' : ''],
            ['Windows', $get('Windows')],
        ]],
        ['title' => 'Engine and performance', 'items' => [
            ['Engine power', $get('EngineKW') ? $get('EngineKW') . ' kW' : ''],
            ['Horsepower range', $range('EngineHP', 'EngineHP_to', ' hp')],
            ['Engine cycles', $get('EngineCycles')],
            ['Valve train', $get('ValveTrainDesign')],
            ['Fuel injection', $get('FuelInjectionType')],
            ['Cooling type', $get('CoolingType')],
            ['Top speed', $get('TopSpeedMPH') ? $get('TopSpeedMPH') . ' mph' : ''],
            ['Other engine information', $get('OtherEngineInfo')],
        ]],
        ['title' => 'Drivetrain and transmission', 'items' => [
            ['Transmission speeds', $get('TransmissionSpeeds')],
            ['Axles', $get('Axles')],
            ['Axle configuration', $get('AxleConfiguration')],
            ['Brake system type', $get('BrakeSystemType')],
            ['Brake system description', $get('BrakeSystemDesc')],
        ]],
        ['title' => 'Driver assistance and parking', 'items' => [
            ['Parking assist', $get('ParkAssist')],
            ['Rear cross-traffic alert', $get('RearCrossTrafficAlert')],
            ['Rear automatic emergency braking', $get('RearAutomaticEmergencyBraking')],
            ['Crash-imminent braking', $get('CIB')],
            ['Dynamic brake support', $get('DynamicBrakeSupport')],
            ['Pedestrian emergency braking', $get('PedestrianAutomaticEmergencyBraking')],
            ['Lane departure warning', $get('LaneDepartureWarning')],
            ['Lane centering assistance', $get('LaneCenteringAssistance')],
            ['Blind-spot intervention', $get('BlindSpotIntervention')],
            ['Automatic crash notification', $get('AutomaticCrashNotification')],
        ]],
        ['title' => 'Lighting and active safety', 'items' => [
            ['Daytime running lights', $get('DaytimeRunningLight')],
            ['Headlight source', $get('HeadlampLightSource')],
            ['Automatic high-beam switching', $get('SemiautomaticHeadlampBeamSwitching')],
            ['Adaptive driving beam', $get('AdaptiveDrivingBeam')],
            ['Event data recorder', $get('EDR')],
            ['Keyless ignition', $get('KeylessIgnition')],
            ['Automatic window/sunroof reverse', $get('AutoReverseSystem')],
            ['Pedestrian alert sound', $get('AutomaticPedestrianAlertingSound')],
            ['Automation level', $range('SAEAutomationLevel', 'SAEAutomationLevel_to')],
            ['Active safety notes', $get('ActiveSafetySysNote')],
        ]],
        ['title' => 'Interior and restraints', 'items' => [
            ['Steering location', $get('SteeringLocation')],
            ['Entertainment system', $get('EntertainmentSystem')],
            ['Seat-belt pretensioners', $get('Pretensioner')],
            ['Seat-cushion airbags', $get('AirBagLocSeatCushion')],
            ['Other restraint information', $get('OtherRestraintSystemInfo')],
        ]],
        ['title' => 'Manufacturing and model metadata', 'items' => [
            ['Plant company', $get('PlantCompanyName')],
            ['Secondary trim', $get('Trim2')],
            ['Secondary series', $get('Series2')],
            ['Original base price', $get('BasePrice') ? '$' . number_format((float)$get('BasePrice'), 0) : ''],
            ['Manufacturer notes', $get('Note')],
            ['Non-land use', $get('NonLandUse')],
        ]],
        ['title' => 'Truck details', 'items' => [
            ['Bed type', $get('BedType')],
            ['Bed length', $get('BedLengthIN') ? $get('BedLengthIN') . ' in' : ''],
            ['Cab type', $get('BodyCabType')],
        ]],
        ['title' => 'Motorcycle, bus and trailer details', 'items' => [
            ['Motorcycle type', $get('CustomMotorcycleType')],
            ['Motorcycle suspension', $get('MotorcycleSuspensionType')],
            ['Motorcycle chassis', $get('MotorcycleChassisType')],
            ['Motorcycle fuel-tank type', $get('MotorcycleFuelTankType')],
            ['Motorcycle fuel-tank material', $get('MotorcycleFuelTankMaterial')],
            ['Combined braking system', $get('CombinedBrakingSystem')],
            ['Wheelie mitigation', $get('WheelieMitigation')],
            ['Other motorcycle information', $get('OtherMotorcycleInfo')],
            ['Bus type', $get('BusType')],
            ['Bus floor configuration', $get('BusFloorConfigType')],
            ['Bus length', $get('BusLength') ? $get('BusLength') . ' ft' : ''],
            ['Other bus information', $get('OtherBusInfo')],
            ['Trailer connection', $get('TrailerType')],
            ['Trailer body type', $get('TrailerBodyType')],
            ['Trailer length', $get('TrailerLength') ? $get('TrailerLength') . ' ft' : ''],
            ['Other trailer information', $get('OtherTrailerInfo')],
        ]],
    ];

    foreach ($groups as &$group) {
        $group['items'] = array_values(array_filter($group['items'], fn(array $item): bool => $item[1] !== ''));
    }
    unset($group);
    return $groups;
}

function clean_vin_value($value): string
{
    $value = trim((string)$value);
    if ($value === '' || in_array(strtolower($value), ['not applicable', 'not available', '0'], true)) {
        return '';
    }
    return $value;
}

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
