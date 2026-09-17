<?php
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'upc');

// API Configuration
define('PSGC_API_BASE', 'https://psgc.cloud/api');
define('CACHE_DIR', __DIR__ . '/cache');

// Create cache directory if it doesn't exist
if (!file_exists(CACHE_DIR)) {
    mkdir(CACHE_DIR, 0755, true);
}

// Function to fetch data from API with caching
function fetchFromAPI($endpoint, $cacheTime = 86400) {
    $cacheFile = CACHE_DIR . '/' . md5($endpoint) . '.json';
    
    // Check if cache is valid
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
        $cachedData = json_decode(file_get_contents($cacheFile), true);
        if ($cachedData !== null) {
            return $cachedData;
        }
    }
    
    // Fetch from API
    $url = PSGC_API_BASE . $endpoint;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: UPC-BioEnergy-HRIS/1.0'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || empty($response)) {
        return null;
    }
    
    $data = json_decode($response, true);
    
    if ($data === null) {
        return null;
    }
    
    // Save to cache
    file_put_contents($cacheFile, json_encode($data, JSON_PRETTY_PRINT));
    
    return $data;
}

// Get provinces by region code
function getProvincesByRegionCode($regionCode) {
    if (empty($regionCode)) return [];
    
    $provincesData = fetchFromAPI("/regions/$regionCode/provinces");
    if (!$provincesData) {
        return [];
    }
    
    $provinces = [];
    foreach ($provincesData as $province) {
        $provinces[] = [
            'code' => $province['code'],
            'name' => $province['name']
        ];
    }
    
    return $provinces;
}

// Get cities by province code
function getCitiesByProvinceCode($provinceCode) {
    if (empty($provinceCode)) return [];
    
    $citiesData = fetchFromAPI("/provinces/$provinceCode/cities-municipalities");
    if (!$citiesData) {
        return [];
    }
    
    $cities = [];
    foreach ($citiesData as $city) {
        $cities[] = [
            'code' => $city['code'],
            'name' => $city['name'],
            'isCity' => isset($city['cityClass']) && $city['cityClass'] !== 'Municipality'
        ];
    }
    
    // Sort cities: Cities first, then municipalities
    usort($cities, function($a, $b) {
        if ($a['isCity'] && !$b['isCity']) return -1;
        if (!$a['isCity'] && $b['isCity']) return 1;
        return strcmp($a['name'], $b['name']);
    });
    
    return $cities;
}

// Get barangays by city code
function getBarangaysByCityCode($cityCode) {
    if (empty($cityCode)) return [];
    
    $barangaysData = fetchFromAPI("/cities-municipalities/$cityCode/barangays");
    if (!$barangaysData) {
        return [];
    }
    
    $barangays = [];
    foreach ($barangaysData as $barangay) {
        $barangays[] = [
            'code' => $barangay['code'],
            'name' => $barangay['name']
        ];
    }
    
    return $barangays;
}

// Get zip code by city name - AUTO-DETECTION VERSION
function getZipCodeByCityName($cityName) {
    if (empty($cityName)) {
        return '0000';
    }
    
    $originalName = trim($cityName);
    $cityName = trim($cityName);
    
    // Database connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        // Fallback to hardcoded array if DB connection fails
        return getZipCodeFromArray($cityName);
    }
    
    // Clean for database search
    $searchName = strtolower($cityName);
    $searchName = preg_replace('/\s+city$/i', '', $searchName);
    $searchName = preg_replace('/\s+\(.*?\)/', '', $searchName);
    $searchName = preg_replace('/\s+municipality$/i', '', $searchName);
    $searchName = trim($searchName);
    
    // Strategy 1: Try exact match with PSGC name
    $stmt = $conn->prepare("SELECT zip_code FROM zip_codes WHERE LOWER(psgc_name) = ? LIMIT 1");
    $stmt->bind_param("s", $cityName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        $conn->close();
        return $row['zip_code'];
    }
    $stmt->close();
    
    // Strategy 2: Try LIKE match with PSGC name
    $likeName = "%$searchName%";
    $stmt = $conn->prepare("SELECT zip_code FROM zip_codes WHERE LOWER(psgc_name) LIKE ? LIMIT 1");
    $stmt->bind_param("s", $likeName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        $conn->close();
        return $row['zip_code'];
    }
    $stmt->close();
    
    // Strategy 3: Try exact match with city name
    $stmt = $conn->prepare("SELECT zip_code FROM zip_codes WHERE LOWER(city_name) = ? LIMIT 1");
    $stmt->bind_param("s", $searchName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        $conn->close();
        return $row['zip_code'];
    }
    $stmt->close();
    
    // Strategy 4: Try LIKE match with city name
    $stmt = $conn->prepare("SELECT zip_code FROM zip_codes WHERE LOWER(city_name) LIKE ? LIMIT 1");
    $stmt->bind_param("s", $likeName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        $conn->close();
        return $row['zip_code'];
    }
    $stmt->close();
    
    $conn->close();
    
    // Fallback to hardcoded array
    return getZipCodeFromArray($originalName);
}

