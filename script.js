// Face Recognition Attendance System - Main Script
// Common functions for all pages

// Global variables
let videoStream = null;
let autoModeInterval = null;
let isAutoMode = false;

// Initialize camera
async function initializeCamera(videoElementId = 'video') {
    const video = document.getElementById(videoElementId);
    if (!video) return null;
    
    try {
        // Get camera permissions
        const stream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: 'user', // Use front camera (laptop camera)
                width: { ideal: 1280 },
                height: { ideal: 720 }
            },
            audio: false
        });
        
        video.srcObject = stream;
        videoStream = stream;
        
        // Handle camera errors
        video.onerror = (e) => {
            console.error('Video error:', e);
            showMessage('Camera error occurred. Please refresh the page.', 'error');
        };
        
        return stream;
    } catch (error) {
        console.error('Error accessing camera:', error);
        
        if (error.name === 'NotAllowedError') {
            showMessage('Camera access denied. Please allow camera permissions.', 'error');
        } else if (error.name === 'NotFoundError') {
            showMessage('No camera found on your device.', 'error');
        } else if (error.name === 'NotReadableError') {
            showMessage('Camera is being used by another application.', 'error');
        } else {
            showMessage('Error accessing camera: ' + error.message, 'error');
        }
        
        return null;
    }
}

// Capture image from video
function captureImage(videoElementId = 'video', canvasElementId = 'canvas') {
    const video = document.getElementById(videoElementId);
    const canvas = document.getElementById(canvasElementId);
    
    if (!video || !canvas) {
        showMessage('Video or canvas element not found', 'error');
        return null;
    }
    
    // Set canvas dimensions to match video
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    // Draw current video frame to canvas
    const context = canvas.getContext('2d');
    context.drawImage(video, 0, 0, canvas.width, canvas.height);
    
    // Convert canvas to base64 image
    return canvas.toDataURL('image/jpeg', 0.8);
}

// Show notification message
function showMessage(text, type = 'info', duration = 3000) {
    // Remove existing message
    let messageDiv = document.getElementById('global-message');
    if (!messageDiv) {
        messageDiv = document.createElement('div');
        messageDiv.id = 'global-message';
        document.body.appendChild(messageDiv);
    }
    
    // Set message content and style
    messageDiv.textContent = text;
    messageDiv.className = `message ${type}`;
    messageDiv.style.display = 'block';
    
    // Position message
    messageDiv.style.position = 'fixed';
    messageDiv.style.top = '20px';
    messageDiv.style.right = '20px';
    messageDiv.style.zIndex = '10000';
    
    // Auto-hide after duration
    setTimeout(() => {
        messageDiv.style.display = 'none';
    }, duration);
    
    // Add click to dismiss
    messageDiv.onclick = () => {
        messageDiv.style.display = 'none';
    };
}

// Validate form inputs
function validateForm(formData) {
    const errors = [];
    
    // Check for empty required fields
    if (formData.name && !formData.name.trim()) {
        errors.push('Name is required');
    }
    
    if (formData.employee_id && !formData.employee_id.trim()) {
        errors.push('Employee ID is required');
    }
    
    // Check for special characters in name
    if (formData.name && /[0-9]/.test(formData.name)) {
        errors.push('Name should not contain numbers');
    }
    
    // Check employee ID format (alphanumeric)
    if (formData.employee_id && !/^[A-Za-z0-9_-]+$/.test(formData.employee_id)) {
        errors.push('Employee ID should contain only letters, numbers, underscores, and hyphens');
    }
    
    return errors;
}

// Format date and time
function formatDateTime(date = new Date(), format = 'full') {
    const options = {
        full: {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        },
        date: {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        },
        time: {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        },
        timeShort: {
            hour: '2-digit',
            minute: '2-digit'
        }
    };
    
    return date.toLocaleDateString('en-US', options[format] || options.full);
}

