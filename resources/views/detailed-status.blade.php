<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mission Control - AI Analysis Status</title>
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
            max-width: 1400px;
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

        .status-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .models-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .model-card {
            background: rgba(0, 30, 60, 0.9);
            border: 1px solid #00ccff;
            border-radius: 8px;
            padding: 15px;
            position: relative;
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
            font-size: 1.1em;
        }

        .status-indicator {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-pending { background: #333; color: #999; }
        .status-processing { background: #ff6600; color: white; animation: pulse 1s infinite; }
        .status-completed { background: #00cc00; color: white; }
        .status-partial_failure { background: #cc6600; color: white; }
        .status-failed { background: #cc0000; color: white; }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .progress-bar {
            width: 100%;
            height: 10px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            overflow: hidden;
            margin: 10px 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #00ff41, #00ccff);
            transition: width 0.5s ease;
            position: relative;
        }

        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
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
            max-height: 300px;
            overflow-y: auto;
        }

        .log-entry {
            display: flex;
            margin-bottom: 8px;
            padding: 8px;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 4px;
            border-left: 3px solid #00ff41;
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
        }

        .stat-box {
            background: rgba(0, 50, 100, 0.3);
            border: 1px solid #00ccff;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            flex: 1;
            margin: 0 5px;
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

        /* Responsive design */
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
            .status-grid {
                grid-template-columns: 1fr;
            }
            .models-grid {
                grid-template-columns: 1fr;
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
            <div class="subtitle">Real-time Intelligence Processing Status</div>
        </div>

        <div id="status-content">
            <!-- Status content will be loaded here -->
            <div style="text-align: center; padding: 50px;">
                <div style="font-size: 2em; margin-bottom: 20px;">⚡ INITIALIZING SYSTEMS ⚡</div>
                <div>Loading operational status...</div>
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
        const jobId = '{{ $jobId ?? "test" }}';
        let refreshInterval;

        function updateStatus() {
            fetch(`/status/${jobId}`)
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
            const stats = data.stats;
            const logs = data.logs;
            const queue = data.queue;

            let html = '';

            // Job overview
            if (stats.job.error_message) {
                html += `<div class="alert">
                    <strong>⚠️ SYSTEM ALERT:</strong> ${stats.job.error_message}
                </div>`;
            }

            // Main stats
            html += `
            <div class="stats-row">
                <div class="stat-box">
                    <div class="stat-number">${stats.job.progress_percentage}%</div>
                    <div class="stat-label">PROGRESS</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">${stats.texts.unique_texts}</div>
                    <div class="stat-label">TEXTS</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">${Object.keys(stats.models).length}</div>
                    <div class="stat-label">AI MODELS</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">${queue.jobs_in_queue}</div>
                    <div class="stat-label">QUEUE</div>
                </div>
            </div>`;

            // Job details
            html += `
            <div class="grid">
                <div class="panel">
                    <h3>🎯 Mission Details</h3>
                    <div class="metric">
                        <span class="metric-label">Job ID:</span>
                        <span class="metric-value">${stats.job.id}</span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Status:</span>
                        <span class="metric-value">${stats.job.status.toUpperCase()}</span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Duration:</span>
                        <span class="metric-value">${stats.job.duration}</span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Progress:</span>
                        <span class="metric-value">${stats.job.processed_texts}/${stats.job.total_texts}</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${stats.job.progress_percentage}%"></div>
                    </div>
                </div>

                <div class="panel">
                    <h3>📊 Data Analysis</h3>
                    <div class="metric">
                        <span class="metric-label">Total Records:</span>
                        <span class="metric-value">${stats.texts.total_records.toLocaleString()}</span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Unique Texts:</span>
                        <span class="metric-value">${stats.texts.unique_texts.toLocaleString()}</span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Avg Text Length:</span>
                        <span class="metric-value">${stats.texts.avg_text_length} chars</span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Total Characters:</span>
                        <span class="metric-value">${stats.texts.total_characters.toLocaleString()}</span>
                    </div>
                </div>
            </div>`;

            // AI Models status
            html += `<div class="models-grid">`;
            
            Object.entries(stats.models).forEach(([key, model]) => {
                const progressPercent = stats.texts.unique_texts > 0 ? 
                    Math.round((model.completed / stats.texts.unique_texts) * 100) : 0;

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
                        <span class="metric-label">Completed:</span>
                        <span class="metric-value">${model.completed}/${stats.texts.unique_texts}</span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Success Rate:</span>
                        <span class="metric-value">${model.success_rate}%</span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Errors:</span>
                        <span class="metric-value" style="color: ${model.errors > 0 ? '#ff4444' : '#00ff41'}">${model.errors}</span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Est. API Calls:</span>
                        <span class="metric-value">${model.estimated_chunks}</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${progressPercent}%"></div>
                    </div>
                </div>`;
            });

            html += `</div>`;

            // System logs
            html += `
            <div class="panel logs-panel">
                <h3>📋 System Logs</h3>`;
            
            logs.forEach(log => {
                const time = new Date(log.timestamp).toLocaleTimeString();
                html += `
                <div class="log-entry">
                    <div class="log-timestamp">${time}</div>
                    <div class="log-level ${log.level}">${log.level}</div>
                    <div class="log-message">${log.message}</div>
                </div>`;
            });

            html += `</div>`;

            document.getElementById('status-content').innerHTML = html;
        }

        // Start monitoring
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