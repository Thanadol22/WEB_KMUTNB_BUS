const admin = require('firebase-admin');
const dotenv = require('dotenv');
const path = require('path');

// Load environment variables from parent directory
dotenv.config({ path: path.resolve(__dirname, '../.env') });

let serviceAccount;

// Parse service account from env or fallback to file
if (process.env.FIREBASE_PRIVATE_KEY && process.env.FIREBASE_CLIENT_EMAIL) {
    serviceAccount = {
        projectId: process.env.FIREBASE_PROJECT_ID,
        privateKey: process.env.FIREBASE_PRIVATE_KEY.replace(/\\n/g, '\n'),
        clientEmail: process.env.FIREBASE_CLIENT_EMAIL,
    };
} else {
    // Fallback to json file if path is specified
    const saPath = process.env.FIREBASE_SERVICE_ACCOUNT || 'config/service-account.json';
    serviceAccount = require(path.resolve(__dirname, '../', saPath));
}

admin.initializeApp({
    credential: admin.credential.cert(serviceAccount),
    databaseURL: process.env.FIREBASE_DATABASE_URL
});

const db = admin.firestore();
const rtdb = admin.database();

// State
let locationsCache = {}; // { stopName: { lat, lng } }
let schedulesCache = {}; // { roundId: [ { order, name, scheduleTime } ] }
let lastArrivedCache = {}; // { busId_stopName: timestamp } — prevents duplicate recording at same stop
let schedulesMetaCache = {}; // { roundId: { startTime, endTime } }

const RADIUS_METERS = 80;
const MASTER_REFRESH_INTERVAL = 5 * 60 * 1000; // Refresh master data every 5 minutes
const STOP_TIME_WINDOW_MINS = 15; // Max time difference (minutes) between current time and stop.time to consider a match

// Haversine formula to calculate distance between two coordinates
function getDistanceFromLatLonInM(lat1, lon1, lat2, lon2) {
    const R = 6371000; // Radius of the earth in m
    const dLat = deg2rad(lat2 - lat1);
    const dLon = deg2rad(lon2 - lon1);
    const a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) *
        Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    const d = R * c; // Distance in m
    return d;
}

function deg2rad(deg) {
    return deg * (Math.PI / 180);
}

// Get current time in minutes (Bangkok timezone)
function getCurrentBangkokMins() {
    const nowStr = getFormattedDateTime();
    const currentHM = nowStr.split(' ')[1].substring(0, 5);
    const [ch, cm] = currentHM.split(':').map(Number);
    return ch * 60 + cm;
}

// Format Date as "YYYY-MM-DD HH:mm:ss" using proper timezone API
function getFormattedDateTime(date = new Date()) {
    // Use Intl.DateTimeFormat for proper timezone handling
    const formatter = new Intl.DateTimeFormat('sv-SE', {
        timeZone: 'Asia/Bangkok',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    });
    return formatter.format(date).replace('T', ' ');
}

// Fetch master data
async function loadMasterData() {
    console.log('[Daemon] Loading master data...');
    try {
        // Load locations (stops)
        const locSnap = await db.collection('locations').get();
        locationsCache = {};
        locSnap.forEach(doc => {
            const data = doc.data();
            if (!locationsCache[data.name]) {
                locationsCache[data.name] = [];
            }
            locationsCache[data.name].push({ lat: parseFloat(data.lat), lng: parseFloat(data.lng) });
        });
        
        // Load detailed schedules (with metadata for time-based round matching)
        const schedSnap = await db.collection('detailed_schedules').get();
        schedulesCache = {};
        schedulesMetaCache = {};
        schedSnap.forEach(doc => {
            const data = doc.data();
            // Cache round metadata (start/end time)
            schedulesMetaCache[doc.id] = {
                startTime: data.start_time || '',
                endTime: data.end_time || ''
            };
            if (data.stops && Array.isArray(data.stops)) {
                schedulesCache[doc.id] = data.stops.sort((a, b) => a.order - b.order);
            }
        });
        
        console.log(`[Daemon] Loaded ${Object.keys(locationsCache).length} locations, ${Object.keys(schedulesCache).length} detailed schedules.`);
    } catch (err) {
        console.error('[Daemon] Error loading master data:', err);
    }
}

// ─── STOP-TIME BASED ROUND MATCHING ──────────────────────────────────────────
// Instead of matching by round start/end time, we match by individual stop.time.
// When bus arrives at a stop, we find ALL rounds that have that stop,
// compare current time to each round's stop.time, and pick the SINGLE round
// whose stop.time is closest to now (within ±STOP_TIME_WINDOW_MINS).
// This eliminates double-recording because stop times in different rounds are unique.

/**
 * Find the best matching round for a specific stop based on stop.time
 * @param {string} stopName - Name of the stop the bus arrived at
 * @param {number} currentMins - Current time in minutes since midnight
 * @returns {{ roundId: string, stop: object, timeDiff: number } | null}
 */
