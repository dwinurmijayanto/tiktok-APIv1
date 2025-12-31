<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

/**
 * TikTok Search API - Real Data Version
 * 
 * REQUIREMENT: Untuk mendapatkan data real dari TikTok, Anda perlu:
 * 
 * OPTION 1 - RapidAPI (Recommended, Easy):
 * 1. Daftar di https://rapidapi.com/
 * 2. Subscribe ke "TikTok API" atau "TikTok Scraper"
 * 3. Masukkan API key di bawah
 * 
 * OPTION 2 - ScraperAPI:
 * 1. Daftar di https://www.scraperapi.com/
 * 2. Masukkan API key di bawah
 * 
 * OPTION 3 - Puppeteer (Advanced):
 * 1. Install Node.js + Puppeteer
 * 2. Buat bridge script untuk PHP
 * 3. Uncomment method searchViaPuppeteer()
 */

class TikTokSearchAPI {
    private $debug = [];
    
    // CONFIGURATION - Pilih method yang akan digunakan
    private $method = 'rapidapi'; // 'rapidapi', 'scraperapi', 'puppeteer'
    
    // API KEYS - Masukkan API key Anda di sini
    private $rapidApiKey = 'YOUR_RAPIDAPI_KEY_HERE'; // Get from https://rapidapi.com
    private $scraperApiKey = 'YOUR_SCRAPERAPI_KEY_HERE'; // Get from https://scraperapi.com
    
    public function search($query, $limit = 10) {
        $this->addDebug('start', 'Memulai pencarian TikTok REAL', [
            'query' => $query, 
            'limit' => $limit,
            'method' => $this->method
        ]);
        
        // Pilih method berdasarkan konfigurasi
        switch ($this->method) {
            case 'rapidapi':
                $results = $this->searchViaRapidAPI($query, $limit);
                break;
            case 'scraperapi':
                $results = $this->searchViaScraperAPI($query, $limit);
                break;
            case 'puppeteer':
                $results = $this->searchViaPuppeteer($query, $limit);
                break;
            default:
                $results = [];
        }
        
        if (empty($results)) {
            return [
                'success' => false,
                'message' => 'Tidak ada hasil. Pastikan API key sudah dikonfigurasi dengan benar.',
                'setup_instructions' => [
                    'method_used' => $this->method,
                    'rapidapi_setup' => [
                        'step_1' => 'Daftar di https://rapidapi.com/',
                        'step_2' => 'Subscribe ke "TikTok API" atau "TikApi - mobile API"',
                        'step_3' => 'Copy API key dari dashboard',
                        'step_4' => 'Paste di $rapidApiKey dalam code',
                        'step_5' => 'Set $method = "rapidapi"',
                        'pricing' => 'Free tier: 500 requests/month'
                    ],
                    'scraperapi_setup' => [
                        'step_1' => 'Daftar di https://www.scraperapi.com/',
                        'step_2' => 'Copy API key dari dashboard',
                        'step_3' => 'Paste di $scraperApiKey dalam code',
                        'step_4' => 'Set $method = "scraperapi"',
                        'pricing' => 'Free tier: 5,000 requests/month'
                    ],
                    'puppeteer_setup' => [
                        'step_1' => 'Install Node.js dari https://nodejs.org/',
                        'step_2' => 'Install: npm install -g tiktok-scraper',
                        'step_3' => 'Set $method = "puppeteer"',
                        'note' => 'Requires Node.js environment'
                    ]
                ],
                'debug' => $this->debug
            ];
        }
        
        return $this->formatResponse(true, $query, $results);
    }
    
