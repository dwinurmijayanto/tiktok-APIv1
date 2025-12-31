<?php
/**
 * SaveTik API - Realtime (No Cache)
 * Endpoint: savetik.php?url=TIKTOK_URL
 * 
 * Download TikTok video via SaveTik (Encrypted API)
 * Always fetches fresh data from API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

class SaveTikAPI {
    
    private $encKey = "GJvE5RZIxrl9SuNrAtgsvCfWha3M7NGC";
    private $decKey = "H3quWdWoHLX5bZSlyCYAnvDFara25FIu";
    
    private function cryptoProc($type, $data) {
        try {
            $key = ($type === 'enc') ? $this->encKey : $this->decKey;
            $iv = substr($key, 0, 16);
            
            if ($type === 'enc') {
                $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
                if ($encrypted === false) {
                    throw new Exception("Encryption failed");
                }
                return $encrypted;
            } else {
                $decrypted = openssl_decrypt($data, 'aes-256-cbc', $key, 0, $iv);
                if ($decrypted === false) {
                    throw new Exception("Decryption failed");
                }
                return $decrypted;
            }
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Expand short URL to full URL
     */
    private function expandShortUrl($shortUrl) {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $shortUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_NOBODY => true,
                CURLOPT_HEADER => true,
                CURLOPT_HTTPHEADER => [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
                ]
            ]);
            
            curl_exec($ch);
            $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Ekstrak video ID dari URL
            if (preg_match('/\/video\/(\d+)/', $finalUrl, $match)) {
                return [
                    'full_url' => $finalUrl,
                    'video_id' => $match[1],
                    'http_code' => $httpCode
                ];
            }
            
            return [
                'full_url' => $finalUrl, 
                'video_id' => null,
                'http_code' => $httpCode
            ];
            
        } catch (Exception $e) {
            return [
                'full_url' => $shortUrl, 
                'video_id' => null,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Normalize URL
     */
    private function normalizeUrl($url) {
        $url = trim($url);
        
        // Jika short URL, expand
        if (preg_match('/^https?:\/\/(vm|vt)\.tiktok\.com/i', $url)) {
            $expanded = $this->expandShortUrl($url);
            
            return [
                'original_url' => $url,
                'full_url' => $expanded['full_url'],
                'video_id' => $expanded['video_id'],
                'is_short_url' => true
            ];
        }
        
        // Ekstrak video ID dari URL penuh
        if (preg_match('/\/video\/(\d+)/', $url, $match)) {
            return [
                'original_url' => $url,
                'full_url' => $url,
                'video_id' => $match[1],
                'is_short_url' => false
            ];
        }
        
        return [
            'original_url' => $url,
            'full_url' => $url,
            'video_id' => null,
            'is_short_url' => false
        ];
    }
    
    /**
     * Download video - REALTIME, NO CACHE
     */
    public function download($url) {
        try {
            // Normalize URL
            $urlInfo = $this->normalizeUrl($url);
            
            if (empty($urlInfo['full_url'])) {
                throw new Exception("Invalid URL provided");
            }
            
            $apiUrl = "https://savetik.app/requests";
            $requestUrl = $urlInfo['full_url'];
            
            // Encrypt URL
            $encryptedUrl = $this->cryptoProc('enc', $requestUrl);
            
            if (!$encryptedUrl) {
                throw new Exception("URL encryption failed");
            }
            
            $postData = json_encode(['bdata' => $encryptedUrl]);
            
            // Request ke SaveTik API dengan retry
            $maxRetries = 3;
            $response = false;
            $lastError = '';
            
            for ($i = 0; $i < $maxRetries; $i++) {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $apiUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $postData,
                    CURLOPT_TIMEOUT => 20,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_HTTPHEADER => [
                        'User-Agent: Mozilla/5.0 (Android 15; Mobile) Gecko/130.0 Firefox/130.0',
                        'Content-Type: application/json',
                        'Accept: application/json',
                        'Accept-Language: en-US,en;q=0.9',
                        'Origin: https://savetik.app',
                        'Referer: https://savetik.app/'
                    ]
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);
                
                if ($httpCode == 200 && $response) {
                    break;
                }
                
                $lastError = "HTTP $httpCode" . ($curlError ? ": $curlError" : "");
                
                if ($i < $maxRetries - 1) {
                    usleep(500000); // 0.5s delay
                }
            }
            
            if (!$response) {
                throw new Exception("SaveTik API failed after $maxRetries attempts. Last: $lastError");
            }
            
            // Parse response
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON: " . json_last_error_msg());
            }
            
            if (!isset($data['status']) || $data['status'] !== 'success') {
                throw new Exception("SaveTik error: " . ($data['message'] ?? 'Unknown'));
            }
            
            if (!isset($data['data'])) {
                throw new Exception("No video data in response");
            }
            
            // Decrypt video URL
            $videoUrl = $this->cryptoProc('dec', $data['data']);
            
            if (!$videoUrl || !filter_var($videoUrl, FILTER_VALIDATE_URL)) {
                throw new Exception("Invalid video URL after decryption");
            }
            
            // Build result
            $result = [
                'type' => 'video',
                'download' => $videoUrl,
                'thumbnail' => $data['thumbnailUrl'] ?? null,
                'audio' => $data['mp3'] ?? null,
                'title' => $data['title'] ?? null,
                'author' => $data['username'] ?? null,
                'duration' => $data['duration'] ?? null
            ];
            
            // Filter null values
            $result = array_filter($result, fn($v) => $v !== null);
            
            return [
                'url_info' => $urlInfo,
                'result' => $result
            ];
            
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}

// ===== API Handler =====
$url = $_GET['url'] ?? $_GET['v'] ?? $_GET['id'] ?? $_POST['url'] ?? '';

if (empty($url)) {
    echo json_encode([
        'success' => false,
        'message' => 'URL parameter required',
        'endpoint' => 'savetik.php',
        'usage' => 'savetik.php?url=TIKTOK_URL',
        'description' => 'Download TikTok video via SaveTik (Encrypted API)',
        'mode' => 'REALTIME - No caching',
        'features' => [
            'Always fetch fresh data',
            'URL expansion for short links',
            'Video ID extraction',
            'Retry mechanism',
            'Detailed debugging info'
        ],
        'response_fields' => [
            'type' => 'Content type (video)',
            'download' => 'Direct video download URL',
            'thumbnail' => 'Video thumbnail URL',
            'audio' => 'Audio/MP3 URL',
            'title' => 'Video title',
            'author' => 'Author username',
            'original_url' => 'Original input URL',
            'full_url' => 'Expanded full URL',
            'video_id' => 'TikTok video ID',
            'is_short_url' => 'Whether input was short URL'
        ],
        'examples' => [
            'savetik.php?url=https://www.tiktok.com/@user/video/123',
            'savetik.php?url=https://vt.tiktok.com/xxxxx',
            'savetik.php?url=https://vm.tiktok.com/xxxxx'
        ],
        'note' => 'This API uses AES-256-CBC encryption. Always returns fresh data from SaveTik.'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $api = new SaveTikAPI();
    $download = $api->download($url);
    
    echo json_encode([
        'success' => true,
        'method' => 'SaveTik',
        'mode' => 'realtime',
        'timestamp' => time(),
        'original_url' => $download['url_info']['original_url'],
        'full_url' => $download['url_info']['full_url'],
        'video_id' => $download['url_info']['video_id'],
        'is_short_url' => $download['url_info']['is_short_url'],
        'data' => $download['result']
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => time()
    ], JSON_PRETTY_PRINT);
}
?>