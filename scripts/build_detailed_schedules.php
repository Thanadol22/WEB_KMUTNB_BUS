<?php
require "includes/firebase_config.php";
require "services/FirebaseService.php";

$fs = new FirebaseService($firebase["db"], null);

// Fetch locations to map coordinates
$docs = $fs->getAllDocuments("locations");
$locationsMap = [];
foreach ($docs as $doc) {
    if (isset($doc["name"])) {
        // Map by name approximation or exact if possible
        $locationsMap[$doc["name"]] = $doc;
    }
}

// Function to find the best matching location in the db
function getLocation($name, $locationsMap) {
    foreach ($locationsMap as $dbName => $loc) {
        if (strpos($dbName, $name) !== false || strpos($name, $dbName) !== false) {
            return $loc;
        }
        // Special mapping
        if ($name === 'หอพักนักศึกษา' && (strpos($dbName, 'หอพักชาย') !== false || strpos($dbName, 'หอพัก') !== false)) {
            return $loc;
        }
        if ($name === 'คณะบริหารธุรกิจฯ' && strpos($dbName, 'คณะบริหาร') !== false) {
            return $loc;
        }
        if ($name === 'คณะอุตสาหกรรมเกษตร' && strpos($dbName, 'คณะอุตสาหกรรมการเกษตร') !== false) {
            return $loc;
        }
        if ($name === 'คณะเทคโนโลฯ' && strpos($dbName, 'คณะเทคโน') !== false) {
            return $loc;
        }
        if ($name === 'คณะวิศวะฯ' && strpos($dbName, 'คณะวิศวะ') !== false) {
            return $loc;
        }
    }
    return ["id" => null, "lat" => null, "lng" => null];
}

$startTimes = [
    "08:00", "08:20", "08:40", "09:00", "09:30", "10:00", "11:00", "11:30", 
    "12:00", "12:30", "13:00", "13:30", "14:30", "15:00", "15:30", "16:00", 
    "16:30", "17:30", "18:30", "19:00", "19:30"
];

$stopsInfo = [
    ["name" => "หอพักนักศึกษา", "offset" => 0],
    ["name" => "หน้ามหาวิทยาลัย", "offset" => 10],
    ["name" => "คณะบริหารธุรกิจฯ", "offset" => 12],
    ["name" => "คณะอุตสาหกรรมเกษตร", "offset" => 17],
    ["name" => "อาคารบริหาร", "offset" => 18],
    ["name" => "คณะเทคโนโลฯ", "offset" => 22],
    ["name" => "คณะวิศวะฯ", "offset" => 25],
];

// Clean existing detailed_schedules
$oldSchedules = $fs->getAllDocuments("detailed_schedules");
foreach ($oldSchedules as $old) {
    if (isset($old["id"])) {
        try {
            $fs->deleteDocument("detailed_schedules", $old["id"]);
            echo "Deleted old detailed_schedule: " . $old["id"] . "\n";
        } catch(Exception $e) {}
    }
}

foreach ($startTimes as $index => $startTime) {
    $roundNum = $index + 1;
    $baseTime = strtotime("2026-04-09 $startTime:00");
    
    $stopsData = [];
    foreach ($stopsInfo as $stopIndex => $stop) {
        $stopTime = date("H:i", $baseTime + ($stop["offset"] * 60));
        
        $loc = getLocation($stop["name"], $locationsMap);
        
        $stopsData[] = [
            "order" => $stopIndex + 1,
            "name" => $stop["name"],
            "time" => $stopTime,
            "location_id" => $loc["id"],
            "lat" => (float)$loc["lat"],
            "lng" => (float)$loc["lng"]
        ];
    }
    
    $docId = sprintf("round_%02d", $roundNum);
    $endTime = date("H:i", $baseTime + (25 * 60)); // Last stop offset is 25
    $data = [
        "id" => $docId,
        "round" => $roundNum,
        "start_time" => $startTime,
        "end_time" => $endTime,
        "stops" => $stopsData,
        "updated_at" => date('c')
    ];
    
    try {
        $fs->saveDocument("detailed_schedules", $docId, $data);
        echo "Saved detailed schedule $docId\n";
    } catch(Exception $e) {
        echo "Error saving $docId: " . $e->getMessage() . "\n";
    }
}
echo "Done building detailed_schedules.\n";
?>