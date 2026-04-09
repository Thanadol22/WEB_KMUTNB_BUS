<?php
require "includes/firebase_config.php";
require "services/FirebaseService.php";

$fs = new FirebaseService($firebase["db"], null);
$oldSchedules = $fs->getAllDocuments("schedules");

// Delete old schedules
foreach ($oldSchedules as $old) {
    if (isset($old["id"])) {
        try {
            $fs->deleteDocument("schedules", $old["id"]);
            echo "Deleted old schedule: " . $old["id"] . "\n";
        } catch(Exception $e) {}
    }
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
    ["name" => "คณะเทคโนฯ", "offset" => 22],
    ["name" => "คณะวิศวะฯ", "offset" => 25],
];

foreach ($startTimes as $index => $startTime) {
    $roundNum = $index + 1;
    $baseTime = strtotime("2026-04-08 $startTime:00");
    
    foreach ($stopsInfo as $stopIndex => $stop) {
        $stopNum = $stopIndex + 1;
        $stopTime = date("H:i", $baseTime + ($stop["offset"] * 60));
        
        $docId = sprintf("round_%02d_stop_%02d", $roundNum, $stopNum);
        $data = [
            "id" => $docId,
            "round" => $roundNum,
            "route_name" => $stop["name"],
            "start_time" => $stopTime,
            "end_time" => $stopTime, 
            "bus_id" => "bus_01"
        ];
        
        try {
            $fs->saveDocument("schedules", $docId, $data);
            echo "Saved $docId -> $stopTime at {$stop['name']}\n";
        } catch(Exception $e) {
            echo "Error saving $docId: " . $e->getMessage() . "\n";
        }
    }
}
?>