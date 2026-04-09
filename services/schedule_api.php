<?php
require_once '../includes/firebase_config.php';
require_once 'FirebaseService.php';

header('Content-Type: application/json');

$firebaseService = new FirebaseService($firebase['db']);
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($action === 'update_time') {
        $id = $data['id'] ?? '';
        $time = $data['time'] ?? '';
        
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Missing ID']);
            exit;
        }

        try {
            // ดึงข้อมูลเดิม
            $docResponse = $firebase['db']->get('schedules/' . $id);
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
            }

            // อัปเดตเวลา
            $docData['start_time'] = $time;
            $docData['end_time'] = $time;

            $firebaseService->saveDocument('schedules', $id, $docData);
            
            echo json_encode(['success' => true, 'message' => 'อัปเดตเวลาสำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'update_stop_name') {
        $oldName = $data['old_name'] ?? '';
        $newName = $data['new_name'] ?? '';

        if (!$oldName || !$newName) {
            echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
            exit;
        }

        try {
            $allSchedules = $firebaseService->getAllDocuments("schedules");
            foreach ($allSchedules as $doc) {
                if (($doc['route_name'] ?? '') === $oldName) {
                    $doc['route_name'] = $newName;
                    $firebaseService->saveDocument('schedules', $doc['id'], $doc);
                }
            }
            echo json_encode(['success' => true, 'message' => 'อัปเดตชื่อป้ายสำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'delete_stop') {
        $stopName = $data['stop_name'] ?? '';

        if (!$stopName) {
            echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
            exit;
        }

        try {
            $allSchedules = $firebaseService->getAllDocuments("schedules");
            foreach ($allSchedules as $doc) {
                if (($doc['route_name'] ?? '') === $stopName) {
                    $firebaseService->deleteDocument('schedules', $doc['id']);
                }
            }
            echo json_encode(['success' => true, 'message' => 'ลบป้ายจอดสำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'add_stop') {
        $stopName = $data['stop_name'] ?? '';
        $lat = $data['lat'] ?? '';
        $lng = $data['lng'] ?? '';

        if (!$stopName) {
            echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
            exit;
        }

        try {
            $allSchedules = $firebaseService->getAllDocuments("schedules");
            // หารอบทั้งหมดที่มี
            $rounds = [];
            $maxStopNum = 0;
            foreach ($allSchedules as $doc) {
                $rounds[$doc['round']] = true;
                if (preg_match('/stop_(\d+)/', $doc['id'], $matches)) {
                    $num = (int)$matches[1];
                    if ($num > $maxStopNum) $maxStopNum = $num;
                }
            }

            $newStopNum = sprintf("%02d", $maxStopNum + 1);
            
            // สร้างเอกสารใหม่ให้ทุกรอบ
            foreach (array_keys($rounds) as $round) {
                $docId = sprintf("round_%02d_stop_%s", $round, $newStopNum);
                $docData = [
                    "id" => $docId,
                    "round" => (int)$round,
                    "route_name" => $stopName,
                    "start_time" => "",
                    "end_time" => "",
                    "bus_id" => "bus_01"
                ];
                
                // ถ้าระบุพิกัดมาให้ใส่ลงไปด้วย
                if ($lat !== '' && $lng !== '') {
                    $docData['latitude'] = (float)$lat;
                    $docData['longitude'] = (float)$lng;
                }
                
                $firebaseService->saveDocument('schedules', $docId, $docData);
            }

            // บันทึกลงใน Collections "locations" ด้วย
            if ($lat !== '' && $lng !== '') {
                $locId = "loc_stop_" . $newStopNum . "_" . time();
                $locData = [
                    "name" => $stopName,
                    "lat" => (float)$lat,
                    "lng" => (float)$lng,
                    "updated_at" => date('Y-m-d\TH:i:sP')
                ];
                try {
                    $firebaseService->saveDocument('locations', $locId, $locData);
                } catch (Exception $e) {
                    // หากไม่สำเร็จ ให้ข้ามไปก่อน (จุดประสงค์หลักคือ schedules)
                }
            }

            echo json_encode(['success' => true, 'message' => 'เพิ่มป้ายจอดและพิกัดลง locations สำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete_round') {
        $round = $data['round'] ?? '';

        if (!$round) {
            echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
            exit;
        }

        try {
            $allSchedules = $firebaseService->getAllDocuments("schedules");
            foreach ($allSchedules as $doc) {
                if ((int)$doc['round'] === (int)$round) {
                    $firebaseService->deleteDocument('schedules', $doc['id']);
                }
            }
            echo json_encode(['success' => true, 'message' => 'ลบรอบรถสำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'add_round') {
        try {
            $allSchedules = $firebaseService->getAllDocuments("schedules");
            $maxRound = 0;
            $stopsTemplate = []; // อิงโครงสร้างจุดจอดจากรอบล่าสุด
            
            foreach ($allSchedules as $doc) {
                $r = (int)$doc['round'];
                if ($r > $maxRound) {
                    $maxRound = $r;
                }
            }

            // หาแม่แบบป้ายจอดจากรอบล่าสุด
            foreach ($allSchedules as $doc) {
                if ((int)$doc['round'] === $maxRound) {
                    $stopsTemplate[] = $doc;
                }
            }

            $newRoundNum = $maxRound + 1;
            
            foreach ($stopsTemplate as $stop) {
                // ค้นหา stop index จาก id เดิม (e.g., round_01_stop_02)
                $stopIndex = "01";
                if (preg_match('/stop_(\d+)/', $stop['id'], $matches)) {
                    $stopIndex = sprintf("%02d", $matches[1]);
                }
                
                $docId = sprintf("round_%02d_stop_%s", $newRoundNum, $stopIndex);
                $docData = [
                    "id" => $docId,
                    "round" => $newRoundNum,
                    "route_name" => $stop['route_name'],
                    "start_time" => "",
                    "end_time" => "", 
                    "bus_id" => "bus_01"
                ];
                $firebaseService->saveDocument('schedules', $docId, $docData);
            }
            
            echo json_encode(['success' => true, 'message' => 'เพิ่มรอบรถสำเร็จ']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>