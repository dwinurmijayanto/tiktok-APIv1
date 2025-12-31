<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

class TikTokScraperEnhanced {
    
    private $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
    ];
    private $currentUserAgent;
    private $debugMode = false;
    private $retryCount = 0;
    private $maxRetries = 3;
    
    /**
     * Constructor - Initialize with random user agent
     */
    public function __construct() {
        $this->currentUserAgent = $this->userAgents[array_rand($this->userAgents)];
    }
    
    /**
     * Fetch URL dengan cURL - Enhanced dengan auto-retry dan rotating user agents
     */
    private function fetch($url, $headers = [], $followLocation = true, $attempt = 1) {
        $ch = curl_init();
        
        // Generate cookies seperti browser asli
        $cookies = 'tt_csrf_token=' . bin2hex(random_bytes(16)) . '; ' .
                   'ttwid=' . bin2hex(random_bytes(20)) . '; ' .
                   's_v_web_id=' . bin2hex(random_bytes(16));
        
        $defaultHeaders = [
            'User-Agent: ' . $this->currentUserAgent,
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9,id;q=0.8',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Ch-Ua: "Google Chrome";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
            'Sec-Ch-Ua-Mobile: ?0',
            'Sec-Ch-Ua-Platform: "Windows"',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Cache-Control: max-age=0',
            'Cookie: ' . $cookies,
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $followLocation,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_ENCODING => 'gzip, deflate, br',
            CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
            CURLOPT_HEADER => !$followLocation,
            CURLOPT_COOKIEJAR => '/tmp/tiktok_cookies.txt',
            CURLOPT_COOKIEFILE => '/tmp/tiktok_cookies.txt',
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            // Auto-retry dengan user agent berbeda
            if ($attempt < $this->maxRetries) {
                $this->currentUserAgent = $this->userAgents[array_rand($this->userAgents)];
                sleep(1); // Delay 1 detik
                return $this->fetch($url, $headers, $followLocation, $attempt + 1);
            }
            throw new Exception("cURL Error after {$attempt} attempts: {$error}");
        }
        
        if ($httpCode !== 200 && $followLocation) {
            // Auto-retry untuk HTTP errors
            if ($attempt < $this->maxRetries && in_array($httpCode, [403, 429, 503])) {
                $this->currentUserAgent = $this->userAgents[array_rand($this->userAgents)];
                sleep(2); // Delay lebih lama untuk error
                return $this->fetch($url, $headers, $followLocation, $attempt + 1);
            }
            throw new Exception("HTTP Error {$httpCode} after {$attempt} attempts");
        }
        
        return [
            'content' => $response,
            'http_code' => $httpCode,
            'effective_url' => $effectiveUrl,
            'attempts' => $attempt
        ];
    }
    
    /**
     * Resolve shortened TikTok URL to full URL
     */
    private function resolveShortUrl($url) {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HEADER => true,
                CURLOPT_NOBODY => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_HTTPHEADER => [
                    'User-Agent: ' . $this->userAgent,
                ],
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if (preg_match('/Location:\s*(.+?)[\r\n]/i', $response, $matches)) {
                $redirectUrl = trim($matches[1]);
                $redirectUrl = str_replace(["\r", "\n"], '', $redirectUrl);
                
                if (strpos($redirectUrl, 'http') !== 0) {
                    $parsed = parse_url($url);
                    $redirectUrl = $parsed['scheme'] . '://' . $parsed['host'] . $redirectUrl;
                }
                
                return $redirectUrl;
            }
            
            if ($httpCode >= 300 && $httpCode < 400) {
                $result = $this->fetch($url, [], true);
                return $result['effective_url'];
            }
            
            throw new Exception("Could not resolve short URL. HTTP Code: {$httpCode}");
            
        } catch (Exception $e) {
            throw new Exception("Failed to resolve short URL: " . $e->getMessage());
        }
    }
    
    /**
     * Extract JSON data from TikTok HTML page - Auto-repair dengan fallback methods
     */
    private function extractJsonFromHTML($html) {
        // Debug: Save HTML to file if debug mode is on
        if ($this->debugMode) {
            file_put_contents('/tmp/tiktok_debug_' . time() . '.html', $html);
        }
        
        $methods = [
            [
                'name' => 'UNIVERSAL_DATA',
                'pattern' => '/<script id="__UNIVERSAL_DATA_FOR_REHYDRATION__" type="application\/json">(.*?)<\/script>/s',
                'parser' => function($data) {
                    return isset($data['__DEFAULT_SCOPE__']['webapp.video-detail']['itemInfo']['itemStruct']) 
                        ? ['data' => $data, 'path' => '__DEFAULT_SCOPE__.webapp.video-detail.itemInfo.itemStruct'] 
                        : null;
                }
            ],
            [
                'name' => 'SIGI_STATE',
                'pattern' => '/<script id="SIGI_STATE" type="application\/json">(.*?)<\/script>/s',
                'parser' => function($data) {
                    return isset($data['ItemModule']) 
                        ? ['data' => $data, 'path' => 'ItemModule'] 
                        : null;
                }
            ],
            [
                'name' => 'NEXT_DATA',
                'pattern' => '/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s',
                'parser' => function($data) {
                    return isset($data['props']['pageProps']['itemInfo']['itemStruct']) 
                        ? ['data' => $data, 'path' => 'props.pageProps.itemInfo.itemStruct'] 
                        : null;
                }
            ],
            [
                'name' => 'INIT_PROPS',
                'pattern' => '/window\.__INIT_PROPS__\s*=\s*({.*?});/s',
                'parser' => function($data) {
                    return isset($data['/video/[id]']['videoData']) 
                        ? ['data' => $data, 'path' => '/video/[id].videoData'] 
                        : null;
                }
            ],
        ];
        
        // Try each extraction method
        foreach ($methods as $method) {
            if (preg_match($method['pattern'], $html, $matches)) {
                $jsonData = json_decode($matches[1], true);
                if ($jsonData && json_last_error() === JSON_ERROR_NONE) {
                    $parsed = $method['parser']($jsonData);
                    if ($parsed) {
                        return [
                            'success' => true, 
                            'data' => $jsonData, 
                            'method' => $method['name'],
                            'data_path' => $parsed['path']
                        ];
                    }
                }
            }
        }
        
        // Fallback: Cari semua script tags
        if (preg_match_all('/<script[^>]*>(.*?)<\/script>/s', $html, $allScripts)) {
            foreach ($allScripts[1] as $script) {
                if (strlen($script) < 100 || stripos($script, '"video"') === false) {
                    continue;
                }
                
                if (preg_match('/({[^{}]*"video"[^{}]*{.*?}.*?})/s', $script, $jsonMatch)) {
                    $jsonData = json_decode($jsonMatch[1], true);
                    if ($jsonData && json_last_error() === JSON_ERROR_NONE) {
                        return ['success' => true, 'data' => $jsonData, 'method' => 'INLINE_SCRIPT'];
                    }
                }
            }
        }
        
        // Check for common error conditions
        if (stripos($html, 'login') !== false && stripos($html, 'Log in') !== false) {
            return ['success' => false, 'error' => 'Redirected to login - video may be private or region-restricted', 'retry_suggestion' => true];
        }
        
        if (stripos($html, 'not available') !== false || stripos($html, "couldn't find") !== false) {
            return ['success' => false, 'error' => 'Video not found or unavailable'];
        }
        
        return ['success' => false, 'error' => 'Could not extract JSON data. TikTok structure may have changed.', 'retry_suggestion' => true];
    }
    
    /**
     * Parse video data from extracted JSON - Enhanced
     */
    private function parseVideoData($jsonData, $method) {
        $videoData = null;
        $authorData = null;
        $musicData = null;
        $statsData = null;
        $itemInfo = null;
        
        // Parse based on extraction method
        if ($method === 'UNIVERSAL_DATA') {
            if (isset($jsonData['__DEFAULT_SCOPE__']['webapp.video-detail']['itemInfo']['itemStruct'])) {
                $itemInfo = $jsonData['__DEFAULT_SCOPE__']['webapp.video-detail']['itemInfo']['itemStruct'];
            }
        } elseif ($method === 'SIGI_STATE') {
            if (isset($jsonData['ItemModule'])) {
                $itemModule = $jsonData['ItemModule'];
                $itemInfo = reset($itemModule);
            }
        } elseif ($method === 'NEXT_DATA') {
            if (isset($jsonData['props']['pageProps']['itemInfo']['itemStruct'])) {
                $itemInfo = $jsonData['props']['pageProps']['itemInfo']['itemStruct'];
            }
        } elseif ($method === 'INIT_PROPS') {
            if (isset($jsonData['/video/[id]']['videoData'])) {
                $itemInfo = $jsonData['/video/[id]']['videoData'];
            }
        } elseif ($method === 'INLINE_SCRIPT') {
            $itemInfo = $jsonData;
        }
        
        if ($itemInfo) {
            $videoData = $itemInfo['video'] ?? null;
            $authorData = $itemInfo['author'] ?? null;
            $musicData = $itemInfo['music'] ?? null;
            $statsData = $itemInfo['stats'] ?? null;
        }
        
        if (!$videoData && !$authorData) {
            return null;
        }
        
        // Build comprehensive data structure
        return [
            'video' => [
                'id' => $itemInfo['id'] ?? null,
                'description' => $itemInfo['desc'] ?? null,
                'create_time' => $itemInfo['createTime'] ?? null,
                'duration' => $videoData['duration'] ?? null,
                'ratio' => $videoData['ratio'] ?? null,
                'height' => $videoData['height'] ?? null,
                'width' => $videoData['width'] ?? null,
                'bitrate' => $videoData['bitrate'] ?? null,
                'encoded_type' => $videoData['encodedType'] ?? null,
                'format' => $videoData['format'] ?? null,
                'video_quality' => $videoData['videoQuality'] ?? null,
                'cover' => $videoData['cover'] ?? null,
                'origin_cover' => $videoData['originCover'] ?? null,
                'dynamic_cover' => $videoData['dynamicCover'] ?? null,
                'play_addr' => $videoData['playAddr'] ?? null,
                'download_addr' => $videoData['downloadAddr'] ?? null,
                'share_cover' => $videoData['shareCover'] ?? null,
                'reflowed_cover' => $videoData['reflowCover'] ?? null,
            ],
            'author' => [
                'id' => $authorData['id'] ?? null,
                'unique_id' => $authorData['uniqueId'] ?? null,
                'nickname' => $authorData['nickname'] ?? null,
                'signature' => $authorData['signature'] ?? null,
                'sec_uid' => $authorData['secUid'] ?? null,
                'avatar_thumb' => $authorData['avatarThumb'] ?? null,
                'avatar_medium' => $authorData['avatarMedium'] ?? null,
                'avatar_larger' => $authorData['avatarLarger'] ?? null,
                'verified' => $authorData['verified'] ?? false,
                'private_account' => $authorData['privateAccount'] ?? false,
                'relation' => $authorData['relation'] ?? 0,
                'open_favorite' => $authorData['openFavorite'] ?? false,
                'comment_setting' => $authorData['commentSetting'] ?? 0,
                'duet_setting' => $authorData['duetSetting'] ?? 0,
                'stitch_setting' => $authorData['stitchSetting'] ?? 0,
            ],
            'music' => [
                'id' => $musicData['id'] ?? null,
                'title' => $musicData['title'] ?? null,
                'play_url' => $musicData['playUrl'] ?? null,
                'cover_thumb' => $musicData['coverThumb'] ?? null,
                'cover_medium' => $musicData['coverMedium'] ?? null,
                'cover_large' => $musicData['coverLarge'] ?? null,
                'author_name' => $musicData['authorName'] ?? null,
                'original' => $musicData['original'] ?? false,
                'duration' => $musicData['duration'] ?? null,
                'album' => $musicData['album'] ?? null,
            ],
            'statistics' => [
                'play_count' => $statsData['playCount'] ?? 0,
                'like_count' => $statsData['diggCount'] ?? 0,
                'comment_count' => $statsData['commentCount'] ?? 0,
                'share_count' => $statsData['shareCount'] ?? 0,
                'collect_count' => $statsData['collectCount'] ?? 0,
            ],
            'extra_info' => [
                'hashtags' => $itemInfo['challenges'] ?? [],
                'text_extra' => $itemInfo['textExtra'] ?? [],
                'is_ad' => $itemInfo['isAd'] ?? false,
                'location_created' => $itemInfo['locationCreated'] ?? null,
                'diversification_labels' => $itemInfo['diversificationLabels'] ?? [],
                'suggest_words' => $itemInfo['suggestWords'] ?? [],
            ],
        ];
    }
    
    /**
     * Get comprehensive video info with auto-retry and self-repair
     */
    public function getCompleteVideoInfo($url, $includeDownload = false) {
        $lastError = null;
        
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                // Clean and resolve URL
                $resolvedUrl = $this->cleanUrl($url);
                
                // Fetch HTML page with auto-retry
                $result = $this->fetch($resolvedUrl);
                $html = $result['content'];
                
                // Extract JSON data from HTML with auto-repair
                $extracted = $this->extractJsonFromHTML($html);
                
                if (!$extracted['success']) {
                    // If error suggests retry, try again with different user agent
                    if (isset($extracted['retry_suggestion']) && $extracted['retry_suggestion'] && $attempt < $this->maxRetries) {
                        $this->currentUserAgent = $this->userAgents[array_rand($this->userAgents)];
                        sleep(2);
                        continue;
                    }
                    throw new Exception($extracted['error']);
                }
                
                // Parse video data
                $parsedData = $this->parseVideoData($extracted['data'], $extracted['method']);
                
                if (!$parsedData) {
                    throw new Exception('Could not parse video data from extracted JSON');
                }
                
                $response = [
                    'success' => true,
                    'data' => $parsedData,
                    'metadata' => [
                        'extraction_method' => $extracted['method'],
                        'data_path' => $extracted['data_path'] ?? 'unknown',
                        'original_url' => $url,
                        'resolved_url' => $resolvedUrl,
                        'timestamp' => date('Y-m-d H:i:s'),
                        'user_agent' => $this->currentUserAgent,
                        'attempts' => $result['attempts'] ?? 1,
                        'total_retries' => $attempt,
                    ]
                ];
                
                // Add download info if requested
                if ($includeDownload) {
                    $downloadUrl = $parsedData['video']['download_addr'] ?? $parsedData['video']['play_addr'] ?? null;
                    
                    if ($downloadUrl) {
                        $videoId = $parsedData['video']['id'];
                        $author = $parsedData['author']['unique_id'];
                        $filename = "tiktok_{$author}_{$videoId}.mp4";
                        
                        $response['download'] = [
                            'status' => 'available',
                            'video_url' => $downloadUrl,
                            'filename' => $filename,
                            'required_headers' => [
                                'Referer' => 'https://www.tiktok.com/',
                                'User-Agent' => $this->currentUserAgent,
                            ],
                            'curl_example' => "curl -L -o \"{$filename}\" \\\n  -H \"Referer: https://www.tiktok.com/\" \\\n  -H \"User-Agent: {$this->currentUserAgent}\" \\\n  \"{$downloadUrl}\"",
                        ];
                    } else {
                        $response['download'] = [
                            'status' => 'unavailable',
                            'error' => 'Video URL not found'
                        ];
                    }
                }
                
                return $response;
                
            } catch (Exception $e) {
                $lastError = $e->getMessage();
                
                // Auto-retry dengan strategi berbeda
                if ($attempt < $this->maxRetries) {
                    $this->currentUserAgent = $this->userAgents[array_rand($this->userAgents)];
                    sleep($attempt * 2); // Exponential backoff
                    continue;
                }
            }
        }
        
        // Jika semua retry gagal
        return [
            'success' => false,
            'error' => $lastError,
            'timestamp' => date('Y-m-d H:i:s'),
            'attempts' => $this->maxRetries,
            'suggestion' => 'Try again later or check if video is accessible from your region'
        ];
    }
    
    /**
     * Clean and normalize TikTok URL
     */
    private function cleanUrl($url) {
        // Remove query parameters yang tidak perlu
        $url = preg_replace('/\?.*$/', '', $url);
        
        // Check if it's a short URL
        if (preg_match('/(?:vt|vm)\.tiktok\.com/i', $url)) {
            $resolvedUrl = $this->resolveShortUrl($url);
            $parsed = parse_url($resolvedUrl);
            if (isset($parsed['path'])) {
                $cleanUrl = $parsed['scheme'] . '://' . $parsed['host'] . $parsed['path'];
            } else {
                $cleanUrl = $resolvedUrl;
            }
            
            if (!preg_match('/tiktok\.com\/@[^\/]+\/video\/\d+/', $cleanUrl)) {
                throw new Exception('Resolved URL is not a valid TikTok video URL: ' . $cleanUrl);
            }
            
            return $cleanUrl;
        }
        
        // For regular TikTok URLs
        $parsed = parse_url($url);
        if (!isset($parsed['host']) || !isset($parsed['path'])) {
            throw new Exception('Invalid URL format');
        }
        
        $cleanUrl = $parsed['scheme'] . '://' . $parsed['host'] . $parsed['path'];
        
        if (!preg_match('/tiktok\.com\/@[^\/]+\/video\/\d+/', $cleanUrl)) {
            throw new Exception('Invalid TikTok URL format. Expected: https://www.tiktok.com/@username/video/123');
        }
        
        return $cleanUrl;
    }
    
    /**
     * Enable debug mode
     */
    public function setDebugMode($enabled) {
        $this->debugMode = $enabled;
    }
}