    private function searchViaRapidAPI($query, $limit) {
        $this->addDebug('rapidapi_start', 'Mencari via RapidAPI');
        
        if ($this->rapidApiKey === 'YOUR_RAPIDAPI_KEY_HERE') {
            $this->addDebug('rapidapi_error', 'API key belum dikonfigurasi');
            return [];
        }
        
        // RapidAPI TikTok endpoint
        $url = "https://tiktok-api6.p.rapidapi.com/search/general";
        
        $params = [
            'keyword' => $query,
            'count' => $limit,
            'offset' => 0
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url . '?' . http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'X-RapidAPI-Key: ' . $this->rapidApiKey,
                'X-RapidAPI-Host: tiktok-api6.p.rapidapi.com'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $this->addDebug('rapidapi_response', 'Response dari RapidAPI', [
            'http_code' => $httpCode,
            'response_length' => strlen($response)
        ]);
        
        if ($httpCode !== 200 || empty($response)) {
            $this->addDebug('rapidapi_failed', 'Request gagal', [
                'http_code' => $httpCode,
                'response_preview' => substr($response, 0, 200)
            ]);
            return [];
        }
        
        $data = json_decode($response, true);
        
        if (!$data) {
            $this->addDebug('rapidapi_parse_error', 'Gagal parse JSON');
            return [];
        }
        
        return $this->parseRapidAPIResponse($data, $limit);
    }
    
    private function parseRapidAPIResponse($data, $limit) {
        $results = [];
        
        // RapidAPI structure berbeda-beda tergantung endpoint
        $items = $data['data']['videos'] ?? $data['videos'] ?? $data['data'] ?? [];
        
        $this->addDebug('parse_rapidapi', 'Parsing response', ['items_count' => count($items)]);
        
        foreach ($items as $item) {
            if (count($results) >= $limit) break;
            
            $videoId = $item['video_id'] ?? $item['id'] ?? $item['aweme_id'] ?? null;
            $author = $item['author']['unique_id'] ?? $item['author']['uniqueId'] ?? $item['username'] ?? null;
            $desc = $item['desc'] ?? $item['title'] ?? $item['description'] ?? 'No Title';
            
            if (!$videoId || !$author) continue;
            
            $result = [
                'title' => $desc,
                'link' => "https://www.tiktok.com/@{$author}/video/{$videoId}",
                'video_id' => $videoId,
                'author' => $author,
                'author_name' => $item['author']['nickname'] ?? $author,
                'thumbnail' => $item['video']['cover'] ?? $item['cover'] ?? $item['thumbnail'] ?? null,
                'stats' => [
                    'views' => $item['stats']['play_count'] ?? $item['play_count'] ?? 0,
                    'likes' => $item['stats']['digg_count'] ?? $item['digg_count'] ?? 0,
                    'comments' => $item['stats']['comment_count'] ?? $item['comment_count'] ?? 0,
                    'shares' => $item['stats']['share_count'] ?? $item['share_count'] ?? 0
                ],
                'created_at' => isset($item['create_time']) ? date('Y-m-d H:i:s', $item['create_time']) : null,
                'duration' => $item['video']['duration'] ?? $item['duration'] ?? null,
                'is_real' => true
            ];
            
            $results[] = $result;
        }
        
        $this->addDebug('parse_complete', 'Parsing selesai', ['total' => count($results)]);
        
        return $results;
    }
    
    private function searchViaScraperAPI($query, $limit) {
        $this->addDebug('scraperapi_start', 'Mencari via ScraperAPI');
        
        if ($this->scraperApiKey === 'YOUR_SCRAPERAPI_KEY_HERE') {
            $this->addDebug('scraperapi_error', 'API key belum dikonfigurasi');
            return [];
        }
        
        // ScraperAPI akan handle rendering JavaScript
        $tiktokUrl = "https://www.tiktok.com/search?q=" . urlencode($query);
        $apiUrl = "http://api.scraperapi.com/?api_key={$this->scraperApiKey}&url=" . urlencode($tiktokUrl) . "&render=true";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60, // ScraperAPI butuh waktu lebih lama
        ]);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $this->addDebug('scraperapi_response', 'Response dari ScraperAPI', [
            'http_code' => $httpCode,
            'html_length' => strlen($html)
        ]);
        
        if ($httpCode !== 200 || empty($html)) {
            return [];
        }
        
