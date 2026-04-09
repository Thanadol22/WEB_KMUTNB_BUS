<?php
date_default_timezone_set("Asia/Bangkok");

// Helper function to calculate distance using Haversine formula
function getDistance($lat1, $lon1, $lat2, $lon2) {
    if (($lat1 == $lat2) && ($lon1 == $lon2)) { return 0; }
    $theta = $lon1 - $lon2;
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
    $dist = acos($dist);
    $dist = rad2deg($dist);
    $miles = $dist * 60 * 1.1515;
    return ($miles * 1.609344) * 1000; // Return in meters
}

$historyLogs = $firebaseService->getAllDocuments("operation_history");
$locations = $firebaseService->getAllDocuments("locations");

// --- AUTO CLEANUP > 7 DAYS ---
$sevenDaysAgo = strtotime("-7 days");
$cleaned = false;
foreach ($historyLogs as $idx => $log) {
    if (isset($log["timestamp"]) && is_numeric($log["timestamp"]) && $log["timestamp"] < $sevenDaysAgo) {
        if (isset($log["id"])) {
            try {
                $firebaseService->deleteDocument("operation_history", $log["id"]);
                unset($historyLogs[$idx]);
                $cleaned = true;
            } catch (Exception $e) {}
        }
    }
}

// --- AUTO-IMPORT LOGIC ---
if (empty($historyLogs) || isset($_GET["sync"])) {
    if (isset($_GET["sync"])) {
        foreach ($historyLogs as $log) {
            if (isset($log["id"])) {
                try {
                    $firebaseService->deleteDocument("operation_history", $log["id"]);
                } catch (Exception $e) {}
            }
        }
    }

    $buses = $firebaseService->getAllDocuments("buses");
    $users = $firebaseService->getAllDocuments("users");
    if (!isset($locations) || empty($locations)) {
        $locations = $firebaseService->getAllDocuments("locations");
    }
    $schedules = $firebaseService->getAllDocuments("schedules");
    
    $busMap = [];
    foreach ($buses as $bus) { $busMap[$bus["bus_id"]] = $bus; }
    
    $webConfig = getFirebaseWebConfig();
    $databaseUrl = rtrim($webConfig["databaseURL"], "/");
    if ($databaseUrl) {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $databaseUrl . "/tracking.json",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        
        $rtdbTracking = json_decode($response, true);
        if ($rtdbTracking) {
            foreach ($schedules as $schedule) {
                // Normalize bus ID
                $rawBusId = $schedule["bus_id"] ?? "";
                $rtdbBusId = strtoupper(str_replace(["_", "-"], "", $rawBusId));
                
                if (!$rawBusId || !isset($rtdbTracking[$rtdbBusId])) continue;
                
                $points = $rtdbTracking[$rtdbBusId];
                // In new structure, start_time and end_time are the same, route_name is the target stop.
                $startTimeStr = $schedule["start_time"];
                $routeName = $schedule["route_name"] ?? "-";
                
                $pointsByDate = [];
                foreach ($points as $pushId => $data) {
                    $d = $data["date"] ?? date("Y-m-d");
                    if (!isset($pointsByDate[$d])) $pointsByDate[$d] = [];
                    $pointsByDate[$d][$pushId] = $data;
                }

                foreach ($pointsByDate as $dateKey => $dayPoints) {
                    $dateParts = explode("/", $dateKey);
                    $formattedDate = date("Y-m-d"); 
                    if (count($dateParts) == 3) {
                        $day = str_pad($dateParts[0], 2, "0", STR_PAD_LEFT);
                        $month = str_pad($dateParts[1], 2, "0", STR_PAD_LEFT);
                        $year = $dateParts[2];
                        $formattedDate = "$year-$month-$day";
                    } else if (strtotime($dateKey)) {
                        $formattedDate = date("Y-m-d", strtotime($dateKey));
                    }

                    $targetTime = strtotime("$formattedDate $startTimeStr:00");
                    $bestPoint = null;
                    
                    $targetLoc = null;
                    foreach ($locations as $loc) {
                        if (isset($loc["name"]) && $loc["name"] === $routeName) {
                            $targetLoc = $loc;
                            break;
                        }
                    }
                    
                    if ($targetLoc && isset($targetLoc["lat"]) && isset($targetLoc["lng"])) {
                        $closestDist = PHP_INT_MAX; 
                        
                        foreach ($dayPoints as $pushId => $data) {
                            $actualTimeStr = $data["time"] ?? "00:00:00";
                            $pointTimestamp = strtotime("$formattedDate $actualTimeStr");
                            if (!$pointTimestamp) continue;
                            
                            $timeDiff = $pointTimestamp - $targetTime;
                            
                            // Check a window from -15 mins to +45 mins of scheduled time
                            if ($timeDiff >= -900 && $timeDiff <= 2700) {
                                $d = getDistance($data["lat"], $data["lon"], $targetLoc["lat"], $targetLoc["lng"]);
                                if ($d < $closestDist) {
                                    $closestDist = $d;
                                    // Pick the point in time where the bus was physically closest to the target stop
                                    $bestPoint = $data;
                                    $bestPoint["actualTimestamp"] = $pointTimestamp;
                                }
                            }
                        }
                    } else {
                        // Fallback if stop location not found in DB: just find point closest in time
                        $minTimeDiff = PHP_INT_MAX;
                        foreach ($dayPoints as $pushId => $data) {
                            $actualTimeStr = $data["time"] ?? "00:00:00";
                            $pointTimestamp = strtotime("$formattedDate $actualTimeStr");
                            if (!$pointTimestamp) continue;
                            $timeDiff = abs($pointTimestamp - $targetTime);
                            if ($timeDiff < 900 && $timeDiff < $minTimeDiff) {
                                $minTimeDiff = $timeDiff;
                                $bestPoint = $data;
                                $bestPoint["actualTimestamp"] = $pointTimestamp;
                            }
                        }
                    }
                    
                    if ($bestPoint) {
                        $nearestStopName = "กำลังเดินทาง (ไม่อยู่ที่ป้าย)";
                        $minDist = 150; 
                        foreach ($locations as $loc) {
                            if (isset($loc["lat"]) && isset($loc["lng"])) {
                                $d = getDistance($bestPoint["lat"], $bestPoint["lon"], $loc["lat"], $loc["lng"]);
                                if ($d < $minDist) {
                                    $minDist = $d;
                                    $nearestStopName = $loc["name"];
                                }
                            }
                        }
                        
                        $diffMinutes = (($bestPoint["actualTimestamp"] ?? 0) - strtotime("$formattedDate $startTimeStr:00")) / 60;
                        if ($diffMinutes > 5) {
                            $status = "LATE";
                        } else if ($diffMinutes > -5) {
                            $status = "ON_TIME";
                        } else {
                            $status = "EARLY";
                        }

                        $saveData = [
                            "round_id" => $schedule["id"],
                            "busId" => strtoupper($rtdbBusId),
                            "route" => $routeName,
                            "scheduleTime" => $startTimeStr,
                            "actualTime" => date("Y-m-d H:i:s", $bestPoint["actualTimestamp"]),
                            "currentStop" => $nearestStopName,
                            "status" => $status,
                            "lat" => (float)$bestPoint["lat"],
                            "lon" => (float)$bestPoint["lon"],
                            "timestamp" => $bestPoint["actualTimestamp"]
                        ];
                        
                        try {
                            $newUid = "HISTORY_" . $schedule["id"] . "_" . uniqid();
                            $firebaseService->saveDocument("operation_history", $newUid, $saveData);
                        } catch (Exception $e) {}
                    }
                }
            }
            $historyLogs = $firebaseService->getAllDocuments("operation_history");
        }
    }
    
    if (isset($_GET["sync"])) {
        echo "<script>window.location.href = \"?page=operation_history\";</script>";
        exit;
    }
}

