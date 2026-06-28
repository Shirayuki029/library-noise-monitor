// Modes Configuration
const modes = {
    reading: { name: 'Reading Room', threshold: 150, sensitivity: 1.0, percentThreshold: 70 },
    silent: { name: 'Silent Study', threshold: 80, sensitivity: 0.8, percentThreshold: 40 }
};

let currentMode = 'reading';
let port = null;
let isConnected = false;
let reader = null;

// API URL - CHANGE THIS TO YOUR FOLDER NAME
const API_URL = 'http://localhost/smart_library/api.php';

// ============================================================
// DOM ELEMENTS
// ============================================================
const elements = {
    soundValue: document.getElementById('soundValue'),
    percentValue: document.getElementById('percentValue'),
    soundBar: document.getElementById('soundBar'),
    statusBadge: document.getElementById('statusBadge'),
    violationsCount: document.getElementById('violationsCount'),
    baselineValue: document.getElementById('baselineValue'),
    sensitivityValue: document.getElementById('sensitivityValue'),
    thresholdDisplay: document.getElementById('thresholdDisplay'),
    currentReadings: document.getElementById('currentReadings'),
    currentViolations: document.getElementById('currentViolations'),
    currentAvg: document.getElementById('currentAvg'),
    serialOutput: document.getElementById('serialOutput'),
    statusText: document.getElementById('statusText'),
    connectBtn: document.getElementById('connectBtn'),
    currentModeDisplay: document.getElementById('currentModeDisplay'),
    displayThreshold: document.getElementById('displayThreshold'),
    displaySensitivity: document.getElementById('displaySensitivity'),
    thresholdSlider: document.getElementById('thresholdSlider'),
    thresholdValue: document.getElementById('thresholdValue'),
    sensitivitySlider: document.getElementById('sensitivitySlider'),
    sensitivityVal: document.getElementById('sensitivityVal'),
    lastSaveTime: document.getElementById('lastSaveTime'),
    readingReadings: document.getElementById('reading-readings'),
    readingViolations: document.getElementById('reading-violations')
};

// ============================================================
// ALERT SYSTEM
// ============================================================
function showAlert(type, icon, title, message) {
    const overlay = document.getElementById('alertOverlay');
    const box = document.getElementById('alertBox');
    if (!overlay || !box) return;
    
    document.getElementById('alertIcon').textContent = icon;
    document.getElementById('alertTitle').textContent = title;
    document.getElementById('alertMessage').textContent = message;
    box.className = 'alert-box';
    box.classList.add(type);
    overlay.classList.add('show');
    setTimeout(closeAlert, 3000);
}

function closeAlert() {
    const overlay = document.getElementById('alertOverlay');
    if (overlay) overlay.classList.remove('show');
}

// ============================================================
// DATABASE FUNCTIONS
// ============================================================

async function testDatabaseConnection() {
    const indicator = document.getElementById('dbIndicator');
    const statusText = document.getElementById('dbStatusText');
    const connStatus = document.getElementById('dbConnStatus');
    
    if (indicator) indicator.className = 'db-indicator checking';
    if (statusText) statusText.innerHTML = 'Checking database...';
    if (connStatus) { connStatus.textContent = 'Checking...'; connStatus.className = ''; }
    
    try {
        const response = await fetch(`${API_URL}?action=test`);
        const data = await response.json();
        
        if (data && data.status === 'ok') {
            if (indicator) indicator.className = 'db-indicator connected';
            if (statusText) statusText.innerHTML = '✅ Database Connected';
            if (connStatus) {
                connStatus.textContent = '✅ CONNECTED';
                connStatus.className = 'connected';
            }
            console.log('✅ Database connected!');
            addSerialMessage('✅ Database connected!');
            return true;
        } else {
            throw new Error(data.message || 'Unknown error');
        }
    } catch (error) {
        if (indicator) indicator.className = 'db-indicator disconnected';
        if (statusText) statusText.innerHTML = '❌ Database Disconnected';
        if (connStatus) {
            connStatus.textContent = '❌ DISCONNECTED';
            connStatus.className = 'disconnected';
        }
        console.error('❌ Database error:', error);
        addSerialMessage('❌ Database disconnected: ' + error.message);
        return false;
    }
}

