<?php
require_once '../includes/firebase_config.php';
require_once 'FirebaseService.php';

header('Content-Type: application/json');

$firebaseService = new FirebaseService($firebase['db']);
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // --- Update top-level field (start_time, end_time) ---
    if ($action === 'update_field') {
        $id = $data['id'] ?? '';
        $field = $data['field'] ?? '';
        $value = $data['value'] ?? '';

        if (!$id || !$field) {
            echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
            exit;
        }

        try {
            $docResponse = $firebase['db']->get('detailed_schedules/' . $id);
            $docData = json_decode($docResponse->getBody(), true);
            $fields = [];
            
            if (isset($docData['fields'])) {
                foreach ($docData['fields'] as $key => $val) {
                    if (isset($val['stringValue'])) $fields[$key] = $val['stringValue'];
                    elseif (isset($val['integerValue'])) $fields[$key] = (int)$val['integerValue'];
                    elseif (isset($val['doubleValue'])) $fields[$key] = (float)$val['doubleValue'];
                    elseif (isset($val['booleanValue'])) $fields[$key] = (bool)$val['booleanValue'];
                    elseif (isset($val['timestampValue'])) $fields[$key] = $val['timestampValue'];
                    elseif (isset($val['mapValue'])) {
                        // handling stops array which is a list inside map, wait, FirebaseService returns flat array if we use getAllDocuments or getDocument?
                        // Actually, FirebaseREST API returns structured data. Let's just use FirebaseService's helper or simple update.
                    }
                }
            }
            
            // To simplify, FirebaseService has saveDocument which overwrites.
            // Better to use getAndParse helper if we have it, or fetch via getAllDocuments and filter.
            $allDocs = $firebaseService->getAllDocuments('detailed_schedules');
            $targetDoc = null;
            foreach ($allDocs as $doc) {
                if (($doc['id'] ?? '') === $id) {
                    $targetDoc = $doc;
                    break;
                }
            }

            if ($targetDoc) {
                $targetDoc[$field] = $value;
                $firebaseService->saveDocument('detailed_schedules', $id, $targetDoc);
                echo json_encode(['success' => true, 'message' => 'อัปเดตข้อมูลสำเร็จ']);
            } else {
                echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลสายรถ']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // --- Update stop field (time, name, lat, lng) ---
    if ($action === 'update_stop') {
        $id = $data['id'] ?? '';
        $stopIdx = $data['stop_index'] ?? '';
        $field = $data['field'] ?? '';
        $value = $data['value'] ?? '';

        if (!$id || $stopIdx === '' || !$field) {
            echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
            exit;
        }

        try {
            $allDocs = $firebaseService->getAllDocuments('detailed_schedules');
            $targetDoc = null;
            foreach ($allDocs as $doc) {
                if (($doc['id'] ?? '') === $id) {
                    $targetDoc = $doc;
                    break;
                }
            }

            if ($targetDoc && isset($targetDoc['stops'][$stopIdx])) {
                if ($field === 'lat' || $field === 'lng') {
                    $targetDoc['stops'][$stopIdx][$field] = (float)$value;
                } else {
                    $targetDoc['stops'][$stopIdx][$field] = $value;
                }
                $firebaseService->saveDocument('detailed_schedules', $id, $targetDoc);
                echo json_encode(['success' => true, 'message' => 'อัปเดตข้อมูลป้ายสำเร็จ']);
            } else {
                echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลป้าย']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // --- Add stop ---
    if ($action === 'add_stop') {
        $id = $data['id'] ?? '';

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
            exit;
        }

        try {
            $allDocs = $firebaseService->getAllDocuments('detailed_schedules');
            $targetDoc = null;
            foreach ($allDocs as $doc) {
                if (($doc['id'] ?? '') === $id) {
                    $targetDoc = $doc;
                    break;
                }
            }

            if ($targetDoc) {
                if (!isset($targetDoc['stops'])) {
                    $targetDoc['stops'] = [];
                }
                $newOrder = count($targetDoc['stops']) + 1;
                $targetDoc['stops'][] = [
                    'order' => $newOrder,
                    'name' => '',
                    'time' => '',
                    'lat' => 0,
                    'lng' => 0
                ];
                $firebaseService->saveDocument('detailed_schedules', $id, $targetDoc);
                echo json_encode(['success' => true, 'message' => 'เพิ่มป้ายจอดสำเร็จ']);
            } else {
                echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลรอบรถ']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // --- Remove stop ---
    if ($action === 'remove_stop') {
        $id = $data['id'] ?? '';
        $stopIdx = $data['stop_index'] ?? '';

        if (!$id || $stopIdx === '') {
            echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
            exit;
        }

        try {
            $allDocs = $firebaseService->getAllDocuments('detailed_schedules');
            $targetDoc = null;
            foreach ($allDocs as $doc) {
                if (($doc['id'] ?? '') === $id) {
                    $targetDoc = $doc;
                    break;
                }
            }

            if ($targetDoc && isset($targetDoc['stops'])) {
                array_splice($targetDoc['stops'], $stopIdx, 1);
                // Re-order stops
                foreach ($targetDoc['stops'] as $idx => &$stop) {
                    $stop['order'] = $idx + 1;
                }
                $firebaseService->saveDocument('detailed_schedules', $id, $targetDoc);
                echo json_encode(['success' => true, 'message' => 'ลบป้ายจอดสำเร็จ']);
            } else {
                echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลป้าย']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // --- Add round ---
    if ($action === 'add_round') {
        try {
            $allDocs = $firebaseService->getAllDocuments('detailed_schedules');
            $maxRound = 0;
            $templateStops = [];
            
            foreach ($allDocs as $doc) {
                $r = (int)($doc['round'] ?? 0);
                if ($r > $maxRound) {
                    $maxRound = $r;
                    $templateStops = $doc['stops'] ?? [];
                }
            }

            $newRound = $maxRound + 1;
            $newId = sprintf("round_%02d", $newRound);
            
            $docData = [
                'id' => $newId,
                'round' => $newRound,
                'start_time' => '',
                'end_time' => '',
                'stops' => $templateStops,
                'updated_at' => date('c')
            ];
            
            $firebaseService->saveDocument('detailed_schedules', $newId, $docData);
            echo json_encode(['success' => true, 'message' => 'เพิ่มรอบรถใหม่สำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // --- Delete round ---
    if ($action === 'delete_round') {
        $id = $data['id'] ?? '';

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
            exit;
        }

        try {
            $firebaseService->deleteDocument('detailed_schedules', $id);
            echo json_encode(['success' => true, 'message' => 'ลบรอบรถสำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>