function findBestRoundForStop(stopName, currentMins) {
    let bestMatch = null;

    for (const [roundId, stops] of Object.entries(schedulesCache)) {
        for (const stop of stops) {
            if (stop.name !== stopName) continue;
            if (!stop.time) continue;

            const [sh, sm] = stop.time.split(':').map(Number);
            const stopMins = sh * 60 + sm;
            const timeDiff = Math.abs(currentMins - stopMins);

            // Only consider if within the allowed time window
            if (timeDiff > STOP_TIME_WINDOW_MINS) continue;

            // Pick the round with the closest stop.time to current time
            if (!bestMatch || timeDiff < bestMatch.timeDiff) {
                bestMatch = { roundId, stop, timeDiff };
            }
        }
    }

    return bestMatch;
}

// Process Bus Movement — uses STOP-LEVEL time matching
// When bus is near a stop, find the round whose stop.time is closest to now
async function processBusMovement(busId, rawData) {
    if (!rawData) return;

    // Extract latest data if nested push keys exist (e.g. tracking/BUS01/-Oqip...)
    let data = rawData;
    const keys = Object.keys(rawData);
    const hasNested = keys.some(k => rawData[k] && typeof rawData[k] === 'object');
    if (hasNested) {
        const pushKeys = keys.filter(k => k !== 'status' && k !== 'last_updated' && k !== 'battery_percent' && k !== 'battery' && k !== 'batt' && typeof rawData[k] === 'object')
                             .sort();
        if (pushKeys.length > 0) {
            data = rawData[pushKeys[pushKeys.length - 1]];
        }
    }

    const { lat, lon, speed, timestamp } = data;
    if (!lat || !lon) return;

    const currentMins = getCurrentBangkokMins();

    // Check all known stops across ALL rounds to see if bus is near any
    // Collect all unique stop locations to check distance
    const nearbyStops = []; // { stopName, distance, stopLat, stopLng }
    const checkedStops = new Set();

    for (const [roundId, stops] of Object.entries(schedulesCache)) {
        for (const stop of stops) {
            // Avoid rechecking the same physical stop
            if (checkedStops.has(stop.name)) continue;
            checkedStops.add(stop.name);

            // Get stop coordinates
            let stopLocs = [];
            if (stop.lat && stop.lng) {
                stopLocs.push({ lat: parseFloat(stop.lat), lng: parseFloat(stop.lng) });
            } else {
                const cachedLocs = locationsCache[stop.name];
                if (cachedLocs && cachedLocs.length > 0) {
                    stopLocs.push(...cachedLocs);
                }
            }
            
            // Add both male and female dorm coordinates if this is the dormitory stop
            if (stop.name.includes("หอพัก")) {
                if (locationsCache["หอพักชาย"]) stopLocs.push(...locationsCache["หอพักชาย"]);
                if (locationsCache["หอพักหญิง"]) stopLocs.push(...locationsCache["หอพักหญิง"]);
            }
            
            if (stopLocs.length === 0) continue;

            let minDistance = Infinity;
            let bestLat = null;
            let bestLng = null;

            for (const loc of stopLocs) {
                const dist = getDistanceFromLatLonInM(lat, lon, loc.lat, loc.lng);
                if (dist < minDistance) {
                    minDistance = dist;
                    bestLat = loc.lat;
                    bestLng = loc.lng;
                }
            }

            if (minDistance <= RADIUS_METERS) {
                nearbyStops.push({ stopName: stop.name, distance: minDistance, stopLat: bestLat, stopLng: bestLng });
            }
        }
    }

    if (nearbyStops.length === 0) return;

    // Fetch last visited stop state for sequence enforcement
    let lastVisitedStop = null;
    let lastVisitedTime = 0;
    try {
        const busDoc = await db.collection('buses').doc(busId).get();
        if (busDoc.exists) {
            const d = busDoc.data();
            lastVisitedStop = d.last_visited_stop;
            lastVisitedTime = d.last_visited_time;
        } else {
            const bQuery = await db.collection('buses').where('bus_id', '==', busId).get();
            if (!bQuery.empty) {
                const d = bQuery.docs[0].data();
                lastVisitedStop = d.last_visited_stop;
                lastVisitedTime = d.last_visited_time;
            }
        }
    } catch(e) {}

    // For each nearby stop, find the best matching round by stop.time
    for (const nearby of nearbyStops) {
        const bestMatch = findBestRoundForStop(nearby.stopName, currentMins);
        if (!bestMatch) {
            console.log(`[Daemon] Bus ${busId} near ${nearby.stopName} but no round matches current time (${Math.floor(currentMins / 60)}:${String(currentMins % 60).padStart(2, '0')}). Skipped.`);
            continue;
        }

        const { roundId, stop, timeDiff } = bestMatch;
        const stopsArray = schedulesCache[roundId];

        // -- Sequence and Time Enforcement --
        let expectedIdx = -1;
        let minDiff = Infinity;
        for (let i = 0; i < stopsArray.length; i++) {
            if (!stopsArray[i].time) continue;
            const [sh, sm] = stopsArray[i].time.split(':').map(Number);
            const diff = Math.abs(currentMins - (sh * 60 + sm));
            if (diff < minDiff) {
                minDiff = diff;
                expectedIdx = i;
            }
        }
        
        const currIdx = stopsArray.findIndex(s => s.name === nearby.stopName);
        
        if (currIdx !== -1 && expectedIdx !== -1) {
            let lastIdx = -1;
            if (lastVisitedStop && lastVisitedTime && (Date.now() - lastVisitedTime < 60 * 60 * 1000)) {
                lastIdx = stopsArray.findIndex(s => s.name === lastVisitedStop);
            }
            
            // 1. Must not go backwards
            if (lastIdx !== -1 && currIdx <= lastIdx) {
                // Only log once per minute to avoid spam
                const cacheKey = `log_${busId}_${nearby.stopName}_back`;
                if (!lastArrivedCache[cacheKey] || Date.now() - lastArrivedCache[cacheKey] > 60000) {
                    console.log(`[Daemon] Bus ${busId} near ${nearby.stopName} (idx ${currIdx}), but last was ${lastVisitedStop} (idx ${lastIdx}). Skipping backwards.`);
                    lastArrivedCache[cacheKey] = Date.now();
                }
                continue;
            }
            
            // 2. Must not jump too far ahead of time
            if (lastIdx !== -1) {
                if (currIdx > lastIdx + 1 && currIdx > expectedIdx) {
                    const cacheKey = `log_${busId}_${nearby.stopName}_ahead`;
                    if (!lastArrivedCache[cacheKey] || Date.now() - lastArrivedCache[cacheKey] > 60000) {
                        console.log(`[Daemon] Bus ${busId} near ${nearby.stopName} (idx ${currIdx}), skipping because expected is ${expectedIdx} and last is ${lastIdx}.`);
                        lastArrivedCache[cacheKey] = Date.now();
                    }
                    continue;
                }
            } else {
                if (currIdx > expectedIdx + 1) {
                    const cacheKey = `log_${busId}_${nearby.stopName}_ahead_first`;
                    if (!lastArrivedCache[cacheKey] || Date.now() - lastArrivedCache[cacheKey] > 60000) {
                        console.log(`[Daemon] Bus ${busId} near ${nearby.stopName} (idx ${currIdx}), but expected first is ${expectedIdx}. Skipping.`);
                        lastArrivedCache[cacheKey] = Date.now();
                    }
                    continue;
                }
            }
        }

        // Prevent duplicate records for the same bus + round + stop within 5 minutes
        const cacheKey = `${busId}_${roundId}_${nearby.stopName}`;
        const lastRecorded = lastArrivedCache[cacheKey];
        if (lastRecorded) {
            const elapsed = Date.now() - lastRecorded;
            if (elapsed < 5 * 60 * 1000) {
                continue; // Already recorded recently
            }
        }

        console.log(`[Daemon] Bus ${busId} arrived at ${nearby.stopName} → matched round ${roundId} (stop.time=${stop.time}, diff=${timeDiff}min, dist=${nearby.distance.toFixed(1)}m)`);

        // Calculate status (ON_TIME / LATE / EARLY)
        let status = 'ON_TIME';
        const actualTimeStr = getFormattedDateTime();
        const actualHM = actualTimeStr.split(' ')[1].substring(0, 5);

        if (stop.time) {
            const [sh, sm] = stop.time.split(':').map(Number);
            const [ah, am] = actualHM.split(':').map(Number);
            const diffMins = (ah * 60 + am) - (sh * 60 + sm);

            if (diffMins > 5) status = 'LATE';
            else if (diffMins < -5) status = 'EARLY';
        }

        // Record to operation_history
        const historyRef = db.collection('operation_history').doc();
        const historyData = {
            id: historyRef.id,
            bus_id: busId,
            round_id: roundId,
            currentStop: nearby.stopName,
            lat: lat,
            lon: lon,
            scheduleTime: stop.time || '',
            actualTime: actualTimeStr,
            status: status,
            timestamp: Date.now()
        };

        await historyRef.set(historyData);
        console.log(`[Daemon] ✓ Recorded: ${busId} at ${nearby.stopName} [${status}] (Round: ${roundId}, scheduleTime: ${stop.time})`);

        // Update buses collection to record last_visited_stop
        try {
            const busDoc = await db.collection('buses').doc(busId).get();
            if (busDoc.exists) {
                await db.collection('buses').doc(busId).update({
                    last_visited_stop: nearby.stopName,
                    last_visited_time: Date.now()
                });
            } else {
                const bQuery = await db.collection('buses').where('bus_id', '==', busId).get();
                bQuery.forEach(async d => {
                    await d.ref.update({
                        last_visited_stop: nearby.stopName,
                        last_visited_time: Date.now()
                    });
                });
            }
        } catch(err) {
            console.error('[Daemon] Error updating bus last_visited_stop:', err);
        }

        // Update dedup cache
        lastArrivedCache[cacheKey] = Date.now();

        // Bus can only be at one physical stop at a time — stop processing
        break;
    }
}

