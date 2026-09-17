<?php
ob_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'upc');

// API Configuration
define('PSGC_API_BASE', 'https://psgc.cloud/api');

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'dadivasedza@gmail.com'); // Update with your Gmail
define('SMTP_PASSWORD', 'jbfr noyu lqfo ectk'); // Use App Password, not regular password
define('SMTP_FROM_EMAIL', 'dadivasedza@gmail.com');
define('SMTP_FROM_NAME', 'UPC BioEnergy HRIS');

require_once 'theme/navbar.php';
require_once 'theme/sidebar.php';

// Load PHPMailer classes manually (replace the require line on line 25)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Adjust the path below to match where you placed the 'src' folder
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$users = [];
$departments = [];
$message = '';
$error = '';
$conn = null;

// Function to send email with login credentials
function sendLoginCredentials($email, $firstName, $password, $employeeId = null) {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email, $firstName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your UPC BioEnergy HRIS Login Credentials';
        
        // Email body template
        $body = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #0d6efd; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background-color: #f8f9fa; padding: 30px; border-radius: 0 0 5px 5px; }
                .credentials { background-color: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d; }
                .login-btn { 
                    display: inline-block; 
                    padding: 10px 20px; 
                    background-color: #0d6efd; 
                    color: white; 
                    text-decoration: none; 
                    border-radius: 5px; 
                    margin: 10px 0; 
                }
                .warning { color: #dc3545; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>UPC BioEnergy HRIS</h1>
                    <p>Human Resource Information System</p>
                </div>
                <div class="content">
                    <h2>Welcome to UPC BioEnergy HRIS!</h2>
                    <p>Dear <strong>' . htmlspecialchars($firstName) . '</strong>,</p>
                    
                    <p>Your employee account has been created successfully in the UPC BioEnergy HRIS system.</p>
                    
                    <div class="credentials">
                        <h3>Your Login Credentials:</h3>
                        <p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>
                        <p><strong>Password:</strong> ' . htmlspecialchars($password) . '</p>
                        ' . ($employeeId ? '<p><strong>Employee ID:</strong> ' . htmlspecialchars($employeeId) . '</p>' : '') . '
                    </div>
                    
                    <p>Please use these credentials to log in to the HRIS system:</p>
                    <a href="http://your-domain.com/login.php" class="login-btn">Login to HRIS</a>
                    
                    <p class="warning">⚠️ For security reasons, please change your password immediately after first login.</p>
                    
                    <h3>System Features:</h3>
                    <ul>
                        <li>View your personal information</li>
                        <li>Check your attendance and DTR</li>
                        <li>View payslips and salary information</li>
                        <li>Request leaves and view leave balances</li>
                        <li>Update your personal details</li>
                    </ul>
                    
                    <p>If you have any questions or encounter issues logging in, please contact the HR department.</p>
                    
                    <p>Best regards,<br>
                    <strong>HR Department</strong><br>
                    UPC BioEnergy</p>
                </div>
                <div class="footer">
                    <p>This is an automated message from UPC BioEnergy HRIS. Please do not reply to this email.</p>
                    <p>© ' . date('Y') . ' UPC BioEnergy. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
        
        $mail->Body = $body;
        
        // Plain text version for non-HTML email clients
        $mail->AltBody = "Welcome to UPC BioEnergy HRIS!\n\n" .
                        "Dear $firstName,\n\n" .
                        "Your employee account has been created successfully.\n\n" .
                        "Login Credentials:\n" .
                        "Email: $email\n" .
                        "Password: $password\n" .
                        ($employeeId ? "Employee ID: $employeeId\n" : "") .
                        "\n" .
                        "Login URL: http://your-domain.com/login.php\n\n" .
                        "For security reasons, please change your password immediately after first login.\n\n" .
                        "Best regards,\n" .
                        "HR Department\n" .
                        "UPC BioEnergy";
        
        // Send email
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

// Function to fetch data from API with caching
function fetchFromAPI($endpoint, $cacheTime = 86400) {
    $cacheFile = __DIR__ . "/cache/" . md5($endpoint) . ".json";
    
    // Create cache directory if it doesn't exist
    if (!file_exists(__DIR__ . "/cache")) {
        mkdir(__DIR__ . "/cache", 0755, true);
    }
    
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
        return getFallbackData($endpoint);
    }
    
    $data = json_decode($response, true);
    
    if ($data === null) {
        return getFallbackData($endpoint);
    }
    
    // Save to cache
    file_put_contents($cacheFile, json_encode($data, JSON_PRETTY_PRINT));
    
    return $data;
}

// Fallback data in case API fails
function getFallbackData($endpoint) {
    $fallbackData = [
        '/regions' => [
            ['code' => '010000000', 'name' => 'Ilocos Region', 'regionName' => 'Region I (Ilocos Region)'],
            ['code' => '020000000', 'name' => 'Cagayan Valley', 'regionName' => 'Region II (Cagayan Valley)'],
            ['code' => '030000000', 'name' => 'Central Luzon', 'regionName' => 'Region III (Central Luzon)'],
            ['code' => '040000000', 'name' => 'CALABARZON', 'regionName' => 'Region IV-A (CALABARZON)'],
            ['code' => '050000000', 'name' => 'Bicol Region', 'regionName' => 'Region V (Bicol Region)'],
            ['code' => '060000000', 'name' => 'Western Visayas', 'regionName' => 'Region VI (Western Visayas)'],
            ['code' => '070000000', 'name' => 'Central Visayas', 'regionName' => 'Region VII (Central Visayas)'],
            ['code' => '080000000', 'name' => 'Eastern Visayas', 'regionName' => 'Region VIII (Eastern Visayas)'],
            ['code' => '090000000', 'name' => 'Zamboanga Peninsula', 'regionName' => 'Region IX (Zamboanga Peninsula)'],
            ['code' => '100000000', 'name' => 'Northern Mindanao', 'regionName' => 'Region X (Northern Mindanao)'],
            ['code' => '110000000', 'name' => 'Davao Region', 'regionName' => 'Region XI (Davao Region)'],
            ['code' => '120000000', 'name' => 'SOCCSKSARGEN', 'regionName' => 'Region XII (SOCCSKSARGEN)'],
            ['code' => '130000000', 'name' => 'National Capital Region', 'regionName' => 'National Capital Region (NCR)'],
            ['code' => '140000000', 'name' => 'Cordillera Administrative Region', 'regionName' => 'Cordillera Administrative Region (CAR)'],
            ['code' => '150000000', 'name' => 'Bangsamoro Autonomous Region in Muslim Mindanao', 'regionName' => 'Bangsamoro Autonomous Region in Muslim Mindanao (BARMM)'],
            ['code' => '160000000', 'name' => 'Caraga', 'regionName' => 'Region XIII (Caraga)'],
            ['code' => '170000000', 'name' => 'MIMAROPA', 'regionName' => 'Region IV-B (MIMAROPA)']
        ]
    ];
    
    return $fallbackData[$endpoint] ?? [];
}

// Get regions from API
function getPhilippineRegions() {
    $regionsData = fetchFromAPI('/regions');
    $regions = [];
    
    if (empty($regionsData)) {
        // Return hardcoded regions if API fails
        return [
            ['code' => '010000000', 'name' => 'Ilocos Region', 'regionName' => 'Region I (Ilocos Region)'],
            ['code' => '020000000', 'name' => 'Cagayan Valley', 'regionName' => 'Region II (Cagayan Valley)'],
            ['code' => '030000000', 'name' => 'Central Luzon', 'regionName' => 'Region III (Central Luzon)'],
            ['code' => '040000000', 'name' => 'CALABARZON', 'regionName' => 'Region IV-A (CALABARZON)'],
            ['code' => '050000000', 'name' => 'Bicol Region', 'regionName' => 'Region V (Bicol Region)'],
            ['code' => '060000000', 'name' => 'Western Visayas', 'regionName' => 'Region VI (Western Visayas)'],
            ['code' => '070000000', 'name' => 'Central Visayas', 'regionName' => 'Region VII (Central Visayas)'],
            ['code' => '080000000', 'name' => 'Eastern Visayas', 'regionName' => 'Region VIII (Eastern Visayas)'],
            ['code' => '090000000', 'name' => 'Zamboanga Peninsula', 'regionName' => 'Region IX (Zamboanga Peninsula)'],
            ['code' => '100000000', 'name' => 'Northern Mindanao', 'regionName' => 'Region X (Northern Mindanao)'],
            ['code' => '110000000', 'name' => 'Davao Region', 'regionName' => 'Region XI (Davao Region)'],
            ['code' => '120000000', 'name' => 'SOCCSKSARGEN', 'regionName' => 'Region XII (SOCCSKSARGEN)'],
            ['code' => '130000000', 'name' => 'National Capital Region', 'regionName' => 'National Capital Region (NCR)'],
            ['code' => '140000000', 'name' => 'Cordillera Administrative Region', 'regionName' => 'Cordillera Administrative Region (CAR)'],
            ['code' => '150000000', 'name' => 'Bangsamoro Autonomous Region in Muslim Mindanao', 'regionName' => 'Bangsamoro Autonomous Region in Muslim Mindanao (BARMM)'],
            ['code' => '160000000', 'name' => 'Caraga', 'regionName' => 'Region XIII (Caraga)'],
            ['code' => '170000000', 'name' => 'MIMAROPA', 'regionName' => 'Region IV-B (MIMAROPA)']
        ];
    }
    
    foreach ($regionsData as $region) {
        $regions[] = [
            'code' => $region['code'],
            'name' => $region['name'],
            'regionName' => $region['regionName'] ?? $region['name']
        ];
    }
    
    return $regions;
}

// Get zip code by city/municipality name - IMPROVED VERSION WITH BETTER MATCHING
function getZipCodeByCity($cityName) {
    // Clean the city name first
    $cityName = trim($cityName);
    
    // If city name is empty, return default
    if (empty($cityName)) {
        return '0000';
    }
    
    // Try to get from database first (if you have a zip_codes table)
    global $conn;
    if ($conn instanceof mysqli) {
        $stmt = $conn->prepare("SELECT zip_code FROM zip_codes WHERE city_name LIKE ? LIMIT 1");
        $searchCity = "%$cityName%";
        $stmt->bind_param("s", $searchCity);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row['zip_code'];
        }
        $stmt->close();
    }
    
    // Comprehensive Philippine ZIP codes database - UPDATED WITH MORE ENTRIES
    $zipCodes = [
        // Metro Manila
        'Manila' => '1000', 'Quezon City' => '1100', 'Caloocan' => '1400', 'Las Piñas' => '1740',
        'Makati' => '1200', 'Malabon' => '1470', 'Mandaluyong' => '1550', 'Marikina' => '1800',
        'Muntinlupa' => '1770', 'Navotas' => '1485', 'Parañaque' => '1700', 'Pasay' => '1300',
        'Pasig' => '1600', 'San Juan' => '1500', 'Taguig' => '1630', 'Valenzuela' => '1440',
        'Pateros' => '1620',
        
        // Cavite
        'Bacoor' => '4102', 'Imus' => '4103', 'Dasmariñas' => '4114', 'Tagaytay' => '4120',
        'Cavite City' => '4100', 'General Trias' => '4107', 'Trece Martires' => '4109',
        'Silang' => '4118', 'Naic' => '4110', 'Rosario' => '4106', 'Carmona' => '4116',
        'Kawit' => '4104', 'Noveleta' => '4105', 'Alfonso' => '4123', 'Amadeo' => '4119',
        'General Emilio Aguinaldo' => '4124', 'General Mariano Alvarez' => '4117',
        'Indang' => '4122', 'Magallanes' => '4112', 'Maragondon' => '4111', 'Mendez' => '4121',
        'Tanza' => '4108', 'Ternate' => '4111',
        
        // Laguna
        'Calamba' => '4027', 'Santa Rosa' => '4026', 'San Pedro' => '4023', 'Biñan' => '4024',
        'Cabuyao' => '4025', 'San Pablo' => '4000', 'Los Baños' => '4030', 'Santa Cruz' => '4009',
        'Siniloan' => '4019', 'Lumban' => '4014', 'Alaminos' => '4001', 'Bay' => '4033',
        'Calauan' => '4012', 'Cavinti' => '4013', 'Famy' => '4021', 'Kalayaan' => '4015',
        'Liliw' => '4004', 'Luisiana' => '4031', 'Magdalena' => '4007', 'Majayjay' => '4005',
        'Nagcarlan' => '4002', 'Paete' => '4016', 'Pagsanjan' => '4008', 'Pakil' => '4017',
        'Pangil' => '4018', 'Pila' => '4010', 'Rizal' => '4003', 'Victoria' => '4011',
        
        // Batangas
        'Batangas City' => '4200', 'Lipa' => '4217', 'Tanauan' => '4232', 'Nasugbu' => '4231',
        'Bauan' => '4201', 'Santo Tomas' => '4234', 'Balayan' => '4213', 'Lemery' => '4209',
        'Taal' => '4208', 'San Juan' => '4226', 'Calaca' => '4212', 'Calatagan' => '4215',
        'Cuenca' => '4222', 'Ibaan' => '4230', 'Laurel' => '4221', 'Lian' => '4216',
        'Lobo' => '4229', 'Mabini' => '4202', 'Malvar' => '4233', 'Mataasnakahoy' => '4223',
        'Padre Garcia' => '4224', 'Rosario' => '4225', 'San Jose' => '4227', 'San Luis' => '4210',
        'San Nicolas' => '4207', 'San Pascual' => '4204', 'Santa Teresita' => '4206',
        'Talisay' => '4220', 'Taysan' => '4218', 'Tingloy' => '4203', 'Tuy' => '4214',
        
        // Bulacan
        'Malolos' => '3000', 'San Jose del Monte' => '3023', 'Meycauayan' => '3020',
        'Marilao' => '3019', 'Santa Maria' => '3022', 'Baliwag' => '3006', 'Plaridel' => '3004',
        'Pulilan' => '3005', 'Bocaue' => '3018', 'Guiguinto' => '3015', 'Angat' => '3012',
        'Balagtas' => '3016', 'Bulakan' => '3017', 'Bustos' => '3007', 'Calumpit' => '3003',
        'Doña Remedios Trinidad' => '3009', 'Hagonoy' => '3002', 'Norzagaray' => '3013',
        'Obando' => '3021', 'Pandi' => '3014', 'Paombong' => '3001', 'San Ildefonso' => '3010',
        'San Miguel' => '3011', 'San Rafael' => '3008',
        
        // Rizal
        'Antipolo' => '1870', 'Taytay' => '1920', 'Cainta' => '1900', 'Binangonan' => '1940',
        'Angono' => '1930', 'Rodriguez' => '1860', 'San Mateo' => '1850', 'Cardona' => '1950',
        'Baras' => '1970', 'Jala-jala' => '1990', 'Morong' => '1960', 'Pililla' => '1910',
        'Tanay' => '1980', 'Teresa' => '1880',
        
        // Pampanga
        'San Fernando' => '2000', 'Angeles' => '2009', 'Mabalacat' => '2010', 'Mexico' => '2021',
        'Guagua' => '2003', 'Floridablanca' => '2006', 'Lubao' => '2005', 'Arayat' => '2012',
        'Apalit' => '2016', 'Porac' => '2008', 'Bacolor' => '2001', 'Candaba' => '2013',
        'Magalang' => '2011', 'Masantol' => '2017', 'Minalin' => '2019', 'San Luis' => '2014',
        'San Simon' => '2015', 'Santa Ana' => '2022', 'Santa Rita' => '2002', 'Santo Tomas' => '2020',
        'Sasmuan' => '2004',
        
        // Cebu
        'Cebu City' => '6000', 'Mandaue' => '6014', 'Lapu-Lapu' => '6015', 'Talisay' => '6045',
        'Danao' => '6004', 'Toledo' => '6038', 'Naga' => '6037', 'Bogo' => '6010',
        'Carcar' => '6019', 'Consolacion' => '6001', 'Argao' => '6021', 'Asturias' => '6042',
        'Badian' => '6031', 'Balamban' => '6041', 'Bantayan' => '6052', 'Barili' => '6036',
        'Boljoon' => '6024', 'Borbon' => '6008', 'Carmen' => '6005', 'Catmon' => '6006',
        'Compostela' => '6003', 'Cordova' => '6017', 'Daanbantayan' => '6013',
        'Dalaguete' => '6022', 'Dumanjug' => '6035', 'Ginatilan' => '6028', 'Liloan' => '6002',
        'Madridejos' => '6053', 'Malabuyoc' => '6029', 'Medellin' => '6012', 'Minglanilla' => '6046',
        'Moalboal' => '6032', 'Oslob' => '6025', 'Pilar' => '6048', 'Pinamungahan' => '6039',
        'Poro' => '6049', 'Ronda' => '6034', 'Samboan' => '6027', 'San Fernando' => '6018',
        'San Francisco' => '6050', 'San Remigio' => '6011', 'Santa Fe' => '6047',
        'Santander' => '6026', 'Sibonga' => '6020', 'Sogod' => '6007', 'Tabogon' => '6009',
        'Tabuelan' => '6044', 'Tuburan' => '6043', 'Tudela' => '6051',
        
        // Davao
        'Davao City' => '8000', 'Tagum' => '8100', 'Panabo' => '8105', 'Digos' => '8002',
        'Mati' => '8200', 'Sta. Cruz' => '8001', 'Samal' => '8119', 'Maco' => '8806',
        'Mabini' => '8807', 'Pantukan' => '8809', 'Magsaysay' => '8804', 'Malandag' => '9504',
        
        // Iloilo
        'Iloilo City' => '5000', 'Passi' => '5037', 'Miagao' => '5023', 'Oton' => '5020',
        'Santa Barbara' => '5002', 'Tigbauan' => '5021', 'Ajuy' => '5012', 'Alimodian' => '5028',
        'Anilao' => '5009', 'Badiangan' => '5033', 'Balasan' => '5018', 'Banate' => '5010',
        'Barotac Nuevo' => '5007', 'Barotac Viejo' => '5011', 'Batad' => '5016',
        'Bingawan' => '5041', 'Cabatuan' => '5031', 'Calinog' => '5040', 'Carles' => '5019',
        'Concepcion' => '5013', 'Dingle' => '5035', 'Dueñas' => '5038', 'Dumangas' => '5006',
        'Estancia' => '5017', 'Guimbal' => '5022', 'Igbaras' => '5029', 'Janiuay' => '5034',
        'Lambunao' => '5042', 'Leganes' => '5003', 'Lemery' => '5043', 'Leon' => '5026',
        'Maasin' => '5030', 'Mina' => '5032', 'New Lucena' => '5005', 'Pavia' => '5001',
        'Pototan' => '5008', 'San Dionisio' => '5015', 'San Enrique' => '5036',
        'San Joaquin' => '5024', 'San Miguel' => '5025', 'San Rafael' => '5039',
        'Sara' => '5014', 'Tubungan' => '5027', 'Zarraga' => '5004',
        
        // Negros Occidental
        'Bacolod' => '6100', 'Bago' => '6101', 'Kabankalan' => '6111', 'Silay' => '6116',
        'Talisay' => '6115', 'Cadiz' => '6121', 'Escalante' => '6124', 'Himamaylan' => '6108',
        'La Carlota' => '6130', 'Sagay' => '6122', 'San Carlos' => '6127', 'Victorias' => '6119',
        'Calatrava' => '6126', 'Candoni' => '6110', 'Cauayan' => '6112',
        'Enrique B. Magalona' => '6118', 'Hinigaran' => '6106', 'Hinoba-an' => '6114',
        'Ilog' => '6109', 'Isabela' => '6128', 'La Castellana' => '6131', 'Manapla' => '6120',
        'Moises Padilla' => '6132', 'Murcia' => '6129', 'Pontevedra' => '6105',
        'Pulupandan' => '6102', 'Salvador Benedicto' => '6117', 'San Enrique' => '6104',
        'Toboso' => '6125', 'Valladolid' => '6103',
        
        // Other Major Cities
        'Baguio' => '2600', 'San Fernando (La Union)' => '2500', 'Dagupan' => '2400',
        'Urdaneta' => '2428', 'Vigan' => '2700', 'Cabanatuan' => '3100', 'San Jose' => '3121',
        'Tarlac City' => '2300', 'Olongapo' => '2200', 'Legazpi' => '4500',
        'Naga (Camarines Sur)' => '4400', 'Roxas' => '5800', 'Puerto Princesa' => '5300',
        'Zamboanga City' => '7000', 'Pagadian' => '7016', 'Cagayan de Oro' => '9000',
        'Butuan' => '8600', 'General Santos' => '9500', 'Koronadal' => '9506',
        'Cotabato City' => '9600', 'Marawi' => '9700',
    ];
    
    // Clean the city name for matching
    $cleanName = trim(strtolower($cityName));
    
    // Remove common suffixes
    $cleanName = preg_replace('/\s+city$/i', '', $cleanName);
    $cleanName = preg_replace('/\s+\(.*?\)/', '', $cleanName);
    $cleanName = trim($cleanName);
    
    // Try exact match first with cleaned name
    foreach ($zipCodes as $city => $zip) {
        $cleanCity = trim(strtolower($city));
        $cleanCity = preg_replace('/\s+city$/i', '', $cleanCity);
        $cleanCity = preg_replace('/\s+\(.*?\)/', '', $cleanCity);
        $cleanCity = trim($cleanCity);
        
        if ($cleanName === $cleanCity) {
            return $zip;
        }
    }
    
    // Try partial match
    foreach ($zipCodes as $city => $zip) {
        $cleanCity = trim(strtolower($city));
        $cleanCity = preg_replace('/\s+city$/i', '', $cleanCity);
        $cleanCity = preg_replace('/\s+\(.*?\)/', '', $cleanCity);
        $cleanCity = trim($cleanCity);
        
        if (strpos($cleanName, $cleanCity) !== false || strpos($cleanCity, $cleanName) !== false) {
            return $zip;
        }
    }
    
    // Try similar sounding names
    foreach ($zipCodes as $city => $zip) {
        $cleanCity = trim(strtolower($city));
        similar_text($cleanName, $cleanCity, $percent);
        if ($percent > 80) { // 80% similarity
            return $zip;
        }
    }
    
    return '0000';
}