// Download file
function downloadFile(content, fileName, contentType = 'text/csv') {
    const blob = new Blob([content], { type: contentType });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = fileName;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Generate CSV from data
function generateCSV(data) {
    if (!data || data.length === 0) return '';
    
    const headers = Object.keys(data[0]);
    const csvRows = [];
    
    // Add headers
    csvRows.push(headers.join(','));
    
    // Add rows
    for (const row of data) {
        const values = headers.map(header => {
            const value = row[header];
            // Escape commas and quotes
            const escaped = ('' + value).replace(/"/g, '""');
            return `"${escaped}"`;
        });
        csvRows.push(values.join(','));
    }
    
    return csvRows.join('\n');
}

// Toggle auto mode for attendance
function toggleAutoMode(callback, interval = 5000) {
    if (!isAutoMode) {
        isAutoMode = true;
        autoModeInterval = setInterval(callback, interval);
        return true;
    } else {
        isAutoMode = false;
        if (autoModeInterval) {
            clearInterval(autoModeInterval);
            autoModeInterval = null;
        }
        return false;
    }
}

// Stop camera stream
function stopCamera() {
    if (videoStream) {
        videoStream.getTracks().forEach(track => {
            track.stop();
        });
        videoStream = null;
    }
    
    // Also stop auto mode
    if (autoModeInterval) {
        clearInterval(autoModeInterval);
        autoModeInterval = null;
        isAutoMode = false;
    }
}

// Check if image contains face (basic validation)
function validateFaceImage(imageData) {
    return new Promise((resolve) => {
        const img = new Image();
        img.onload = function() {
            // Basic checks
            const hasReasonableSize = img.width >= 200 && img.height >= 200;
            const isNotTooLarge = img.width <= 5000 && img.height <= 5000;
            
            if (!hasReasonableSize) {
                resolve({ valid: false, error: 'Image is too small. Please capture a closer shot.' });
            } else if (!isNotTooLarge) {
                resolve({ valid: false, error: 'Image is too large.' });
            } else {
                resolve({ valid: true });
            }
        };
        img.onerror = function() {
            resolve({ valid: false, error: 'Invalid image data.' });
        };
        img.src = imageData;
    });
}

// API Call functions
async function apiCall(endpoint, data = null, method = 'POST') {
    const url = endpoint.startsWith('http') ? endpoint : `api.php?action=${endpoint}`;
    
    try {
        let options = {
            method: method,
            headers: {}
        };
        
        if (data) {
            if (data instanceof FormData) {
                options.body = data;
            } else {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(data);
            }
        }
        
        const response = await fetch(url, options);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message || 'API call failed');
        }
        
        return result;
    } catch (error) {
        console.error('API Call Error:', error);
        showMessage(`Error: ${error.message}`, 'error');
        throw error;
    }
}

// Load dashboard statistics
async function loadDashboardStats() {
    try {
        const employees = await apiCall('get_employees', null, 'GET');
        const attendance = await apiCall('get_today_attendance', null, 'GET');
        
        return {
            totalEmployees: employees.count || 0,
            todayPresent: attendance.total || 0,
            todayAttendance: attendance.attendance || []
        };
    } catch (error) {
        return {
            totalEmployees: 0,
            todayPresent: 0,
            todayAttendance: []
        };
    }
}

// Update dashboard UI
function updateDashboardUI(stats) {
    // Update counters
    const totalEl = document.getElementById('totalEmployees');
    const presentEl = document.getElementById('todayPresent');
    const absentEl = document.getElementById('absentToday');
    
    if (totalEl) totalEl.textContent = stats.totalEmployees;
    if (presentEl) presentEl.textContent = stats.todayPresent;
    if (absentEl) absentEl.textContent = stats.totalEmployees - stats.todayPresent;
    
    // Update attendance table
    const tableBody = document.getElementById('attendanceList');
    if (tableBody) {
        tableBody.innerHTML = '';
        
        if (stats.todayAttendance.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center">No attendance recorded today</td>
                </tr>
            `;
        } else {
            stats.todayAttendance.forEach(record => {
                const status = getAttendanceStatus(record.time_in);
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${record.employee_id || 'N/A'}</td>
                    <td>${record.name}</td>
                    <td>${record.department || 'N/A'}</td>
                    <td>${record.time_in || '--:--:--'}</td>
                    <td>${record.time_out || '--:--:--'}</td>
                    <td><span class="badge ${status.class}">${status.text}</span></td>
                `;
                tableBody.appendChild(row);
            });
        }
    }
}

