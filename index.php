<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TikTok Downloader API</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            max-width: 900px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        h1 {
            color: #333;
            font-size: 36px;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .subtitle {
            color: #666;
            font-size: 16px;
        }
        
        .status {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 10px;
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .section-title {
            color: #333;
            font-size: 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .endpoint {
            background: #f8fafc;
            border-left: 4px solid #667eea;
            padding: 15px 20px;
            margin-bottom: 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .endpoint:hover {
            background: #f1f5f9;
            transform: translateX(5px);
        }
        
        .endpoint-name {
            font-weight: 600;
            color: #667eea;
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .endpoint-url {
            font-family: 'Courier New', monospace;
            color: #64748b;
            font-size: 13px;
            word-break: break-all;
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .feature {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            font-size: 14px;
            font-weight: 500;
        }
        
        .example {
            background: #1e293b;
            color: #10b981;
            padding: 20px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
            margin-top: 10px;
        }
        
        .example code {
            color: #10b981;
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
            color: #64748b;
            font-size: 14px;
        }
        
        .badge {
            display: inline-block;
            background: #ede9fe;
            color: #7c3aed;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 25px;
            }
            
            h1 {
                font-size: 28px;
            }
            
            .logo {
                font-size: 36px;
            }
            
            .features {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🎵</div>
            <h1>API TIKTOK DOWNLOADER</h1>
            <p class="subtitle">Fast & Reliable TikTok Video Downloader API</p>
            <span class="status">🟢 ACTIVE</span>
        </div>
        
        <div class="section">
            <h2 class="section-title">📡 Available Endpoints</h2>
            
            <div class="endpoint">
                <div class="endpoint-name">TikWM API <span class="badge">Recommended</span></div>
                <div class="endpoint-url"><?php echo 'https://' . $_SERVER['HTTP_HOST']; ?>/api/tikwm?url=VIDEO_URL</div>
            </div>
            
            <div class="endpoint">
                <div class="endpoint-name">TikTok API</div>
                <div class="endpoint-url"><?php echo 'https://' . $_SERVER['HTTP_HOST']; ?>/api/tiktokapi?url=VIDEO_URL</div>
            </div>
            
            <div class="endpoint">
                <div class="endpoint-name">TTSave</div>
                <div class="endpoint-url"><?php echo 'https://' . $_SERVER['HTTP_HOST']; ?>/api/ttsave?url=VIDEO_URL</div>
            </div>
            
            <div class="endpoint">
                <div class="endpoint-name">SnaptikIO</div>
                <div class="endpoint-url"><?php echo 'https://' . $_SERVER['HTTP_HOST']; ?>/api/sstikio?url=VIDEO_URL</div>
            </div>
            
            <div class="endpoint">
                <div class="endpoint-name">MusicalDown</div>
                <div class="endpoint-url"><?php echo 'https://' . $_SERVER['HTTP_HOST']; ?>/api/musicaldown?url=VIDEO_URL</div>
            </div>
            
            <div class="endpoint">
                <div class="endpoint-name">TikTok Download Online</div>
                <div class="endpoint-url"><?php echo 'https://' . $_SERVER['HTTP_HOST']; ?>/api/tiktokdownloadonline?url=VIDEO_URL</div>
            </div>
            
            <div class="endpoint">
                <div class="endpoint-name">TikTok Stalk Profile</div>
                <div class="endpoint-url"><?php echo 'https://' . $_SERVER['HTTP_HOST']; ?>/api/tiktok-stalk?username=USERNAME</div>
            </div>
            
            <div class="endpoint">
                <div class="endpoint-name">Search TikTok</div>
                <div class="endpoint-url"><?php echo 'https://' . $_SERVER['HTTP_HOST']; ?>/api/caritiktok?query=KEYWORD</div>
            </div>
            
            <div class="endpoint">
                <div class="endpoint-name">Video Info</div>
                <div class="endpoint-url"><?php echo 'https://' . $_SERVER['HTTP_HOST']; ?>/api/infotiktok?url=VIDEO_URL</div>
            </div>
            
            <div class="endpoint">
                <div class="endpoint-name">Video Metadata</div>
                <div class="endpoint-url"><?php echo 'https://' . $_SERVER['HTTP_HOST']; ?>/api/metadata?url=VIDEO_URL</div>
            </div>
            
            <div class="endpoint">
                <div class="endpoint-name">All API List</div>
                <div class="endpoint-url"><?php echo 'https://' . $_SERVER['HTTP_HOST']; ?>/api/allapi</div>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">✨ Features</h2>
            <div class="features">
                <div class="feature">🎬 No Watermark</div>
                <div class="feature">⚡ Fast Download</div>
                <div class="feature">🎵 Audio Extract</div>
                <div class="feature">📊 Statistics</div>
                <div class="feature">👤 Profile Info</div>
                <div class="feature">🔍 Search Videos</div>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">💻 Example Usage</h2>
            <div class="example">
<code>// JavaScript Fetch
fetch('<?php echo 'https://' . $_SERVER['HTTP_HOST']; ?>/api/tikwm?url=VIDEO_URL')
  .then(res => res.json())
  .then(data => console.log(data));

// cURL
curl "<?php echo 'https://' . $_SERVER['HTTP_HOST']; ?>/api/tikwm?url=VIDEO_URL"</code>
            </div>
        </div>
        
        <div class="footer">
            <p><strong>API Version 1.0</strong> | Made with ❤️</p>
            <p>⚠️ For educational purposes only. Please respect TikTok's Terms of Service.</p>
        </div>
    </div>
</body>
</html>