// Function to check and update database structure
function updateDatabaseStructure($conn) {
    // List of columns to add
    $columnsToAdd = [
        'region' => "ALTER TABLE users ADD COLUMN region VARCHAR(255) NULL",
        'province' => "ALTER TABLE users ADD COLUMN province VARCHAR(255) NULL",
        'city' => "ALTER TABLE users ADD COLUMN city VARCHAR(255) NULL",
        'barangay' => "ALTER TABLE users ADD COLUMN barangay VARCHAR(255) NULL",
        'zip_code' => "ALTER TABLE users ADD COLUMN zip_code VARCHAR(10) NULL",
        'address' => "ALTER TABLE users ADD COLUMN address TEXT NULL",
        'shift_type' => "ALTER TABLE users ADD COLUMN shift_type VARCHAR(50) NULL",
        'shift_start' => "ALTER TABLE users ADD COLUMN shift_start TIME NULL",
        'shift_end' => "ALTER TABLE users ADD COLUMN shift_end TIME NULL",
        'shift_name' => "ALTER TABLE users ADD COLUMN shift_name VARCHAR(100) NULL"
    ];
    
    foreach ($columnsToAdd as $column => $sql) {
        $checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE '$column'");
        if ($checkColumn->num_rows == 0) {
            $conn->query($sql);
        }
    }
}

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) throw new Exception("Connection failed: " . $conn->connect_error);

    // Update database structure if needed
    updateDatabaseStructure($conn);

    // Fetch departments
    $deptResult = $conn->query("SELECT id, department_name FROM departments ORDER BY department_name ASC");
    while ($row = $deptResult->fetch_assoc()) {
        $departments[] = $row;
    }

    // Initialize search/filter
    $search = $_GET['search'] ?? '';
    $filterDept = $_GET['filter_dept'] ?? '';

    // Handle Add Employee
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
        // Basic info
        $firstName = trim($conn->real_escape_string($_POST['first_name'] ?? ''));
        $middleInitial = trim($conn->real_escape_string($_POST['middle_initial'] ?? ''));
        $lastName  = trim($conn->real_escape_string($_POST['last_name'] ?? ''));
        $email     = trim($conn->real_escape_string($_POST['email'] ?? ''));
        $password  = trim($_POST['password'] ?? '');
        $employeeType = trim($conn->real_escape_string($_POST['employee_type'] ?? ''));
        $phone     = trim($conn->real_escape_string($_POST['phone_number'] ?? ''));
        $departmentId = isset($_POST['department']) ? (int) $_POST['department'] : null;
        $civilStatus = trim($conn->real_escape_string($_POST['civil_status'] ?? ''));
        $dob       = trim($_POST['date_of_birth'] ?? '');
        $salaryAmount = isset($_POST['basic_salary']) ? (float) $_POST['basic_salary'] : 0;
        
        // Address fields (using simple column names)
        $region = trim($conn->real_escape_string($_POST['region_name'] ?? ''));
        $province = trim($conn->real_escape_string($_POST['province_name'] ?? ''));
        $city = trim($conn->real_escape_string($_POST['city_name'] ?? ''));
        $barangay = trim($conn->real_escape_string($_POST['barangay_name'] ?? ''));
        $zipCode = trim($conn->real_escape_string($_POST['zip_code'] ?? ''));
        
        // Shift information - check both naming patterns