$selectedDate = isset($_GET['date']) ? $_GET['date'] : date("Y-m-d");
$selectedStop = isset($_GET['stop']) ? $_GET['stop'] : "";

usort($historyLogs, function($a, $b) {
    return strcmp($a["scheduleTime"] ?? "", $b["scheduleTime"] ?? "");
});

// Filter by selected date and stop
$filteredLogs = array_filter($historyLogs, function($log) use ($selectedDate, $selectedStop) {
    if (!isset($log["actualTime"])) return false;
    $matchDate = strpos($log["actualTime"], $selectedDate) === 0;
    $matchStop = true;
    if (!empty($selectedStop)) {
        // filter by either target route name or the currently arrived stop
        $matchStop = (($log["route"] ?? "") === $selectedStop || ($log["currentStop"] ?? "") === $selectedStop);
    }
    return $matchDate && $matchStop;
});

// Gather unique location names for the filter dropdown
$availableStops = [];
if (!empty($locations)) {
    foreach ($locations as $loc) {
        if (!empty($loc['name'])) {
            $availableStops[] = $loc['name'];
        }
    }
}
$availableStops = array_unique($availableStops);

?>

<div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-primary">ประวัติการเดินรถ</h1>
        <p class="text-gray-400 mt-1 sm:mt-2 text-sm sm:text-base">ตรวจสอบตำแหน่งรถจากรอบเวลาล่าสุด (ซิงค์จากตารางเดินรถและ RTDB)</p>
    </div>
    <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
        <select id="stopFilter" class="bg-gray-800 border border-gray-700 text-white text-sm rounded-lg focus:ring-primary focus:border-primary w-full sm:w-auto block p-2.5" onchange="updateHistoryFilters()">
            <option value="">ทุกจุดจอด</option>
            <?php foreach ($availableStops as $stopName): ?>
                <option value="<?php echo htmlspecialchars($stopName); ?>" <?php if($selectedStop === $stopName) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($stopName); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <input type="date" id="historyDate" class="bg-gray-800 border border-gray-700 text-white text-sm rounded-lg focus:ring-primary focus:border-primary w-full sm:w-auto block p-2.5" value="<?php echo htmlspecialchars($selectedDate); ?>" onchange="updateHistoryFilters()" />
        
        <a href="?page=operation_history&sync=true&date=<?php echo urlencode($selectedDate); ?>&stop=<?php echo urlencode($selectedStop); ?>" class="bg-primary hover:bg-accent text-white font-medium rounded-lg text-sm px-5 py-2.5 transition-colors text-center w-full sm:w-auto">
            ดึงข้อมูลล่าสุด
        </a>
    </div>
