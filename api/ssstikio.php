<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

class TikTokDownloader {
    private $baseUrl = 'https://ssstik.io';
    private $apiEndpoint = '/abc?url=dl';
    private $locale = 'id';
    private $tt = 'eGJYY0xl';
    
    public function download($tiktokUrl) {
        try {
            // Validasi URL TikTok
            if (!$this->isValidTikTokUrl($tiktokUrl)) {
                return $this->errorResponse('URL TikTok tidak valid');
            }
            
            // Ambil cookies dan headers dari homepage
            $homepageData = $this->getHomepageData();
            
            // Request ke API ssstik
            $result = $this->requestDownload($tiktokUrl, $homepageData);
            
            if ($result['success']) {
                return $this->successResponse($result['data']);
            } else {
                return $this->errorResponse($result['message']);
            }
            
        } catch (Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage());
        }
    }
    
    private function isValidTikTokUrl($url) {
        $patterns = [
            '/tiktok\.com\/@[\w\.-]+\/video\/\d+/',
            '/tiktok\.com\/.*\/video\/\d+/',
            '/vm\.tiktok\.com\/[\w\-]+/',
            '/vt\.tiktok\.com\/[\w\-]+/',
            '/m\.tiktok\.com\/v\/\d+/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }
        return false;
    }
    
    private function getHomepageData() {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/' . $this->locale . '-1',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HEADER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9,id;q=0.8',
                'Accept-Encoding: gzip, deflate, br',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1'
            ]
        ]);
        
        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $headerSize);
        
        curl_close($ch);
        
        // Extract cookies
        $cookies = [];
        preg_match_all('/Set-Cookie: ([^;]+)/', $header, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $cookie) {
                $cookies[] = $cookie;
            }
        }
        
        return [
            'cookies' => implode('; ', $cookies)
        ];
    }
    
    private function requestDownload($tiktokUrl, $homepageData) {
        $postData = http_build_query([
            'id' => $tiktokUrl,
            'locale' => $this->locale,
            'tt' => $this->tt
        ]);
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . $this->apiEndpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING => 'gzip, deflate, br',
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: */*',
                'Accept-Language: en-US,en;q=0.9,id;q=0.8',
                'Content-Type: application/x-www-form-urlencoded',
                'Origin: ' . $this->baseUrl,
                'Referer: ' . $this->baseUrl . '/' . $this->locale . '-1',
                'HX-Current-URL: ' . $this->baseUrl . '/' . $this->locale . '-1',
                'HX-Request: true',
                'HX-Target: target',
                'HX-Trigger: _gcaptcha_pt',
                'Cookie: ' . $homepageData['cookies'],
                'sec-fetch-dest: empty',
                'sec-fetch-mode: cors',
                'sec-fetch-site: same-origin'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return [
                'success' => false,
                'message' => 'HTTP Error: ' . $httpCode
            ];
        }
        
        return $this->parseResponse($response);
    }
    
    private function parseResponse($html) {
        if (empty($html)) {
            return [
                'success' => false,
                'message' => 'Response kosong'
            ];
        }
        
        $data = [
            'title' => '',
            'description' => '',
            'thumbnail' => '',
            'video_url' => '',
            'video_url_hd' => '',
            'video_url_watermark' => '',
            'music' => [
                'title' => '',
                'author' => '',
                'url' => ''
            ],
            'author' => [
                'username' => '',
                'nickname' => '',
                'avatar' => ''
            ],
            'stats' => [
                'views' => '0',
                'likes' => '0',
                'comments' => '0',
                'shares' => '0',
                'saves' => '0'
            ],
            'duration' => '',
            'created_at' => ''
        ];
        
        // Extract title dari berbagai pattern
        if (preg_match('/<p[^>]*class="[^"]*maintext[^"]*"[^>]*>(.*?)<\/p>/s', $html, $match)) {
            $title = $this->cleanText($match[1]);
            $data['title'] = $title;
        } else if (preg_match('/<div[^>]*class="[^"]*video-title[^"]*"[^>]*>(.*?)<\/div>/s', $html, $match)) {
            $data['title'] = $this->cleanText($match[1]);
        }
        
        // Extract description (biasanya sama dengan title tapi lebih panjang)
        if (preg_match('/<div[^>]*class="[^"]*video-desc[^"]*"[^>]*>(.*?)<\/div>/s', $html, $match)) {
            $data['description'] = $this->cleanText($match[1]);
        }
        
        // Extract author username dan nickname
        // Pattern 1: dari link author
        if (preg_match('/<a[^>]+href="[^"]*\/@([^"\/\?]+)[^"]*"[^>]*>([^<]*)<\/a>/i', $html, $match)) {
            $data['author']['username'] = trim($match[1]);
            if (!empty($match[2])) {
                $data['author']['nickname'] = $this->cleanText($match[2]);
            }
        }
        // Pattern 2: dari text biasa
        else if (preg_match('/@([\w\.]+)/', $html, $match)) {
            $data['author']['username'] = trim($match[1]);
        }
        
        // Extract author nickname jika belum dapat
        if (empty($data['author']['nickname'])) {
            if (preg_match('/<div[^>]*class="[^"]*author-name[^"]*"[^>]*>([^<]+)<\/div>/i', $html, $match)) {
                $data['author']['nickname'] = $this->cleanText($match[1]);
            }
        }
        
        // Extract avatar dengan prioritas
        $avatarPatterns = [
            '/<img[^>]*class="[^"]*author_avatar[^"]*"[^>]*src="([^"]+)"/i',
            '/<img[^>]*src="([^"]+)"[^>]*class="[^"]*avatar[^"]*"/i',
            '/<img[^>]*src="(https:\/\/[^"]+)"[^>]*alt="[^"]*avatar[^"]*"/i',
            '/<div[^>]*class="[^"]*author[^"]*"[^>]*>.*?<img[^>]*src="([^"]+)"/s'
        ];
        
        foreach ($avatarPatterns as $pattern) {
            if (preg_match($pattern, $html, $match)) {
                $data['author']['avatar'] = $match[1];
                break;
            }
        }
        
        // Extract thumbnail video
        $thumbnailPatterns = [
            '/<div[^>]*class="[^"]*result_overlay[^"]*"[^>]*>.*?<img[^>]*src="([^"]+)"/s',
            '/<img[^>]*class="[^"]*thumbnail[^"]*"[^>]*src="([^"]+)"/i',
            '/<div[^>]*class="[^"]*video-thumbnail[^"]*"[^>]*>.*?<img[^>]*src="([^"]+)"/s'
        ];
        
        foreach ($thumbnailPatterns as $pattern) {
            if (preg_match($pattern, $html, $match)) {
                $data['thumbnail'] = $match[1];
                break;
            }
        }
        
        // Extract all download links
        preg_match_all('/<a[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/s', $html, $allLinks);
        
        for ($i = 0; $i < count($allLinks[0]); $i++) {
            $url = $allLinks[1][$i];
            $linkText = strtolower($this->cleanText($allLinks[2][$i]));
            
            // Skip invalid links
            if (empty($url) || strpos($url, 'javascript:') !== false || $url === '#') {
                continue;
            }
            
            // Video downloads
            if (preg_match('/download|unduh/i', $linkText) && preg_match('/video|mp4/i', $linkText)) {
                if (preg_match('/hd|kualitas tinggi/i', $linkText) && empty($data['video_url_hd'])) {
                    $data['video_url_hd'] = $url;
                } else if (preg_match('/watermark/i', $linkText)) {
                    $data['video_url_watermark'] = $url;
                } else if (empty($data['video_url'])) {
                    $data['video_url'] = $url;
                }
            }
            // Audio/Music downloads
            else if (preg_match('/download|unduh/i', $linkText) && preg_match('/audio|musik|music|mp3/i', $linkText)) {
                if (empty($data['music']['url'])) {
                    $data['music']['url'] = $url;
                }
            }
        }
        
        // Fallback: extract tikcdn.io URLs
        if (empty($data['video_url']) && empty($data['video_url_hd'])) {
            preg_match_all('/https:\/\/tikcdn\.io\/ssstik\/\d+\?[^\s"\'<>]+/i', $html, $tikcdnMatches);
            if (!empty($tikcdnMatches[0])) {
                foreach ($tikcdnMatches[0] as $idx => $url) {
                    if ($idx === 0 && empty($data['video_url'])) {
                        $data['video_url'] = $url;
                    } else if ($idx === 1 && empty($data['video_url_hd'])) {
                        $data['video_url_hd'] = $url;
                    }
                }
            }
        }
        
        // Extract stats dengan berbagai pattern
        // Pattern 1: dari div statistics
        if (preg_match('/<div[^>]*class="[^"]*statistic[^"]*"[^>]*>(.*?)<\/div>/s', $html, $statsBlock)) {
            $this->extractStats($statsBlock[1], $data['stats']);
        }
        
        // Pattern 2: dari pure-g stats container
        if (preg_match('/<div[^>]*class="[^"]*pure-g[^"]*"[^>]*>(.*?)<\/div>/s', $html, $statsBlock)) {
            $this->extractStats($statsBlock[1], $data['stats']);
        }
        
        // Pattern 3: ekstrak langsung angka dengan label
        preg_match_all('/([\d\.KMB]+)\s*(views?|like[sd]?|comment[sd]?|share[sd]?|save[sd]?|suka|komentar|bagikan|simpan)/i', $html, $statMatches);
        if (!empty($statMatches[1])) {
            for ($i = 0; $i < count($statMatches[1]); $i++) {
                $num = $this->normalizeNumber($statMatches[1][$i]);
                $type = strtolower($statMatches[2][$i]);
                
                if (preg_match('/view/i', $type) && $data['stats']['views'] === '0') {
                    $data['stats']['views'] = $num;
                }
                if (preg_match('/like|suka/i', $type) && $data['stats']['likes'] === '0') {
                    $data['stats']['likes'] = $num;
                }
                if (preg_match('/comment|komentar/i', $type) && $data['stats']['comments'] === '0') {
                    $data['stats']['comments'] = $num;
                }
                if (preg_match('/share|bagikan/i', $type) && $data['stats']['shares'] === '0') {
                    $data['stats']['shares'] = $num;
                }
                if (preg_match('/save|simpan/i', $type) && $data['stats']['saves'] === '0') {
                    $data['stats']['saves'] = $num;
                }
            }
        }
        
        // Extract music info
        if (preg_match('/<div[^>]*class="[^"]*music-info[^"]*"[^>]*>(.*?)<\/div>/s', $html, $musicBlock)) {
            if (preg_match('/<span[^>]*>([^<]+)<\/span>/i', $musicBlock[1], $match)) {
                $data['music']['title'] = $this->cleanText($match[1]);
            }
            if (preg_match('/@([\w\.]+)/', $musicBlock[1], $match)) {
                $data['music']['author'] = $match[1];
            }
        }
        
        // Extract duration
        if (preg_match('/(\d+:\d+)/', $html, $match)) {
            $data['duration'] = $match[1];
        }
        
        // Extract created date
        if (preg_match('/(\d{4}-\d{2}-\d{2}|\d{1,2}[\s\-]\w+[\s\-]\d{4})/i', $html, $match)) {
            $data['created_at'] = $match[1];
        }
        
        // Clean up data
        $data = $this->cleanData($data);
        
        if (empty($data['video_url']) && empty($data['video_url_hd'])) {
            return [
                'success' => false,
                'message' => 'Tidak dapat menemukan link download video'
            ];
        }
        
        return [
            'success' => true,
            'data' => $data
        ];
    }
    
    private function extractStats($html, &$stats) {
        // Extract views
        if (preg_match('/([\d\.KMB]+)\s*(?:views?|tayangan)/i', $html, $match)) {
            $stats['views'] = $this->normalizeNumber($match[1]);
        }
        // Extract likes
        if (preg_match('/([\d\.KMB]+)\s*(?:like[sd]?|suka)/i', $html, $match)) {
            $stats['likes'] = $this->normalizeNumber($match[1]);
        }
        // Extract comments
        if (preg_match('/([\d\.KMB]+)\s*(?:comment[sd]?|komentar)/i', $html, $match)) {
            $stats['comments'] = $this->normalizeNumber($match[1]);
        }
        // Extract shares
        if (preg_match('/([\d\.KMB]+)\s*(?:share[sd]?|bagikan)/i', $html, $match)) {
            $stats['shares'] = $this->normalizeNumber($match[1]);
        }
        // Extract saves
        if (preg_match('/([\d\.KMB]+)\s*(?:save[sd]?|simpan)/i', $html, $match)) {
            $stats['saves'] = $this->normalizeNumber($match[1]);
        }
    }
    
    private function cleanText($text) {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
    
    private function normalizeNumber($num) {
        $num = strtoupper(trim($num));
        $num = str_replace([',', ' '], '', $num);
        
        if (strpos($num, 'B') !== false) {
            return str_replace('B', '', $num) . 'B';
        }
        if (strpos($num, 'M') !== false) {
            return str_replace('M', '', $num) . 'M';
        }
        if (strpos($num, 'K') !== false) {
            return str_replace('K', '', $num) . 'K';
        }
        return $num;
    }
    
    private function cleanData($data) {
        // Remove empty video URLs
        if (empty($data['video_url_hd'])) unset($data['video_url_hd']);
        if (empty($data['video_url_watermark'])) unset($data['video_url_watermark']);
        if (empty($data['duration'])) unset($data['duration']);
        if (empty($data['created_at'])) unset($data['created_at']);
        if (empty($data['thumbnail'])) unset($data['thumbnail']);
        if (empty($data['description'])) unset($data['description']);
        
        // Clean music data
        if (empty($data['music']['url']) && empty($data['music']['title']) && empty($data['music']['author'])) {
            unset($data['music']);
        } else {
            if (empty($data['music']['url'])) unset($data['music']['url']);
            if (empty($data['music']['title'])) unset($data['music']['title']);
            if (empty($data['music']['author'])) unset($data['music']['author']);
        }
        
        // Clean author data
        if (empty($data['author']['username']) && empty($data['author']['nickname']) && empty($data['author']['avatar'])) {
            unset($data['author']);
        } else {
            if (empty($data['author']['username'])) unset($data['author']['username']);
            if (empty($data['author']['nickname'])) unset($data['author']['nickname']);
            if (empty($data['author']['avatar'])) unset($data['author']['avatar']);
        }
        
        // Remove zero stats
        foreach ($data['stats'] as $key => $value) {
            if ($value === '0') {
                unset($data['stats'][$key]);
            }
        }
        
        // If all stats are 0, keep the structure but with actual 0 values
        if (empty($data['stats'])) {
            $data['stats'] = [
                'likes' => '0',
                'comments' => '0',
                'shares' => '0'
            ];
        }
        
        return $data;
    }
    
    private function successResponse($data) {
        return [
            'status' => 'success',
            'code' => 200,
            'data' => $data
        ];
    }
    
    private function errorResponse($message) {
        return [
            'status' => 'error',
            'code' => 400,
            'message' => $message
        ];
    }
}

// Handle request
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $tiktokUrl = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $tiktokUrl = $input['url'] ?? ($_POST['url'] ?? '');
    } else {
        $tiktokUrl = $_GET['url'] ?? '';
    }
    
    if (empty($tiktokUrl)) {
        echo json_encode([
            'status' => 'error',
            'code' => 400,
            'message' => 'Parameter URL TikTok diperlukan'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $downloader = new TikTokDownloader();
    $result = $downloader->download($tiktokUrl);
    
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'status' => 'error',
        'code' => 405,
        'message' => 'Method tidak diizinkan'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
?>