$shiftType = trim($conn->real_escape_string($_POST['shift_type'] ?? ''));
$shiftName = trim($conn->real_escape_string($_POST['shift_name'] ?? $_POST['custom_shift_name'] ?? ''));
$shiftStart = trim($conn->real_escape_string($_POST['shift_start'] ?? $_POST['custom_shift_start'] ?? ''));
$shiftEnd = trim($conn->real_escape_string($_POST['shift_end'] ?? $_POST['custom_shift_end'] ?? ''));

// Debug log
error_log("DEBUG - Shift Values:");
error_log("Shift Type: " . $shiftType);
error_log("Shift Name: " . $shiftName);
error_log("Shift Start: " . $shiftStart);
error_log("Shift End: " . $shiftEnd);

// If predefined shift and times are empty, set defaults
if ($shiftType !== 'custom' && (empty($shiftStart) || empty($shiftEnd))) {
    switch($shiftType) {
        case 'day_shift':
            $shiftStart = '08:00';
            $shiftEnd = '17:00';
            $shiftName = empty($shiftName) ? 'Day Shift' : $shiftName;
            break;
        case 'morning_shift':
            $shiftStart = '06:00';
            $shiftEnd = '15:00';
            $shiftName = empty($shiftName) ? 'Morning Shift' : $shiftName;
            break;
        case 'afternoon_shift':
            $shiftStart = '15:00';
            $shiftEnd = '00:00';
            $shiftName = empty($shiftName) ? 'Afternoon Shift' : $shiftName;
            break;
        case 'night_shift':
            $shiftStart = '22:00';
            $shiftEnd = '07:00';
            $shiftName = empty($shiftName) ? 'Night Shift' : $shiftName;
            break;
    }
}
        
        // Debug: Log the received city and zip
        error_log("Add Employee - City: $city, Received Zip: $zipCode");
        
        // Get zip code if not provided or is 0000
        if (empty($zipCode) || $zipCode === '0000') {
            $zipCode = getZipCodeByCity($city);
            error_log("Add Employee - Calculated Zip: $zipCode");
        }
        
        // Create full address string
        $fullAddress = "$barangay, $city, $province, $region, Philippines $zipCode";
        
        // Password hash
        $passwordHash = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : '';

        // Calculate age
        $dobDateTime = DateTime::createFromFormat('Y-m-d', $dob);
        if (!$dobDateTime) throw new Exception("Invalid date of birth format.");
        $today = new DateTime();
        $age = $today->diff($dobDateTime)->y;

        // Insert user with address columns and shift information
        $stmt = $conn->prepare("INSERT INTO users 
            (first_name, middle_initial, last_name, email, password, employee_type, 
             phone_number, department_id, civil_status, date_of_birth, age, 
             address, region, province, city, barangay, zip_code, basic_salary,
             shift_type, shift_name, shift_start, shift_end) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param(
            "sssssssssssssssssdssss",
            $firstName,
            $middleInitial,
            $lastName,
            $email,
            $passwordHash,
            $employeeType,
            $phone,
            $departmentId,
            $civilStatus,
            $dob,
            $age,
            $fullAddress,
            $region,
            $province,
            $city,
            $barangay,
            $zipCode,
            $salaryAmount,
            $shiftType,
            $shiftName,
            $shiftStart,
            $shiftEnd
        );

        if ($stmt->execute()) {
            // Get the inserted user ID
            $userId = $stmt->insert_id;
            
            // Send email with login credentials
            $emailSent = sendLoginCredentials($email, $firstName, $password, $userId);
            
            if ($emailSent) {
                $message = "✅ Employee added successfully! Login credentials have been sent to $email.";
            } else {
                $message = "✅ Employee added successfully! However, email sending failed. Login credentials: Email: $email, Password: $password";
            }
        } else {
            $error = "❌ Error: " . $stmt->error;
        }
        $stmt->close();
    }

    // Handle Edit Employee
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
        $userId = (int) $_POST['user_id'];
        $firstName = trim($conn->real_escape_string($_POST['first_name'] ?? ''));
        $middleInitial = trim($conn->real_escape_string($_POST['middle_initial'] ?? ''));
        $lastName  = trim($conn->real_escape_string($_POST['last_name'] ?? ''));
        $email     = trim($conn->real_escape_string($_POST['email'] ?? ''));
        $employeeType = trim($conn->real_escape_string($_POST['employee_type'] ?? ''));
        $phone     = trim($conn->real_escape_string($_POST['phone_number'] ?? ''));
        $departmentId = isset($_POST['department']) ? (int) $_POST['department'] : null;
        $civilStatus = trim($conn->real_escape_string($_POST['civil_status'] ?? ''));
        $dob       = trim($_POST['date_of_birth'] ?? '');
        $salaryAmount = isset($_POST['basic_salary']) ? (float) $_POST['basic_salary'] : 0;
        
        // Address fields (using simple column names)
        $region = trim($conn->real_escape_string($_POST['region_name'] ?? ''));
        $province = trim($conn->real_escape_string($_POST['province_name'] ?? ''));
        $city = trim($conn->real_escape_string($_POST['city_name'] ?? ''));
        $barangay = trim($conn->real_escape_string($_POST['barangay_name'] ?? ''));
        $zipCode = trim($conn->real_escape_string($_POST['zip_code'] ?? ''));
        
        // Shift information
        $shiftType = trim($conn->real_escape_string($_POST['shift_type'] ?? ''));
        $shiftName = trim($conn->real_escape_string($_POST['shift_name'] ?? ''));
        $shiftStart = trim($conn->real_escape_string($_POST['shift_start'] ?? ''));
        $shiftEnd = trim($conn->real_escape_string($_POST['shift_end'] ?? ''));
        
        // Debug: Log the received city and zip
        error_log("Edit Employee - User ID: $userId, City: $city, Received Zip: $zipCode");
        
        // Get zip code if not provided or is 0000
        if (empty($zipCode) || $zipCode === '0000') {
            $zipCode = getZipCodeByCity($city);
            error_log("Edit Employee - Calculated Zip: $zipCode");
        }
        
        // Create full address string
        $fullAddress = "$barangay, $city, $province, $region, Philippines $zipCode";

        // Calculate age
        $dobDateTime = DateTime::createFromFormat('Y-m-d', $dob);
        if (!$dobDateTime) throw new Exception("Invalid date of birth format.");
        $today = new DateTime();
        $age = $today->diff($dobDateTime)->y;

        // Check if password is being updated
        $passwordUpdate = '';
        $passwordHash = '';
        
        if (!empty($_POST['password'])) {
            $newPassword = trim($_POST['password']);
            $confirmPassword = trim($_POST['confirm_password'] ?? '');
            
            if ($newPassword === $confirmPassword) {
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $passwordUpdate = ", password = ?";
                
                // Send password update email
                sendPasswordUpdateEmail($email, $firstName, $newPassword);
            }
        }

        // Update user with address columns and shift information
        if (!empty($passwordUpdate)) {
            $stmt = $conn->prepare("UPDATE users SET 
                first_name = ?, 
                middle_initial = ?, 
                last_name = ?, 
                email = ?, 
                employee_type = ?, 
                phone_number = ?, 
                department_id = ?, 
                civil_status = ?, 
                date_of_birth = ?, 
                age = ?, 
                address = ?,
                region = ?,
                province = ?,
                city = ?,
                barangay = ?,
                zip_code = ?,
                basic_salary = ?,
                shift_type = ?,
                shift_name = ?,
                shift_start = ?,
                shift_end = ?,
                password = ?
                WHERE id = ?");
            
            $stmt->bind_param(
                "ssssssssssssssssdsssssi",
                $firstName,
                $middleInitial,
                $lastName,
                $email,
                $employeeType,
                $phone,
                $departmentId,
                $civilStatus,
                $dob,
                $age,
                $fullAddress,
                $region,
                $province,
                $city,
                $barangay,
                $zipCode,
                $salaryAmount,
                $shiftType,
                $shiftName,
                $shiftStart,
                $shiftEnd,
                $passwordHash,
                $userId
            );
        } else {
            $stmt = $conn->prepare("UPDATE users SET 
                first_name = ?, 
                middle_initial = ?, 
                last_name = ?, 
                email = ?, 
                employee_type = ?, 
                phone_number = ?, 
                department_id = ?, 
                civil_status = ?, 
                date_of_birth = ?, 
                age = ?, 
                address = ?,
                region = ?,
                province = ?,
                city = ?,
                barangay = ?,
                zip_code = ?,
                basic_salary = ?,
                shift_type = ?,
                shift_name = ?,
                shift_start = ?,
                shift_end = ?
                WHERE id = ?");
            
            $stmt->bind_param(
                "ssssssssssssssssdssssi",
                $firstName,
                $middleInitial,
                $lastName,
                $email,
                $employeeType,
                $phone,
                $departmentId,
                $civilStatus,
                $dob,
                $age,
                $fullAddress,
                $region,
                $province,
                $city,
                $barangay,
                $zipCode,
                $salaryAmount,
                $shiftType,
                $shiftName,
                $shiftStart,
                $shiftEnd,
                $userId
            );
        }

        if ($stmt->execute()) {
            $message = "✅ Employee updated successfully!";
            
            // Check if password was updated and add to message
            if (!empty($_POST['password'])) {
                if ($newPassword === $confirmPassword) {
                    $message .= " Password has been updated and email notification sent.";
                } else {
                    $message .= " Note: Passwords did not match, password was not changed.";
                }
            }
        } else {
            $error = "❌ Error: " . $stmt->error;
        }
        $stmt->close();
    }

    // Search & filter logic
    $where = [];
    if (!empty($search)) {
        $where[] = "(users.first_name LIKE '%$search%' OR users.middle_initial LIKE '%$search%' OR users.last_name LIKE '%$search%' OR users.email LIKE '%$search%')";
    }
    if (!empty($filterDept)) {
        $where[] = "users.department_id = '$filterDept'";
    }
    $whereSQL = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Pagination
    $perPage = 10;
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $perPage;

    $totalResult = $conn->query("SELECT COUNT(*) AS total FROM users $whereSQL");
    $totalUsers = $totalResult->fetch_assoc()['total'];
    $totalPages = ceil($totalUsers / $perPage);

    // Fetch users with department names
    $sql = "SELECT 
                users.*, 
                departments.department_name
            FROM users 
            LEFT JOIN departments ON users.department_id = departments.id
            $whereSQL 
            ORDER BY users.last_name, users.first_name 
            LIMIT $perPage OFFSET $offset";
    
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

} catch (Exception $e) {
    $error = $e->getMessage();
} finally {
    if ($conn instanceof mysqli && $conn->ping()) $conn->close();
}

// Get regions for dropdown
$regions = getPhilippineRegions();