// Determine attendance status
function getAttendanceStatus(timeIn) {
    if (!timeIn) return { text: 'Absent', class: 'badge-absent' };
    
    const time = new Date(`1970-01-01T${timeIn}`);
    const nineAM = new Date(`1970-01-01T09:00:00`);
    
    if (time > nineAM) {
        return { text: 'Late', class: 'badge-late' };
    }
    
    return { text: 'Present', class: 'badge-present' };
}

// Export attendance data
async function exportAttendance(date = null) {
    try {
        const today = date || new Date().toISOString().split('T')[0];
        const response = await apiCall(`get_today_attendance&date=${today}`, null, 'GET');
        
        if (response.success && response.attendance.length > 0) {
            const csvData = generateCSV(response.attendance);
            const fileName = `attendance_${today}.csv`;
            downloadFile(csvData, fileName);
            showMessage(`Attendance exported: ${fileName}`, 'success');
        } else {
            showMessage('No attendance data to export', 'info');
        }
    } catch (error) {
        showMessage('Error exporting attendance', 'error');
    }
}

// Check camera support
function checkCameraSupport() {
    return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
}

// Take screenshot with flash effect
function takeScreenshotWithFlash(videoElementId, callback) {
    const video = document.getElementById(videoElementId);
    if (!video) return;
    
    // Add flash effect
    const flash = document.createElement('div');
    flash.style.position = 'fixed';
    flash.style.top = '0';
    flash.style.left = '0';
    flash.style.width = '100%';
    flash.style.height = '100%';
    flash.style.backgroundColor = 'white';
    flash.style.opacity = '0.8';
    flash.style.zIndex = '9999';
    flash.style.transition = 'opacity 0.3s';
    document.body.appendChild(flash);
    
    // Remove flash after effect
    setTimeout(() => {
        flash.style.opacity = '0';
        setTimeout(() => {
            document.body.removeChild(flash);
        }, 300);
    }, 100);
    
    // Capture image after flash
    setTimeout(() => {
        const imageData = captureImage(videoElementId);
        if (callback && imageData) {
            callback(imageData);
        }
    }, 50);
}

// Initialize page
function initializePage() {
    // Check for required elements
    if (!checkCameraSupport()) {
        showMessage('Your browser does not support camera access. Please use Chrome, Firefox, or Edge.', 'error');
    }
    
    // Set current date and time
    updateDateTime();
    
    // Stop camera when page unloads
    window.addEventListener('beforeunload', stopCamera);
    window.addEventListener('pagehide', stopCamera);
}

// Update date and time display
function updateDateTime() {
    const dateTimeElements = document.querySelectorAll('.current-datetime');
    dateTimeElements.forEach(el => {
        el.textContent = formatDateTime();
    });
}

// Debounce function for performance
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Throttle function for performance
function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializePage();
    
    // Update time every second
    setInterval(updateDateTime, 1000);
    
    // Initialize camera if video element exists
    const videoElement = document.getElementById('video');
    if (videoElement) {
        initializeCamera('video').then(stream => {
            if (stream) {
                showMessage('Camera ready', 'success');
            }
        });
    }
    
    // Load dashboard stats if on dashboard
    if (document.getElementById('totalEmployees')) {
        loadDashboardStats().then(stats => {
            updateDashboardUI(stats);
        });
        
        // Refresh stats every 30 seconds
        setInterval(() => {
            loadDashboardStats().then(stats => {
                updateDashboardUI(stats);
            });
        }, 30000);
    }
});

// Export functions for use in HTML files
window.FaceAttendance = {
    initializeCamera,
    captureImage,
    showMessage,
    validateForm,
    formatDateTime,
    downloadFile,
    generateCSV,
    toggleAutoMode,
    stopCamera,
    validateFaceImage,
    apiCall,
    loadDashboardStats,
    updateDashboardUI,
    getAttendanceStatus,
    exportAttendance,
    checkCameraSupport,
    takeScreenshotWithFlash,
    debounce,
    throttle
};