        // Parse HTML untuk extract video data
        return $this->parseHTMLForVideos($html, $limit);
    }
    
    private function parseHTMLForVideos($html, $limit) {
        $results = [];
        
        // Extract JSON data dari HTML
        if (preg_match('/<script[^>]*id="__UNIVERSAL_DATA_FOR_REHYDRATION__"[^>]*>(.*?)<\/script>/s', $html, $matches)) {
            $json = $matches[1];
            $data = json_decode($json, true);
            
            if ($data) {
                $this->findVideosRecursive($data, $results, $limit);
            }
        }
        
        $this->addDebug('html_parse_complete', 'Parsing HTML selesai', ['found' => count($results)]);
        
        return $results;
    }
    
    private function findVideosRecursive($data, &$results, $limit, $depth = 0) {
        if ($depth > 10 || count($results) >= $limit || !is_array($data)) return;
        
        if (isset($data['id']) && isset($data['desc'])) {
            $author = $data['author']['uniqueId'] ?? $data['author']['unique_id'] ?? null;
            $videoId = $data['id'];
            
            if ($author && $videoId) {
                $results[] = [
                    'title' => $data['desc'],
                    'link' => "https://www.tiktok.com/@{$author}/video/{$videoId}",
                    'video_id' => $videoId,
                    'author' => $author,
                    'author_name' => $data['author']['nickname'] ?? $author,
                    'thumbnail' => $data['video']['cover'] ?? null,
                    'stats' => [
                        'views' => $data['stats']['playCount'] ?? 0,
                        'likes' => $data['stats']['diggCount'] ?? 0,
                        'comments' => $data['stats']['commentCount'] ?? 0,
                        'shares' => $data['stats']['shareCount'] ?? 0
                    ],
                    'is_real' => true
                ];
                return;
            }
        }
        
        foreach ($data as $value) {
            if (is_array($value)) {
                $this->findVideosRecursive($value, $results, $limit, $depth + 1);
            }
        }
    }
    
    private function searchViaPuppeteer($query, $limit) {
        $this->addDebug('puppeteer_start', 'Mencari via Puppeteer/tiktok-scraper');
        
        // Menggunakan tiktok-scraper npm package
        $command = "tiktok-scraper search " . escapeshellarg($query) . " -n {$limit} --json";
        
        $output = shell_exec($command . " 2>&1");
        
        $this->addDebug('puppeteer_output', 'Output dari tiktok-scraper', [
            'output_length' => strlen($output)
        ]);
        
        if (empty($output)) {
            $this->addDebug('puppeteer_error', 'tiktok-scraper tidak menghasilkan output. Pastikan sudah diinstall: npm install -g tiktok-scraper');
            return [];
        }
        
        $data = json_decode($output, true);
        
        if (!$data || !isset($data['collector'])) {
            return [];
        }
        
        return $this->parsePuppeteerResponse($data, $limit);
    }
    
    private function parsePuppeteerResponse($data, $limit) {
        $results = [];
        $items = $data['collector'] ?? [];
        
        foreach ($items as $item) {
            if (count($results) >= $limit) break;
            
            $results[] = [
                'title' => $item['text'] ?? 'No Title',
                'link' => $item['webVideoUrl'] ?? $item['videoUrl'],
                'video_id' => $item['id'],
                'author' => $item['authorMeta']['name'] ?? 'Unknown',
                'author_name' => $item['authorMeta']['nickName'] ?? $item['authorMeta']['name'],
                'thumbnail' => $item['covers']['default'] ?? null,
                'stats' => [
                    'views' => $item['playCount'] ?? 0,
                    'likes' => $item['diggCount'] ?? 0,
                    'comments' => $item['commentCount'] ?? 0,
                    'shares' => $item['shareCount'] ?? 0
                ],
                'created_at' => isset($item['createTime']) ? date('Y-m-d H:i:s', $item['createTime']) : null,
                'is_real' => true
            ];
        }
        
        return $results;
    }
    
    private function formatResponse($success, $query, $results) {
        $this->addDebug('complete', 'Pencarian selesai', ['total' => count($results)]);
        
        return [
            'success' => $success,
            'query' => $query,
            'total' => count($results),
            'method' => $this->method,
            'data_source' => 'real_tiktok',
            'results' => $results,
            'debug' => $this->debug
        ];
    }
    
    private function addDebug($step, $message, $data = []) {
        $this->debug[] = [
            'step' => $step,
            'message' => $message,
            'data' => $data,
            'timestamp' => microtime(true)
        ];
    }
}

// Handle Request
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' || $method === 'POST') {
    $query = $_GET['q'] ?? $_POST['q'] ?? '';
    $limit = (int)($_GET['limit'] ?? $_POST['limit'] ?? 10);
    
    if (empty($query)) {
        echo json_encode([
            'success' => false,
            'message' => 'Parameter "q" (query) diperlukan',
            'usage' => [
                'endpoint' => $_SERVER['PHP_SELF'],
                'parameters' => [
                    'q' => 'Query pencarian (required)',
                    'limit' => 'Jumlah hasil (optional, default: 10)'
                ],
                'example' => $_SERVER['PHP_SELF'] . '?q=dance&limit=5'
            ],
            'setup_required' => 'API ini membutuhkan konfigurasi API key untuk mendapatkan data real dari TikTok. Lihat dokumentasi di dalam code.'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    $limit = min($limit, 20);
    
    $api = new TikTokSearchAPI();
    $result = $api->search($query, $limit);
    
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method tidak didukung'
    ], JSON_PRETTY_PRINT);
}
?>