// Function to send password update email (for edit functionality)
function sendPasswordUpdateEmail($email, $firstName, $newPassword) {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email, $firstName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your UPC BioEnergy HRIS Password Has Been Updated';
        
        // Email body template
        $body = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #ffc107; color: #333; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background-color: #f8f9fa; padding: 30px; border-radius: 0 0 5px 5px; }
                .credentials { background-color: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d; }
                .warning { color: #dc3545; font-weight: bold; }
                .info { color: #0d6efd; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>UPC BioEnergy HRIS</h1>
                    <p>Password Update Notification</p>
                </div>
                <div class="content">
                    <h2>Password Updated</h2>
                    <p>Dear <strong>' . htmlspecialchars($firstName) . '</strong>,</p>
                    
                    <p>Your UPC BioEnergy HRIS account password has been updated by the HR department.</p>
                    
                    <div class="credentials">
                        <h3>Your New Login Credentials:</h3>
                        <p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>
                        <p><strong>New Password:</strong> ' . htmlspecialchars($newPassword) . '</p>
                    </div>
                    
                    <p class="info">Please use these credentials to log in to the HRIS system:</p>
                    <p class="warning">⚠️ For security reasons, we recommend that you change your password immediately after logging in.</p>
                    
                    <p>If you did not request this password change or have any concerns, please contact the HR department immediately.</p>
                    
                    <p>Best regards,<br>
                    <strong>HR Department</strong><br>
                    UPC BioEnergy</p>
                </div>
                <div class="footer">
                    <p>This is an automated message from UPC BioEnergy HRIS. Please do not reply to this email.</p>
                    <p>© ' . date('Y') . ' UPC BioEnergy. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
        
        $mail->Body = $body;
        
        // Plain text version
        $mail->AltBody = "Password Update Notification\n\n" .
                        "Dear $firstName,\n\n" .
                        "Your UPC BioEnergy HRIS account password has been updated by the HR department.\n\n" .
                        "New Login Credentials:\n" .
                        "Email: $email\n" .
                        "New Password: $newPassword\n\n" .
                        "Login URL: http://your-domain.com/login.php\n\n" .
                        "For security reasons, we recommend that you change your password immediately after logging in.\n\n" .
                        "If you did not request this password change, please contact the HR department immediately.\n\n" .
                        "Best regards,\n" .
                        "HR Department\n" .
                        "UPC BioEnergy";
        
        // Send email
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Password update email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Employees Management - UPC BioEnergy HRIS</title>
<link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
.step-indicator {
    padding: 8px 16px;
    margin: 0 5px;
    border-radius: 20px;
    background-color: #e9ecef;
    color: #6c757d;
    cursor: default;
    transition: all 0.3s;
}
.step-indicator.active {
    background-color: #0d6efd;
    color: white;
}
.step-indicator.completed {
    background-color: #198754;
    color: white;
}
.step-indicator:not(:last-child)::after {
    content: "→";
    margin-left: 10px;
    color: #6c757d;
}

.select2-container {
    width: 100% !important;
}
.select2-container .select2-selection--single {
    height: 38px !important;
    border: 1px solid #ced4da !important;
    border-radius: 0.375rem !important;
}
.select2-container .select2-selection--single .select2-selection__rendered {
    line-height: 36px !important;
    padding-left: 12px !important;
}
.select2-container .select2-selection--single .select2-selection__arrow {
    height: 36px !important;
    right: 8px !important;
}
.select2-container--default.select2-container--focus .select2-selection--single,
.select2-container--default.select2-container--open .select2-selection--single {
    border-color: #86b7fe !important;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
}
.select2-dropdown {
    border: 1px solid #ced4da !important;
    border-radius: 0.375rem !important;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

.address-loading {
    color: #6c757d;
    font-size: 0.875rem;
    margin-top: 0.25rem;
}

.spinner-border-sm {
    width: 1rem;
    height: 1rem;
    border-width: 0.2em;
}

.address-field-group {
    position: relative;
}

.address-field-group .loading {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    display: none;
}

.btn-sm i {
    font-size: 0.9rem;
}

.zip-code-loading {
    font-size: 0.75rem;
    color: #6c757d;
    margin-top: 2px;
}

.zip-code-success {
    font-size: 0.75rem;
    color: #198754;
    margin-top: 2px;
}

.zip-code-error {
    font-size: 0.75rem;
    color: #dc3545;
    margin-top: 2px;
}

.shift-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.85rem;
    font-weight: 500;
    display: inline-block;
}

.shift-day {
    background-color: #e7f5ff;
    color: #0c63e4;
    border: 1px solid #0c63e4;
}

.shift-morning {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #856404;
}

.shift-afternoon {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #155724;
}

.shift-night {
    background-color: #343a40;
    color: white;
    border: 1px solid #343a40;
}

.shift-custom {
    background-color: #f8f9fa;
    color: #495057;
    border: 1px solid #dee2e6;
}

.shift-preview {
    padding: 10px;
    border-radius: 5px;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    margin-top: 5px;
    font-size: 0.9rem;
}

.email-notification {
    background-color: #d1ecf1;
    border-color: #bee5eb;
    color: #0c5460;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 15px;
    font-size: 0.9rem;
}
</style>
</head>
<body>
<main id="main" class="main">
  <div class="pagetitle"><h1>Employees Management</h1></div>
  <section class="section">
    <div class="row">
      <div class="col-lg-12">

        <?php if ($message): ?><div class="alert alert-success"><?= $message ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

        <div class="card mb-4">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h5 class="card-title">Add New Employee</h5>
                <p class="card-text">Manage employee records and DTR</p>
              </div>
              <div class="btn-group">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                  <i class="bi bi-person-plus"></i> Add Employee
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Search & Filter -->
        <form method="GET" class="row g-3 mb-3">
          <div class="col-md-6">
            <input type="text" class="form-control" name="search" placeholder="Search by name or email"
                   value="<?= htmlspecialchars($search) ?>">
          </div>
          <div class="col-md-4">
            <select class="form-select" name="filter_dept">
              <option value="">All Departments</option>
              <?php foreach ($departments as $dept): ?>
                <option value="<?= $dept['id'] ?>" <?= ($filterDept == $dept['id']) ? 'selected' : '' ?>><?= htmlspecialchars($dept['department_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <button class="btn btn-primary"><i class="bi bi-search"></i> Filter</button>
          </div>
        </form>

        <!-- Employee Table -->
        <?php if (!empty($users)): ?>
        <table class="table table-hover table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Employee Type</th>
              <th>Department</th>
              <th>Civil Status</th>
              <th>DOB</th>
              <th>Age</th>
              <th>Shift</th>
              <th>Date Registered</th>
              <th>Address</th>
              <th>Rate (₱)</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $user): 
                // Get shift display
                $shiftDisplay = 'Not set';
                $shiftClass = '';
                if (!empty($user['shift_type'])) {
                    if ($user['shift_type'] == 'custom') {
                        $shiftDisplay = htmlspecialchars($user['shift_name'] ?? 'Custom Shift');
                        if (!empty($user['shift_start']) && !empty($user['shift_end'])) {
                            $shiftDisplay .= ' (' . date('g:i A', strtotime($user['shift_start'])) . 
                                             ' - ' . date('g:i A', strtotime($user['shift_end'])) . ')';
                        }
                        $shiftClass = 'shift-custom';
                    } else {
                        $shiftNames = [
                            'day_shift' => 'Day Shift (8:00 AM - 5:00 PM)',
                            'morning_shift' => 'Morning Shift (6:00 AM - 3:00 PM)',
                            'afternoon_shift' => 'Afternoon Shift (3:00 PM - 12:00 AM)',
                            'night_shift' => 'Night Shift (10:00 PM - 7:00 AM)'
                        ];
                        $shiftDisplay = $shiftNames[$user['shift_type']] ?? $user['shift_type'];
                        
                        // Set CSS class based on shift type
                        switch($user['shift_type']) {
                            case 'day_shift': $shiftClass = 'shift-day'; break;
                            case 'morning_shift': $shiftClass = 'shift-morning'; break;
                            case 'afternoon_shift': $shiftClass = 'shift-afternoon'; break;
                            case 'night_shift': $shiftClass = 'shift-night'; break;
                            default: $shiftClass = 'shift-custom'; break;
                        }
                    }
                }
            ?>
            <tr>
              <td><?= htmlspecialchars($user['first_name'].' '.$user['middle_initial'].' '.$user['last_name']) ?></td>
              <td><?= htmlspecialchars($user['email']) ?></td>
              <td><?= htmlspecialchars($user['phone_number']) ?></td>
              <td><?= htmlspecialchars($user['employee_type']) ?></td>
              <td><?= htmlspecialchars($user['department_name'] ?? '') ?></td>
              <td><?= htmlspecialchars($user['civil_status']) ?></td>
              <td><?= htmlspecialchars($user['date_of_birth']) ?></td>
              <td><?= htmlspecialchars($user['age']) ?></td>
              <td>
                <span class="shift-badge <?= $shiftClass ?>"><?= $shiftDisplay ?></span>
              </td>
              <td><?= htmlspecialchars($user['date_registered']) ?></td>
              <td>
                <?php if (!empty($user['barangay'])): ?>
                  <?= htmlspecialchars($user['barangay']) ?>, <?= htmlspecialchars($user['city']) ?>, 
                  <?= htmlspecialchars($user['province']) ?>, <?= htmlspecialchars($user['region']) ?>, 
                  <?= htmlspecialchars($user['zip_code']) ?>
                <?php elseif (!empty($user['address'])): ?>
                  <?= htmlspecialchars($user['address']) ?>
                <?php else: ?>
                  No address set
                <?php endif; ?>
              </td>
              <td>₱ <?= number_format($user['basic_salary'] ?? 0, 2) ?></td>
              <td>
                <div class="btn-group" role="group">
                  <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewUserModal<?= $user['id'] ?>">
                    <i class="bi bi-eye"></i>
                  </button>
                  <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $user['id'] ?>">
                    <i class="bi bi-pencil"></i>
                  </button>
                </div>
              </td>
            </tr>

            <!-- View Details Modal -->
            <div class="modal fade" id="viewUserModal<?= $user['id'] ?>" tabindex="-1">
              <div class="modal-dialog">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title">Employee Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <p><strong>Name:</strong> <?= htmlspecialchars($user['first_name'].' '.$user['middle_initial'].' '.$user['last_name']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                    <p><strong>Phone:</strong> <?= htmlspecialchars($user['phone_number']) ?></p>
                    <p><strong>Employee Type:</strong> <?= htmlspecialchars($user['employee_type']) ?></p>
                    <p><strong>Department:</strong> <?= htmlspecialchars($user['department_name'] ?? '') ?></p>
                    <p><strong>Civil Status:</strong> <?= htmlspecialchars($user['civil_status']) ?></p>
                    <p><strong>DOB:</strong> <?= htmlspecialchars($user['date_of_birth']) ?></p>
                    <p><strong>Age:</strong> <?= htmlspecialchars($user['age']) ?></p>
                    <p><strong>Shift:</strong> <span class="shift-badge <?= $shiftClass ?>"><?= $shiftDisplay ?></span></p>
                    <p><strong>Date Registered:</strong> <?= htmlspecialchars($user['date_registered']) ?></p>
                    <p><strong>Address:</strong> 
                      <?php if (!empty($user['barangay'])): ?>
                        <?= htmlspecialchars($user['barangay']) ?>, <?= htmlspecialchars($user['city']) ?>, 
                        <?= htmlspecialchars($user['province']) ?>, <?= htmlspecialchars($user['region']) ?>, 
                        <?= htmlspecialchars($user['zip_code']) ?>
                      <?php elseif (!empty($user['address'])): ?>
                        <?= htmlspecialchars($user['address']) ?>
                      <?php else: ?>
                        No address set
                      <?php endif; ?>
                    </p>
                    <p><strong>Rate:</strong> ₱ <?= number_format($user['basic_salary'] ?? 0, 2) ?></p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Edit Employee Modal -->
            <div class="modal fade" id="editUserModal<?= $user['id'] ?>" tabindex="-1">
              <div class="modal-dialog modal-lg">
                <div class="modal-content">
                  <form method="POST">
                    <div class="modal-header">
                      <h5 class="modal-title">Edit Employee</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                      <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                      
                      <h6>Personal Information</h6>
                      <div class="row g-3">
                        <div class="col-md-4">
                          <label>First Name</label>
                          <input type="text" class="form-control" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                        </div>
                        <div class="col-md-2">
                          <label>Middle Name</label>
                          <input type="text" class="form-control" name="middle_initial" value="<?= htmlspecialchars($user['middle_initial']) ?>">
                        </div>
                        <div class="col-md-4">
                          <label>Last Name</label>
                          <input type="text" class="form-control" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                          <label>Email</label>
                          <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                          <label>Phone</label>
                          <input type="text" class="form-control" name="phone_number" value="<?= htmlspecialchars($user['phone_number']) ?>">
                        </div>
                        <div class="col-md-6">
                          <label>Date of Birth</label>
                          <input type="date" class="form-control" name="date_of_birth" id="edit_dob_<?= $user['id'] ?>" 
                                 value="<?= htmlspecialchars($user['date_of_birth']) ?>" required>
                        </div>
                        <div class="col-md-6">
                          <label>Age</label>
                          <input type="text" class="form-control" id="edit_age_<?= $user['id'] ?>" value="<?= htmlspecialchars($user['age']) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                          <label>Civil Status</label>
                          <select class="form-select" name="civil_status" required>
                            <option value="Single" <?= $user['civil_status'] == 'Single' ? 'selected' : '' ?>>Single</option>
                            <option value="Married" <?= $user['civil_status'] == 'Married' ? 'selected' : '' ?>>Married</option>
                            <option value="Divorced" <?= $user['civil_status'] == 'Divorced' ? 'selected' : '' ?>>Divorced</option>
                            <option value="Widowed" <?= $user['civil_status'] == 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                          </select>
                        </div>
                      </div>

                      <h6 class="mt-3">Address</h6>
                      <div class="row g-3">
                        <div class="col-md-4">
                          <label>Country</label>
                          <select class="form-select" name="country" disabled required>
                            <option value="Philippines" selected>Philippines</option>
                          </select>
                        </div>
                        <div class="col-md-4">
                          <label>Region</label>
                          <select class="form-select address-select" name="region_code" id="edit_region_<?= $user['id'] ?>" required>
                            <option value="">Select Region</option>
                            <?php foreach ($regions as $region): ?>
                              <option value="<?= htmlspecialchars($region['code']) ?>" 
                                      data-name="<?= htmlspecialchars($region['regionName']) ?>"
                                      <?= ($user['region'] == $region['regionName']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($region['regionName']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                          <input type="hidden" name="region_name" id="edit_region_name_<?= $user['id'] ?>" value="<?= htmlspecialchars($user['region']) ?>">
                        </div>
                        <div class="col-md-4">
                          <label>Province</label>
                          <select class="form-select address-select" name="province_code" id="edit_province_<?= $user['id'] ?>" required>
                            <option value="">Select Province</option>
                            <?php if (!empty($user['province'])): ?>
                              <option value="<?= htmlspecialchars($user['province']) ?>" 
                                      data-name="<?= htmlspecialchars($user['province']) ?>" selected>
                                <?= htmlspecialchars($user['province']) ?>
                              </option>
                            <?php endif; ?>
                          </select>
                          <input type="hidden" name="province_name" id="edit_province_name_<?= $user['id'] ?>" value="<?= htmlspecialchars($user['province']) ?>">
                        </div>
                        <div class="col-md-4">
                          <label>City/Municipality</label>
                          <select class="form-select address-select" name="city_code" id="edit_city_<?= $user['id'] ?>" required>
                            <option value="">Select City/Municipality</option>
                            <?php if (!empty($user['city'])): ?>
                              <option value="<?= htmlspecialchars($user['city']) ?>" 
                                      data-name="<?= htmlspecialchars($user['city']) ?>" selected>
                                <?= htmlspecialchars($user['city']) ?>
                              </option>
                            <?php endif; ?>
                          </select>
                          <input type="hidden" name="city_name" id="edit_city_name_<?= $user['id'] ?>" value="<?= htmlspecialchars($user['city']) ?>">
                          <div class="zip-code-loading" id="edit_zip_loading_<?= $user['id'] ?>" style="display: none;">
                            <span class="spinner-border spinner-border-sm" role="status"></span> Getting ZIP code...
                          </div>
                          <div class="zip-code-success" id="edit_zip_success_<?= $user['id'] ?>" style="display: none;"></div>
                          <div class="zip-code-error" id="edit_zip_error_<?= $user['id'] ?>" style="display: none;"></div>
                        </div>
                        <div class="col-md-4">
                          <label>Barangay</label>
                          <select class="form-select address-select" name="barangay_code" id="edit_barangay_<?= $user['id'] ?>" required>
                            <option value="">Select Barangay</option>
                            <?php if (!empty($user['barangay'])): ?>
                              <option value="<?= htmlspecialchars($user['barangay']) ?>" 
                                      data-name="<?= htmlspecialchars($user['barangay']) ?>" selected>
                                <?= htmlspecialchars($user['barangay']) ?>
                              </option>
                            <?php endif; ?>
                          </select>
                          <input type="hidden" name="barangay_name" id="edit_barangay_name_<?= $user['id'] ?>" value="<?= htmlspecialchars($user['barangay']) ?>">
                        </div>
                        <div class="col-md-4">
                          <label>Zip Code</label>
                          <input type="text" class="form-control" name="zip_code" 
                                 id="edit_zip_<?= $user['id'] ?>" 
                                 value="<?= htmlspecialchars($user['zip_code'] ?? '') ?>" required>
                          <div class="form-text">Auto-filled when you select a city</div>
                        </div>
                      </div>

                      <h6 class="mt-3">Work Information</h6>
                      <div class="row g-3">
                        <div class="col-md-6">
                          <label>Department</label>
                          <select class="form-select" name="department" required>
                            <option value="" disabled>Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                              <option value="<?= $dept['id'] ?>" <?= $user['department_id'] == $dept['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['department_name']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="col-md-6">
                          <label>Employee Type</label>
                          <select class="form-select" name="employee_type" required>
                            <option value="Probationary" <?= $user['employee_type'] == 'Probationary' ? 'selected' : '' ?>>Probationary</option>
                            <option value="Regular" <?= $user['employee_type'] == 'Regular' ? 'selected' : '' ?>>Regular</option>
                            <option value="Project-Based" <?= $user['employee_type'] == 'Project-Based' ? 'selected' : '' ?>>Project-Based</option>
                            <option value="Contractual" <?= $user['employee_type'] == 'Contractual' ? 'selected' : '' ?>>Contractual</option>
                          </select>
                        </div>
                        
                        <input type="hidden" name="shift_start" id="edit_hidden_shift_start_<?= $user['id'] ?>" 
                              value="<?= htmlspecialchars($user['shift_start'] ?? '08:00') ?>">
                        <input type="hidden" name="shift_end" id="edit_hidden_shift_end_<?= $user['id'] ?>" 
                              value="<?= htmlspecialchars($user['shift_end'] ?? '17:00') ?>">
                        <input type="hidden" name="shift_name" id="edit_hidden_shift_name_<?= $user['id'] ?>" 
                              value="<?= htmlspecialchars($user['shift_name'] ?? 'Day Shift') ?>">

                        <!-- Custom Shift Fields (Initially Hidden) -->
                        <div class="col-md-6" id="edit_custom_shift_name_container_<?= $user['id'] ?>" style="<?= ($user['shift_type'] == 'custom') ? '' : 'display: none;' ?>">
                          <label>Custom Shift Name</label>
                          <input type="text" class="form-control" name="custom_shift_name" id="edit_custom_shift_name_<?= $user['id'] ?>" 
                                value="<?= htmlspecialchars($user['shift_name'] ?? '') ?>" 
                                placeholder="e.g., Mid Shift, Graveyard">
                        </div>

                        <div class="col-md-3" id="edit_shift_start_container_<?= $user['id'] ?>" style="<?= ($user['shift_type'] == 'custom') ? '' : 'display: none;' ?>">
                          <label>Shift Start Time</label>
                          <input type="time" class="form-control" name="custom_shift_start" id="edit_custom_shift_start_<?= $user['id'] ?>" 
                                value="<?= htmlspecialchars($user['shift_start'] ?? '') ?>">
                        </div>

                        <div class="col-md-3" id="edit_shift_end_container_<?= $user['id'] ?>" style="<?= ($user['shift_type'] == 'custom') ? '' : 'display: none;' ?>">
                          <label>Shift End Time</label>
                          <input type="time" class="form-control" name="custom_shift_end" id="edit_custom_shift_end_<?= $user['id'] ?>" 
                                value="<?= htmlspecialchars($user['shift_end'] ?? '') ?>">
                        </div>
                        
                        <div class="col-md-6">
                          <label>Rate (₱)</label>
                          <input type="number" class="form-control" name="basic_salary" 
                                 value="<?= htmlspecialchars($user['basic_salary'] ?? 0) ?>" 
                                 step="0.01" min="0" placeholder="0.00" required>
                        </div>
                      </div>

                      <h6 class="mt-3">Change Password (Optional)</h6>
                      <div class="row g-3">
                        <div class="col-md-6">
                          <label>New Password</label>
                          <input type="password" class="form-control" name="password" placeholder="Leave blank to keep current password">
                          <div class="form-text">If you change the password, an email will be sent to the employee.</div>
                        </div>
                        <div class="col-md-6">
                          <label>Confirm Password</label>
                          <input type="password" class="form-control" name="confirm_password" placeholder="Leave blank to keep current password">
                        </div>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" name="edit_user" class="btn btn-success">Save Changes</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <?php endforeach; ?>
          </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Page navigation">
          <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>&search=<?= htmlspecialchars($search) ?>&filter_dept=<?= htmlspecialchars($filterDept) ?>">
                  <?= $i ?>
                </a>
              </li>
            <?php endfor; ?>
          </ul>
        </nav>
        <?php endif; ?>
        
        <?php else: ?>
          <div class="alert alert-info">No employees found.</div>
        <?php endif; ?>

      </div>
    </div>
  </section>
</main>

<!-- Add Employee Modal (3-Step with DTR included) -->
<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" id="addEmployeeForm">
        <div class="modal-header">
          <h5 class="modal-title">Add Employee</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">

          <!-- Step Indicators -->
          <div class="d-flex justify-content-center mb-4">
            <div class="step-indicator active" data-step="1">1. Personal Info</div>
            <div class="step-indicator" data-step="2">2. Work Info</div>
            <div class="step-indicator" data-step="3">3. Add DTR</div>
          </div>

          <!-- Email Notification Alert -->
          <div class="email-notification">
            <i class="bi bi-envelope-check"></i> 
            <strong>Email Notification:</strong> Login credentials will be automatically sent to the employee's email address.
          </div>

          <!-- Step 1: Personal Info -->
          <div class="step" id="step1">
            <h6>Personal Information</h6>
            <div class="row g-3">
              <div class="col-md-4"><label>First Name</label><input type="text" class="form-control" name="first_name" required></div>
              <div class="col-md-2"><label>Middle Name</label><input type="text" class="form-control" name="middle_initial"></div>
              <div class="col-md-4"><label>Last Name</label><input type="text" class="form-control" name="last_name" required></div>
              <div class="col-md-6"><label>Email</label><input type="email" class="form-control" name="email" required>
                <div class="form-text">Login credentials will be sent to this email</div>
              </div>
              <div class="col-md-3"><label>Password</label><input type="password" class="form-control" name="password" id="password" required></div>
              <div class="col-md-3"><label>Confirm Password</label><input type="password" class="form-control" id="confirm_password" required></div>
              <div class="col-md-6"><label>Phone</label><input type="text" class="form-control" name="phone_number"></div>
              <div class="col-md-6"><label>Date of Birth</label><input type="date" class="form-control" name="date_of_birth" id="dob" required></div>
              <div class="col-md-6"><label>Age</label><input type="text" class="form-control" id="age" readonly></div>
              <div class="col-md-6">
                <label>Civil Status</label>
                <select class="form-select" name="civil_status" required>
                  <option value="">Select Civil Status</option>
                  <option value="Single">Single</option>
                  <option value="Married">Married</option>
                  <option value="Divorced">Divorced</option>
                  <option value="Widowed">Widowed</option>
                </select>
              </div>
            </div>

            <h6 class="mt-3">Address</h6>
            <div class="row g-3">
              <div class="col-md-4">
                <label>Country</label>
                <select class="form-select" name="country" disabled required>
                  <option value="Philippines" selected>Philippines</option>
                </select>
              </div>
              <div class="col-md-4">
                <div class="address-field-group">
                  <label>Region</label>
                  <select class="form-select address-select" name="region_code" id="add_region" required>
                    <option value="">Select Region</option>
                    <?php foreach ($regions as $region): ?>
                      <option value="<?= htmlspecialchars($region['code']) ?>" 
                              data-name="<?= htmlspecialchars($region['regionName']) ?>">
                        <?= htmlspecialchars($region['regionName']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="loading" id="region_loading">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                  </div>
                </div>
                <input type="hidden" name="region_name" id="add_region_name">
              </div>
              <div class="col-md-4">
                <div class="address-field-group">
                  <label>Province</label>
                  <select class="form-select address-select" name="province_code" id="add_province" required disabled>
                    <option value="">Select Province</option>
                  </select>
                  <div class="loading" id="province_loading">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                  </div>
                </div>
                <input type="hidden" name="province_name" id="add_province_name">
              </div>
              <div class="col-md-4">
                <div class="address-field-group">
                  <label>City/Municipality</label>
                  <select class="form-select address-select" name="city_code" id="add_city" required disabled>
                    <option value="">Select City/Municipality</option>
                  </select>
                  <div class="loading" id="city_loading">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                  </div>
                </div>
                <input type="hidden" name="city_name" id="add_city_name">
                <div class="zip-code-loading" id="zip_loading" style="display: none;">
                  <span class="spinner-border spinner-border-sm" role="status"></span> Getting ZIP code...
                </div>
                <div class="zip-code-success" id="zip_success" style="display: none;"></div>
                <div class="zip-code-error" id="zip_error" style="display: none;"></div>
              </div>
              <div class="col-md-4">
                <div class="address-field-group">
                  <label>Barangay</label>
                  <select class="form-select address-select" name="barangay_code" id="add_barangay" required disabled>
                    <option value="">Select Barangay</option>
                  </select>
                  <div class="loading" id="barangay_loading">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                  </div>
                </div>
                <input type="hidden" name="barangay_name" id="add_barangay_name">
              </div>
              <div class="col-md-4">
                <label>Zip Code</label>
                <input type="text" class="form-control" name="zip_code" id="add_zip" required readonly>
                <div class="form-text">Auto-filled when you select a city</div>
              </div>
            </div>
          </div>
 
          <!-- Step 2: Work Info -->
          <div class="step d-none" id="step2">
            <h6>Work Information</h6>
            <div class="row g-3">
              <div class="col-md-6">
                <label>Department</label>
                <select class="form-select" name="department" required>
                  <option value="" disabled selected>Select Department</option>
                  <?php foreach ($departments as $dept): ?>
                    <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label>Employee Type</label>
                <select class="form-select" name="employee_type" required>
                  <option value="" disabled selected>Select Employee Type</option>
                  <option value="Probationary">Probationary</option>
                  <option value="Regular">Regular</option>
                  <option value="Project-Based">Project-Based</option>
                  <option value="Contractual">Contractual</option>
                </select>
              </div>
              
              <!-- Shift Selection Section -->
              <div class="col-md-6">
                <label>Shift</label>
                <select class="form-select" name="shift_type" id="shift_type" required>
                  <option value="" disabled selected>Select Shift</option>
                  <option value="day_shift">Day Shift (8:00 AM - 5:00 PM)</option>
                  <option value="morning_shift">Morning Shift (6:00 AM - 3:00 PM)</option>
                  <option value="afternoon_shift">Afternoon Shift (3:00 PM - 12:00 AM)</option>
                  <option value="night_shift">Night Shift (10:00 PM - 7:00 AM)</option>
                  <option value="custom">Custom Shift</option>
                </select>
              </div>

              <!-- Hidden inputs for predefined shift times -->
              <input type="hidden" name="shift_start" id="hidden_shift_start" value="08:00">
              <input type="hidden" name="shift_end" id="hidden_shift_end" value="17:00">
              <input type="hidden" name="shift_name" id="hidden_shift_name" value="Day Shift">

              <!-- Custom Shift Fields (Initially Hidden) -->
              <div class="col-md-6" id="custom_shift_name_container" style="display: none;">
                <label>Custom Shift Name</label>
                <input type="text" class="form-control" name="custom_shift_name" id="custom_shift_name" placeholder="e.g., Mid Shift, Graveyard">
              </div>

              <div class="col-md-3" id="shift_start_container" style="display: none;">
                <label>Shift Start Time</label>
                <input type="time" class="form-control" name="custom_shift_start" id="custom_shift_start" value="08:00">
              </div>

              <div class="col-md-3" id="shift_end_container" style="display: none;">
                <label>Shift End Time</label>
                <input type="time" class="form-control" name="custom_shift_end" id="custom_shift_end" value="17:00">
              </div>

              <!-- Shift Preview -->
              <div class="col-md-12">
                <div class="shift-preview" id="shift_preview" style="display: none;">
                  <strong>Shift Details:</strong>
                  <span id="shift_details"></span>
                </div>
              </div>
                            
              <div class="col-md-6">
                <label>Rate (₱)</label>
                <input type="number" class="form-control" name="basic_salary" 
                       step="0.01" min="0" placeholder="0.00" required>
              </div>
            </div>
          </div>

          <!-- Step 3: Add DTR -->
          <div class="step d-none" id="step3">
            <h6>Add Initial Daily Time Record (Optional)</h6>
            <div class="alert alert-info">
              <i class="bi bi-info-circle"></i> This is optional. You can add DTR records later from the employee's DTR tab.
            </div>
            
            <div class="row g-3">
              <div class="col-md-6">
                <label>Date</label>
                <input type="date" class="form-control" name="dtr_date" id="dtr_date" value="<?= date('Y-m-d') ?>">
              </div>
              <div class="col-md-3">
                <label>Time In</label>
                <input type="time" class="form-control" name="time_in" id="time_in" value="08:00">
              </div>
              <div class="col-md-3">
                <label>Time Out</label>
                <input type="time" class="form-control" name="time_out" id="time_out" value="17:00">
              </div>
              <div class="col-md-12">
                <label>Remarks</label>
                <textarea class="form-control" name="remarks" id="remarks" rows="2" placeholder="Any additional notes (optional)"></textarea>
              </div>
              <div class="col-md-12">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="add_dtr_checkbox" checked>
                  <label class="form-check-label" for="add_dtr_checkbox">
                    Add this DTR record for the new employee
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="modal-footer">
          <div id="stepButtons">
            <button type="button" class="btn btn-secondary" id="prevStep" style="display: none;">Previous</button>
            <button type="button" class="btn btn-primary" id="nextStep">Next: Work Info</button>
            <button type="submit" name="add_user" class="btn btn-success d-none" id="submitBtn">Add Employee</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Global function to initialize Select2 for address dropdowns
    function initSelect2() {
        $('.address-select').each(function() {
            const $select = $(this);
            const $modal = $select.closest('.modal');
            
            if (!$select.hasClass('select2-hidden-accessible')) {
                $select.select2({
                    placeholder: "Select an option",
                    allowClear: false,
                    width: '100%',
                    dropdownParent: $modal.length ? $modal : $('#addUserModal, .modal.show')
                });
            }
        });
    }
    
    // Initialize Select2 for existing selects on page load
    initSelect2();
    
    // Reinitialize when any modal is shown
    $('.modal').on('shown.bs.modal', function() {
        initSelect2();
        resetAddressFields();
    });

    // Variables for step management
    const step1 = $('#step1');
    const step2 = $('#step2');
    const step3 = $('#step3');
    const nextStepBtn = $('#nextStep');
    const prevStepBtn = $('#prevStep');
    const submitBtn = $('#submitBtn');
    const stepIndicators = $('.step-indicator');
    let currentStep = 1;
    const totalSteps = 3;
    
    // Initialize step indicators
    updateStepIndicators();

    // Auto-calculate age for add form
    $('#dob').on('change', function() {
        const dob = new Date(this.value);
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
        $('#age').val(age);
    });

    // Auto-calculate age for all edit forms
    <?php foreach ($users as $user): ?>
    $('#edit_dob_<?= $user['id'] ?>')?.on('change', function() {
        const dob = new Date(this.value);
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
        $('#edit_age_<?= $user['id'] ?>').val(age);
    });
    <?php endforeach; ?>

    function updateStepIndicators() {
        stepIndicators.each(function() {
            const stepNum = parseInt($(this).data('step'));
            if (stepNum === currentStep) {
                $(this).addClass('active fw-bold');
            } else if (stepNum < currentStep) {
                $(this).addClass('completed').removeClass('active fw-bold');
            } else {
                $(this).removeClass('active completed fw-bold');
            }
        });
    }

    function validateStep(step) {
        let isValid = true;
        
        if (step === 1) {
            // Password validation
            const pw = $('#password').val();
            const cpw = $('#confirm_password').val();
            if (pw !== cpw) { 
                alert('Passwords do not match!'); 
                $('#password, #confirm_password').addClass('is-invalid');
                return false; 
            } else {
                $('#password, #confirm_password').removeClass('is-invalid');
            }

            // Address validation
            const region = $('#add_region').val();
            const province = $('#add_province').val();
            const city = $('#add_city').val();
            const barangay = $('#add_barangay').val();
            const zip = $('#add_zip').val();
            
            if (!region || !province || !city || !barangay || !zip || zip === '0000') {
                alert('Please complete all address fields with valid information.');
                return false;
            }

            // Basic form validation for Step 1
            const requiredFields = step1.find('[required]');
            requiredFields.each(function() {
                if (!$(this).val().trim()) {
                    isValid = false;
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });
            
            if (!isValid) {
                alert('Please fill in all required fields in Personal Information.');
            }
        } else if (step === 2) {
            // Validate shift selection
            const shiftType = $('#shift_type').val();
            if (!shiftType) {
                alert('Please select a shift.');
                return false;
            }
            
            if (shiftType === 'custom') {
                const shiftStart = $('#shift_start').val();
                const shiftEnd = $('#shift_end').val();
                if (!shiftStart || !shiftEnd) {
                    alert('Please enter both shift start and end times for custom shift.');
                    return false;
                }
            }
        }
        
        return isValid;
    }

    function showStep(step) {
        // Hide all steps
        [step1, step2, step3].forEach(s => s.addClass('d-none'));
        
        // Show current step
        if (step === 1) {
            step1.removeClass('d-none');
            prevStepBtn.hide();
            nextStepBtn.show().text('Next: Work Info');
            submitBtn.addClass('d-none');
        } else if (step === 2) {
            step2.removeClass('d-none');
            prevStepBtn.show();
            nextStepBtn.show().text('Next: Add DTR');
            submitBtn.addClass('d-none');
        } else if (step === 3) {
            step3.removeClass('d-none');
            prevStepBtn.show();
            nextStepBtn.hide();
            submitBtn.removeClass('d-none').text('Add Employee');
        }
        
        currentStep = step;
        updateStepIndicators();
        
        // Reinitialize Select2 for newly shown selects
        setTimeout(() => {
            initSelect2();
        }, 100);
    }

    // Next step button
    nextStepBtn.on('click', function() {
        if (!validateStep(currentStep)) return;
        
        if (currentStep < totalSteps) {
            showStep(currentStep + 1);
        }
    });

    // Previous step button
    prevStepBtn.on('click', function() {
        if (currentStep > 1) {
            showStep(currentStep - 1);
        }
    });

    // Reset address fields
    function resetAddressFields() {
        $('#add_region').val('').trigger('change');
        $('#add_region_name').val('');
        $('#add_province').html('<option value="">Select Province</option>').val('').trigger('change');
        $('#add_province').prop('disabled', true);
        $('#add_province_name').val('');
        $('#add_city').html('<option value="">Select City/Municipality</option>').val('').trigger('change');
        $('#add_city').prop('disabled', true);
        $('#add_city_name').val('');
        $('#add_barangay').html('<option value="">Select Barangay</option>').val('').trigger('change');
        $('#add_barangay').prop('disabled', true);
        $('#add_barangay_name').val('');
        $('#add_zip').val('').prop('readonly', true);
        
        // Hide all loading indicators and messages
        $('.loading').hide();
        $('#zip_loading').hide();
        $('#zip_success').hide().text('');
        $('#zip_error').hide().text('');
    }

    // Show loading indicator
    function showLoading(selector) {
        $(selector).show();
    }
    
    // Hide loading indicator
    function hideLoading(selector) {
        $(selector).hide();
    }

    // When region changes, load provinces via AJAX - ADD FORM
    $('#add_region').on('change', function() {
        const regionCode = $(this).val();
        const regionName = $(this).find(':selected').data('name');
        const provinceSelect = $('#add_province');
        const citySelect = $('#add_city');
        const barangaySelect = $('#add_barangay');
        const zipInput = $('#add_zip');
        
        // Update hidden field
        $('#add_region_name').val(regionName);
        
        if (!regionCode) {
            provinceSelect.html('<option value="">Select Region First</option>').val('').trigger('change');
            provinceSelect.prop('disabled', true);
            citySelect.html('<option value="">Select City/Municipality</option>').val('').trigger('change');
            citySelect.prop('disabled', true);
            barangaySelect.html('<option value="">Select Barangay</option>').val('').trigger('change');
            barangaySelect.prop('disabled', true);
            zipInput.val('').prop('readonly', true);
            $('#zip_loading').hide();
            return;
        }
        
        // Show loading indicator
        showLoading('#province_loading');
        provinceSelect.prop('disabled', true);
        citySelect.prop('disabled', true);
        barangaySelect.prop('disabled', true);
        zipInput.val('').prop('readonly', true);
        
        // Fetch provinces via AJAX
        $.ajax({
            url: 'api_handler.php',
            method: 'POST',
            data: {
                action: 'get_provinces',
                region_code: regionCode
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data && response.data.length > 0) {
                    let options = '<option value="">Select Province</option>';
                    response.data.forEach(province => {
                        options += `<option value="${province.code}" data-name="${province.name}">${province.name}</option>`;
                    });
                    
                    provinceSelect.html(options).val('').trigger('change');
                    provinceSelect.prop('disabled', false);
                    
                    // Clear downstream fields
                    citySelect.html('<option value="">Select City/Municipality</option>').val('').trigger('change');
                    citySelect.prop('disabled', true);
                    barangaySelect.html('<option value="">Select Barangay</option>').val('').trigger('change');
                    barangaySelect.prop('disabled', true);
                    zipInput.val('').prop('readonly', true);
                } else {
                    provinceSelect.html('<option value="">No provinces found</option>').val('').trigger('change');
                    provinceSelect.prop('disabled', true);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading provinces:', error);
                provinceSelect.html('<option value="">Error loading provinces</option>').val('').trigger('change');
                provinceSelect.prop('disabled', true);
            },
            complete: function() {
                hideLoading('#province_loading');
            }
        });
    });

    // When province changes, load cities via AJAX - ADD FORM
    $('#add_province').on('change', function() {
        const provinceCode = $(this).val();
        const provinceName = $(this).find(':selected').data('name');
        const citySelect = $('#add_city');
        const barangaySelect = $('#add_barangay');
        const zipInput = $('#add_zip');
        
        // Update hidden field
        $('#add_province_name').val(provinceName);
        
        if (!provinceCode) {
            citySelect.html('<option value="">Select Province First</option>').val('').trigger('change');
            citySelect.prop('disabled', true);
            barangaySelect.html('<option value="">Select Barangay</option>').val('').trigger('change');
            barangaySelect.prop('disabled', true);
            zipInput.val('').prop('readonly', true);
            $('#zip_loading').hide();
            return;
        }
        
        // Show loading indicator
        showLoading('#city_loading');
        citySelect.prop('disabled', true);
        barangaySelect.prop('disabled', true);
        zipInput.val('').prop('readonly', true);
        
        // Fetch cities via AJAX
        $.ajax({
            url: 'api_handler.php',
            method: 'POST',
            data: {
                action: 'get_cities',
                province_code: provinceCode
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data && response.data.length > 0) {
                    let options = '<option value="">Select City/Municipality</option>';
                    response.data.forEach(city => {
                        const type = city.isCity ? ' (City)' : ' (Municipality)';
                        options += `<option value="${city.code}" data-name="${city.name}">${city.name}${type}</option>`;
                    });
                    
                    citySelect.html(options).val('').trigger('change');
                    citySelect.prop('disabled', false);
                    
                    // Clear downstream fields
                    barangaySelect.html('<option value="">Select Barangay</option>').val('').trigger('change');
                    barangaySelect.prop('disabled', true);
                    zipInput.val('').prop('readonly', true);
                } else {
                    citySelect.html('<option value="">No cities/municipalities found</option>').val('').trigger('change');
                    citySelect.prop('disabled', true);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading cities:', error);
                citySelect.html('<option value="">Error loading cities</option>').val('').trigger('change');
                citySelect.prop('disabled', true);
            },
            complete: function() {
                hideLoading('#city_loading');
            }
        });
    });

    // When city changes, load barangays via AJAX and get zip code - ADD FORM
    $('#add_city').on('change', function() {
        const cityCode = $(this).val();
        const cityName = $(this).find(':selected').data('name');
        const barangaySelect = $('#add_barangay');
        const zipInput = $('#add_zip');
        const zipLoading = $('#zip_loading');
        const zipSuccess = $('#zip_success');
        const zipError = $('#zip_error');
        
        // Update hidden field
        $('#add_city_name').val(cityName);
        
        if (!cityCode || !cityName) {
            barangaySelect.html('<option value="">Select City First</option>').val('').trigger('change');
            barangaySelect.prop('disabled', true);
            zipInput.val('').prop('readonly', true);
            zipLoading.hide();
            zipSuccess.hide().text('');
            zipError.hide().text('');
            return;
        }
        
        // Show loading indicator for barangays
        showLoading('#barangay_loading');
        barangaySelect.prop('disabled', true);
        zipInput.val('').prop('readonly', true);
        
        // Hide previous messages
        zipSuccess.hide().text('');
        zipError.hide().text('');
        
        // Fetch barangays via AJAX
        $.ajax({
            url: 'api_handler.php',
            method: 'POST',
            data: {
                action: 'get_barangays',
                city_code: cityCode
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data && response.data.length > 0) {
                    let options = '<option value="">Select Barangay</option>';
                    response.data.forEach(barangay => {
                        options += `<option value="${barangay.code}" data-name="${barangay.name}">${barangay.name}</option>`;
                    });
                    
                    barangaySelect.html(options).val('').trigger('change');
                    barangaySelect.prop('disabled', false);
                } else {
                    barangaySelect.html('<option value="">No barangays found</option>').val('').trigger('change');
                    barangaySelect.prop('disabled', true);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading barangays:', error);
                barangaySelect.html('<option value="">Error loading barangays</option>').val('').trigger('change');
                barangaySelect.prop('disabled', true);
            },
            complete: function() {
                hideLoading('#barangay_loading');
            }
        });
        
        // Get zip code for this city
        zipLoading.show();
        $.ajax({
            url: 'api_handler.php',
            method: 'POST',
            data: {
                action: 'get_zip',
                city_name: cityName
            },
            dataType: 'json',
            success: function(response) {
                zipLoading.hide();
                
                if (response.success && response.zip && response.zip !== '0000') {
                    zipInput.val(response.zip).prop('readonly', false);
                    zipSuccess.text('✓ ZIP code found: ' + response.zip).show();
                    zipError.hide();
                } else {
                    zipInput.val('').prop('readonly', false);
                    zipError.text('⚠ Could not find ZIP code for this city. Please enter manually.').show();
                    zipSuccess.hide();
                }
            },
            error: function(xhr, status, error) {
                zipLoading.hide();
                zipInput.val('').prop('readonly', false);
                zipError.text('⚠ Error fetching ZIP code. Please enter manually.').show();
                zipSuccess.hide();
                console.error('Error getting ZIP code:', error);
            }
        });
    });

    // When barangay changes, update hidden field - ADD FORM
    $('#add_barangay').on('change', function() {
        const barangayName = $(this).find(':selected').data('name');
        $('#add_barangay_name').val(barangayName || '');
    });

    // EDIT FORM ADDRESS FUNCTIONALITY - Fixed Version
    <?php foreach ($users as $user): ?>
    // Initialize Select2 for edit form when modal is shown
    $('#editUserModal<?= $user['id'] ?>').on('shown.bs.modal', function() {
        // Reinitialize Select2 for this specific modal
        $(this).find('.address-select').each(function() {
            const $select = $(this);
            if (!$select.hasClass('select2-hidden-accessible')) {
                $select.select2({
                    placeholder: "Select an option",
                    allowClear: false,
                    width: '100%',
                    dropdownParent: $('#editUserModal<?= $user['id'] ?>')
                });
            }
        });
    });

    // When region changes in EDIT FORM
    $('#edit_region_<?= $user['id'] ?>').on('change', function() {
        const regionCode = $(this).val();
        const regionName = $(this).find(':selected').data('name');
        const provinceSelect = $('#edit_province_<?= $user['id'] ?>');
        const citySelect = $('#edit_city_<?= $user['id'] ?>');
        const barangaySelect = $('#edit_barangay_<?= $user['id'] ?>');
        const zipInput = $('#edit_zip_<?= $user['id'] ?>');
        const zipSuccess = $('#edit_zip_success_<?= $user['id'] ?>');
        const zipError = $('#edit_zip_error_<?= $user['id'] ?>');
        
        // Update hidden field
        $('#edit_region_name_<?= $user['id'] ?>').val(regionName);
        
        // Clear messages
        zipSuccess.hide().text('');
        zipError.hide().text('');
        
        if (!regionCode) {
            provinceSelect.html('<option value="">Select Region First</option>').val('').trigger('change');
            citySelect.html('<option value="">Select City/Municipality</option>').val('').trigger('change');
            barangaySelect.html('<option value="">Select Barangay</option>').val('').trigger('change');
            zipInput.val('');
            return;
        }
        
        // Fetch provinces via AJAX for edit form
        $.ajax({
            url: 'api_handler.php',
            method: 'POST',
            data: {
                action: 'get_provinces',
                region_code: regionCode
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data && response.data.length > 0) {
                    let options = '<option value="">Select Province</option>';
                    response.data.forEach(province => {
                        options += `<option value="${province.code}" data-name="${province.name}">${province.name}</option>`;
                    });
                    
                    provinceSelect.html(options).val('').trigger('change');
                    
                    // Clear downstream fields
                    citySelect.html('<option value="">Select City/Municipality</option>').val('').trigger('change');
                    barangaySelect.html('<option value="">Select Barangay</option>').val('').trigger('change');
                    zipInput.val('');
                } else {
                    provinceSelect.html('<option value="">No provinces found</option>').val('').trigger('change');
                }
            },
            error: function() {
                provinceSelect.html('<option value="">Error loading provinces</option>').val('').trigger('change');
            }
        });
    });
    
    // When province changes in EDIT FORM
    $('#edit_province_<?= $user['id'] ?>').on('change', function() {
        const provinceCode = $(this).val();
        const provinceName = $(this).find(':selected').data('name');
        const citySelect = $('#edit_city_<?= $user['id'] ?>');
        const barangaySelect = $('#edit_barangay_<?= $user['id'] ?>');
        const zipInput = $('#edit_zip_<?= $user['id'] ?>');
        const zipSuccess = $('#edit_zip_success_<?= $user['id'] ?>');
        const zipError = $('#edit_zip_error_<?= $user['id'] ?>');
        
        // Update hidden field
        $('#edit_province_name_<?= $user['id'] ?>').val(provinceName);
        
        // Clear messages
        zipSuccess.hide().text('');
        zipError.hide().text('');
        
        if (!provinceCode) {
            citySelect.html('<option value="">Select Province First</option>').val('').trigger('change');
            barangaySelect.html('<option value="">Select Barangay</option>').val('').trigger('change');
            zipInput.val('');
            return;
        }
        
        // Fetch cities via AJAX for edit form
        $.ajax({
            url: 'api_handler.php',
            method: 'POST',
            data: {
                action: 'get_cities',
                province_code: provinceCode
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data && response.data.length > 0) {
                    let options = '<option value="">Select City/Municipality</option>';
                    response.data.forEach(city => {
                        const type = city.isCity ? ' (City)' : ' (Municipality)';
                        options += `<option value="${city.code}" data-name="${city.name}">${city.name}${type}</option>`;
                    });
                    
                    citySelect.html(options).val('').trigger('change');
                    
                    // Clear downstream fields
                    barangaySelect.html('<option value="">Select Barangay</option>').val('').trigger('change');
                    zipInput.val('');
                } else {
                    citySelect.html('<option value="">No cities/municipalities found</option>').val('').trigger('change');
                }
            },
            error: function() {
                citySelect.html('<option value="">Error loading cities</option>').val('').trigger('change');
            }
        });
    });
    
    // When city changes in EDIT FORM
    $('#edit_city_<?= $user['id'] ?>').on('change', function() {
        const cityCode = $(this).val();
        const cityName = $(this).find(':selected').data('name');
        const barangaySelect = $('#edit_barangay_<?= $user['id'] ?>');
        const zipInput = $('#edit_zip_<?= $user['id'] ?>');
        const zipLoading = $('#edit_zip_loading_<?= $user['id'] ?>');
        const zipSuccess = $('#edit_zip_success_<?= $user['id'] ?>');
        const zipError = $('#edit_zip_error_<?= $user['id'] ?>');
        
        // Update hidden field
        $('#edit_city_name_<?= $user['id'] ?>').val(cityName);
        
        // Clear messages
        zipSuccess.hide().text('');
        zipError.hide().text('');
        
        if (!cityCode || !cityName) {
            barangaySelect.html('<option value="">Select City First</option>').val('').trigger('change');
            zipInput.val('');
            return;
        }
        
        // Show loading indicator for zip code
        zipLoading.show();
        
        // Fetch barangays via AJAX for edit form
        $.ajax({
            url: 'api_handler.php',
            method: 'POST',
            data: {
                action: 'get_barangays',
                city_code: cityCode
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data && response.data.length > 0) {
                    let options = '<option value="">Select Barangay</option>';
                    response.data.forEach(barangay => {
                        options += `<option value="${barangay.code}" data-name="${barangay.name}">${barangay.name}</option>`;
                    });
                    
                    barangaySelect.html(options).val('').trigger('change');
                } else {
                    barangaySelect.html('<option value="">No barangays found</option>').val('').trigger('change');
                }
            },
            error: function() {
                barangaySelect.html('<option value="">Error loading barangays</option>').val('').trigger('change');
            }
        });
        
        // Get zip code for this city
        $.ajax({
            url: 'api_handler.php',
            method: 'POST',
            data: {
                action: 'get_zip',
                city_name: cityName
            },
            dataType: 'json',
            success: function(response) {
                zipLoading.hide();
                
                if (response.success && response.zip && response.zip !== '0000') {
                    zipInput.val(response.zip);
                    zipSuccess.text('✓ ZIP code found: ' + response.zip).show();
                    zipError.hide();
                } else {
                    // If not found, try to get from the existing value
                    const currentZip = zipInput.val();
                    if (!currentZip || currentZip === '0000') {
                        zipInput.val('');
                        zipError.text('⚠ Could not find ZIP code for this city. Please enter manually.').show();
                        zipSuccess.hide();
                    } else {
                        // Keep existing value
                        zipSuccess.text('✓ Using existing ZIP code: ' + currentZip).show();
                        zipError.hide();
                    }
                }
            },
            error: function(xhr, status, error) {
                zipLoading.hide();
                console.error('Error getting ZIP code:', error);
                
                const currentZip = zipInput.val();
                if (!currentZip || currentZip === '0000') {
                    zipInput.val('');
                    zipError.text('⚠ Error fetching ZIP code. Please enter manually.').show();
                    zipSuccess.hide();
                } else {
                    // Keep existing value
                    zipSuccess.text('✓ Using existing ZIP code: ' + currentZip).show();
                    zipError.hide();
                }
            }
        });
    });
    
    // When barangay changes in EDIT FORM
    $('#edit_barangay_<?= $user['id'] ?>').on('change', function() {
        const barangayName = $(this).find(':selected').data('name');
        $('#edit_barangay_name_<?= $user['id'] ?>').val(barangayName || '');
    });
    <?php endforeach; ?>

    // Shift Management Functions
    function setupShiftManagement() {
        // For add employee form
        $('#shift_type').on('change', function() {
            handleShiftChange(this, 'add');
        });
        
        // For edit employee forms
        <?php foreach ($users as $user): ?>
        $('#edit_shift_type_<?= $user['id'] ?>').on('change', function() {
            handleShiftChange(this, 'edit', '<?= $user['id'] ?>');
        });
        <?php endforeach; ?>
        
        // Listen for time changes in custom shifts for add form
        $('#shift_start, #shift_end').on('change', function() {
            updateShiftPreview('custom', '#shift_preview', '#shift_details', 'add');
        });
    }
    
    function handleShiftChange(selectElement, formType, userId = null) {
        const shiftType = $(selectElement).val();
        const prefix = (formType === 'edit' && userId) ? 'edit_' + userId + '_' : '';
        const containerPrefix = (formType === 'edit' && userId) ? 'edit_' : '';
        const previewId = (formType === 'edit' && userId) ? '#edit_shift_preview_' + userId : '#shift_preview';
        const detailsId = (formType === 'edit' && userId) ? '#edit_shift_details_' + userId : '#shift_details';
        
        // Show/hide custom shift fields
        if (shiftType === 'custom') {
            $('#' + containerPrefix + 'custom_shift_name_container' + (userId ? '_' + userId : '')).show();
            $('#' + containerPrefix + 'shift_start_container' + (userId ? '_' + userId : '')).show();
            $('#' + containerPrefix + 'shift_end_container' + (userId ? '_' + userId : '')).show();
            
            // Set default custom shift times for add form
            if (formType === 'add') {
                $('#shift_start').val('08:00');
                $('#shift_end').val('17:00');
            }
            
            // Listen for time changes in custom shifts for edit forms
            if (formType === 'edit' && userId) {
                $('#edit_shift_start_' + userId + ', #edit_shift_end_' + userId).off('change').on('change', function() {
                    updateShiftPreview('custom', previewId, detailsId, 'edit', userId);
                });
            }
        } else {
            $('#' + containerPrefix + 'custom_shift_name_container' + (userId ? '_' + userId : '')).hide();
            $('#' + containerPrefix + 'shift_start_container' + (userId ? '_' + userId : '')).hide();
            $('#' + containerPrefix + 'shift_end_container' + (userId ? '_' + userId : '')).hide();
            
            // Clear custom fields for add form
            if (formType === 'add') {
                $('#shift_name').val('');
                $('#shift_start').val('');
                $('#shift_end').val('');
            }
        }
        
        // Shift Management Functions
        function setupShiftManagement() {
          // For add employee form
          $('#shift_type').on('change', function() {
            handleShiftChange(this, 'add');
          });
          
          // For edit employee forms
          <?php foreach ($users as $user): ?>
          $('#edit_shift_type_<?= $user['id'] ?>').on('change', function() {
            handleShiftChange(this, 'edit', '<?= $user['id'] ?>');
          });
          <?php endforeach; ?>
          
          // Listen for time changes in custom shifts for add form
          $('#custom_shift_start, #custom_shift_end').on('change', function() {
            updateShiftPreview('custom', '#shift_preview', '#shift_details', 'add');
          });
        }

        function handleShiftChange(selectElement, formType, userId = null) {
          const shiftType = $(selectElement).val();
          const prefix = (formType === 'edit' && userId) ? 'edit_' + userId + '_' : '';
          const containerPrefix = (formType === 'edit' && userId) ? 'edit_' : '';
          const previewId = (formType === 'edit' && userId) ? '#edit_shift_preview_' + userId : '#shift_preview';
          const detailsId = (formType === 'edit' && userId) ? '#edit_shift_details_' + userId : '#shift_details';
          
          // Show/hide custom shift fields
          if (shiftType === 'custom') {
            $('#' + containerPrefix + 'custom_shift_name_container' + (userId ? '_' + userId : '')).show();
            $('#' + containerPrefix + 'shift_start_container' + (userId ? '_' + userId : '')).show();
            $('#' + containerPrefix + 'shift_end_container' + (userId ? '_' + userId : '')).show();
            
            // Set default custom shift times for add form
            if (formType === 'add') {
              $('#custom_shift_start').val('08:00');
              $('#custom_shift_end').val('17:00');
            }
            
            // Listen for time changes in custom shifts for edit forms
            if (formType === 'edit' && userId) {
              $('#edit_custom_shift_start_' + userId + ', #edit_custom_shift_end_' + userId).off('change').on('change', function() {
                updateShiftPreview('custom', previewId, detailsId, 'edit', userId);
              });
            }
          } else {
            $('#' + containerPrefix + 'custom_shift_name_container' + (userId ? '_' + userId : '')).hide();
            $('#' + containerPrefix + 'shift_start_container' + (userId ? '_' + userId : '')).hide();
            $('#' + containerPrefix + 'shift_end_container' + (userId ? '_' + userId : '')).hide();
            
            // Set hidden fields based on selected shift type
            if (formType === 'add') {
              setPredefinedShiftValues(shiftType);
            } else if (formType === 'edit' && userId) {
              setPredefinedShiftValues(shiftType, userId);
            }
          }
          
          // Update shift preview
          updateShiftPreview(shiftType, previewId, detailsId, formType, userId);
        }

        function setPredefinedShiftValues(shiftType, userId = null) {
          let shiftStart = '';
          let shiftEnd = '';
          let shiftName = '';
          
          switch(shiftType) {
            case 'day_shift':
              shiftStart = '08:00';
              shiftEnd = '17:00';
              shiftName = 'Day Shift';
              break;
            case 'morning_shift':
              shiftStart = '06:00';
              shiftEnd = '15:00';
              shiftName = 'Morning Shift';
              break;
            case 'afternoon_shift':
              shiftStart = '15:00';
              shiftEnd = '00:00';
              shiftName = 'Afternoon Shift';
              break;
            case 'night_shift':
              shiftStart = '22:00';
              shiftEnd = '07:00';
              shiftName = 'Night Shift';
              break;
            default:
              shiftStart = '08:00';
              shiftEnd = '17:00';
              shiftName = shiftType;
              break;
          }
          
          if (userId) {
            // For edit forms, we need to update the actual form fields
            $('#edit_hidden_shift_start_' + userId).val(shiftStart);
            $('#edit_hidden_shift_end_' + userId).val(shiftEnd);
            $('#edit_hidden_shift_name_' + userId).val(shiftName);
          } else {
            // For add form
            $('#hidden_shift_start').val(shiftStart);
            $('#hidden_shift_end').val(shiftEnd);
            $('#hidden_shift_name').val(shiftName);
          }
        }
        
        // Convert 24-hour format to 12-hour format
        const [hours, minutes] = timeString.split(':');
        const hour = parseInt(hours);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const hour12 = hour % 12 || 12;
        
        return `${hour12}:${minutes} ${ampm}`;
    }

    // Initialize shift management
    setupShiftManagement();
    
    // Initialize shift previews for edit forms
    <?php foreach ($users as $user): ?>
    if ($('#edit_shift_type_<?= $user['id'] ?>').val()) {
        updateShiftPreview(
            $('#edit_shift_type_<?= $user['id'] ?>').val(),
            '#edit_shift_preview_<?= $user['id'] ?>',
            '#edit_shift_details_<?= $user['id'] ?>',
            'edit',
            '<?= $user['id'] ?>'
        );
    }
    <?php endforeach; ?>

    // Reset modal when closed
    $('#addUserModal').on('hidden.bs.modal', function() {
        showStep(1);
        $('#addEmployeeForm')[0].reset();
        resetAddressFields();
        $('#shift_type').val('').trigger('change');
        $('#shift_preview').hide();
    });
    
    // Form submission validation
    $('#addEmployeeForm').on('submit', function(e) {
        // Final validation before submit
        const zip = $('#add_zip').val();
        if (!zip || zip === '0000') {
            alert('Please enter a valid ZIP code.');
            e.preventDefault();
            return false;
        }
        
        // Check if all required fields are filled
        let allValid = true;
        $(this).find('[required]').each(function() {
            if (!$(this).val().trim()) {
                $(this).addClass('is-invalid');
                allValid = false;
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        if (!allValid) {
            alert('Please fill in all required fields.');
            e.preventDefault();
            return false;
        }
        
        return true;
    });
});
</script>
</body>
</html>    
<?php ob_end_flush(); ?>