</div>

<script>
function updateHistoryFilters() {
    const dateVal = document.getElementById('historyDate').value;
    const stopVal = document.getElementById('stopFilter').value;
    let url = '?page=operation_history&date=' + encodeURIComponent(dateVal);
    if (stopVal) {
        url += '&stop=' + encodeURIComponent(stopVal);
    }
    window.location.href = url;
}
</script>

<div class="bg-cardbg rounded-2xl shadow-lg border border-gray-700 overflow-hidden">
    <div class="p-6 border-b border-gray-700 flex justify-between items-center">
        <h2 class="text-xl font-bold text-white flex items-center">
            <svg class="w-6 h-6 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            ตำแหน่งรถแต่ละรอบในปัจจุบัน
        </h2>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-gray-300">
            <thead class="bg-gray-800/50 text-xs uppercase text-gray-400">
                <tr>
                    <th class="px-6 py-4 font-medium">เวลาตามตาราง (รอบรถ)</th>
                    <th class="px-6 py-4 font-medium">เวลาจริงที่เครื่องบันทึก</th>
                    <th class="px-6 py-4 font-medium">หมายเลขรถ</th>
                    <th class="px-6 py-4 font-medium">เส้นทาง/จุดรับ-ส่ง</th>
                    <th class="px-6 py-4 font-medium">ตำแหน่งรถ/ป้ายรถที่กำลังอยู่</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700/50">
                <?php if (empty($filteredLogs)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                            ไม่พบข้อมูลตารางเดินรถสำหรับวันดังกล่าว
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($filteredLogs as $log): 
                        $actualTime = $log["actualTime"] ?? "-";
                        $scheduleTime = $log["scheduleTime"] ?? "-";
                        $busId = $log["busId"] ?? "-";
                        $route = $log["route"] ?? "-";
                        $currentStop = $log["currentStop"] ?? "-";
                        $status = $log["status"] ?? "ON_TIME";

                        $statusHtml = '<span class="px-2.5 py-1 text-[10px] font-medium rounded-full bg-green-500/20 text-green-500 border border-green-500/20">ตรงเวลา</span>';
                        if ($status === 'LATE') {
                            $statusHtml = '<span class="px-2.5 py-1 text-[10px] font-medium rounded-full bg-yellow-500/20 text-yellow-500 border border-yellow-500/20">ล่าช้า</span>';
                        } else if ($status === 'EARLY') {
                            $statusHtml = '<span class="px-2.5 py-1 text-[10px] font-medium rounded-full bg-blue-500/20 text-blue-500 border border-blue-500/20">ก่อนเวลา</span>';
                        }
                    ?>
                    <tr class="hover:bg-gray-800/30 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap"><span class="px-2.5 py-1 text-sm font-bold rounded-md bg-gray-700/50 text-white border border-gray-600"><?php echo htmlspecialchars($scheduleTime); ?></span></td>
                        <td class="px-6 py-4 whitespace-nowrap text-gray-400 flex flex-col">
                            <span><?php echo htmlspecialchars($actualTime); ?></span>
                            <span class="mt-1"><?php echo $statusHtml; ?></span>
                        </td>
                        <td class="px-6 py-4 text-blue-400 font-medium"><?php echo htmlspecialchars(strtoupper($busId)); ?></td>
                        <td class="px-6 py-4"><span class="text-sm font-semibold text-orange-400"><?php echo htmlspecialchars($route); ?></span></td>
                        <td class="px-6 py-4 font-bold text-gray-200"><?php echo htmlspecialchars($currentStop); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