// Delete raw GPS tracking data older than 3 days
async function cleanOldTrackingData() {
    console.log('[Daemon] Running tracking data cleanup...');
    try {
        const trackingRef = rtdb.ref('tracking');
        const snapshot = await trackingRef.get();
        if (!snapshot.exists()) {
            console.log('[Daemon] No tracking data found for cleanup.');
            return;
        }

        const threeDaysAgo = Date.now() - 3 * 24 * 60 * 60 * 1000;
        let deleteCount = 0;

        const busesData = snapshot.val();
        for (const busId in busesData) {
            const busNode = busesData[busId];
            if (typeof busNode !== 'object' || busNode === null) continue;

            for (const key in busNode) {
                // Skip metadata nodes at the root of the bus node
                if (key === 'status' || key === 'last_updated' || key === 'battery_percent' || key === 'battery' || key === 'batt' || key === 'battery_voltage') {
                    continue;
                }

                const point = busNode[key];
                if (typeof point !== 'object' || point === null) continue;

                let pointTimestamp = null;

                if (point.timestamp) {
                    pointTimestamp = Number(point.timestamp);
                } else if (point.date && point.time) {
                    // Parse date "20/5/2026" and time "14:7:53"
                    const dateParts = point.date.split('/'); // [d, m, yyyy]
                    const timeParts = point.time.split(':'); // [hh, mm, ss]
                    if (dateParts.length === 3 && timeParts.length === 3) {
                        const day = parseInt(dateParts[0], 10);
                        const month = parseInt(dateParts[1], 10);
                        const year = parseInt(dateParts[2], 10);
                        const hour = parseInt(timeParts[0], 10);
                        const minute = parseInt(timeParts[1], 10);
                        const second = parseInt(timeParts[2], 10);
                        
                        // Construct precise ISO string using Asia/Bangkok (+07:00) offset to prevent timezone shift issues
                        const pad = (num) => String(num).padStart(2, '0');
                        const isoString = `${year}-${pad(month)}-${pad(day)}T${pad(hour)}:${pad(minute)}:${pad(second)}+07:00`;
                        
                        const parsedDate = new Date(isoString);
                        if (!isNaN(parsedDate.getTime())) {
                            pointTimestamp = parsedDate.getTime();
                        }
                    }
                }

                // Delete if older than 3 days
                if (pointTimestamp && pointTimestamp < threeDaysAgo) {
                    await rtdb.ref(`tracking/${busId}/${key}`).remove();
                    deleteCount++;
                }
            }
        }
        console.log(`[Daemon] Cleanup complete. Deleted ${deleteCount} old GPS data points.`);
    } catch (err) {
        console.error('[Daemon] Error during tracking data cleanup:', err);
    }
}