// Fallback function using hardcoded array
function getZipCodeFromArray($cityName) {
    // Comprehensive ZIP codes with PSGC name mapping
    $zipCodes = [
        // PSGC Names
        'City of Manila (Capital)' => '1000',
        'Quezon City (Capital)' => '1100',
        'Caloocan (City)' => '1400',
        'Las Piñas (City)' => '1740',
        'Makati (City)' => '1200',
        'Malabon (City)' => '1470',
        'Mandaluyong (City)' => '1550',
        'Marikina (City)' => '1800',
        'Muntinlupa (City)' => '1770',
        'Navotas (City)' => '1485',
        'Parañaque (City)' => '1700',
        'Pasay (City)' => '1300',
        'Pasig (City)' => '1600',
        'San Juan (City)' => '1500',
        'Taguig (City)' => '1630',
        'Valenzuela (City)' => '1440',
        'Bacoor (City)' => '4102',
        'Imus (City)' => '4103',
        'Dasmariñas (City)' => '4114',
        'Tagaytay (City)' => '4120',
        'Calamba (City)' => '4027',
        'Santa Rosa (City)' => '4026',
        'San Pedro (City)' => '4023',
        'Biñan (City)' => '4024',
        'Cabuyao (City)' => '4025',
        'San Pablo (City)' => '4000',
        'Los Baños (Municipality)' => '4030',
        'Santa Cruz (Municipality)' => '4009',
        'Siniloan (Municipality)' => '4019',
        'Lumban (Municipality)' => '4014',
        'Pagsanjan (Municipality)' => '4008',
        'Paete (Municipality)' => '4016',
        'Nagcarlan (Municipality)' => '4002',
        'Liliw (Municipality)' => '4004',
        'Majayjay (Municipality)' => '4005',
        'Batangas City (Capital)' => '4200',
        'Lipa (City)' => '4217',
        'Tanauan (City)' => '4232',
        'Nasugbu (Municipality)' => '4231',
        'Bauan (Municipality)' => '4201',
        'Santo Tomas (Municipality)' => '4234',
        'Balayan (Municipality)' => '4213',
        'Lemery (Municipality)' => '4209',
        'Taal (Municipality)' => '4208',
        'Malolos (City)' => '3000',
        'San Jose del Monte (City)' => '3023',
        'Meycauayan (City)' => '3020',
        'Marilao (Municipality)' => '3019',
        'Santa Maria (Municipality)' => '3022',
        'Baliwag (Municipality)' => '3006',
        'Plaridel (Municipality)' => '3004',
        'Antipolo (City)' => '1870',
        'Taytay (Municipality)' => '1920',
        'Cainta (Municipality)' => '1900',
        'San Fernando (City)' => '2000',
        'Angeles (City)' => '2009',
        'Mabalacat (City)' => '2010',
        'Cebu City (Capital)' => '6000',
        'Mandaue (City)' => '6014',
        'Lapu-Lapu City' => '6015',
        'Talisay (City)' => '6045',
        'Davao City (Capital)' => '8000',
        'Tagum (City)' => '8100',
        'Panabo (City)' => '8105',
        'Iloilo City (Capital)' => '5000',
        'Bacolod (City)' => '6100',
        'Baguio (City)' => '2600',
        'San Fernando (Capital)' => '2500',
        'Dagupan (City)' => '2400',
        'Cabanatuan (City)' => '3100',
        'Tarlac City' => '2300',
        'Olongapo (City)' => '2200',
        'Legazpi (City)' => '4500',
        'Naga (Capital)' => '4400',
        'Roxas (City)' => '5800',
        'Puerto Princesa (City)' => '5300',
        'Zamboanga City (Capital)' => '7000',
        'Cagayan de Oro (Capital)' => '9000',
        'Butuan (City)' => '8600',
        'General Santos (City)' => '9500',
        
        // Simplified names for backward compatibility
        'Manila' => '1000',
        'Quezon City' => '1100',
        'Caloocan' => '1400',
        'Las Piñas' => '1740',
        'Makati' => '1200',
        'Malabon' => '1470',
        'Mandaluyong' => '1550',
        'Marikina' => '1800',
        'Muntinlupa' => '1770',
        'Navotas' => '1485',
        'Parañaque' => '1700',
        'Pasay' => '1300',
        'Pasig' => '1600',
        'San Juan' => '1500',
        'Taguig' => '1630',
        'Valenzuela' => '1440',
        'Bacoor' => '4102',
        'Imus' => '4103',
        'Dasmariñas' => '4114',
        'Tagaytay' => '4120',
        'Calamba' => '4027',
        'Santa Rosa' => '4026',
        'San Pedro' => '4023',
        'Biñan' => '4024',
        'Cabuyao' => '4025',
        'San Pablo' => '4000',
        'Los Baños' => '4030',
        'Batangas City' => '4200',
        'Lipa' => '4217',
        'Tanauan' => '4232',
        'Antipolo' => '1870',
        'Cebu City' => '6000',
        'Mandaue' => '6014',
        'Lapu-Lapu' => '6015',
        'Davao City' => '8000',
        'Iloilo City' => '5000',
        'Bacolod' => '6100',
        'Baguio' => '2600',
    ];
    
    // First try: Direct match
    if (isset($zipCodes[$cityName])) {
        return $zipCodes[$cityName];
    }
    
    // Clean the city name
    $cleanName = strtolower($cityName);
    $cleanName = preg_replace('/\s+city$/i', '', $cleanName);
    $cleanName = preg_replace('/\s+\(.*?\)/', '', $cleanName);
    $cleanName = preg_replace('/\s+municipality$/i', '', $cleanName);
    $cleanName = trim($cleanName);
    
    // Second try: Match with cleaned names
    foreach ($zipCodes as $psgcName => $zip) {
        $cleanPsgc = strtolower($psgcName);
        $cleanPsgc = preg_replace('/\s+city$/i', '', $cleanPsgc);
        $cleanPsgc = preg_replace('/\s+\(.*?\)/', '', $cleanPsgc);
        $cleanPsgc = preg_replace('/\s+municipality$/i', '', $cleanPsgc);
        $cleanPsgc = trim($cleanPsgc);
        
        if ($cleanName === $cleanPsgc) {
            return $zip;
        }
    }
    
    // Third try: Partial match
    foreach ($zipCodes as $psgcName => $zip) {
        if (stripos($cityName, $psgcName) !== false || stripos($psgcName, $cityName) !== false) {
            return $zip;
        }
    }
    
    return '0000';
}

