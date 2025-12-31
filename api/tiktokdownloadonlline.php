<?php
/**
 * TikTok Downloader API - tiktokdownload.online
 * Fokus parsing HTML response dari tiktokdownload.online
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

class TikTokDownloader {
    private $baseUrl = 'https://tiktokdownload.online';
    private $endpoint = '/abc?url=dl';
    private $locale = 'id';
    private $token = 'djJFWkoz'; // s_tt dari JavaScript
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    
    public function download($url) {
        try {
            if (!$this->isValidTikTokUrl($url)) {
                return $this->error('URL TikTok tidak valid');
            }
            
            $url = $this->normalizeUrl($url);
            $response = $this->makeRequest($url);
            
            if (!$response['success']) {
                return $this->error($response['message']);
            }
            
            return $this->success($response['data']);
            
        } catch (Exception $e) {
            return $this->error('Terjadi kesalahan: ' . $e->getMessage());
        }
    }
    
    private function isValidTikTokUrl($url) {
        $patterns = [
            '/tiktok\.com\/@[\w.-]+\/video\/(\d+)/',
            '/vm\.tiktok\.com\/[\w\-]+/',
            '/vt\.tiktok\.com\/[\w\-]+/',
            '/m\.tiktok\.com\/v\/(\d+)/',
            '/tiktok\.com\/t\/[\w\-]+/',
            '/www\.tiktok\.com\/@[\w.-]+\/video\/(\d+)/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }
        return false;
    }
    
    private function normalizeUrl($url) {
        if (preg_match('/(vm\.tiktok\.com|vt\.tiktok\.com)/', $url)) {
            $url = $this->resolveShortUrl($url);
        }
        return $url;
    }
    
    private function resolveShortUrl($shortUrl) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $shortUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HEADER => false
        ]);
        curl_exec($ch);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        return $finalUrl ?: $shortUrl;
    }
    
    private function makeRequest($url) {
        $ch = curl_init();
        
        // POST data sesuai form tiktokdownload.online
        $postData = http_build_query([
            'id' => $url,
            'locale' => $this->locale,
            'tt' => $this->token
        ]);
        
        $headers = [
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'Accept: */*',
            'X-Requested-With: XMLHttpRequest',
            'Referer: ' . $this->baseUrl . '/' . $this->locale,
            'Origin: ' . $this->baseUrl,
            'Accept-Language: id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7'
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . $this->endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_ENCODING => 'gzip, deflate, br'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'message' => 'CURL Error: ' . $error];
        }
        
        if ($httpCode !== 200) {
            return ['success' => false, 'message' => 'HTTP Error: ' . $httpCode];
        }
        
        if (empty($response)) {
            return ['success' => false, 'message' => 'Response kosong'];
        }
        
        return $this->parseResponse($response);
    }
    
    private function parseResponse($html) {
        if (empty($html)) {
            return ['success' => false, 'message' => 'HTML kosong'];
        }
        
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Struktur field baru: judul, thumbnail, download, durasi, audio
        $data = [
            'judul' => '',
            'thumbnail' => '',
            'download' => '',
            'durasi' => '',
            'audio' => ''
        ];
        
        // === PARSING BERDASARKAN STRUKTUR tiktokdownload.online ===
        
        // 1. Thumbnail - biasanya dalam div.download-box atau img dengan class thumb
        $thumbs = $xpath->query('//img[@class="thumb" or contains(@class, "preview") or @id="slide-img"]');
        if ($thumbs->length > 0) {
            $src = $thumbs->item(0)->getAttribute('src');
            if (!empty($src) && $src !== 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7') {
                $data['thumbnail'] = $src;
            }
        }
        
        // 2. Title/Description - h2.title atau div dengan class description
        $titles = $xpath->query('//h2[@class="title"] | //p[contains(@class, "description")] | //div[contains(@class, "video-title")]');
        if ($titles->length > 0) {
            $data['judul'] = trim($titles->item(0)->textContent);
        }
        
        // Fallback untuk judul jika kosong
        if (empty($data['judul'])) {
            $data['judul'] = 'TikTok Video';
        }
        
        // 3. Durasi - cari dalam berbagai format
        $data['durasi'] = $this->extractDuration($xpath, $html);
        
        // 4. DOWNLOAD LINKS - ini yang paling penting!
        $downloadUrl = $this->extractDownloadUrl($xpath, $html);
        $data['download'] = $downloadUrl;
        
        // 5. Audio/Music links
        $audioUrl = $this->extractAudioUrl($xpath, $html);
        $data['audio'] = $audioUrl;
        
        // Validasi: minimal harus ada download link
        if (empty($data['download'])) {
            // Save untuk debugging
            $debugFile = 'debug_response_' . time() . '.html';
            file_put_contents($debugFile, $html);
            
            return [
                'success' => false,
                'message' => 'Tidak dapat menemukan link download',
                'debug' => [
                    'file' => $debugFile,
                    'html_length' => strlen($html),
                    'has_tikcdn' => (stripos($html, 'tikcdn') !== false),
                    'has_download_class' => (stripos($html, 'class="download') !== false)
                ]
            ];
        }
        
        return ['success' => true, 'data' => $data];
    }
    
    private function extractDuration($xpath, $html) {
        // Cari durasi dalam berbagai format dari HTML
        $durationElements = $xpath->query('//*[contains(@class, "duration") or contains(@class, "time")]');
        
        foreach ($durationElements as $elem) {
            $text = trim($elem->textContent);
            // Format MM:SS atau HH:MM:SS
            if (preg_match('/(\d{1,2}):(\d{2})/', $text, $matches)) {
                return $text;
            }
        }
        
        // Cari dalam JavaScript
        if (preg_match('/duration["\s:]+([0-9]+)/i', $html, $matches)) {
            $seconds = intval($matches[1]);
            $minutes = floor($seconds / 60);
            $secs = $seconds % 60;
            return sprintf('%02d:%02d', $minutes, $secs);
        }
        
        // Pattern lain untuk durasi
        if (preg_match('/([0-9]+:[0-9]{2})/i', $html, $matches)) {
            return $matches[1];
        }
        
        return '';
    }
    
    private function extractDownloadUrl($xpath, $html) {
        // Strategi A: Cari semua link/button download
        $downloadElements = $xpath->query('
            //a[contains(@class, "download") or contains(@class, "btn") or contains(@class, "abutton")] |
            //a[contains(text(), "Download") or contains(text(), "Unduh")] |
            //button[contains(@class, "download")]
        ');
        
        foreach ($downloadElements as $elem) {
            $href = $elem->getAttribute('href');
            $onclick = $elem->getAttribute('onclick');
            $dataUrl = $elem->getAttribute('data-url');
            $text = strtolower(trim($elem->textContent));
            
            // Prioritas: data-url > href > onclick
            $url = $dataUrl ?: $href;
            
            // Extract dari onclick jika ada
            if (empty($url) && !empty($onclick)) {
                if (preg_match('/(https?:\/\/[^\'"]+)/', $onclick, $matches)) {
                    $url = $matches[1];
                }
            }
            
            // Prioritas untuk link tanpa watermark
            if ($this->isValidVideoUrl($url)) {
                if (strpos($text, 'watermark') === false || 
                    strpos($text, 'tanpa') !== false || 
                    strpos($text, 'no water') !== false ||
                    strpos($text, 'nowm') !== false ||
                    strpos($url, 'tikcdn.io') !== false) {
                    return $url;
                }
            }
        }
        
        // Strategi B: Parse JavaScript variables
        // tiktokdownload.online sering embed URL dalam JavaScript
        $scripts = $xpath->query('//script[not(@src)]');
        foreach ($scripts as $script) {
            $content = $script->textContent;
            
            // Pattern untuk tikcdn.io (yang paling umum digunakan tiktokdownload.online)
            if (preg_match('/(https?:\/\/tikcdn\.io\/[^\s"\'<>]+)/', $content, $matches)) {
                $tikUrl = html_entity_decode($matches[1]);
                $tikUrl = str_replace('\\', '', $tikUrl);
                return $tikUrl;
            }
            
            // Pattern untuk MP4 links umum
            if (preg_match('/(https?:\/\/[^\s"\'<>]+\.mp4[^\s"\'<>]*)/', $content, $matches)) {
                $mp4Url = html_entity_decode($matches[1]);
                $mp4Url = str_replace('\\', '', $mp4Url);
                
                if ($this->isValidVideoUrl($mp4Url)) {
                    return $mp4Url;
                }
            }
            
            // Pattern untuk URL dalam JSON object
            if (preg_match('/["\']?(?:url|downloadUrl|videoUrl|play_addr)["\']?\s*:\s*["\'](https?:\/\/[^"\']+)["\']/', $content, $matches)) {
                $jsonUrl = html_entity_decode($matches[1]);
                $jsonUrl = str_replace('\\', '', $jsonUrl);
                
                if ($this->isValidVideoUrl($jsonUrl)) {
                    return $jsonUrl;
                }
            }
        }
        
        // Strategi C: Cari MP4 links langsung di HTML
        if (preg_match('/(https?:\/\/[^\s"\'<>]+\.mp4[^\s"\'<>]*)/', $html, $matches)) {
            $mp4Url = html_entity_decode($matches[1]);
            $mp4Url = str_replace('\\', '', $mp4Url);
            
            if ($this->isValidVideoUrl($mp4Url)) {
                return $mp4Url;
            }
        }
        
        return '';
    }
    
    private function extractAudioUrl($xpath, $html) {
        // Strategi A: Cari audio elements
        $audioElements = $xpath->query('
            //a[contains(@class, "music") or contains(@class, "audio")] |
            //a[contains(text(), "MP3") or contains(text(), "Music") or contains(text(), "Audio")]
        ');
        
        foreach ($audioElements as $elem) {
            $href = $elem->getAttribute('href');
            $dataUrl = $elem->getAttribute('data-url');
            $url = $dataUrl ?: $href;
            
            if (!empty($url) && (strpos($url, '.mp3') !== false || strpos($url, '.m4a') !== false)) {
                return $url;
            }
        }
        
        // Strategi B: Cari dalam JavaScript
        if (preg_match('/(https?:\/\/[^\s"\'<>]+\.mp3[^\s"\'<>]*)/', $html, $matches)) {
            return html_entity_decode($matches[1]);
        }
        
        if (preg_match('/(https?:\/\/[^\s"\'<>]+\.m4a[^\s"\'<>]*)/', $html, $matches)) {
            return html_entity_decode($matches[1]);
        }
        
        // Pattern untuk audio URL dalam JSON
        if (preg_match('/["\']?(?:audioUrl|musicUrl|audio)["\']?\s*:\s*["\'](https?:\/\/[^"\']+)["\']/', $html, $matches)) {
            $audioUrl = html_entity_decode($matches[1]);
            $audioUrl = str_replace('\\', '', $audioUrl);
            return $audioUrl;
        }
        
        return '';
    }
    
    private function isValidVideoUrl($url) {
        if (empty($url)) return false;
        
        // Harus URL valid atau protocol-relative
        if (!filter_var($url, FILTER_VALIDATE_URL) && strpos($url, '//') !== 0) {
            return false;
        }
        
        // Exclude non-video URLs
        $exclude = ['javascript:', 'mailto:', '#', 'google-analytics', 'facebook.com', 'twitter.com'];
        foreach ($exclude as $pattern) {
            if (stripos($url, $pattern) !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    private function success($data) {
        return [
            'status' => 'success',
            'message' => 'Video berhasil diproses',
            'data' => $data
        ];
    }
    
    private function error($message) {
        return [
            'status' => 'error',
            'message' => $message,
            'data' => null
        ];
    }
}

// ==========================================
// MAIN EXECUTION
// ==========================================

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($method !== 'GET' && $method !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed',
        'data' => null
    ]);
    exit;
}

// Get URL parameter
$url = '';
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $url = $input['url'] ?? ($_POST['url'] ?? '');
} else {
    $url = $_GET['url'] ?? '';
}

if (empty($url)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Parameter URL diperlukan',
        'data' => null
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Process
$downloader = new TikTokDownloader();
$result = $downloader->download($url);

http_response_code($result['status'] === 'success' ? 200 : 400);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);