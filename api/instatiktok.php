<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

/**
 * TikTok & Facebook Downloader API (InstaTikTok)
 * Menggunakan instatiktok.com service
 * 
 * Usage: 
 * GET /instatiktok.php?url=https://vt.tiktok.com/ZSSm2fhLX/
 * GET /instatiktok.php?url=https://www.facebook.com/watch/?v=123456789
 * GET /instatiktok.php?url=https://fb.watch/abc123/
 */

function detectPlatform($url) {
    if (preg_match('/tiktok\.com/i', $url)) {
        return 'tiktok';
    } elseif (preg_match('/(facebook\.com|fb\.watch|fb\.me)/i', $url)) {
        return 'facebook';
    }
    return null;
}

function validateUrl($url, $platform) {
    if ($platform === 'tiktok') {
        $patterns = [
            '/(?:https?:\/\/)?(?:w{3}|vm|vt|t)?\.?tiktok\.com\/([^\s&]+)/i',
        ];
    } elseif ($platform === 'facebook') {
        $patterns = [
            '/(?:https?:\/\/)?(?:www\.|m\.|web\.)?facebook\.com\/([^\s&]+)/i',
            '/(?:https?:\/\/)?fb\.watch\/([^\s&]+)/i',
            '/(?:https?:\/\/)?fb\.me\/([^\s&]+)/i',
        ];
    } else {
        return false;
    }
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url)) {
            return true;
        }
    }
    
    return false;
}

function fetchDownloadLinks($url, $platform) {
    try {
        if (!validateUrl($url, $platform)) {
            throw new Exception('Invalid ' . ucfirst($platform) . ' URL format');
        }
        
        $siteUrl = 'https://instatiktok.com/';
        
        // Prepare POST data
        $postData = http_build_query([
            'url' => $url,
            'platform' => $platform,
            'siteurl' => $siteUrl
        ]);
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $siteUrl . 'api',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'Origin: ' . $siteUrl,
                'Referer: ' . $siteUrl,
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'X-Requested-With: XMLHttpRequest',
                'Accept: */*'
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
        
        if (!isset($data['status']) || $data['status'] !== 'success') {
            throw new Exception($data['message'] ?? 'Failed to fetch media data');
        }
        
        if (!isset($data['html'])) {
            throw new Exception('No HTML data found');
        }
        
        // Parse HTML to extract download links
        $html = $data['html'];
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        
        // Find all <a> tags with class 'btn' and href starting with http
        $links = [];
        $nodes = $xpath->query('//a[contains(@class, "btn") and starts-with(@href, "http")]');
        
        foreach ($nodes as $node) {
            $link = $node->getAttribute('href');
            $text = trim($node->textContent);
            
            if ($link && !in_array($link, array_column($links, 'url'))) {
                $links[] = [
                    'url' => $link,
                    'label' => $text
                ];
            }
        }
        
        if (empty($links)) {
            throw new Exception('No download links found');
        }
        
        return $links;
        
    } catch (Exception $e) {
        throw $e;
    }
}

function getDownloadLink($platform, $links) {
    if ($platform === 'tiktok') {
        // Prioritize HD quality
        foreach ($links as $link) {
            if (stripos($link['url'], 'hdplay') !== false || 
                stripos($link['label'], 'HD') !== false ||
                stripos($link['label'], 'High') !== false) {
                return [
                    'quality' => 'hd',
                    'url' => $link['url'],
                    'label' => $link['label']
                ];
            }
        }
        // Return first link as fallback
        return [
            'quality' => 'standard',
            'url' => $links[0]['url'],
            'label' => $links[0]['label']
        ];
    } elseif ($platform === 'facebook') {
        // Prioritize HD/High quality
        foreach ($links as $link) {
            $label = strtolower($link['label']);
            if (stripos($label, 'hd') !== false || 
                stripos($label, 'high') !== false ||
                stripos($label, '1080') !== false ||
                stripos($label, '720') !== false) {
                return [
                    'quality' => 'hd',
                    'url' => $link['url'],
                    'label' => $link['label']
                ];
            }
        }
        
        // If no HD, look for standard quality
        foreach ($links as $link) {
            $label = strtolower($link['label']);
            if (stripos($label, 'sd') !== false || 
                stripos($label, 'standard') !== false ||
                stripos($label, '480') !== false ||
                stripos($label, '360') !== false) {
                return [
                    'quality' => 'sd',
                    'url' => $link['url'],
                    'label' => $link['label']
                ];
            }
        }
        
        // Fallback to first link
        return [
            'quality' => 'unknown',
            'url' => $links[0]['url'],
            'label' => $links[0]['label']
        ];
    }
    
    return null;
}

function downloadMedia($url) {
    try {
        if (empty($url)) {
            throw new Exception('URL parameter is required');
        }
        
        // Detect platform
        $platform = detectPlatform($url);
        
        if (!$platform) {
            throw new Exception('URL must be from TikTok or Facebook');
        }
        
        // Fetch download links
        $links = fetchDownloadLinks($url, $platform);
        
        if (empty($links)) {
            throw new Exception('No download links available');
        }
        
        // Get best download link
        $downloadInfo = getDownloadLink($platform, $links);
        
        $response = [
            'success' => true,
            'data' => [
                'platform' => $platform,
                'url' => $url,
                'download_url' => $downloadInfo['url'],
                'quality' => $downloadInfo['quality'],
                'label' => $downloadInfo['label'] ?? null,
                'total_links' => count($links)
            ]
        ];
        
        // Add all links for reference
        $allLinks = [];
        foreach ($links as $link) {
            $allLinks[] = [
                'url' => $link['url'],
                'label' => $link['label']
            ];
        }
        $response['data']['all_links'] = $allLinks;
        
        return $response;
        
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
            'error' => 'URL parameter is required',
            'usage' => [
                'TikTok Short' => 'GET /instatiktok.php?url=https://vt.tiktok.com/ZSSm2fhLX/',
                'TikTok Full' => 'GET /instatiktok.php?url=https://www.tiktok.com/@user/video/123',
                'Facebook Video' => 'GET /instatiktok.php?url=https://www.facebook.com/watch/?v=123456789',
                'Facebook Watch' => 'GET /instatiktok.php?url=https://www.facebook.com/user/videos/123456789',
                'Facebook Short' => 'GET /instatiktok.php?url=https://fb.watch/abc123/'
            ],
            'features' => [
                'TikTok' => [
                    'Download videos without watermark',
                    'HD quality support (if available)',
                    'All TikTok URL formats supported'
                ],
                'Facebook' => [
                    'Download public videos',
                    'HD/SD quality options',
                    'Support fb.watch short links',
                    'Support all Facebook video formats'
                ]
            ],
            'supported_platforms' => ['TikTok', 'Facebook']
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    $result = downloadMedia($url);
    
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>