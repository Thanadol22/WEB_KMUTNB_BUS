<?php
require_once '../includes/firebase_config.php';
require_once 'FirebaseService.php';

header('Content-Type: application/json');

/** @var array $firebase Defined in includes/firebase_config.php */
$db = $firebase['db'] ?? null;
if (!$db) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection not initialized']);
    exit;
}

$firebaseService = new FirebaseService($db);
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($action === 'update') {
        $busId = $data['id'] ?? '';
        if (!$busId) {
            echo json_encode(['status' => 'error', 'message' => 'Missing Bus ID']);
            exit;
        }

        try {
            // Fetch current document via service
            $docData = $firebaseService->getDocument('buses', $busId) ?? [];
            unset($docData['id']); // Remove id if it exists in parsed data to avoid field duplication

            // Update with new data
            if (isset($data['license_plate'])) $docData['license_plate'] = $data['license_plate'];
            if (isset($data['bus_number'])) $docData['bus_number'] = $data['bus_number'];
            if (isset($data['driver_id'])) $docData['driver_id'] = $data['driver_id'];
            if (isset($data['capacity'])) $docData['capacity'] = $data['capacity'];
            if (isset($data['status'])) $docData['status'] = $data['status'];
            if (isset($data['is_active'])) $docData['is_active'] = (bool)$data['is_active'];

            $firebaseService->saveDocument('buses', $busId, $docData);
            echo json_encode(['status' => 'success', 'message' => 'Bus updated successfully']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
?>