async function loadStats() {
    try {
        const response = await fetch(`${API_URL}?action=get_stats&area=${currentMode}`);
        const stats = await response.json();
        
        if (stats && !stats.error) {
            if (elements.currentReadings) elements.currentReadings.textContent = stats.total || 0;
            if (elements.currentViolations) elements.currentViolations.textContent = stats.violations || 0;
            if (elements.currentAvg) elements.currentAvg.textContent = Math.round(stats.avg_sound || 0);
        }
    } catch (error) {
        console.log('Error loading stats:', error);
    }
}

async function loadAllStats() {
    try {
        const response = await fetch(`${API_URL}?action=get_all_stats`);
        const stats = await response.json();
        
        if (stats && !stats.error) {
            if (elements.readingReadings) {
                elements.readingReadings.textContent = stats.reading?.total_readings || 0;
            }
            if (elements.readingViolations) {
                elements.readingViolations.textContent = stats.reading?.total_violations || 0;
            }
            if (elements.currentReadings) {
                elements.currentReadings.textContent = stats.reading?.total_readings || 0;
            }
            if (elements.currentViolations) {
                elements.currentViolations.textContent = stats.reading?.total_violations || 0;
            }
        }
        console.log('Loaded stats:', stats);
    } catch (error) {
        console.log('Error loading all stats:', error);
    }
}

async function loadIncidents() {
    try {
        const response = await fetch(`${API_URL}?action=get_incidents`);
        const incidents = await response.json();
        const container = document.getElementById('incidentsList');
        
        if (!container) return;
        
        if (!incidents || incidents.length === 0 || incidents.error) {
            container.innerHTML = '<div class="empty-message">No incidents recorded</div>';
            return;
        }
        
        container.innerHTML = '';
        incidents.slice(0, 20).forEach(inc => {
            const div = document.createElement('div');
            div.className = 'incident-item';
            const date = new Date(inc.timestamp);
            div.innerHTML = `
                <div class="incident-time">⚠️ ${date.toLocaleString()}</div>
                <div>🔊 Sound: ${inc.sound_value}/1023 (${inc.percent_value}%)</div>
                <div>📍 ${inc.area === 'reading' ? '📖 Reading Room' : '🤫 Silent Study'}</div>
            `;
            container.appendChild(div);
        });
    } catch (error) {
        console.log('Error loading incidents:', error);
    }
}

// ============================================================
// SAVE FUNCTIONS
// ============================================================

