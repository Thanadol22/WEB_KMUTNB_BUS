<?php
require_once '../includes/firebase_config.php';
require_once 'FirebaseService.php';

header('Content-Type: application/json');

// Initialize Service with REST client
$firebaseService = new FirebaseService($firebase['db']);

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'list') {
        try {
            $users = $firebaseService->getAllDocuments('users');
            echo json_encode(['status' => 'success', 'data' => $users]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($action === 'create') {
        try {
            // Generate a random 20-character document ID similar to Firestore's default
            $uid = bin2hex(random_bytes(10));
            
            // Prepare document structure based on the schema
            $docData = [
                'name' => $data['name'] ?? '',
                'username' => $data['username'] ?? '',
                'password' => $data['password'] ?? '',
                'phone' => $data['phone'] ?? '',
                'role' => $data['role'] ?? 'student',
                'status' => $data['status'] ?? 'active',
                'fcm_token' => '',
                'created_at' => date('F j, Y \a\t g:i:s A \U\T\C\+7') // E.g., "March 31, 2026 at 2:09:10 PM UTC+7"
            ];
            
            $firebaseService->saveDocument('users', $uid, $docData);
            
            echo json_encode(['status' => 'success', 'message' => 'User created successfully', 'uid' => $uid]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'update') {
        $uid = $data['uid'] ?? '';
        if (!$uid) {
            echo json_encode(['status' => 'error', 'message' => 'Missing User ID']);
            exit;
        }

        try {
            // Because saveDocument does a full replace (PATCH) with the current FirebaseService implementation,
            // we first fetch existing data to preserve fields like created_at or fcm_token if they don't exist in $data.
            // But wait, the PATCH method in FirebaseService uses `updateMask` or overrides?
            // "Using PATCH for Upsert behavior" -> Without updateMask, it overwrites the whole document.
            // Let's just create a complete docData from the incoming $data.
            // In a better approach, we'd query first, merge, and save.
            
            // To be safe, we just update the specific fields provided by the form.
            $docData = [
                'name' => $data['name'] ?? '',
                'username' => $data['username'] ?? '',
                'password' => $data['password'] ?? '',
                'phone' => $data['phone'] ?? '',
                'role' => $data['role'] ?? 'student',
                'status' => $data['status'] ?? 'active',
                // Keep fcm_token empty for web admin edits or you could ideally merge it
                'fcm_token' => $data['fcm_token'] ?? '',
            ];
            
            // Just use a basic timestamp update if desired, or leave it to avoid replacing created_at
            // If the old record had created_at, it will be lost if not fetched.
            // Let's try to get it first.
            try {
                $docResponse = $firebase['db']->get('users/' . $uid);
                $existingDoc = json_decode($docResponse->getBody(), true);
                $existingData = [];
                // Parse existing
                if (isset($existingDoc['fields'])) {
                    foreach ($existingDoc['fields'] as $key => $value) {
                        if (isset($value['stringValue'])) $existingData[$key] = $value['stringValue'];
                        elseif (isset($value['integerValue'])) $existingData[$key] = (int)$value['integerValue'];
                        elseif (isset($value['doubleValue'])) $existingData[$key] = (float)$value['doubleValue'];
                        elseif (isset($value['booleanValue'])) $existingData[$key] = (bool)$value['booleanValue'];
                    }
                }
                if (isset($existingData['created_at'])) $docData['created_at'] = $existingData['created_at'];
                if (isset($existingData['fcm_token'])) $docData['fcm_token'] = $existingData['fcm_token'];
            } catch (Exception $e) {
                // If it fails to fetch (e.g., doesn't exist), we just proceed
                $docData['created_at'] = date('F j, Y \a\t g:i:s A \U\T\C\+7');
            }

            $firebaseService->saveDocument('users', $uid, $docData);
            
            echo json_encode(['status' => 'success', 'message' => 'User updated successfully']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'delete') {
        $uid = $data['uid'] ?? '';
        if (!$uid) {
            echo json_encode(['status' => 'error', 'message' => 'Missing User ID']);
            exit;
        }

        try {
            $firebaseService->deleteDocument('users', $uid);
            echo json_encode(['status' => 'success', 'message' => 'User deleted successfully']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
?>
