<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

/**
 * TikTok Video Downloader API
 * Menggunakan tikwm.com service
 * 
 * Usage: 
 * GET /tiktok-download.php?url=https://www.tiktok.com/@dwinurmijayanto/video/7347080009141587201
 * GET /tiktok-download.php?url=7347080009141587201
 */

function extractVideoId($input) {
    // If already a video ID (19 digits)
    if (preg_match('/^\d{19}$/', $input)) {
        return $input;
    }
    
    // Extract from TikTok URLs
    $patterns = [
        '/tiktok\.com\/@[\w.-]+\/video\/(\d+)/',
        '/tiktok\.com\/v\/(\d+)/',
        '/vm\.tiktok\.com\/(\w+)/',
        '/vt\.tiktok\.com\/(\w+)/',
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input, $matches)) {
            return $matches[1];
        }
    }
    
    return null;
}

function formatNumber($num) {
    if ($num >= 1000000) {
        return round($num / 1000000, 1) . 'M';
    } elseif ($num >= 1000) {
        return round($num / 1000, 1) . 'K';
    }
    return $num;
}

function formatDuration($seconds) {
    $minutes = floor($seconds / 60);
    $secs = $seconds % 60;
    return sprintf('%d:%02d', $minutes, $secs);
}

function tiktokDownload($url) {
    try {
        if (empty($url)) {
            throw new Exception('URL or Video ID is required');
        }
        
        // Extract video ID if URL is provided
        $videoId = extractVideoId($url);
        
        // If video ID found, rebuild URL
        if ($videoId && strlen($videoId) === 19) {
            $url = 'https://www.tiktok.com/@username/video/' . $videoId;
        }
        
        $ch = curl_init();
        
        $apiUrl = 'https://tikwm.com/api/?url=' . urlencode($url);
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept: application/json',
                'Referer: https://tikwm.com/'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception('Connection error: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new Exception('API returned status code: ' . $httpCode);
        }
        
        if (empty($response)) {
            throw new Exception('Empty response from server');
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response');
        }
        
        if (!isset($data['code']) || $data['code'] !== 0) {
            throw new Exception($data['msg'] ?? 'Failed to fetch video data');
        }
        
        if (!isset($data['data'])) {
            throw new Exception('No data found');
        }
        
        $videoData = $data['data'];
        
        // Format response
        return [
            'success' => true,
            'data' => [
                'video_id' => $videoData['id'] ?? null,
                'region' => $videoData['region'] ?? null,
                'title' => $videoData['title'] ?? null,
                'duration' => $videoData['duration'] ?? 0,
                'duration_formatted' => isset($videoData['duration']) ? formatDuration($videoData['duration']) : '0:00',
                'author' => [
                    'id' => $videoData['author']['id'] ?? null,
                    'unique_id' => $videoData['author']['unique_id'] ?? null,
                    'nickname' => $videoData['author']['nickname'] ?? null,
                    'avatar' => $videoData['author']['avatar'] ?? null
                ],
                'statistics' => [
                    'play_count' => $videoData['play_count'] ?? 0,
                    'play_count_formatted' => formatNumber($videoData['play_count'] ?? 0),
                    'like_count' => $videoData['digg_count'] ?? 0,
                    'like_count_formatted' => formatNumber($videoData['digg_count'] ?? 0),
                    'comment_count' => $videoData['comment_count'] ?? 0,
                    'comment_count_formatted' => formatNumber($videoData['comment_count'] ?? 0),
                    'share_count' => $videoData['share_count'] ?? 0,
                    'share_count_formatted' => formatNumber($videoData['share_count'] ?? 0),
                    'download_count' => $videoData['download_count'] ?? 0,
                    'collect_count' => $videoData['collect_count'] ?? 0
                ],
                'media' => [
                    'cover' => $videoData['cover'] ?? null,
                    'origin_cover' => $videoData['origin_cover'] ?? null,
                    'dynamic_cover' => $videoData['ai_dynamic_cover'] ?? null,
                    'video_no_watermark' => $videoData['play'] ?? null,
                    'video_watermark' => $videoData['wmplay'] ?? null,
                    'music' => $videoData['music'] ?? null,
                    'size' => $videoData['size'] ?? 0,
                    'size_mb' => isset($videoData['size']) ? round($videoData['size'] / 1048576, 2) : 0
                ],
                'music_info' => isset($videoData['music_info']) ? [
                    'id' => $videoData['music_info']['id'] ?? null,
                    'title' => $videoData['music_info']['title'] ?? null,
                    'author' => $videoData['music_info']['author'] ?? null,
                    'original' => $videoData['music_info']['original'] ?? false,
                    'duration' => $videoData['music_info']['duration'] ?? 0,
                    'cover' => $videoData['music_info']['cover'] ?? null,
                    'play_url' => $videoData['music_info']['play'] ?? null
                ] : null,
                'create_time' => $videoData['create_time'] ?? null,
                'create_time_formatted' => isset($videoData['create_time']) ? date('Y-m-d H:i:s', $videoData['create_time']) : null,
                'is_ad' => $videoData['is_ad'] ?? false
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Main execution
try {
    $url = isset($_GET['url']) ? trim($_GET['url']) : '';
    
    if (empty($url)) {
        echo json_encode([
            'success' => false,
            'error' => 'URL or Video ID parameter is required',
            'usage' => [
                'Full URL' => 'GET /tiktok-download.php?url=https://www.tiktok.com/@dwinurmijayanto/video/7347080009141587201',
                'Video ID' => 'GET /tiktok-download.php?url=7347080009141587201',
                'Short URL' => 'GET /tiktok-download.php?url=https://vm.tiktok.com/ZSFsqLc9X/'
            ],
            'features' => [
                'Download video without watermark',
                'Get video with watermark',
                'Extract audio/music',
                'Get video statistics',
                'Author information',
                'Formatted numbers (1.7K, 2.5M)',
                'Video thumbnails'
            ]
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    $result = tiktokDownload($url);
    
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>