async function saveReading(sound, percent, status) {
    try {
        const data = {
            area: currentMode,
            sound: parseInt(sound),
            percent: parseInt(percent),
            status: status,
            threshold: parseInt(elements.thresholdSlider?.value || modes[currentMode].threshold),
            sensitivity: parseFloat(elements.sensitivitySlider?.value || modes[currentMode].sensitivity)
        };
        
        console.log('💾 SAVING READING:', data);
        
        const response = await fetch(`${API_URL}?action=save_reading`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        console.log('💾 Save result:', result);
        
        if (result.success) {
            if (elements.lastSaveTime) {
                const now = new Date();
                elements.lastSaveTime.textContent = now.toLocaleTimeString();
            }
            loadStats();
            loadAllStats();
        }
    } catch (error) {
        console.error('❌ Save error:', error);
    }
}

async function saveIncident(sound, percent) {
    try {
        const data = {
            area: currentMode,
            sound: parseInt(sound),
            percent: parseInt(percent)
        };
        
        console.log('🚨 SAVING INCIDENT:', data);
        
        const response = await fetch(`${API_URL}?action=save_incident`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        console.log('🚨 Incident result:', result);
        
        if (result.success) {
            addSerialMessage('✅ Incident saved to database');
            loadIncidents();
            loadStats();
            loadAllStats();
        } else {
            addSerialMessage('❌ Incident save failed: ' + (result.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('❌ Incident error:', error);
        addSerialMessage('❌ Incident save error: ' + error.message);
    }
}

// ============================================================
// ARDUINO FUNCTIONS - FIXED sendCommand
// ============================================================

async function connectSerial() {
    try {
        addSerialMessage('🔌 Requesting serial port...');
        port = await navigator.serial.requestPort();
        
        addSerialMessage('📡 Opening connection at 9600 baud...');
        await port.open({ baudRate: 9600 });
        
        isConnected = true;
        elements.statusText.className = 'status-badge connected';
        elements.statusText.innerHTML = '🟢 Connected';
        elements.connectBtn.textContent = '✅ Connected';
        elements.connectBtn.disabled = true;
        
        addSerialMessage('✅ Connected to Arduino!');
        addSerialMessage('📊 Waiting for data...');
        
        await delay(1000);
        
        // Send initial config
        const config = modes[currentMode];
        await sendCommand('SET_THRESHOLD', config.threshold);
        await delay(200);
        await sendCommand('SET_SENSITIVITY', config.sensitivity);
        
        // Start reading
        readSerialData();
    } catch (err) {
        addSerialMessage(`❌ Error: ${err.message}`);
        showAlert('error', '❌', 'Connection Error', err.message);
        isConnected = false;
    }
}

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

// ============================================================
// FIXED: sendCommand - Properly sends data to Arduino
// ============================================================
async function sendCommand(command, value) {
    // Check if connected
    if (!port || !isConnected) {
        addSerialMessage(`⚠️ Not connected. Command: ${command} ${value || ''}`);
        showAlert('error', '❌', 'Not Connected', 'Please connect to Arduino first.');
        return false;
    }
    
    try {
        // Get a fresh writer each time
        const writer = port.writable.getWriter();
        
        // Build the command message
        let msg;
        if (value !== undefined) {
            msg = `CMD:{"command":"${command}","value":${value}}\n`;
        } else {
            msg = `CMD:{"command":"${command}"}\n`;
        }
        
        console.log('📤 SENDING:', msg);
        addSerialMessage(`📤 Sending: ${command} ${value !== undefined ? '= ' + value : ''}`);
        
        // Send the message - encode as UTF-8
        const encoder = new TextEncoder();
        await writer.write(encoder.encode(msg));
        
        // IMPORTANT: Release the writer
        writer.releaseLock();
        
        addSerialMessage(`✅ Command sent successfully`);
        return true;
    } catch (err) {
        console.error('❌ Send error:', err);
        addSerialMessage(`❌ Send error: ${err.message}`);
        return false;
    }
}

// ============================================================
// READ SERIAL DATA
// ============================================================
async function readSerialData() {
    try {
        const reader = port.readable.getReader();
        let buffer = "";
        
        addSerialMessage('📡 Reading serial data...');
        
        while (isConnected) {
            try {
                const { value, done } = await reader.read();
                if (done) {
                    addSerialMessage('⚠️ Stream ended');
                    break;
                }
                
                const text = new TextDecoder().decode(value);
                buffer += text;
                
                let lines = buffer.split('\n');
                buffer = lines.pop() || '';
                
                for (const line of lines) {
                    const trimmed = line.trim();
                    if (trimmed) {
                        processLine(trimmed);
                    }
                }
            } catch (err) {
                if (err.name === 'TypeError') {
                    break;
                }
                console.error('Read error:', err);
            }
        }
    } catch (err) {
        addSerialMessage(`❌ Read error: ${err.message}`);
    }
    
    // Cleanup
    isConnected = false;
    elements.statusText.className = 'status-badge disconnected';
    elements.statusText.innerHTML = '⚫ Disconnected';
    elements.connectBtn.textContent = '🔌 Connect to Arduino';
    elements.connectBtn.disabled = false;
    addSerialMessage('⚠️ Disconnected from Arduino');
}

// ============================================================
// PROCESS INCOMING DATA
// ============================================================

function processLine(line) {
    if (!line) return;
    
    console.log('📥 RAW LINE:', line);
    
    // ===== CHECK FOR COMMAND RESPONSE =====
    if (line.includes('"command"')) {
        try {
            let jsonStr = line;
            if (line.startsWith('CMD:')) {
                jsonStr = line.substring(4).trim();
            }
            if (jsonStr.startsWith('{') && jsonStr.includes('}')) {
                const jsonData = JSON.parse(jsonStr);
                console.log('📊 Command response:', jsonData);
                
                if (jsonData.command === 'SET_THRESHOLD' && jsonData.value !== undefined) {
                    elements.thresholdDisplay.textContent = jsonData.value;
                    elements.displayThreshold.textContent = jsonData.value;
                    addSerialMessage(`✅ Threshold set to ${jsonData.value}`);
                    showAlert('success', '✅', 'Threshold Updated', `Threshold set to ${jsonData.value}`);
                }
                if (jsonData.command === 'SET_SENSITIVITY' && jsonData.value !== undefined) {
                    elements.sensitivityValue.textContent = parseFloat(jsonData.value).toFixed(2);
                    elements.displaySensitivity.textContent = parseFloat(jsonData.value).toFixed(1);
                    addSerialMessage(`✅ Sensitivity set to ${jsonData.value}`);
                    showAlert('success', '✅', 'Sensitivity Updated', `Sensitivity set to ${jsonData.value}`);
                }
                if (jsonData.command === 'RESET_VIOLATIONS') {
                    elements.violationsCount.textContent = '0';
                    elements.violationsCount.style.color = '#a5b4fc';
                    if (elements.currentViolations) elements.currentViolations.textContent = '0';
                    addSerialMessage('✅ Violations reset to 0');
                    showAlert('success', '🔄', 'Violations Reset', 'Violation counter reset to 0');
                }
                if (jsonData.command === 'STATUS') {
                    addSerialMessage(`📊 Status: Threshold=${jsonData.threshold}, Sensitivity=${jsonData.sensitivity}, Violations=${jsonData.violations}`);
                    elements.thresholdDisplay.textContent = jsonData.threshold;
                    elements.sensitivityValue.textContent = jsonData.sensitivity;
                    elements.violationsCount.textContent = jsonData.violations;
                    elements.baselineValue.textContent = jsonData.baseline;
                    if (jsonData.violations > 0) {
                        elements.violationsCount.style.color = '#ef4444';
                    }
                }
                if (jsonData.command === 'RECALIBRATE') {
                    addSerialMessage('✅ Recalibration complete!');
                    showAlert('success', '✅', 'Recalibrated', 'Sensor recalibration complete!');
                }
            }
        } catch (e) {
            console.warn('JSON parse error:', e.message);
        }
        return;
    }
    
    // ===== CHECK FOR INCIDENT =====
    if (line.startsWith('INCIDENT:')) {
        let data = line.substring(9).trim();
        let parts = data.split(',');
        if (parts.length >= 2) {
            let count = parts[0] || '0';
            let sound = parts[1] || '0';
            let percent = parts.length > 2 ? parts[2] : '0';
            addSerialMessage(`🚨 INCIDENT #${count}: ${sound} (${percent}%)`);
            // Save incident to database
            saveIncident(sound, percent);
        }
        return;
    }
    
    // ===== PARSE DATA LINE =====
    if (line.startsWith('DATA:')) {
        let data = line.substring(5).trim();
        console.log('📥 DATA:', data);
        
        // Parse the data - it should be comma separated
        let parts = data.split(',').filter(p => p.length > 0);
        console.log('📊 Parts:', parts);
        
        if (parts.length >= 7) {
            const sound = parseInt(parts[0]) || 0;
            const percent = parseInt(parts[1]) || 0;
            const status = parts[2].toUpperCase() || 'QUIET';
            const threshold = parseInt(parts[3]) || modes[currentMode].threshold;
            const violations = parseInt(parts[4]) || 0;
            const baseline = parseInt(parts[5]) || 50;
            const sensitivity = parseFloat(parts[6]) || 1.0;
            
            console.log(`📊 SOUND: ${sound}, PERCENT: ${percent}%, STATUS: ${status}, VIOLATIONS: ${violations}`);
            
            // Update violation count
            if (elements.violationsCount) {
                elements.violationsCount.textContent = violations;
                elements.violationsCount.style.color = violations > 0 ? '#ef4444' : '#a5b4fc';
            }
            
            // Update current violations in stats
            if (elements.currentViolations) {
                elements.currentViolations.textContent = violations;
            }
            
            updateUI(sound, percent, status, threshold, violations, baseline, sensitivity);
            saveReading(sound, percent, status);
            
            if (status === 'NOISE' || status === 'NOISE!') {
                addSerialMessage('🚨 NOISE DETECTED!');
            }
            return;
        }
        
        // If less than 7 parts, try to parse with fewer
        if (parts.length >= 3) {
            const sound = parseInt(parts[0]) || 0;
            const percent = parseInt(parts[1]) || 0;
            const status = parts[2].toUpperCase() || 'QUIET';
            
            console.log(`📊 Simple parse: SOUND: ${sound}, PERCENT: ${percent}%, STATUS: ${status}`);
            updateUI(sound, percent, status, modes[currentMode].threshold, 0, 50, 1.0);
            saveReading(sound, percent, status);
            return;
        }
    }
    
    // ===== UNKNOWN =====
    if (line.length > 0 && line !== 'READY' && !line.includes('Noise Monitor Started')) {
        console.log('⚠️ Unknown line:', line);
    }
}

// ============================================================
// UPDATE UI
// ============================================================

function updateUI(sound, percent, status, threshold, violations, baseline, sensitivity) {
    console.log('🔄 UPDATING UI:', { sound, percent, status, threshold, violations, baseline, sensitivity });
    
    // Sound value
    const soundEl = document.getElementById('soundValue');
    if (soundEl) {
        soundEl.textContent = sound;
        soundEl.style.color = percent > 70 ? '#ef4444' : percent > 40 ? '#eab308' : '#22c55e';
    }
    
    // Percent value
    const percentEl = document.getElementById('percentValue');
    if (percentEl) {
        percentEl.textContent = percent;
        percentEl.style.color = percent > 70 ? '#ef4444' : percent > 40 ? '#eab308' : '#a5b4fc';
    }
    
    // Sound bar
    const barEl = document.getElementById('soundBar');
    if (barEl) {
        const width = Math.min(percent, 100);
        barEl.style.width = width + '%';
        barEl.style.background = width > 70 ? 'linear-gradient(90deg, #ef4444, #dc2626)' : 
                                  width > 40 ? 'linear-gradient(90deg, #eab308, #f59e0b)' : 
                                  'linear-gradient(90deg, #22c55e, #16a34a)';
    }
    
    // Status badge
    const badgeEl = document.getElementById('statusBadge');
    if (badgeEl) {
        if (status === 'NOISE' || status === 'NOISE!') {
            badgeEl.textContent = '🔊 NOISE DETECTED!';
            badgeEl.className = 'status noise';
        } else if (status === 'WARNING') {
            badgeEl.textContent = '⚠️ WARNING';
            badgeEl.className = 'status warning';
        } else {
            badgeEl.textContent = '🔇 QUIET';
            badgeEl.className = 'status quiet';
        }
    }
    
    // Violations
    const vioEl = document.getElementById('violationsCount');
    if (vioEl) {
        vioEl.textContent = violations;
        vioEl.style.color = violations > 0 ? '#ef4444' : '#a5b4fc';
    }
    
    // Baseline
    const baseEl = document.getElementById('baselineValue');
    if (baseEl) baseEl.textContent = baseline;
    
    // Sensitivity
    const sensEl = document.getElementById('sensitivityValue');
    if (sensEl) sensEl.textContent = sensitivity.toFixed(2);
    
    // Threshold
    const thrEl = document.getElementById('thresholdDisplay');
    if (thrEl) thrEl.textContent = threshold;
}

// ============================================================
// SERIAL MONITOR
// ============================================================

function addSerialMessage(msg) {
    const log = elements.serialOutput;
    if (!log) return;
    
    if (log.children.length === 1 && log.children[0].className === 'empty-message') {
        log.innerHTML = '';
    }
    
    const div = document.createElement('div');
    const timestamp = new Date().toLocaleTimeString();
    div.textContent = `[${timestamp}] ${msg}`;
    
    if (msg.includes('❌') || msg.includes('ERROR') || msg.includes('error')) {
        div.style.color = '#ef4444';
    } else if (msg.includes('✅') || msg.includes('success')) {
        div.style.color = '#22c55e';
    } else if (msg.includes('⚠️') || msg.includes('WARNING')) {
        div.style.color = '#eab308';
    } else if (msg.includes('📤') || msg.includes('📥') || msg.includes('📡')) {
        div.style.color = '#38bdf8';
    } else if (msg.includes('🚨') || msg.includes('NOISE')) {
        div.style.color = '#ef4444';
        div.style.fontWeight = 'bold';
    } else {
        div.style.color = '#94a3b8';
    }
    
    log.appendChild(div);
    log.scrollTop = log.scrollHeight;
    
    while (log.children.length > 200) {
        log.removeChild(log.firstChild);
    }
}

// ============================================================
// EVENT LISTENERS
// ============================================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Dashboard loaded!');
    addSerialMessage('🎯 System Ready! Click "Connect to Arduino" to start');
    
    testDatabaseConnection();
    loadStats();
    loadAllStats();
    loadIncidents();
    
    // Close alert on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeAlert();
    });
    
    document.getElementById('alertOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeAlert();
    });
    
    // Connect button
    elements.connectBtn?.addEventListener('click', connectSerial);
    
    // Threshold slider
    elements.thresholdSlider?.addEventListener('input', function(e) {
        const val = parseInt(e.target.value);
        elements.thresholdValue.textContent = val;
        if (document.getElementById('displayThreshold')) {
            document.getElementById('displayThreshold').textContent = val;
        }
        if (elements.thresholdDisplay) elements.thresholdDisplay.textContent = val;
    });
    
    // Apply Threshold
    document.getElementById('applyThresholdBtn')?.addEventListener('click', async function() {
        const val = parseInt(elements.thresholdSlider.value);
        addSerialMessage(`📤 Applying threshold: ${val}`);
        const result = await sendCommand('SET_THRESHOLD', val);
        if (result) {
            showAlert('success', '✅', 'Threshold Applied', `Threshold set to ${val}`);
        } else {
            showAlert('error', '❌', 'Failed', 'Could not send command to Arduino. Make sure it\'s connected.');
        }
    });
    
    // Reset Threshold
    document.getElementById('resetThresholdBtn')?.addEventListener('click', async function() {
        const val = 150;
        elements.thresholdSlider.value = val;
        elements.thresholdValue.textContent = val;
        if (document.getElementById('displayThreshold')) {
            document.getElementById('displayThreshold').textContent = val;
        }
        if (elements.thresholdDisplay) elements.thresholdDisplay.textContent = val;
        addSerialMessage(`↩️ Threshold reset to ${val}`);
        await sendCommand('SET_THRESHOLD', val);
        showAlert('info', '🔄', 'Threshold Reset', `Threshold reset to ${val}`);
    });
    
    // Sensitivity slider
    elements.sensitivitySlider?.addEventListener('input', function(e) {
        const val = parseFloat(e.target.value);
        elements.sensitivityVal.textContent = val.toFixed(1);
        if (document.getElementById('displaySensitivity')) {
            document.getElementById('displaySensitivity').textContent = val.toFixed(1);
        }
        if (elements.sensitivityValue) elements.sensitivityValue.textContent = val.toFixed(1);
    });
    
    // Apply Sensitivity
    document.getElementById('applySensitivityBtn')?.addEventListener('click', async function() {
        const val = parseFloat(elements.sensitivitySlider.value);
        addSerialMessage(`📤 Applying sensitivity: ${val}`);
        const result = await sendCommand('SET_SENSITIVITY', val);
        if (result) {
            showAlert('success', '✅', 'Sensitivity Applied', `Sensitivity set to ${val}`);
        } else {
            showAlert('error', '❌', 'Failed', 'Could not send command to Arduino. Make sure it\'s connected.');
        }
    });
    
    // Reset Sensitivity
    document.getElementById('resetSensitivityBtn')?.addEventListener('click', async function() {
        const val = 1.0;
        elements.sensitivitySlider.value = val;
        elements.sensitivityVal.textContent = val.toFixed(1);
        if (document.getElementById('displaySensitivity')) {
            document.getElementById('displaySensitivity').textContent = val.toFixed(1);
        }
        if (elements.sensitivityValue) elements.sensitivityValue.textContent = val.toFixed(1);
        addSerialMessage(`↩️ Sensitivity reset to ${val}`);
        await sendCommand('SET_SENSITIVITY', val);
        showAlert('info', '🔄', 'Sensitivity Reset', `Sensitivity reset to ${val}`);
    });
    
    // Reset Violations
    document.getElementById('resetViolationsBtn')?.addEventListener('click', async function() {
        addSerialMessage('🔄 Resetting violations...');
        await sendCommand('RESET_VIOLATIONS');
    });
    
    // Recalibrate
    document.getElementById('recalibrateBtn')?.addEventListener('click', async function() {
        addSerialMessage('📡 Recalibrating sensor...');
        await sendCommand('RECALIBRATE');
        showAlert('warning', '📡', 'Recalibrating', 'Please keep the area quiet for 10 seconds...');
    });
    
    // Get Status
    document.getElementById('getStatusBtn')?.addEventListener('click', async function() {
        addSerialMessage('📊 Getting status...');
        await sendCommand('STATUS');
    });
    
    // Clear Serial
    document.getElementById('clearSerialBtn')?.addEventListener('click', function() {
        const serialOutput = document.getElementById('serialOutput');
        if (serialOutput) serialOutput.innerHTML = '<div class="empty-message">Cleared...</div>';
    });
    
    // Export
    document.getElementById('exportDataBtn')?.addEventListener('click', function() {
        showAlert('info', '📊', 'Data Export', 'Data is in your MySQL database!\nUse phpMyAdmin to view: noise_monitor database');
    });
    
    // Clear Data
    document.getElementById('clearDataBtn')?.addEventListener('click', async function() {
        if (confirm('⚠️ WARNING: This will delete ALL data from database! Are you sure?')) {
            try {
                const response = await fetch(`${API_URL}?action=clear_data`, { 
                    method: 'DELETE' 
                });
                const result = await response.json();
                if (result.success) {
                    showAlert('success', '✅', 'Data Cleared', 'All data has been cleared from the database!');
                    addSerialMessage('✅ Database cleared');
                    loadStats();
                    loadAllStats();
                    loadIncidents();
                } else {
                    showAlert('error', '❌', 'Clear Failed', result.error || 'Unknown error');
                }
            } catch (error) {
                showAlert('error', '❌', 'Clear Failed', 'Make sure database is connected.');
                console.error('Clear error:', error);
            }
        }
    });
    
    // Test DB
    document.getElementById('testDbBtn')?.addEventListener('click', testDatabaseConnection);
});

// Make functions globally accessible
window.sendCommand = sendCommand;
window.addSerialMessage = addSerialMessage;
window.connectSerial = connectSerial;
window.showAlert = showAlert;
window.closeAlert = closeAlert;

console.log('✅ Dashboard loaded successfully!');