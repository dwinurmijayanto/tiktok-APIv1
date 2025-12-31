<?php
/**
 * TikTok Downloader API
 * Multi-source with fallback mechanism
 * Author: Claude
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

class TikTokDownloader {
    
    private $apis = [
        [
            'name' => 'Deline API',
            'url' => 'https://api.deline.web.id/downloader/tiktok',
            'method' => 'POST',
            'map' => [
                'title' => 'result.title',
                'download' => 'result.download',
                'music' => 'result.music'
            ]
        ],
        [
            'name' => 'FikXz API',
            'url' => 'https://api.fikmydomainsz.xyz/download/tiktok',
            'method' => 'GET',
            'map' => [
                'title' => 'result.title',
                'download' => 'result.video_sd',
                'music' => 'result.mp3'
            ]
        ],
        [
            'name' => 'Magma API',
            'url' => 'https://www.magma-api.biz.id/download/tiktok',
            'method' => 'GET',
            'map' => [
                'title' => 'result.title',
                'download' => 'result.video_nowm',
                'music' => 'result.audio_url'
            ]
        ]
    ];

    private function getValue($data, $path) {
        $keys = explode('.', $path);
        $value = $data;
        
        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } else {
                return null;
            }
        }
        
        return $value;
    }

    private function fetchAPI($api, $url) {
        $ch = curl_init();
        
        if ($api['method'] === 'POST') {
            curl_setopt($ch, CURLOPT_URL, $api['url']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['url' => $url]));
        } else {
            curl_setopt($ch, CURLOPT_URL, $api['url'] . '?url=' . urlencode($url));
        }
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode !== 200 || empty($response)) {
            return null;
        }
        
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['status']) || $data['status'] !== true) {
            return null;
        }
        
        return $data;
    }

    public function download($url) {
        // Validasi URL
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'message' => 'URL TikTok tidak valid'
            ];
        }

        // Cek apakah URL TikTok
        if (!preg_match('/tiktok\.com/i', $url)) {
            return [
                'success' => false,
                'message' => 'URL harus dari TikTok'
            ];
        }

        // Coba setiap API dengan fallback
        $errors = [];
        
        foreach ($this->apis as $api) {
            $data = $this->fetchAPI($api, $url);
            
            if ($data) {
                // Extract data sesuai mapping
                $result = [
                    'title' => $this->getValue($data, $api['map']['title']) ?? '',
                    'download' => $this->getValue($data, $api['map']['download']) ?? '',
                    'music' => $this->getValue($data, $api['map']['music']) ?? ''
                ];
                
                // Validasi hasil
                if (!empty($result['title']) && !empty($result['download'])) {
                    return [
                        'success' => true,
                        'source' => $api['name'],
                        'data' => $result
                    ];
                }
            }
            
            $errors[] = $api['name'] . ' gagal';
        }

        return [
            'success' => false,
            'message' => 'Semua API gagal. ' . implode(', ', $errors),
            'errors' => $errors
        ];
    }
}

// Handle Request
if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $url = $_GET['url'] ?? $_POST['url'] ?? '';
    
    if (empty($url)) {
        echo json_encode([
            'success' => false,
            'message' => 'Parameter url diperlukan',
            'usage' => [
                'endpoint' => $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'],
                'method' => 'GET atau POST',
                'parameter' => 'url',
                'example' => $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . '?url=https://www.tiktok.com/@username/video/1234567890'
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    $downloader = new TikTokDownloader();
    $result = $downloader->download($url);
    
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Method tidak didukung. Gunakan GET atau POST'
    ], JSON_PRETTY_PRINT);
}
?>