// API Routes
try {
    $request = $_REQUEST;
    $scraper = new TikTokScraperEnhanced();
    
    // Enable debug mode if requested
    if (isset($request['debug']) && $request['debug'] == '1') {
        $scraper->setDebugMode(true);
    }
    
    // Get video info
    if (isset($request['action']) && $request['action'] === 'video') {
        if (!isset($request['url'])) {
            throw new Exception('URL parameter is required');
        }
        
        $includeDownload = isset($request['download']) && $request['download'] == '1';
        
        $result = $scraper->getCompleteVideoInfo($request['url'], $includeDownload);
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    // Default: Show API documentation
    echo json_encode([
        'name' => 'TikTok Info Scraper API - Self-Repairing Edition',
        'version' => '4.0.0',
        'description' => 'Auto-repair TikTok scraper with intelligent retry and fallback mechanisms',
        'endpoints' => [
            '?action=video&url=VIDEO_URL' => 'Get complete video information',
            '?action=video&url=VIDEO_URL&download=1' => 'Get video info + download instructions',
            '?action=video&url=VIDEO_URL&debug=1' => 'Enable debug mode (saves HTML to /tmp/tiktok_debug.html)',
        ],
        'improvements' => [
            '🔄 Auto-retry mechanism (up to 3 attempts)',
            '🔀 Rotating user agents (4 different browsers)',
            '⏱️ Exponential backoff on failures',
            '🔧 Self-repairing extraction methods',
            '📊 Multiple data extraction strategies',
            '🛡️ Automatic error detection and recovery',
            '🍪 Dynamic cookie generation',
            '🔍 Smart pattern matching',
        ],
        'troubleshooting' => [
            'If still getting errors, try these:',
            '1. Use debug=1 parameter to save HTML response',
            '2. Check if video is private or region-restricted',
            '3. Try with a different video URL',
            '4. Wait a few minutes and try again (rate limiting)',
            '5. Contact TikTok video owner for access',
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
?>