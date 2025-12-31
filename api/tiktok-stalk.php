<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

/**
 * TikTok Profile Stalker API
 * Scraping langsung dari TikTok.com
 * 
 * Usage: 
 * GET /tiktok-stalk.php?username=dwinurmijayanto
 */

function tiktokStalk($username) {
    try {
        if (empty($username)) {
            throw new Exception('Username is required');
        }
        
        // Remove @ if present
        $username = ltrim($username, '@');
        
        // Validate username format (alphanumeric, dots, underscores)
        if (!preg_match('/^[a-zA-Z0-9._]+$/', $username)) {
            throw new Exception('Invalid TikTok username format');
        }
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://www.tiktok.com/@' . $username,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING => 'gzip, deflate, br',
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Accept-Encoding: gzip, deflate, br',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: none',
                'Sec-Fetch-User: ?1',
                'Cache-Control: max-age=0'
            ]
        ]);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception('Connection error: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new Exception('TikTok returned status code: ' . $httpCode);
        }
        
        if (empty($html)) {
            throw new Exception('Empty response from TikTok');
        }
        
        // Try to find SIGI_STATE (TikTok's data structure)
        if (preg_match('/<script id="__UNIVERSAL_DATA_FOR_REHYDRATION__" type="application\/json">(.*?)<\/script>/s', $html, $matches)) {
            $jsonData = json_decode($matches[1], true);
            
            if (isset($jsonData['__DEFAULT_SCOPE__']['webapp.user-detail']['userInfo']['user'])) {
                $user = $jsonData['__DEFAULT_SCOPE__']['webapp.user-detail']['userInfo']['user'];
                $stats = $jsonData['__DEFAULT_SCOPE__']['webapp.user-detail']['userInfo']['stats'];
                
                return [
                    'success' => true,
                    'data' => [
                        'username' => $user['uniqueId'] ?? $username,
                        'name' => $user['nickname'] ?? null,
                        'bio' => $user['signature'] ?? null,
                        'followers' => $stats['followerCount'] ?? 0,
                        'following' => $stats['followingCount'] ?? 0,
                        'likes' => $stats['heartCount'] ?? 0,
                        'video_count' => $stats['videoCount'] ?? 0,
                        'avatar' => $user['avatarLarger'] ?? $user['avatarMedium'] ?? null,
                        'verified' => $user['verified'] ?? false,
                        'private' => $user['privateAccount'] ?? false,
                        'profile_url' => 'https://www.tiktok.com/@' . $username
                    ]
                ];
            }
        }
        
        // Fallback: Try regex pattern matching
        function pick($html, $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                return $matches[1];
            }
            return null;
        }
        
        // Try multiple patterns for better compatibility
        $uniqueId = pick($html, '/"uniqueId":"([^"]+)"/') ?? 
                    pick($html, '/uniqueId&quot;:&quot;([^&]+)&quot;/');
        
        $nickname = pick($html, '/"nickname":"([^"]+)"/') ?? 
                    pick($html, '/nickname&quot;:&quot;([^&]+)&quot;/');
        
        $signature = pick($html, '/"signature":"([^"]*)"/') ?? 
                     pick($html, '/signature&quot;:&quot;([^&]*)&quot;/');
        
        $followerCount = pick($html, '/"followerCount":(\d+)/') ?? 
                        pick($html, '/followerCount&quot;:(\d+)/');
        
        $followingCount = pick($html, '/"followingCount":(\d+)/') ?? 
                         pick($html, '/followingCount&quot;:(\d+)/');
        
        $heartCount = pick($html, '/"heartCount":(\d+)/') ?? 
                     pick($html, '/heartCount&quot;:(\d+)/');
        
        $videoCount = pick($html, '/"videoCount":(\d+)/') ?? 
                     pick($html, '/videoCount&quot;:(\d+)/');
        
        $avatarLarger = pick($html, '/"avatarLarger":"([^"]+)"/') ?? 
                       pick($html, '/avatarLarger&quot;:&quot;([^&]+)&quot;/');
        
        // Decode unicode and HTML entities in avatar URL
        if ($avatarLarger) {
            $avatarLarger = str_replace(['\\u002F', '\\/', '&amp;'], ['/', '/', '&'], $avatarLarger);
            $avatarLarger = html_entity_decode($avatarLarger, ENT_QUOTES, 'UTF-8');
        }
        
        // Decode HTML entities in text fields
        if ($nickname) {
            $nickname = html_entity_decode($nickname, ENT_QUOTES, 'UTF-8');
        }
        if ($signature) {
            $signature = html_entity_decode($signature, ENT_QUOTES, 'UTF-8');
        }
        
        // Check if user exists
        if (!$uniqueId && !$nickname) {
            throw new Exception('User not found or profile is private');
        }
        
        return [
            'success' => true,
            'data' => [
                'username' => $uniqueId ?? $username,
                'name' => $nickname ?? null,
                'bio' => $signature ?? null,
                'followers' => $followerCount ? (int)$followerCount : 0,
                'following' => $followingCount ? (int)$followingCount : 0,
                'likes' => $heartCount ? (int)$heartCount : 0,
                'video_count' => $videoCount ? (int)$videoCount : 0,
                'avatar' => $avatarLarger ?? null,
                'profile_url' => 'https://www.tiktok.com/@' . $username
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
    // Get username from query parameter
    $username = isset($_GET['username']) ? trim($_GET['username']) : '';
    
    if (empty($username)) {
        echo json_encode([
            'success' => false,
            'error' => 'Username parameter is required',
            'usage' => 'GET /tiktok-stalk.php?username=dwinurmijayanto',
            'note' => 'Username can be with or without @ symbol'
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    $result = tiktokStalk($username);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>