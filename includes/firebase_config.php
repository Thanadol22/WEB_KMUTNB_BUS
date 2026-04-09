<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Kreait\Firebase\Factory;
use Dotenv\Dotenv;
use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Google\Auth\Middleware\AuthTokenMiddleware;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// Global Firebase state
$firebase = [
    'status' => 'pending',
    'message' => '',
    'db' => null,
    'auth' => null,
    'project_id' => $_ENV['FIREBASE_PROJECT_ID'] ?? ''
];

try {
    $serviceAccountConfig = null;
    
    // Check if credentials are directly in .env
    if (!empty($_ENV['FIREBASE_PRIVATE_KEY']) && !empty($_ENV['FIREBASE_CLIENT_EMAIL'])) {
        $serviceAccountConfig = [
            'type' => 'service_account',
            'project_id' => $_ENV['FIREBASE_PROJECT_ID'] ?? '',
            'private_key' => str_replace('\\n', "\n", $_ENV['FIREBASE_PRIVATE_KEY']),
            'client_email' => $_ENV['FIREBASE_CLIENT_EMAIL'],
            'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
            'token_uri' => 'https://oauth2.googleapis.com/token',
            'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
            'client_x509_cert_url' => 'https://www.googleapis.com/robot/v1/metadata/x509/' . urlencode($_ENV['FIREBASE_CLIENT_EMAIL']),
            'universe_domain' => 'googleapis.com'
        ];
    } else {
        // Fallback to JSON file
        $serviceAccountPath = __DIR__ . '/../' . ($_ENV['FIREBASE_SERVICE_ACCOUNT'] ?? 'config/service-account.json');
        if (!file_exists($serviceAccountPath)) {
            throw new Exception("ไม่พบไฟล์ Service Account JSON หรือการตั้งค่าใน .env ครบถ้วน");
        }
        $serviceAccountConfig = $serviceAccountPath;
    }

    // 1. Initialize Auth (Using kreait/firebase-php if possible)
    try {
        $factory = (new Factory)->withServiceAccount($serviceAccountConfig);
        $firebase['auth'] = $factory->createAuth();
    } catch (Exception $e) {
        // Auth might still work even if Firestore class is missing
    }

    // 2. Initialize Firestore via REST (Bypassing grpc dependency)
    $scopes = ['https://www.googleapis.com/auth/datastore'];
    $credentials = new ServiceAccountCredentials($scopes, $serviceAccountConfig);
    
    // Create Guzzle Client with Auth Middleware
    $middleware = new AuthTokenMiddleware($credentials);
    $stack = HandlerStack::create();
    $stack->push($middleware);
    
    $firebase['db'] = new Client([
        'handler' => $stack,
        'base_uri' => 'https://firestore.googleapis.com/v1/projects/' . $firebase['project_id'] . '/databases/(default)/documents/',
        'auth' => 'google_auth'
    ]);

    $firebase['status'] = 'connected';
} catch (Exception $e) {
    $firebase['status'] = 'error';
    $firebase['message'] = $e->getMessage();
}

// Helper to get Web Config
function getFirebaseWebConfig() {
    return [
        'apiKey' => $_ENV['FIREBASE_API_KEY'] ?? '',
        'authDomain' => $_ENV['FIREBASE_AUTH_DOMAIN'] ?? '',
        'projectId' => $_ENV['FIREBASE_PROJECT_ID'] ?? '',
        'storageBucket' => $_ENV['FIREBASE_STORAGE_BUCKET'] ?? '',
        'messagingSenderId' => $_ENV['FIREBASE_MESSAGING_SENDER_ID'] ?? '',
        'appId' => $_ENV['FIREBASE_APP_ID'] ?? '',
        'measurementId' => $_ENV['FIREBASE_MEASUREMENT_ID'] ?? '',
        'databaseURL' => $_ENV['FIREBASE_DATABASE_URL'] ?? ''
    ];
}
?>
