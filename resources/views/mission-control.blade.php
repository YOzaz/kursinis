<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mission Control - System-Wide AI Analysis Monitoring</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(45deg, #0a0a0a, #1a1a2e, #16213e);
            color: #00ff41;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
            min-height: 100vh;
            position: relative;
        }

        /* Matrix rain effect */
        .matrix-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.1;
            z-index: -1;
            pointer-events: none;
        }

        .container {
            padding: 20px;
            max-width: 1600px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #00ff41;
            padding-bottom: 20px;
        }

        .header h1 {
            font-size: 2.5em;
            text-shadow: 0 0 10px #00ff41;
            animation: glow 2s ease-in-out infinite alternate;
        }

        .header .subtitle {
            color: #00ccff;
            margin-top: 10px;
            font-size: 1.2em;
        }

        .controls {
            text-align: center;
            margin-bottom: 20px;
        }

        .filter-input {
            background: rgba(0, 0, 0, 0.8);
            border: 2px solid #00ff41;
            color: #00ff41;
            padding: 10px 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            margin: 0 10px;
            width: 300px;
        }

        .filter-input:focus {
            outline: none;
            box-shadow: 0 0 10px #00ff41;
        }

        .btn {
            background: rgba(0, 255, 65, 0.2);
            border: 2px solid #00ff41;
            color: #00ff41;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Courier New', monospace;
            margin: 0 5px;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: rgba(0, 255, 65, 0.4);
            box-shadow: 0 0 15px #00ff41;
        }

        @keyframes glow {
            from { text-shadow: 0 0 10px #00ff41; }
            to { text-shadow: 0 0 20px #00ff41, 0 0 30px #00ff41; }
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .panel {
            background: rgba(0, 0, 0, 0.8);
            border: 2px solid #00ff41;
            border-radius: 10px;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        .panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #00ff41, transparent);
            animation: scan 3s linear infinite;
        }

        @keyframes scan {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .panel h3 {
            color: #00ccff;
            margin-bottom: 15px;
            font-size: 1.3em;
            text-transform: uppercase;
        }

        .models-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 1400px) {
            .models-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        .model-card {
            background: rgba(0, 30, 60, 0.9);
            border: 1px solid #00ccff;
            border-radius: 8px;
            padding: 12px;
            position: relative;
            min-height: 180px;
        }

        .model-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .model-name {
            color: #00ccff;
            font-weight: bold;
            font-size: 1em;
        }

        .status-indicator {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-idle { background: #333; color: #999; }
        .status-processing { background: #ff6600; color: white; animation: pulse 1s infinite; }
        .status-operational { background: #00cc00; color: white; }
        .status-partial_failure { background: #cc6600; color: white; }
        .status-failed { background: #cc0000; color: white; }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .metric {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            padding: 5px 0;
            border-bottom: 1px solid rgba(0, 255, 65, 0.2);
        }

        .metric-label {
            color: #ccc;
        }

        .metric-value {
            color: #00ff41;
            font-weight: bold;
        }

        .logs-panel {
            grid-column: 1 / -1;
            max-height: 400px;
            overflow-y: auto;
        }

        .log-entry {
            display: flex;
            margin-bottom: 8px;
            padding: 8px;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 4px;
            border-left: 3px solid #00ff41;
            cursor: pointer;
        }

        .log-entry:hover {
            background: rgba(0, 255, 65, 0.1);
        }

        .log-timestamp {
            color: #666;
            width: 80px;
            flex-shrink: 0;
            font-size: 0.9em;
        }

        .log-level {
            width: 60px;
            text-align: center;
            font-weight: bold;
            flex-shrink: 0;
        }

        .log-level.INFO { color: #00ccff; }
        .log-level.WARNING { color: #ffaa00; }
        .log-level.ERROR { color: #ff4444; }

        .log-message {
            flex: 1;
            margin-left: 15px;
        }

        .log-job-id {
            color: #00ff41;
            font-size: 0.8em;
            width: 120px;
            flex-shrink: 0;
            text-overflow: ellipsis;
            overflow: hidden;
        }

        .refresh-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #00ff41;
            border-radius: 20px;
            padding: 10px 20px;
            color: #00ff41;
        }

        .refresh-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #00ff41;
            border-radius: 50%;
            margin-right: 10px;
            animation: blink 1s infinite;
        }

        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0.3; }
        }

        .stats-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .stat-box {
            background: rgba(0, 50, 100, 0.3);
            border: 1px solid #00ccff;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            flex: 1;
            margin: 5px;
            min-width: 120px;
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #00ff41;
        }

        .stat-label {
            color: #ccc;
            font-size: 0.9em;
            margin-top: 5px;
        }

        .alert {
            background: rgba(255, 0, 0, 0.2);
            border: 1px solid #ff4444;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #ff4444;
        }

        .success {
            background: rgba(0, 255, 0, 0.2);
            border: 1px solid #00ff41;
            color: #00ff41;
        }

        .filter-active {
            background: rgba(255, 204, 0, 0.2);
            border: 1px solid #ffcc00;
            color: #ffcc00;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
            .models-grid {
                grid-template-columns: 1fr;
            }
            .stats-row {
                flex-direction: column;
            }
        }
        
        @media (max-width: 1024px) and (min-width: 769px) {
            .models-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <canvas class="matrix-bg" id="matrix"></canvas>
    
    <div class="refresh-indicator">
        <span class="refresh-dot"></span>
        <span id="refresh-status">LIVE</span>
    </div>

    <div class="container">
        <div class="header">
            <h1>🤖 AI ANALYSIS MISSION CONTROL</h1>
            <div class="subtitle">System-Wide Intelligence Processing Status</div>
        </div>

        <div class="controls">
            <input type="text" id="jobFilter" class="filter-input" placeholder="Filter by Job ID (optional)" />
            <button class="btn" onclick="applyFilter()">Filter</button>
            <button class="btn" onclick="clearFilter()">Show All</button>
            <button class="btn" onclick="forceRefresh()">Force Refresh</button>
        </div>

        <div id="filter-indicator"></div>

        <div id="status-content">
            <!-- Status content will be loaded here -->
            <div style="text-align: center; padding: 50px;">
                <div style="font-size: 2em; margin-bottom: 20px;">⚡ INITIALIZING SYSTEMS ⚡</div>
                <div>Loading system-wide operational status...</div>
            </div>
        </div>
    </div>

    <script>
        // Matrix rain effect
        const canvas = document.getElementById('matrix');
        const ctx = canvas.getContext('2d');

        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        const matrix = "ABCDEFGHIJKLMNOPQRSTUVWXYZ123456789@#$%^&*()*&^%+-/~{[|`]}";
        const matrixArray = matrix.split("");

        const fontSize = 10;
        const columns = canvas.width / fontSize;

        const drops = [];
        for(let x = 0; x < columns; x++) {
            drops[x] = 1;
        }

        function draw() {
            ctx.fillStyle = 'rgba(0, 0, 0, 0.04)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            ctx.fillStyle = '#00ff41';
            ctx.font = fontSize + 'px monospace';

            for(let i = 0; i < drops.length; i++) {
                const text = matrixArray[Math.floor(Math.random() * matrixArray.length)];
                ctx.fillText(text, i * fontSize, drops[i] * fontSize);
                
                if(drops[i] * fontSize > canvas.height && Math.random() > 0.975) {
                    drops[i] = 0;
                }
                drops[i]++;
            }
        }

        setInterval(draw, 35);

        // Status management
        let refreshInterval;
        let currentJobFilter = null;

        function updateStatus() {
            const url = new URL('/api/mission-control', window.location.origin);
            if (currentJobFilter) {
                url.searchParams.append('job_id', currentJobFilter);
            }

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    renderStatus(data);
                    document.getElementById('refresh-status').textContent = 'LIVE';
                })
                .catch(error => {
                    console.error('Status update failed:', error);
                    document.getElementById('refresh-status').textContent = 'ERROR';
                });
        }

        function renderStatus(data) {
            const system = data.system;
            const jobDetails = data.job_details;
            const logs = data.logs;
            const isFiltered = data.filtered_by_job;

            // Preserve scroll position of logs panel
            let logsScrollTop = 0;
            const existingLogsPanel = document.querySelector('.logs-panel');
            if (existingLogsPanel) {
                logsScrollTop = existingLogsPanel.scrollTop;
            }

            let html = '';

            // Filter indicator
            const filterDiv = document.getElementById('filter-indicator');
            if (isFiltered) {
                filterDiv.innerHTML = `<div class="filter-active">
                    <strong>🔍 FILTERED VIEW:</strong> Showing data for Job ID: ${isFiltered}
                    <button class="btn" onclick="clearFilter()" style="margin-left: 10px; padding: 5px 10px;">Show All</button>
                </div>`;
            } else {
                filterDiv.innerHTML = '';
            }

            // System overview stats
            html += `
            <div class="stats-row">
                <div class="stat-box">
                    <div class="stat-number">${system.overview.total_jobs}</div>
                    <div class="stat-label">TOTAL JOBS</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">${system.overview.active_jobs}</div>
                    <div class="stat-label">ACTIVE</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">${system.overview.completed_jobs}</div>
                    <div class="stat-label">COMPLETED</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">${system.overview.failed_jobs}</div>
                    <div class="stat-label">FAILED</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">${system.overview.unique_texts}</div>
                    <div class="stat-label">UNIQUE TEXTS</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">${system.queue.jobs_in_queue}</div>
                    <div class="stat-label">QUEUE</div>
                </div>
            </div>`;

            // Job details (if filtering by specific job)
            if (jobDetails) {
                html += `
                <div class="grid">
                    <div class="panel">
                        <h3>🎯 Current Job Details</h3>
                        <div class="metric">
                            <span class="metric-label">Job ID:</span>
                            <span class="metric-value">${jobDetails.id}</span>
                        </div>
                        <div class="metric">
                            <span class="metric-label">Name:</span>
                            <span class="metric-value">${jobDetails.name || 'Unnamed'}</span>
                        </div>
                        <div class="metric">
                            <span class="metric-label">Status:</span>
                            <span class="metric-value">${jobDetails.status.toUpperCase()}</span>
                        </div>
                        <div class="metric">
                            <span class="metric-label">Progress:</span>
                            <span class="metric-value">${jobDetails.progress_percentage}%</span>
                        </div>
                        <div class="metric">
                            <span class="metric-label">Duration:</span>
                            <span class="metric-value">${jobDetails.duration}</span>
                        </div>
                    </div>
                    <div class="panel">
                        <h3>📊 Queue Status</h3>
                        <div class="metric">
                            <span class="metric-label">Jobs in Queue:</span>
                            <span class="metric-value">${system.queue.jobs_in_queue}</span>
                        </div>
                        <div class="metric">
                            <span class="metric-label">Failed Jobs:</span>
                            <span class="metric-value" style="color: ${system.queue.failed_jobs > 0 ? '#ff4444' : '#00ff41'}">${system.queue.failed_jobs}</span>
                        </div>
                        <div class="metric">
                            <span class="metric-label">Workers:</span>
                            <span class="metric-value">${system.queue.batch_workers_active ? 'ACTIVE' : 'INACTIVE'}</span>
                        </div>
                    </div>
                </div>`;
            }

            // AI Models status
            html += `<div class="models-grid">`;
            
            Object.entries(system.models).forEach(([key, model]) => {
                html += `
                <div class="model-card">
                    <div class="model-header">
                        <div class="model-name">${model.name}</div>
                        <div class="status-indicator status-${model.status}">${model.status.replace('_', ' ')}</div>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Provider:</span>
                        <span class="metric-value">${model.provider.toUpperCase()}</span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Total Analyses:</span>
                        <span class="metric-value">${model.total_analyses}</span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Successful:</span>
                        <span class="metric-value" style="color: #00cc00">${model.successful}</span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Failed:</span>
                        <span class="metric-value" style="color: ${model.failed > 0 ? '#ff4444' : '#00ff41'}">${model.failed}</span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Pending:</span>
                        <span class="metric-value" style="color: #ffaa00">${model.pending}</span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Success Rate:</span>
                        <span class="metric-value">${model.success_rate}%</span>
                    </div>
                </div>`;
            });

            html += `</div>`;

            // System logs
            html += `
            <div class="panel logs-panel">
                <h3>📋 System Logs ${isFiltered ? '(Filtered)' : '(System-wide)'}</h3>`;
            
            logs.forEach(log => {
                const time = new Date(log.timestamp).toLocaleTimeString();
                const jobId = log.job_id && typeof log.job_id === 'string' ? log.job_id.substring(0, 8) + '...' : 'SYSTEM';
                html += `
                <div class="log-entry" ${log.job_id && typeof log.job_id === 'string' ? `onclick="filterByJob('${log.job_id}')"` : ''}>
                    <div class="log-timestamp">${time}</div>
                    <div class="log-level ${log.level}">${log.level}</div>
                    <div class="log-job-id">${jobId}</div>
                    <div class="log-message">${log.message}</div>
                </div>`;
            });

            html += `</div>`;

            document.getElementById('status-content').innerHTML = html;
            
            // Restore scroll position of logs panel
            if (logsScrollTop > 0) {
                const newLogsPanel = document.querySelector('.logs-panel');
                if (newLogsPanel) {
                    newLogsPanel.scrollTop = logsScrollTop;
                }
            }
        }

        function applyFilter() {
            console.log('applyFilter called');
            const filterValue = document.getElementById('jobFilter').value.trim();
            console.log('Filter value:', filterValue);
            if (filterValue) {
                currentJobFilter = filterValue;
                updateStatus();
            }
        }

        function clearFilter() {
            console.log('clearFilter called');
            currentJobFilter = null;
            document.getElementById('jobFilter').value = '';
            updateStatus();
        }
        
        // Make functions globally accessible
        window.clearFilter = clearFilter;
        window.applyFilter = applyFilter;
        window.filterByJob = filterByJob;
        window.forceRefresh = forceRefresh;

        function filterByJob(jobId) {
            currentJobFilter = jobId;
            document.getElementById('jobFilter').value = jobId;
            updateStatus();
        }

        function forceRefresh() {
            updateStatus();
        }

        // Handle Enter key in filter input
        document.getElementById('jobFilter').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilter();
            }
        });

        // Check URL parameters on page load
        function initializeFromURL() {
            const urlParams = new URLSearchParams(window.location.search);
            const jobIdParam = urlParams.get('job_id');
            if (jobIdParam) {
                document.getElementById('jobFilter').value = jobIdParam;
                currentJobFilter = jobIdParam;
            }
        }

        // Start monitoring
        initializeFromURL();
        updateStatus();
        refreshInterval = setInterval(updateStatus, 5000);

        // Handle page visibility
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                clearInterval(refreshInterval);
            } else {
                updateStatus();
                refreshInterval = setInterval(updateStatus, 5000);
            }
        });

        // Window resize handler for matrix effect
        window.addEventListener('resize', function() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        });
    </script>
</body>
</html>