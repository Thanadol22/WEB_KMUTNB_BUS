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
let lastArrivedCache = {}; // { busId_roundId: { lastStopName, timestamp } }
let busScheduleMap = {}; // { busId: roundId } — cached bus-to-schedule mapping

const RADIUS_METERS = 80;
const MASTER_REFRESH_INTERVAL = 5 * 60 * 1000; // Refresh master data every 5 minutes

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
            locationsCache[data.name] = { lat: data.lat, lng: data.lng };
        });
        
        // Load detailed schedules
        const schedSnap = await db.collection('detailed_schedules').get();
        schedulesCache = {};
        schedSnap.forEach(doc => {
            const data = doc.data();
            if (data.stops && Array.isArray(data.stops)) {
                schedulesCache[doc.id] = data.stops.sort((a, b) => a.order - b.order);
            }
        });

        // Cache bus-to-schedule mapping to avoid querying Firestore on every GPS event
        const busSchedSnap = await db.collection('schedules').get();
        busScheduleMap = {};
        busSchedSnap.forEach(doc => {
            const data = doc.data();
            if (data.bus_id) {
                busScheduleMap[data.bus_id] = doc.id;
            }
        });
        
        console.log(`[Daemon] Loaded ${Object.keys(locationsCache).length} locations, ${Object.keys(schedulesCache).length} schedules, ${Object.keys(busScheduleMap).length} bus-schedule mappings.`);
    } catch (err) {
        console.error('[Daemon] Error loading master data:', err);
    }
}

// Process Bus Movement (uses cached data instead of querying Firestore each time)
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

    // Use cached bus-schedule mapping instead of querying Firestore
    const currentRoundId = busScheduleMap[busId];
    if (!currentRoundId) return;
    
    const stopsForRound = schedulesCache[currentRoundId];
    if (!stopsForRound) return;

    // Find if bus is near any stop in this round
    for (const stop of stopsForRound) {
        const stopLoc = locationsCache[stop.name];
        if (!stopLoc) continue;

        const distance = getDistanceFromLatLonInM(lat, lon, stopLoc.lat, stopLoc.lng);
        
        if (distance <= RADIUS_METERS) {
            // Bus is within radius
            const cacheKey = `${busId}_${currentRoundId}`;
            const lastArrived = lastArrivedCache[cacheKey];

            // Prevent duplicate records for the same stop within a 5-minute window
            if (lastArrived && lastArrived.lastStopName === stop.name) {
                const timeDiff = Date.now() - lastArrived.timestamp;
                if (timeDiff < 5 * 60 * 1000) {
                    continue; // Skip, already recorded recently
                }
            }

            console.log(`[Daemon] Bus ${busId} arrived at ${stop.name} (Distance: ${distance.toFixed(2)}m)`);
            
            // Calculate status
            let status = 'ON_TIME';
            const actualTimeStr = getFormattedDateTime(); // "YYYY-MM-DD HH:mm:ss"
            const actualHM = actualTimeStr.split(' ')[1].substring(0, 5); // "HH:mm"
            
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
                busId: busId,
                round_id: currentRoundId,
                currentStop: stop.name,
                lat: lat,
                lon: lon,
                scheduleTime: stop.time || '',
                actualTime: actualTimeStr,
                status: status,
                timestamp: Date.now()
            };

            await historyRef.set(historyData);
            console.log(`[Daemon] Recorded history for ${busId} at ${stop.name} [${status}]`);

            // Update cache
            lastArrivedCache[cacheKey] = {
                lastStopName: stop.name,
                timestamp: Date.now()
            };
            
            // We can break here since it can only be at one stop at a time
            break; 
        }
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
