<?php
require_once '../includes/firebase_config.php';
require_once 'FirebaseService.php';

header('Content-Type: application/json');

$firebaseService = new FirebaseService($firebase['db']);
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($action === 'update_status') {
        $id = $data['id'] ?? '';
        $newStatus = $data['status'] ?? '';
        
        if (!$id || !$newStatus) {
            echo json_encode(['status' => 'error', 'message' => 'Missing ID or Status']);
            exit;
        }

        try {
            // First fetch the existing document to preserve all fields
            $docResponse = $firebase['db']->get('issue_reports/' . $id);
            $existingDoc = json_decode($docResponse->getBody(), true);
            $docData = [];
            
            if (isset($existingDoc['fields'])) {
                foreach ($existingDoc['fields'] as $key => $value) {
                    if (isset($value['stringValue'])) $docData[$key] = $value['stringValue'];
                    elseif (isset($value['integerValue'])) $docData[$key] = (int)$value['integerValue'];
                    elseif (isset($value['doubleValue'])) $docData[$key] = (float)$value['doubleValue'];
                    elseif (isset($value['booleanValue'])) $docData[$key] = (bool)$value['booleanValue'];
                    elseif (isset($value['timestampValue'])) $docData[$key] = $value['timestampValue'];
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Document not found']);
                exit;
            }

            // Update only the status field
            $docData['status'] = $newStatus;

            $firebaseService->saveDocument('issue_reports', $id, $docData);
            
            echo json_encode(['status' => 'success', 'message' => 'Issue status updated successfully']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'delete') {
        $id = $data['id'] ?? '';
        
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'Missing ID']);
            exit;
        }

        try {
            $firebaseService->deleteDocument('issue_reports', $id);
            echo json_encode(['status' => 'success', 'message' => 'Issue deleted successfully']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
?>