// Handler for tracking data changes
function handleTrackingEvent(snapshot) {
    const busId = snapshot.key;
    const data = snapshot.val();
    processBusMovement(busId, data).catch(err => {
        console.error(`[Daemon] Error processing bus ${busId}:`, err);
    });
}

// Start listener
async function startDaemon() {
    await loadMasterData();
    
    // Periodic master data refresh
    const refreshInterval = setInterval(loadMasterData, MASTER_REFRESH_INTERVAL);

    // Initial GPS data cleanup and periodic cleanup every 12 hours
    cleanOldTrackingData();
    const cleanupInterval = setInterval(cleanOldTrackingData, 12 * 60 * 60 * 1000);
    
    console.log('[Daemon] Starting RTDB listener on /tracking...');
    const trackingRef = rtdb.ref('tracking');
    
    // Listen for both new and updated tracking data
    trackingRef.on('child_changed', handleTrackingEvent);
    trackingRef.on('child_added', handleTrackingEvent);

    // Graceful shutdown
    const shutdown = () => {
        console.log('[Daemon] Shutting down gracefully...');
        clearInterval(refreshInterval);
        clearInterval(cleanupInterval);
        trackingRef.off('child_changed', handleTrackingEvent);
        trackingRef.off('child_added', handleTrackingEvent);
        process.exit(0);
    };

    process.on('SIGTERM', shutdown);
    process.on('SIGINT', shutdown);
}

// Run
startDaemon();
