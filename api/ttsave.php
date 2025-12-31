<?php
/**
 * TikTok Downloader API
 * Endpoint untuk download video TikTok tanpa watermark
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

class TikTokDownloader {
    private $baseUrl = 'https://ttsave.app';
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
    
    public function download($url) {
        try {
            // Validasi URL
            if (empty($url)) {
                throw new Exception('URL tidak boleh kosong');
            }
            
            // Bersihkan URL
            $url = trim($url);
            
            // Kirim request ke TTSave API
            $response = $this->sendRequest($url);
            
            if ($response['success']) {
                return [
                    'status' => 'success',
                    'message' => 'Video berhasil diproses',
                    'data' => $response['data']
                ];
            } else {
                throw new Exception($response['message'] ?? 'Gagal memproses video');
            }
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }
    
    private function sendRequest($videoUrl) {
        $ch = curl_init();
        
        $postData = json_encode([
            'query' => $videoUrl,
            'language_id' => '2' // Indonesian
        ]);
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/download',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: ' . $this->userAgent,
                'Accept: */*',
                'Origin: ' . $this->baseUrl,
                'Referer: ' . $this->baseUrl . '/id'
            ],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('CURL Error: ' . $error);
        }
        
        if ($httpCode !== 200) {
            // Retry jika rate limit
            if ($httpCode === 429) {
                sleep(2);
                return $this->sendRequest($videoUrl);
            }
            throw new Exception('HTTP Error: ' . $httpCode);
        }
        
        // Parse HTML response
        return $this->parseResponse($response);
    }
    
    private function parseResponse($html) {
        // Extract video info dari HTML response dengan struktur field baru
        $data = [
            'judul' => $this->extractTitle($html) ?? '',
            'thumbnail' => $this->extractThumbnail($html) ?? '',
            'download' => $this->extractDownloadUrl($html) ?? '',
            'durasi' => $this->extractDuration($html) ?? '',
            'audio' => $this->extractAudioUrl($html) ?? ''
        ];
        
        if (empty($data['download'])) {
            return [
                'success' => false,
                'message' => 'Tidak dapat menemukan link download'
            ];
        }
        
        return [
            'success' => true,
            'data' => $data
        ];
    }
    
    private function extractTitle($html) {
        if (preg_match('/<h3[^>]*class="[^"]*font-bold[^"]*"[^>]*>(.*?)<\/h3>/s', $html, $matches)) {
            return strip_tags(trim($matches[1]));
        }
        if (preg_match('/<title>(.*?)<\/title>/i', $html, $matches)) {
            return strip_tags(trim($matches[1]));
        }
        return 'TikTok Video';
    }
    
    private function extractThumbnail($html) {
        // Cari thumbnail image
        if (preg_match('/<img[^>]+src="([^"]+)"[^>]*class="[^"]*rounded[^"]*"/i', $html, $matches)) {
            return $matches[1];
        }
        // Fallback pattern
        if (preg_match('/<img[^>]+src="(https?:\/\/[^"]+\.(jpg|jpeg|png|webp))"[^>]*>/i', $html, $matches)) {
            return $matches[1];
        }
        return '';
    }
    
    private function extractDownloadUrl($html) {
        // Prioritas: link tanpa watermark
        if (preg_match('/href="([^"]+)"[^>]*type="no-watermark"/i', $html, $matches)) {
            return html_entity_decode($matches[1]);
        }
        
        // Pattern alternatif untuk download button
        if (preg_match('/href="([^"]+)"[^>]*download[^>]*>.*?Download.*?HD/is', $html, $matches)) {
            return html_entity_decode($matches[1]);
        }
        
        // Fallback: cari link .mp4 pertama
        if (preg_match('/https?:\/\/[^\s"<>]+\.mp4[^\s"<>]*/i', $html, $matches)) {
            return html_entity_decode($matches[0]);
        }
        
        return '';
    }
    
    private function extractDuration($html) {
        // Cari durasi dalam berbagai format
        $patterns = [
            '/duration["\s:]+([0-9]+)/i',
            '/([0-9]+:[0-9]{2})\s*<\/span>/i',
            '/<span[^>]*>([0-9]+:[0-9]{2})<\/span>/i',
            '/data-duration="([^"]+)"/i',
            '/playtime["\s:]+([0-9]+)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $duration = $matches[1];
                
                // Convert ke format MM:SS jika dalam detik
                if (is_numeric($duration)) {
                    $minutes = floor($duration / 60);
                    $seconds = $duration % 60;
                    return sprintf('%02d:%02d', $minutes, $seconds);
                }
                
                return $duration;
            }
        }
        
        return '';
    }
    
    private function extractAudioUrl($html) {
        // Cari link audio/MP3
        if (preg_match('/href="([^"]+)"[^>]*type="audio"/i', $html, $matches)) {
            return html_entity_decode($matches[1]);
        }
        
        // Pattern alternatif untuk audio download
        if (preg_match('/href="([^"]+)"[^>]*>.*?Download.*?Audio/is', $html, $matches)) {
            return html_entity_decode($matches[1]);
        }
        
        // Cari link .mp3
        if (preg_match('/https?:\/\/[^\s"<>]+\.mp3[^\s"<>]*/i', $html, $matches)) {
            return html_entity_decode($matches[0]);
        }
        
        return null;
    }
}

// Main handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $url = $input['url'] ?? $_POST['url'] ?? '';
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $url = $_GET['url'] ?? '';
} else {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed'
    ]);
    exit();
}

// Process request
$downloader = new TikTokDownloader();
$result = $downloader->download($url);

// Output response
http_response_code($result['status'] === 'success' ? 200 : 400);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>