// Main handler
try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_provinces':
            $regionCode = $_POST['region_code'] ?? '';
            if (empty($regionCode)) {
                throw new Exception('Region code is required');
            }
            
            $provinces = getProvincesByRegionCode($regionCode);
            
            echo json_encode([
                'success' => true,
                'data' => $provinces
            ]);
            break;
            
        case 'get_cities':
            $provinceCode = $_POST['province_code'] ?? '';
            if (empty($provinceCode)) {
                throw new Exception('Province code is required');
            }
            
            $cities = getCitiesByProvinceCode($provinceCode);
            
            echo json_encode([
                'success' => true,
                'data' => $cities
            ]);
            break;
            
        case 'get_barangays':
            $cityCode = $_POST['city_code'] ?? '';
            if (empty($cityCode)) {
                throw new Exception('City code is required');
            }
            
            $barangays = getBarangaysByCityCode($cityCode);
            
            echo json_encode([
                'success' => true,
                'data' => $barangays
            ]);
            break;
            
        case 'get_zip':
            $cityName = $_POST['city_name'] ?? '';
            if (empty($cityName)) {
                throw new Exception('City name is required');
            }
            
            $zip = getZipCodeByCityName($cityName);
            
            echo json_encode([
                'success' => true,
                'zip' => $zip,
                'city' => $cityName
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>