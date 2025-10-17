/**
 * VMI Service Map - SCADA Control Room Interface
 * Real-time monitoring of console status and alerts
 */

class ServiceMap {
    constructor() {
        this.map = null;
        this.markers = [];
        this.alerts = [];
        this.consoleData = [];
        this.currentFilter = 'all';
        this.refreshInterval = null;
        this.hiddenStatuses = new Set(); // Track which status types are hidden
        
        this.init();
    }

    async init() {
        this.initMap();
        this.initEventHandlers();
        this.startClock();
        await this.loadData();
        this.startAutoRefresh();
    }

    initMap() {
        // Initialize Leaflet map with dark theme
        this.map = L.map('serviceMap', {
            zoomControl: false,
            attributionControl: false
        }).setView([-27.5, 136], 5);

        // Use OpenStreetMap with dark filter instead of requiring API key
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>',
            className: 'dark-tiles'
        }).addTo(this.map);

        // Add custom zoom control
        L.control.zoom({
            position: 'bottomright'
        }).addTo(this.map);
    }

    initEventHandlers() {
        // Filter buttons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.setFilter(e.target.dataset.filter);
            });
        });

        // Legend item toggles
        document.querySelectorAll('.legend-item').forEach(item => {
            item.addEventListener('click', (e) => {
                const status = e.currentTarget.dataset.status;
                this.toggleStatusVisibility(status);
            });
        });

        // Close modal when clicking outside
        document.getElementById('consoleModal').addEventListener('click', (e) => {
            if (e.target.id === 'consoleModal') {
                this.closeModal();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.closeModal();
            if (e.key === 'F5') {
                e.preventDefault();
                this.refreshData();
            }
        });
    }

    startClock() {
        const updateTime = () => {
            const now = new Date();
            const timeStr = now.toLocaleString('en-AU', {
                timeZone: 'Australia/Brisbane',
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('currentTime').textContent = timeStr;
        };
        
        updateTime();
        setInterval(updateTime, 1000);
    }

    async loadData() {
        try {
            this.showLoading(true);
            
            // Try enhanced endpoint first, fallback to basic GPS data
            try {
                const alertResponse = await this.fetchAlertData();
                
                if (alertResponse.success) {
                    console.log('Raw backend response:', alertResponse);
                    
                    // Convert console data to a unified format regardless of source (GPS or enhanced API)
                    this.consoleData = (alertResponse.consoles || []).map(console => ({
                        lat: console.coordinates?.lat ?? console.lat,
                        lng: console.coordinates?.lng ?? console.lng,
                        name: console.site_name ?? console.name,
                        alert: console.status ? this.mapStatusToAlert(console.status) : (console.alert || 'OK'),
                        tanks: (console.tanks || []).map(tank => ({
                            id: (tank.id ?? tank.tank_id),
                            level: (tank.level ?? tank.current_percent ?? 0)
                        })),
                        console: console // Keep original console data for details
                    }));
                    // Merge backend alerts with derived alerts from console statuses
                    const backendAlerts = alertResponse.alerts || [];
                    const derivedAlerts = this.generateAlertsFromConsoles();
                    
                    console.log('Backend alerts:', backendAlerts.length, 'alerts');
                    console.log('Backend alert severities:', backendAlerts.reduce((acc, alert) => {
                        acc[alert.severity] = (acc[alert.severity] || 0) + 1;
                        return acc;
                    }, {}));
                    
                    console.log('Derived alerts:', derivedAlerts.length, 'alerts');
                    console.log('Derived alert severities:', derivedAlerts.reduce((acc, alert) => {
                        acc[alert.severity] = (acc[alert.severity] || 0) + 1;
                        return acc;
                    }, {}));
                    
                    this.alerts = this.mergeAlerts(backendAlerts, derivedAlerts);
                    
                    console.log('Final merged alerts:', this.alerts.length, 'alerts');
                    console.log('Final alert severities:', this.alerts.reduce((acc, alert) => {
                        acc[alert.severity] = (acc[alert.severity] || 0) + 1;
                        return acc;
                    }, {}));
                    
                    // Update base stats from backend (total, online)
                    this.updateSummaryStats(alertResponse.summary);
                    // Then normalize counters using the final merged alerts so UI numbers match the list
                    this.updateCountersFromAlerts();
                } else {
                    throw new Error('Enhanced endpoint failed');
                }
            } catch (enhancedError) {
                console.warn('Enhanced endpoint failed, falling back to real GPS data:', enhancedError);
                
                // Fallback to existing working GPS endpoint (real data)
                const gpsResponse = await this.fetchConsoleData();
                if (gpsResponse && gpsResponse.locations) {
                    // Normalize GPS data to unified format used everywhere else
                    this.consoleData = (gpsResponse.locations || []).map(loc => ({
                        lat: loc.lat,
                        lng: loc.lng,
                        name: loc.name,
                        alert: loc.alert || 'OK',
                        tanks: (loc.tanks || []).map(t => ({ id: (t.id ?? t.tank_id), level: (t.level ?? t.current_percent ?? 0) }))
                    }));
                    this.alerts = this.generateAlertsFromConsoles();
                    this.updateBasicStats();
                    console.log('Successfully loaded real console data:', this.consoleData.length, 'consoles');
                    return; // Exit here, we have real data
                } else {
                    console.warn('GPS endpoint also failed, trying static fallback');
                }
            }

            this.updateMap();
            this.updateAlerts();
            this.updateLastUpdateTime();

            this.showLoading(false);
        } catch (error) {
            console.error('Error loading data:', error);
            this.showError('Failed to load data');
            this.showLoading(false);
        }
    }

    async fetchConsoleData() {
        const response = await fetch('../gps_call.php', {
            method: 'POST'
        });
        
        if (!response.ok) {
            throw new Error('Failed to fetch console data');
        }
        
        return await response.json();
    }

    async fetchAlertData() {
        
        // Try enhanced endpoint FIRST (includes offline/disconnected status)
        try {
            const response = await fetch('./console_status.php', {
                method: 'POST'
            });
            
            if (response.ok) {
                const data = await response.json();
                // Expect shape: { success, consoles, alerts, summary }
                if (data && data.success) {
                    return data;
                }
            } else {
                const errorText = await response.text();
                console.error('Console status HTTP error:', response.status, errorText);
            }
        } catch (enhancedError) {
            console.warn('Enhanced endpoint failed:', enhancedError);
        }

        // Fallback: existing working GPS endpoint (real data)
        try {
            const gpsResponse = await fetch('../gps_call.php', {
                method: 'POST'
            });
            
            if (gpsResponse.ok) {
                const gpsData = await gpsResponse.json();
                console.log('GPS call response (real data):', gpsData);
                console.log('Total locations found:', gpsData.locations?.length || 0);
                
                if (gpsData.locations && gpsData.locations.length > 0) {
                    // Convert GPS data to expected format
                    return {
                        success: true,
                        consoles: gpsData.locations.map(loc => ({
                            uid: loc.uid || `site_${loc.name}`,
                            site_name: loc.name,
                            coordinates: { lat: loc.lat, lng: loc.lng },
                            status: this.mapAlertToStatus(loc.alert),
                            tanks: loc.tanks || []
                        })),
                        alerts: this.generateAlertsFromLocations(gpsData.locations),
                        summary: {
                            total_consoles: gpsData.locations.length,
                            online_consoles: gpsData.locations.filter(l => l.alert === 'OK').length,
                            alert_consoles: gpsData.locations.filter(l => l.alert !== 'OK').length,
                            critical_alerts: gpsData.locations.filter(l => ['CRITHIGH', 'CRITLOW'].includes(l.alert)).length,
                            warning_alerts: gpsData.locations.filter(l => ['HIGH', 'LOW'].includes(l.alert)).length,
                            info_alerts: 0
                        }
                    };
                }
            } else {
                console.warn('GPS endpoint HTTP error:', gpsResponse.status);
            }
        } catch (gpsError) {
            console.warn('GPS endpoint failed:', gpsError);
        }

        // If everything fails, throw
        throw new Error('All data sources failed');
    }

    updateMap() {
        // Clear existing markers
        this.markers.forEach(marker => this.map.removeLayer(marker));
        this.markers = [];

        if (!this.consoleData.length) {
            return;
        }

        const bounds = L.latLngBounds();
        const locationCounts = {}; // Track duplicate coordinates at the same lat/lng

        this.consoleData.forEach((location, index) => {
            // Validate coordinates
            const lat = parseFloat(location.lat);
            const lng = parseFloat(location.lng);
            
            if (isNaN(lat) || isNaN(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) {
                console.warn(`Out-of-range coordinates for ${location.name}: ${location.lat}, ${location.lng}`);
                return;
            }

            // Create coordinate key to track duplicates
            const coordKey = `${lat.toFixed(5)}_${lng.toFixed(5)}`;
            locationCounts[coordKey] = (locationCounts[coordKey] || 0) + 1;
            
            // Add micro-offset for duplicate locations so markers don't overlap
            const offset = locationCounts[coordKey] - 1;
            const jitter = 0.0009; // ~100m
            const offsetLat = lat + (offset * jitter);
            const offsetLng = lng + (offset * jitter);
            
            // Create location object with adjusted coordinates
            const adjustedLocation = {
                ...location,
                lat: offsetLat,
                lng: offsetLng,
                originalLat: lat,
                originalLng: lng
            };

            const status = this.getConsoleStatus(adjustedLocation);
            const marker = this.createConsoleMarker(adjustedLocation, status);
            
            marker.addTo(this.map);
            this.markers.push(marker);
            bounds.extend(marker.getLatLng());
        });

        // Fit map to show all markers
        if (this.markers.length > 0) {
            this.map.fitBounds(bounds, { padding: [50, 50] });
        }
        
        // Reapply visibility filter to newly created markers
        this.updateMarkerVisibility();
    }

    getConsoleStatus(location) {
        // Determine console status based on alert level or status
        const alert = location.alert?.toLowerCase();
        const status = location.status?.toLowerCase();
        
        // Check status first (from enhanced API)
        if (status) {
            switch (status) {
                case 'ok':
                    return 'ok';
                case 'critical_high':
                case 'critical_low':
                    return 'crit';
                case 'high':
                case 'low':
                    return 'warn';
                case 'offline':
                case 'disconnected':
                    return 'offline';
                case 'dip_offline':
                    return 'crit';
                default:
                    break;
            }
        }
        
        // Fallback to alert-based detection (for GPS data)
        switch (alert) {
            case 'crithigh':
            case 'critlow':
                return 'crit';
            case 'high':
            case 'low':
                return 'warn';
            case 'ok':
                return 'ok';
            case 'dip_offline':
                return 'crit';
            case 'offline':
            case 'disconnected':
                return 'offline';
            default:
                return 'offline';
        }
    }

    createConsoleMarker(location, status) {
        const icon = L.divIcon({
            className: `console-marker ${status}`,
            iconSize: [24, 24],
            iconAnchor: [12, 12],
            popupAnchor: [0, -12],
            html: `<i class="fas fa-circle"></i>`
        });

        const lat = Number(location.lat);
        const lng = Number(location.lng);
        const marker = L.marker([lat, lng], { icon });
        
        // Store status for filtering
        marker.markerStatus = status;
        
        // Create popup content
        const popupContent = this.createPopupContent(location);
        marker.bindPopup(popupContent, {
            maxWidth: 300,
            className: 'custom-popup'
        });

        // Add click handler for detailed view
        marker.on('click', () => {
            console.log('Marker clicked for location:', location.name);
            this.showConsoleDetails(location);
        });
        
        // Add double-click handler as alternative
        marker.on('dblclick', () => {
            console.log('Marker double-clicked for location:', location.name);
            this.showConsoleDetails(location);
        });

        return marker;
    }

    createPopupContent(location) {
        const statusClass = this.getConsoleStatus(location);
        const statusText = this.getStatusText(location.alert);
        const locationIndex = this.consoleData.indexOf(location);
        
        console.log('Creating popup for location:', location.name, 'index:', locationIndex);
        
        return `
            <div class="popup-content">
                <div class="popup-header">
                    <h4>${location.name}</h4>
                    <span class="status-badge ${statusClass}">${statusText}</span>
                </div>
                <div class="popup-tanks">
                    ${(location.tanks || []).map(tank => `
                        <div class="tank-info">
                            <span>Tank ${tank.id || tank.tank_id || 'Unknown'}</span>
                            <span>${this.formatFixed(tank.level !== undefined ? tank.level : tank.current_percent, 1, '0.0')}%</span>
                            <div class="level-bar">
                                <div class="level-fill" style="width: ${this.coercePercent(tank.level !== undefined ? tank.level : tank.current_percent)}%"></div>
                            </div>
                        </div>
                    `).join('')}
                </div>
                <button class="popup-details-btn" onclick="console.log('Details button clicked for index: ${locationIndex}'); serviceMap.showConsoleDetailsByIndex(${locationIndex})">
                    View Details
                </button>
            </div>
        `;
    }

    getStatusText(alert) {
        const statusMap = {
            'CRITHIGH': 'Critical High',
            'CRITLOW': 'Critical Low',
            'CRITICAL_HIGH': 'Critical High',
            'CRITICAL_LOW': 'Critical Low',
            'HIGH': 'High',
            'LOW': 'Low',
            'OK': 'Normal',
            'OFFLINE': 'Offline',
            'DISCONNECTED': 'Disconnected',
            'DIP_OFFLINE': 'Dip Out of Sync'
        };
        return statusMap[alert?.toUpperCase()] || 'Offline';
    }

    formatFixed(value, digits, fallback = 'N/A') {
        const num = Number(value);
        return Number.isFinite(num) ? num.toFixed(digits) : fallback;
    }

    coercePercent(value) {
        const num = Number(value);
        if (!Number.isFinite(num)) return 0;
        if (num < 0) return 0;
        if (num > 100) return 100;
        return num;
    }

    updateAlerts() {
        const alertsList = document.getElementById('alertsList');
        
        if (this.alerts.length === 0) {
            alertsList.innerHTML = `
                <div class="no-alerts">
                    <i class="fas fa-check-circle"></i>
                    <p>No active alerts</p>
                </div>
            `;
            return;
        }

        const filteredAlerts = this.filterAlerts();
        
        alertsList.innerHTML = filteredAlerts.map(alert => `
            <div class="alert-item ${alert.severity} ${alert.acknowledged ? 'acknowledged' : ''}" onclick="serviceMap.showAlertDetails('${alert.id}')">
                <div class="alert-title">
                    <i class="fas ${this.getAlertIcon(alert.alert_type)}"></i>
                    ${alert.title}
                    ${alert.acknowledged ? '<span class="ack-badge"><i class="fas fa-check"></i> ACK</span>' : ''}
                </div>
                <div class="alert-description">${alert.message}</div>
                ${alert.acknowledged && alert.ticket_id ? `
                <div class="alert-ticket">
                    <span><i class="fas fa-ticket-alt"></i> Ticket: ${alert.ticket_id}</span>
                    ${alert.ticket_comment ? `<span class="ticket-comment">${alert.ticket_comment}</span>` : ''}
                </div>
                ` : ''}
                <div class="alert-meta">
                    <span>${alert.site_name}</span>
                    <span>${this.formatTime(new Date(alert.timestamp))}</span>
                </div>
            </div>
        `).join('');
    }

    generateAlertsFromConsoles() {
        const alerts = [];
        
        this.consoleData.forEach(location => {
            // Check both status (from enhanced API) and alert (from GPS API)
            const status = (location.console?.status || location.status || location.alert)?.toLowerCase();
            const alertType = location.console?.alert_type || location.alert_type;
            
            // Check if console is offline or disconnected
            const isOfflineOrDisconnected = status === 'offline' || status === 'disconnected' || 
                                          alertType === 'console_offline' || alertType === 'device_disconnected';
            
            // Also check if this is a dip_offline status that should be suppressed if there are connectivity issues
            const isDipOffline = status === 'dip_offline' || alertType === 'dip_out_of_sync';
            
            if (status && status !== 'ok') {
                // Site-level alert
                alerts.push({
                    id: `site_${location.name}_${Date.now()}`,
                    type: status,
                    alert_type: alertType || status,
                    severity: this.getSeverityFromStatus(status),
                    title: `${this.getStatusText(status)} - ${location.name}`,
                    description: `Site status: ${this.getStatusText(status)}`,
                    site_name: location.name,
                    message: location.console?.message || `Site status: ${this.getStatusText(status)}`,
                    timestamp: new Date().toISOString(),
                    location: location
                });

                // ONLY generate tank-level alerts if console is NOT offline/disconnected
                // When offline, tank data is unreliable so we skip tank alerts
                if (!isOfflineOrDisconnected) {
                    location.tanks.forEach(tank => {
                        if (tank.level <= 10) {
                            alerts.push({
                                id: `tank_${location.name}_${tank.id}_low`,
                                type: 'low',
                                alert_type: 'critical_low',
                                severity: 'warning',
                                title: `Critical Low Level - Tank ${tank.id}`,
                                description: `Tank level at ${tank.level}%`,
                                site_name: location.name,
                                message: `Tank level at ${tank.level}%`,
                                timestamp: new Date().toISOString(),
                                location: location
                            });
                        }
                    });
                }
            }
        });

        return alerts;
    }

    mergeAlerts(backendAlerts, derivedAlerts) {
        // Group alerts by console/site to apply priority rules
        const alertsByConsole = new Map();
        
        const addAlert = (alert) => {
            // Normalize alert type variants to avoid duplicates across sources
            if (alert && alert.alert_type) {
                alert.alert_type = (alert.alert_type || '').toLowerCase()
                  .replace('device_disconnected', 'console_offline')
                  .replace('disconnected', 'console_offline')
                  .replace('volume_critical_low', 'critical_low')
                  .replace('volume_critical_high', 'critical_high');
            }
            const consoleKey = alert.console_uid || alert.location?.console?.uid || alert.site_name;
            if (!alertsByConsole.has(consoleKey)) {
                alertsByConsole.set(consoleKey, []);
            }
            alertsByConsole.get(consoleKey).push(alert);
        };
        
        // Add all alerts grouped by console
        backendAlerts.forEach(addAlert);
        derivedAlerts.forEach(addAlert);
        
        const finalAlerts = [];
        
        // For each console, apply priority rules
        alertsByConsole.forEach((consoleAlerts, consoleKey) => {
            // Check if there are any critical alerts for this console (offline, disconnected, dip_out_of_sync)
            const hasCriticalAlert = consoleAlerts.some(alert => {
                const alertType = alert.alert_type || alert.type || '';
                const severity = alert.severity || '';
                return severity === 'critical' || 
                       alertType.includes('offline') || 
                       alertType.includes('disconnected') ||
                       alertType === 'dip_out_of_sync';
            });
            
            // If console has critical alerts, filter out warning-level alerts and dip_out_of_sync (if offline)
            if (hasCriticalAlert) {
                const hasOfflineAlert = consoleAlerts.some(alert => {
                    const alertType = alert.alert_type || alert.type || '';
                    return alertType.includes('offline') || alertType.includes('disconnected');
                });
                
                const filteredAlerts = consoleAlerts.filter(alert => {
                    const alertType = alert.alert_type || alert.type || '';
                    const severity = alert.severity || '';
                    
                    // Filter out dip_out_of_sync if console is offline
                    if (hasOfflineAlert && alertType === 'dip_out_of_sync') {
                        return false;
                    }
                    
                    // Keep only critical alerts, filter out warnings
                    return severity === 'critical';
                });
                finalAlerts.push(...filteredAlerts);
            } else {
                // No critical alerts, include all alerts for this console
                finalAlerts.push(...consoleAlerts);
            }
        });
        
        // Remove duplicates by (console, normalized type, tank)
        const unique = new Map();
        finalAlerts.forEach(alert => {
            const key = `${alert.console_uid || alert.location?.console?.uid || alert.site_name}_${(alert.alert_type || alert.type || '').toLowerCase()}_${alert.tank_id || ''}`;
            if (!unique.has(key)) unique.set(key, alert);
        });
        
        return Array.from(unique.values());
    }

    getSeverityFromStatus(status) {
        switch (status) {
            case 'offline':
            case 'disconnected':
            case 'dip_offline':
                return 'critical';
            case 'crithigh':
            case 'critlow':
            case 'critical_high':
            case 'critical_low':
            case 'high':
            case 'low':
                return 'warning';
            default:
                return 'info';
        }
    }

    getAlertIcon(type) {
        const normalized = (type || '').toLowerCase()
            .replace('device_disconnected', 'console_offline')
            .replace('disconnected', 'console_offline')
            .replace('volume_critical_low', 'critical_low')
            .replace('volume_critical_high', 'critical_high');
        const iconMap = {
            'crithigh': 'fa-arrow-up',
            'critlow': 'fa-arrow-down',
            'critical_high': 'fa-arrow-up',
            'critical_low': 'fa-arrow-down',
            'high': 'fa-exclamation-triangle',
            'low': 'fa-exclamation-circle',
            'offline': 'fa-wifi-slash',
            'console_offline': 'fa-wifi-slash',
            'dip_offline': 'fa-wifi-slash'
        };
        return iconMap[normalized] || 'fa-exclamation';
    }

    filterAlerts() {
        if (this.currentFilter === 'acknowledged') {
            // Only show acknowledged alarms
            return this.alerts.filter(alert => alert.acknowledged === true);
        }
        
        // For all other filters, exclude acknowledged alarms
        const activeAlerts = this.alerts.filter(alert => !alert.acknowledged);
        
        if (this.currentFilter === 'all') {
            return activeAlerts;
        }
        
        return activeAlerts.filter(alert => {
            if (this.currentFilter === 'critical') {
                return alert.severity === 'critical';
            }
            if (this.currentFilter === 'warning') {
                return alert.severity === 'warning';
            }
            if (this.currentFilter === 'offline') {
                // Check for offline/disconnected alert types
                const alertType = alert.alert_type || alert.type || '';
                return (alertType.includes('offline') && alertType !== 'dip_offline') || 
                       alertType.includes('disconnected') || 
                       alertType === 'console_offline' || 
                       alertType === 'device_disconnected';
            }
            return true;
        });
    }

    setFilter(filter) {
        this.currentFilter = filter;
        
        // Update filter buttons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.filter === filter);
        });
        
        this.updateAlerts();
    }

    toggleStatusVisibility(status) {
        const legendItem = document.querySelector(`.legend-item[data-status="${status}"]`);
        
        if (this.hiddenStatuses.has(status)) {
            // Show this status
            this.hiddenStatuses.delete(status);
            legendItem.classList.add('active');
        } else {
            // Hide this status
            this.hiddenStatuses.add(status);
            legendItem.classList.remove('active');
        }
        
        // Update map markers to show/hide based on status
        this.updateMarkerVisibility();
    }

    updateMarkerVisibility() {
        this.markers.forEach(marker => {
            const markerStatus = marker.markerStatus;
            
            if (this.hiddenStatuses.has(markerStatus)) {
                // Hide marker
                marker.setOpacity(0);
                marker.options.interactive = false;
            } else {
                // Show marker
                marker.setOpacity(1);
                marker.options.interactive = true;
            }
        });
    }

    mapStatusToAlert(status) {
        // Map internal status to display alert format
        const statusMap = {
            'ok': 'OK',
            'critical_high': 'CRITHIGH',
            'critical_low': 'CRITLOW',
            'high': 'HIGH',
            'low': 'LOW',
            'offline': 'OFFLINE',
            'disconnected': 'DISCONNECTED',
            'dip_offline': 'DIP_OFFLINE'
        };
        return statusMap[status] || 'OFFLINE';
    }

    mapAlertToStatus(alert) {
        // Map GPS alert format to internal status
        const alertMap = {
            'OK': 'ok',
            'CRITHIGH': 'critical_high',
            'CRITLOW': 'critical_low',
            'HIGH': 'high',
            'LOW': 'low'
        };
        return alertMap[alert] || 'offline';
    }

    generateAlertsFromLocations(locations) {
        const alerts = [];
        let alertId = 1;
        
        locations.forEach(location => {
            if (location.alert && location.alert !== 'OK') {
                const severity = ['CRITHIGH', 'CRITLOW'].includes(location.alert) ? 'critical' : 'warning';
                
                // Check if console is offline or disconnected
                const isOfflineOrDisconnected = location.alert === 'OFFLINE' || location.alert === 'DISCONNECTED';
                
                alerts.push({
                    id: alertId++,
                    console_uid: location.uid || `site_${location.name}`,
                    site_name: location.name,
                    alert_type: location.alert.toLowerCase(),
                    severity: severity,
                    title: `${this.getStatusText(location.alert)} - ${location.name}`,
                    message: `Site status: ${this.getStatusText(location.alert)}`,
                    timestamp: new Date().toISOString()
                });

                // ONLY add tank-level alerts if console is NOT offline/disconnected
                // When offline, tank data is unreliable so we skip tank alerts
                if (!isOfflineOrDisconnected) {
                    location.tanks?.forEach(tank => {
                        if (tank.level <= 10) {
                            alerts.push({
                                id: alertId++,
                                console_uid: location.uid || `site_${location.name}`,
                                site_name: location.name,
                                alert_type: 'critical_low',
                                severity: 'warning',
                                title: `Critical Low Level - Tank ${tank.id}`,
                                message: `Tank level at ${tank.level}%`,
                                timestamp: new Date().toISOString()
                            });
                        }
                    });
                }
            }
        });

        return alerts;
    }

    updateSummaryStats(summary) {
        if (summary) {
            document.getElementById('totalConsoles').textContent = summary.total_consoles;
            document.getElementById('onlineConsoles').textContent = summary.online_consoles;
            // Defer alertConsoles/critical/warning/info to merged alerts to keep UI == list
            // document.getElementById('alertConsoles').textContent = summary.alert_consoles;
            // document.getElementById('criticalCount').textContent = summary.critical_alerts;
            // document.getElementById('warningCount').textContent = summary.warning_alerts;
            // document.getElementById('infoCount').textContent = summary.info_alerts;
        }
    }

    // Normalize header counters from the final merged alerts so badges match the list
    updateCountersFromAlerts() {
        // Only count active (non-acknowledged) alerts
        const activeAlerts = this.alerts.filter(a => !a.acknowledged);
        
        const criticalAlerts = activeAlerts.filter(a => a.severity === 'critical').length;
        const warningAlerts  = activeAlerts.filter(a => a.severity === 'warning').length;
        const infoAlerts     = activeAlerts.filter(a => a.severity === 'info').length;

        const consolesWithAlerts = new Set();
        activeAlerts.forEach(a => {
            const key = a.console_uid || a.location?.console?.uid || a.site_name;
            if (key) consolesWithAlerts.add(key);
        });

        document.getElementById('criticalCount').textContent = criticalAlerts;
        document.getElementById('warningCount').textContent  = warningAlerts;
        document.getElementById('infoCount').textContent     = infoAlerts;
        document.getElementById('alertConsoles').textContent = consolesWithAlerts.size;
    }

    updateBasicStats() {
        const totalConsoles = this.consoleData.length;
        const onlineConsoles = this.consoleData.filter(c => c.alert === 'OK').length;
        const alertConsoles = totalConsoles - onlineConsoles;
        
        const criticalAlerts = this.alerts.filter(a => a.severity === 'critical').length;
        const warningAlerts = this.alerts.filter(a => a.severity === 'warning').length;
        const infoAlerts = this.alerts.filter(a => a.severity === 'info').length;

        document.getElementById('totalConsoles').textContent = totalConsoles;
        document.getElementById('onlineConsoles').textContent = onlineConsoles;
        document.getElementById('alertConsoles').textContent = alertConsoles;
        
        document.getElementById('criticalCount').textContent = criticalAlerts;
        document.getElementById('warningCount').textContent = warningAlerts;
        document.getElementById('infoCount').textContent = infoAlerts;
    }

    updateLastUpdateTime() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('en-AU');
        document.getElementById('lastUpdate').textContent = timeStr;
    }

    showConsoleDetailsByIndex(index) {
        console.log('showConsoleDetailsByIndex called with index:', index);
        console.log('consoleData length:', this.consoleData.length);
        
        if (index >= 0 && index < this.consoleData.length) {
            console.log('Showing details for console:', this.consoleData[index]);
            this.showConsoleDetails(this.consoleData[index]);
        } else {
            console.error('Invalid index:', index, 'consoleData length:', this.consoleData.length);
        }
    }

    getDeviceTypeName(deviceType) {
        const deviceTypeMap = {
            20: 'Ehon Link',
            30: 'Ehon Gateway',
            200: 'MCS Lite',
            201: 'MCS Pro'
        };
        return deviceTypeMap[deviceType] || `Unknown (${deviceType})`;
    }

    showConsoleDetails(location) {
        console.log('showConsoleDetails called with location:', location);
        
        const modal = document.getElementById('consoleModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalBody = document.getElementById('modalBody');
        
        console.log('Modal elements:', { modal, modalTitle, modalBody });
        
        if (!modal || !modalTitle || !modalBody) {
            console.error('Modal elements not found!', { modal, modalTitle, modalBody });
            return;
        }
        
        const consoleData = location.console || location;
        const siteName = consoleData.site_name || location.name;
        
        console.log('Console data:', consoleData);
        console.log('Site name:', siteName);
        
        modalTitle.textContent = `Console Details - ${siteName}`;
        
        modalBody.innerHTML = `
            <div class="console-details">
                <div class="detail-section">
                    <h4>Site Information</h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="label">Site Name:</span>
                            <span class="value">${siteName}</span>
                        </div>
                        ${consoleData.device_type ? `
                        <div class="detail-item">
                            <span class="label">Device Type:</span>
                            <span class="value">${this.getDeviceTypeName(consoleData.device_type)}</span>
                        </div>
                        ` : ''}
                        <div class="detail-item">
                            <span class="label">Coordinates:</span>
                            <span class="value">${this.formatFixed(location.originalLat ?? location.lat, 6, 'N/A')}, ${this.formatFixed(location.originalLng ?? location.lng, 6, 'N/A')}</span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Status:</span>
                            <span class="value status-${this.getConsoleStatus(location)}">${this.getStatusText(location.alert || location.status || consoleData.status)}</span>
                        </div>
                        ${consoleData.firmware ? `
                        <div class="detail-item">
                            <span class="label">Firmware:</span>
                            <span class="value">${consoleData.firmware}</span>
                        </div>
                        ` : ''}
                        ${consoleData.last_connection && consoleData.last_connection.date && consoleData.last_connection.date !== '0000-00-00' ? `
                        <div class="detail-item">
                            <span class="label">Last Connection:</span>
                            <span class="value">${consoleData.last_connection.date} ${consoleData.last_connection.time || ''}</span>
                        </div>
                        ` : (consoleData.device_type != 201 ? `
                        <div class="detail-item">
                            <span class="label">Last Connection:</span>
                            <span class="value">No connection data</span>
                        </div>
                        ` : '')}
                        ${consoleData.debug && (consoleData.debug.last_conn_age_hours !== null && consoleData.debug.last_conn_age_hours !== undefined) ? `
                        <div class="detail-item">
                            <span class="label">Last Conn Age (h):</span>
                            <span class="value">${consoleData.debug.last_conn_age_hours}</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
                
                ${consoleData.network ? `
                <div class="detail-section">
                    <h4>Network Information</h4>
                    <div class="detail-grid">
                        ${consoleData.network.ip ? `
                        <div class="detail-item">
                            <span class="label">IP Address:</span>
                            <span class="value">${consoleData.network.ip}</span>
                        </div>
                        ` : ''}
                        ${consoleData.network.imei ? `
                        <div class="detail-item">
                            <span class="label">IMEI:</span>
                            <span class="value">${consoleData.network.imei}</span>
                        </div>
                        ` : ''}
                        ${consoleData.network.signal !== null ? `
                        <div class="detail-item">
                            <span class="label">Signal Strength:</span>
                            <span class="value">${consoleData.network.signal}%</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
                ` : ''}
                
                <div class="detail-section">
                    <h4>Tank Status</h4>
                    <div class="tanks-grid">
                        ${(location.tanks && location.tanks.length > 0) ? location.tanks.map(tank => `
                    <div class="tank-detail">
                        <div class="tank-header">
                            <span>Tank ${tank.id || tank.tank_id || 'Unknown'}</span>
                            <span class="tank-level">${this.formatFixed(tank.level !== undefined ? tank.level : tank.current_percent, 1, '0.0')}%</span>
                        </div>
                        <div class="tank-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: ${this.coercePercent(tank.level !== undefined ? tank.level : tank.current_percent)}%; background: ${this.getTankLevelColor(this.coercePercent(tank.level !== undefined ? tank.level : tank.current_percent))}"></div>
                            </div>
                        </div>
                    </div>
                        `).join('') : '<div class="no-tanks">No tank data available</div>'}
                    </div>
                </div>
                
                <div class="detail-actions">
                    <button class="btn-action" onclick="serviceMap.focusOnConsole(${location.originalLat || location.lat}, ${location.originalLng || location.lng})">
                        <i class="fas fa-crosshairs"></i> Focus on Map
                    </button>
                    ${consoleData.acknowledged ? `
                    <button class="btn-action btn-acknowledged" disabled>
                        <i class="fas fa-check-circle"></i> Acknowledged
                    </button>
                    ` : `
                    <button class="btn-action btn-acknowledge" onclick="serviceMap.acknowledgeAlarm(${consoleData.uid}, '${siteName}')">
                        <i class="fas fa-clipboard-check"></i> Acknowledge
                    </button>
                    `}
                </div>
                ${consoleData.acknowledged && consoleData.ticket_id ? `
                <div class="detail-section ticket-info">
                    <h4>Service Ticket Information</h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="label">Ticket ID:</span>
                            <span class="value">${consoleData.ticket_id}</span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Comment:</span>
                            <span class="value">${consoleData.ticket_comment || 'N/A'}</span>
                        </div>
                    </div>
                </div>
                ` : ''}
            </div>
        `;
        
        console.log('About to show modal...');
        modal.style.display = 'block';
        console.log('Modal display set to block, modal style:', modal.style.display);
    }

    getTankLevelColor(level) {
        if (level <= 10) return 'var(--status-critical)';
        if (level <= 20) return 'var(--status-warning)';
        return 'var(--status-ok)';
    }

    focusOnConsole(lat, lng) {
        this.map.setView([lat, lng], 15);
        this.closeModal();
    }

    async acknowledgeAlarm(uid, siteName) {
        // Prompt for ticket ID and comment
        const ticketId = prompt(`Acknowledge alarm for ${siteName}\n\nEnter Ticket ID:`);
        if (!ticketId || ticketId.trim() === '') {
            alert('Ticket ID is required to acknowledge an alarm');
            return;
        }

        const ticketComment = prompt(`Acknowledge alarm for ${siteName}\n\nEnter Ticket Comment:`);
        if (!ticketComment || ticketComment.trim() === '') {
            alert('Ticket comment is required to acknowledge an alarm');
            return;
        }

        try {
            const response = await fetch('./acknowledge_alarm.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    uid: uid,
                    ticket_id: ticketId.trim(),
                    ticket_comment: ticketComment.trim()
                })
            });

            const result = await response.json();

            if (result.success) {
                alert(`Alarm acknowledged successfully!\nTicket ID: ${result.ticket_id}`);
                this.closeModal();
                await this.loadData(); // Reload data to update UI
            } else {
                alert(`Error: ${result.error}`);
            }
        } catch (error) {
            console.error('Error acknowledging alarm:', error);
            alert('Failed to acknowledge alarm. Please try again.');
        }
    }

    showAlertDetails(alertId) {
        const alert = this.alerts.find(a => a.id == alertId);
        if (!alert) return;
        
        // Find the console associated with this alert
        const consoleItem = this.consoleData.find(c => c.console && c.console.uid == alert.console_uid);
        if (consoleItem) {
            this.showConsoleDetails(consoleItem);
        }
    }

    closeModal() {
        console.log('closeModal called');
        const modal = document.getElementById('consoleModal');
        if (modal) {
            modal.style.display = 'none';
            console.log('Modal hidden');
        } else {
            console.error('Modal element not found when trying to close');
        }
    }
    
    // Test function to verify modal works
    testModal() {
        console.log('Testing modal...');
        const modal = document.getElementById('consoleModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalBody = document.getElementById('modalBody');
        
        if (!modal || !modalTitle || !modalBody) {
            console.error('Modal elements missing:', { modal, modalTitle, modalBody });
            return;
        }
        
        modalTitle.textContent = 'Test Modal';
        modalBody.innerHTML = '<p>This is a test modal to verify functionality.</p>';
        modal.style.display = 'block';
        console.log('Test modal should now be visible');
    }

    formatTime(date) {
        return date.toLocaleTimeString('en-AU', {
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    async refreshData() {
        const refreshBtn = document.querySelector('.btn-refresh i');
        refreshBtn.style.animation = 'spin 1s linear infinite';
        
        await this.loadData();
        
        setTimeout(() => {
            refreshBtn.style.animation = '';
        }, 1000);
    }

    startAutoRefresh() {
        // Refresh every 30 seconds
        this.refreshInterval = setInterval(() => {
            this.loadData();
        }, 30000);
    }

    showLoading(show) {
        // You could add a loading overlay here
        const refreshBtn = document.querySelector('.btn-refresh');
        refreshBtn.disabled = show;
    }

    showError(message) {
        // You could show a toast notification here
        console.error(message);
    }
}

// Global functions for HTML onclick handlers
let serviceMap;

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
}

function refreshData() {
    serviceMap.refreshData();
}

function closeModal() {
    serviceMap.closeModal();
}

// Test function for debugging
function testModal() {
    if (serviceMap) {
        serviceMap.testModal();
    } else {
        console.error('serviceMap not initialized');
    }
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .custom-popup .leaflet-popup-content-wrapper {
        background: var(--bg-panel);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
        border-radius: 8px;
    }
    
    .custom-popup .leaflet-popup-tip {
        background: var(--bg-panel);
        border: 1px solid var(--border-color);
    }
    
    .popup-content {
        padding: 10px;
    }
    
    .popup-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        padding-bottom: 8px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .popup-header h4 {
        margin: 0;
        color: var(--accent-cyan);
        font-size: 1rem;
    }
    
    .status-badge {
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: bold;
        text-transform: uppercase;
    }
    
    .status-badge.ok { background: var(--status-ok); color: black; }
    .status-badge.warn { background: var(--status-warning); color: black; }
    .status-badge.crit { background: var(--status-critical); color: white; }
    .status-badge.offline { background: var(--status-offline); color: white; }
    
    .tank-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        font-size: 0.85rem;
    }
    
    .level-bar {
        width: 80px;
        height: 6px;
        background: var(--bg-tertiary);
        border-radius: 3px;
        overflow: hidden;
        margin-left: 10px;
    }
    
    .level-fill {
        height: 100%;
        background: var(--status-ok);
        transition: width 0.3s ease;
    }
    
    .popup-details-btn {
        width: 100%;
        padding: 8px;
        background: var(--accent-blue);
        border: none;
        border-radius: 4px;
        color: white;
        cursor: pointer;
        margin-top: 10px;
        font-size: 0.8rem;
    }
    
    .popup-details-btn:hover {
        background: var(--accent-cyan);
    }
    
    .no-alerts {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-secondary);
    }
    
    .no-alerts i {
        font-size: 2rem;
        color: var(--status-ok);
        margin-bottom: 10px;
    }
    
    .console-details .detail-section {
        margin-bottom: 20px;
    }
    
    .console-details h4 {
        color: var(--accent-cyan);
        margin-bottom: 10px;
        font-size: 1rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .detail-grid {
        display: grid;
        gap: 10px;
    }
    
    .detail-item {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .detail-item .label {
        color: var(--text-secondary);
        font-size: 0.9rem;
    }
    
    .detail-item .value {
        color: var(--text-primary);
        font-weight: bold;
    }
    
    .tanks-grid {
        display: grid;
        gap: 15px;
    }
    
    .tank-detail {
        background: var(--bg-tertiary);
        border: 1px solid var(--border-color);
        border-radius: 6px;
        padding: 12px;
    }
    
    .tank-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    
    .tank-level {
        font-weight: bold;
        color: var(--accent-cyan);
    }
    
    .tank-progress {
        margin-top: 8px;
    }
    
    .progress-bar {
        width: 100%;
        height: 8px;
        background: var(--bg-secondary);
        border-radius: 4px;
        overflow: hidden;
    }
    
    .progress-fill {
        height: 100%;
        transition: width 0.3s ease;
    }
    
    .detail-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }
    
    .btn-action {
        flex: 1;
        padding: 10px;
        background: var(--bg-tertiary);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.85rem;
        transition: all 0.3s ease;
    }
    
    .btn-action:hover {
        background: var(--accent-blue);
        border-color: var(--accent-blue);
    }
    
    .no-tanks {
        text-align: center;
        padding: 20px;
        color: var(--text-secondary);
        font-style: italic;
    }
`;
document.head.appendChild(style);

// Initialize the service map when the page loads
document.addEventListener('DOMContentLoaded', () => {
    serviceMap = new ServiceMap();
});
