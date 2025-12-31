<?php
/**
 * MusicalDown API
 * Endpoint: musicaldown.php?url=TIKTOK_URL
 * 
 * Download TikTok video via MusicalDown (HD, No Watermark, Slideshow)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

class MusicalDownAPI {
    
    private $baseUrl = 'https://musicaldown.com';
    
    public function download($url) {
        try {
            if (strpos($url, 'tiktok.com') === false) {
                throw new Exception('Invalid TikTok URL');
            }
            
            $userAgent = 'Mozilla/5.0 (Linux; Android 15; SM-F958 Build/AP3A.240905.015) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.6723.86 Mobile Safari/537.36';
            
            // Step 1: Get form
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://musicaldown.com/en',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HEADER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => [
                    'User-Agent: ' . $userAgent,
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
                ]
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);
            
            if (!$response || $httpCode != 200) {
                throw new Exception("Failed to fetch MusicalDown homepage");
            }
            
            $header = substr($response, 0, $headerSize);
            $html = substr($response, $headerSize);
            
            preg_match_all('/Set-Cookie:\s*([^;]+)/i', $header, $matches);
            $cookies = isset($matches[1]) ? implode('; ', $matches[1]) : '';
            
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            @$dom->loadHTML($html);
            libxml_clear_errors();
            
            $xpath = new DOMXPath($dom);
            
            $payload = [];
            $inputs = $xpath->query('//form[@id="submit-form"]//input');
            
            if ($inputs->length === 0) {
                $inputs = $xpath->query('//form//input[@name]');
            }
            
            foreach ($inputs as $input) {
                $name = $input->getAttribute('name');
                $value = $input->getAttribute('value');
                if ($name) {
                    $payload[$name] = $value ?: '';
                }
            }
            
            if (empty($payload)) {
                throw new Exception("No form inputs found");
            }
            
            // Insert URL
            $urlInserted = false;
            foreach ($payload as $key => $value) {
                if (empty($value)) {
                    $payload[$key] = $url;
                    $urlInserted = true;
                    break;
                }
            }
            
            if (!$urlInserted) {
                if (isset($payload['url'])) {
                    $payload['url'] = $url;
                } else {
                    $firstKey = array_key_first($payload);
                    if ($firstKey) {
                        $payload[$firstKey] = $url;
                    } else {
                        $payload['url'] = $url;
                    }
                }
            }
            
            $postData = http_build_query($payload);
            
            // Step 2: Submit form
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://musicaldown.com/download',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                    'Cookie: ' . $cookies,
                    'Origin: https://musicaldown.com',
                    'Referer: https://musicaldown.com/',
                    'User-Agent: ' . $userAgent
                ]
            ]);
            
            $data = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if (!$data || $httpCode != 200) {
                throw new Exception("Failed to submit form");
            }
            
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            @$dom->loadHTML($data);
            libxml_clear_errors();
            
            $xpath = new DOMXPath($dom);
            
            // Extract cover
            $videoHeader = $xpath->query('//div[contains(@class, "video-header")]');
            $cover = null;
            if ($videoHeader->length > 0) {
                $style = $videoHeader->item(0)->getAttribute('style');
                if (preg_match('/url\((.*?)\)/', $style, $coverMatch)) {
                    $cover = trim($coverMatch[1], '\'"');
                }
            }
            
            // Extract downloads
            $downloads = [];
            $downloadLinks = $xpath->query('//a[contains(@class, "download")]');
            
            foreach ($downloadLinks as $elem) {
                $type = $elem->getAttribute('data-event');
                $type = str_replace('_download_click', '', $type);
                $label = trim($elem->textContent);
                $downloadUrl = $elem->getAttribute('href');
                
                if ($downloadUrl) {
                    $downloads[] = [
                        'type' => $type,
                        'label' => $label,
                        'url' => $downloadUrl
                    ];
                }
            }
            
            // Extract slideshow images
            $images = [];
            $imgNodes = $xpath->query('//img[@data-splide-lazy]');
            foreach ($imgNodes as $img) {
                $imgUrl = $img->getAttribute('data-splide-lazy');
                if ($imgUrl) {
                    $images[] = $imgUrl;
                }
            }
            
            foreach ($downloads as $download) {
                if ($download['type'] === 'slide') {
                    $images[] = $download['url'];
                }
            }
            
            // Process downloads
            $result = [
                'type' => count($images) > 0 ? 'slideshow' : 'video',
                'thumbnail' => $cover
            ];
            
            if (count($images) > 0) {
                $result['images'] = array_values(array_unique($images));
                $result['image_count'] = count($result['images']);
            }
            
            foreach ($downloads as $download) {
                if ($download['type'] === 'hd') {
                    $result['video_hd'] = $download['url'];
                } elseif ($download['type'] === 'video') {
                    $result['video_nowm'] = $download['url'];
                } elseif ($download['type'] === 'music') {
                    $result['audio'] = $download['url'];
                }
            }
            
            if (!isset($result['images']) && !isset($result['video_hd']) && !isset($result['video_nowm'])) {
                throw new Exception("No download links found");
            }
            
            return $result;
            
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}

// API Handler
$url = $_GET['url'] ?? $_GET['v'] ?? $_GET['id'] ?? $_POST['url'] ?? '';

if (empty($url)) {
    echo json_encode([
        'success' => false,
        'message' => 'URL parameter required',
        'endpoint' => 'musicaldown.php',
        'usage' => 'musicaldown.php?url=TIKTOK_URL',
        'description' => 'Download TikTok video via MusicalDown',
        'features' => [
            'Video HD quality',
            'Video without watermark',
            'Slideshow/Images support',
            'Audio/MP3 extraction',
            'Multiple quality options'
        ],
        'response_fields' => [
            'type' => 'video or slideshow',
            'thumbnail' => 'Cover image URL',
            'video_hd' => 'HD video URL (if available)',
            'video_nowm' => 'Video without watermark URL',
            'audio' => 'Audio/MP3 URL',
            'images' => 'Array of image URLs (for slideshow)',
            'image_count' => 'Total images (for slideshow)'
        ],
        'examples' => [
            'musicaldown.php?url=https://www.tiktok.com/@user/video/123',
            'musicaldown.php?url=https://vm.tiktok.com/xxxxx'
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $api = new MusicalDownAPI();
    $result = $api->download($url);
    
    echo json_encode([
        'success' => true,
        'method' => 'MusicalDown',
        'data' => $result
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>