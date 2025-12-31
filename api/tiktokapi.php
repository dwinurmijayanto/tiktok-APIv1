<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

class TikTokDownloader {
    
    private $baseUrl = 'https://musicaldown.com';
    private $debugMode = false;
    private $testAllApis = false;
    private $debugLogs = [];
    
    // Encryption keys untuk SaveTik
    private $encKey = "GJvE5RZIxrl9SuNrAtgsvCfWha3M7NGC";
    private $decKey = "H3quWdWoHLX5bZSlyCYAnvDFara25FIu";
    
    // Shared metadata storage
    private $sharedMetadata = null;
    
    public function __construct($debug = false, $testAllApis = false) {
        $this->debugMode = $debug;
        $this->testAllApis = $testAllApis;
    }
    
    private function log($message, $data = null) {
        if ($this->debugMode) {
            $logEntry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'message' => $message
            ];
            if ($data !== null) {
                $logEntry['data'] = $data;
            }
            $this->debugLogs[] = $logEntry;
        }
    }
    
    public function getDebugLogs() {
        return $this->debugLogs;
    }
    
    // Method untuk mendapatkan metadata lengkap dari TikTok
    private function getTikTokMetadata($url) {
        try {
            $this->log("Fetching TikTok metadata directly", ['url' => $url]);
            
            // Method 1: TikTok oEmbed API
            $oembed_url = 'https://www.tiktok.com/oembed?url=' . urlencode($url);
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $oembed_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode == 200 && $response) {
                $data = json_decode($response, true);
                if ($data) {
                    $this->log("oEmbed data retrieved", ['data' => $data]);
                    
                    $metadata = [
                        'title' => $data['title'] ?? null,
                        'author_name' => $data['author_name'] ?? null,
                        'author_url' => $data['author_url'] ?? null,
                        'thumbnail' => $data['thumbnail_url'] ?? null,
                        'provider' => 'oembed'
                    ];
                    
                    // Extract username from author_url or author_unique_id
                    if (isset($data['author_unique_id'])) {
                        $metadata['username'] = $data['author_unique_id'];
                    } elseif ($metadata['author_url']) {
                        if (preg_match('/@([^\/\?]+)/', $metadata['author_url'], $match)) {
                            $metadata['username'] = $match[1];
                        }
                    }
                    
                    // Extract music from HTML if present
                    if (isset($data['html'])) {
                        if (preg_match('/title="♬\s*([^"]+)"/', $data['html'], $musicMatch)) {
                            $metadata['music'] = trim($musicMatch[1]);
                        } elseif (preg_match('/>♬\s*([^<]+)<\/a>/', $data['html'], $musicMatch)) {
                            $metadata['music'] = trim($musicMatch[1]);
                        }
                    }
                    
                    return $metadata;
                }
            }
            
            // Method 2: Scrape from TikTok page directly
            $this->log("Trying direct TikTok page scraping");
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9'
                ]
            ]);
            
            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode == 200 && $html) {
                $this->log("TikTok page retrieved", ['length' => strlen($html)]);
                
                $metadata = [];
                
                // Extract from __UNIVERSAL_DATA_FOR_REHYDRATION__
                if (preg_match('/<script[^>]*id="__UNIVERSAL_DATA_FOR_REHYDRATION__"[^>]*>(.*?)<\/script>/s', $html, $match)) {
                    $jsonData = json_decode($match[1], true);
                    if ($jsonData) {
                        $this->log("Found __UNIVERSAL_DATA_FOR_REHYDRATION__");
                        
                        $detail = $jsonData['__DEFAULT_SCOPE__']['webapp.video-detail']['itemInfo']['itemStruct'] ?? null;
                        
                        if ($detail) {
                            $metadata = [
                                'title' => $detail['desc'] ?? null,
                                'username' => $detail['author']['uniqueId'] ?? null,
                                'nickname' => $detail['author']['nickname'] ?? null,
                                'avatar' => $detail['author']['avatarMedium'] ?? $detail['author']['avatarThumb'] ?? null,
                                'likes' => $detail['stats']['diggCount'] ?? null,
                                'comments' => $detail['stats']['commentCount'] ?? null,
                                'shares' => $detail['stats']['shareCount'] ?? null,
                                'views' => $detail['stats']['playCount'] ?? null,
                                'duration' => isset($detail['video']['duration']) ? gmdate("i:s", $detail['video']['duration']) : null,
                                'music' => $detail['music']['title'] ?? $detail['music']['authorName'] ?? null,
                                'created_at' => isset($detail['createTime']) ? date('Y-m-d H:i:s', $detail['createTime']) : null,
                                'cover' => $detail['video']['cover'] ?? $detail['video']['originCover'] ?? null,
                                'provider' => 'tiktok_page'
                            ];
                            
                            // Extract hashtags
                            if (isset($detail['challenges']) && is_array($detail['challenges'])) {
                                $metadata['hashtags'] = array_map(function($tag) {
                                    return $tag['title'] ?? null;
                                }, $detail['challenges']);
                                $metadata['hashtags'] = array_filter($metadata['hashtags']);
                            }
                            
                            // Format numbers
                            foreach (['likes', 'comments', 'shares', 'views'] as $key) {
                                if (isset($metadata[$key]) && is_numeric($metadata[$key])) {
                                    $metadata[$key] = $this->formatNumber($metadata[$key]);
                                }
                            }
                            
                            return $metadata;
                        }
                    }
                }
                
                // Try SIGI_STATE
                if (preg_match('/<script[^>]*id="SIGI_STATE"[^>]*>(.*?)<\/script>/s', $html, $match)) {
                    $jsonData = json_decode($match[1], true);
                    if ($jsonData) {
                        $this->log("Found SIGI_STATE");
                        
                        $detail = null;
                        if (isset($jsonData['ItemModule'])) {
                            $detail = reset($jsonData['ItemModule']);
                        } elseif (isset($jsonData['VideoDetailModule'])) {
                            $detail = $jsonData['VideoDetailModule'];
                        }
                        
                        if ($detail) {
                            $metadata = [
                                'title' => $detail['desc'] ?? $detail['caption'] ?? null,
                                'username' => $detail['author'] ?? null,
                                'nickname' => $detail['authorName'] ?? null,
                                'likes' => $detail['diggCount'] ?? null,
                                'comments' => $detail['commentCount'] ?? null,
                                'shares' => $detail['shareCount'] ?? null,
                                'views' => $detail['playCount'] ?? null,
                                'provider' => 'tiktok_page'
                            ];
                            
                            foreach (['likes', 'comments', 'shares', 'views'] as $key) {
                                if (isset($metadata[$key]) && is_numeric($metadata[$key])) {
                                    $metadata[$key] = $this->formatNumber($metadata[$key]);
                                }
                            }
                            
                            return $metadata;
                        }
                    }
                }
                
                // Extract from meta tags
                $metadata = [];
                if (preg_match('/<meta[^>]*property="og:title"[^>]*content="([^"]*)"/', $html, $match)) {
                    $metadata['title'] = html_entity_decode($match[1]);
                }
                if (preg_match('/<meta[^>]*property="og:image"[^>]*content="([^"]*)"/', $html, $match)) {
                    $metadata['cover'] = $match[1];
                }
                if (preg_match('/<meta[^>]*name="description"[^>]*content="([^"]*)"/', $html, $match)) {
                    $metadata['description'] = html_entity_decode($match[1]);
                }
                
                if (!empty($metadata)) {
                    $metadata['provider'] = 'meta_tags';
                    return $metadata;
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            $this->log("TikTok metadata fetch failed", ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    private function formatNumber($num) {
        if ($num >= 1000000000) {
            return round($num / 1000000000, 1) . 'B';
        } elseif ($num >= 1000000) {
            return round($num / 1000000, 1) . 'M';
        } elseif ($num >= 1000) {
            return round($num / 1000, 1) . 'K';
        }
        return $num;
    }
    
    // Method untuk enkripsi/dekripsi SaveTik
    private function cryptoProc($type, $data) {
        try {
            $key = ($type === 'enc') ? $this->encKey : $this->decKey;
            $iv = substr($key, 0, 16);
            
            if ($type === 'enc') {
                $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
                $this->log("Encryption successful", ['input_length' => strlen($data)]);
                return $encrypted;
            } else {
                $decrypted = openssl_decrypt($data, 'aes-256-cbc', $key, 0, $iv);
                $this->log("Decryption successful", ['output_length' => strlen($decrypted)]);
                return $decrypted;
            }
        } catch (Exception $e) {
            $this->log("Crypto error", ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    // Method untuk membangun response data yang konsisten
    private function buildUnifiedResponse($downloadData, $metadata = null) {
        // Gunakan shared metadata jika ada, atau metadata yang diberikan
        $meta = $metadata ?? $this->sharedMetadata;
        
        $response = [
            'Judul' => $downloadData['title'] ?? ($meta['title'] ?? 'TikTok Video'),
            'Type' => $downloadData['type'] ?? 'video'
        ];
        
        // Author information - prioritas: metadata TikTok > data download
        $authorData = [];
        
        if ($meta) {
            if (isset($meta['username']) || isset($meta['author_name'])) {
                $authorData['Username'] = $meta['username'] ?? $meta['author_name'];
            }
            if (isset($meta['nickname'])) {
                $authorData['Nickname'] = $meta['nickname'];
            }
            if (isset($meta['avatar']) || isset($meta['thumbnail'])) {
                $authorData['Avatar'] = $meta['avatar'] ?? $meta['thumbnail'];
            }
        }
        
        // Fallback ke data dari download jika metadata tidak ada
        if (empty($authorData) && isset($downloadData['author'])) {
            if (is_array($downloadData['author'])) {
                $authorData = array_filter($downloadData['author']);
            } elseif (is_string($downloadData['author'])) {
                $authorData['Username'] = $downloadData['author'];
            }
        }
        
        if (!empty($authorData)) {
            $response['Author'] = $authorData;
        }
        
        // Media content
        if ($downloadData['type'] === 'image' && isset($downloadData['images'])) {
            $response['Images'] = $downloadData['images'];
            $response['Image_Count'] = count($downloadData['images']);
        } elseif (isset($downloadData['download'])) {
            $response['Thumbnail'] = $downloadData['thumbnail'] ?? ($meta['cover'] ?? ($meta['thumbnail'] ?? null));
            $response['Download'] = $downloadData['download'];
            
            if (isset($downloadData['video_hd'])) {
                $response['Video_HD'] = $downloadData['video_hd'];
            }
            if (isset($downloadData['video_nowm'])) {
                $response['Video_No_Watermark'] = $downloadData['video_nowm'];
            }
        }
        
        // Audio/Music
        if (isset($downloadData['audio'])) {
            $response['Audio'] = $downloadData['audio'];
        }
        
        if ($meta && isset($meta['music'])) {
            $response['Music'] = $meta['music'];
        }
        
        // Additional metadata dari TikTok
        if ($meta) {
            if (isset($meta['duration'])) {
                $response['Duration'] = $meta['duration'];
            }
            
            if (isset($meta['created_at'])) {
                $response['Created_At'] = $meta['created_at'];
            }
            
            if (isset($meta['hashtags']) && !empty($meta['hashtags'])) {
                $response['Hashtags'] = $meta['hashtags'];
            }
            
            // Statistics
            $stats = [];
            if (isset($meta['likes'])) $stats['likes'] = $meta['likes'];
            if (isset($meta['comments'])) $stats['comments'] = $meta['comments'];
            if (isset($meta['shares'])) $stats['shares'] = $meta['shares'];
            if (isset($meta['views'])) $stats['views'] = $meta['views'];
            
            if (!empty($stats)) {
                $response['Stats'] = $stats;
            }
        }
        
        // Quality info
        if (isset($downloadData['quality'])) {
            $response['Quality'] = $downloadData['quality'];
        }
        
        return $response;
    }
    
    // Method SaveTik dengan unified metadata
    private function getVideoFromSaveTik($url) {
        try {
            $this->log("Starting SaveTik method", ['url' => $url]);
            $apiUrl = "https://savetik.app/requests";
            
            $encryptedUrl = $this->cryptoProc('enc', $url);
            
            if (!$encryptedUrl) {
                throw new Exception("Encryption failed");
            }
            
            $postData = json_encode([
                'bdata' => $encryptedUrl
            ]);
            
            $this->log("SaveTik request prepared", ['encrypted_length' => strlen($encryptedUrl)]);
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => [
                    'User-Agent: Mozilla/5.0 (Android 15; Mobile; SM-D639N; rv:130.0) Gecko/130.0 Firefox/130.0',
                    'Content-Type: application/json',
                    'Accept: application/json'
                ]
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            $this->log("SaveTik response received", [
                'http_code' => $httpCode,
                'response_length' => strlen($response),
                'curl_error' => $curlError ?: 'none'
            ]);
            
            if ($httpCode == 200 && $response) {
                $data = json_decode($response, true);
                
                if (isset($data['status']) && $data['status'] === 'success' && isset($data['data'])) {
                    $videoUrl = $this->cryptoProc('dec', $data['data']);
                    
                    $this->log("SaveTik success", ['video_url_found' => !empty($videoUrl)]);
                    
                    // Build download data structure
                    $downloadData = [
                        'success' => true,
                        'type' => 'video',
                        'title' => $data['title'] ?? null,
                        'author' => $data['username'] ?? null,
                        'thumbnail' => $data['thumbnailUrl'] ?? null,
                        'download' => $videoUrl,
                        'audio' => $data['mp3'] ?? null
                    ];
                    
                    // Build unified response dengan metadata
                    return [
                        'success' => true,
                        'data' => $this->buildUnifiedResponse($downloadData, $this->sharedMetadata)
                    ];
                }
            }
            
            $this->log("SaveTik failed", ['reason' => 'Invalid response or status']);
            return ['success' => false];
        } catch (Exception $error) {
            $this->log("SaveTik exception", ['error' => $error->getMessage()]);
            return ['success' => false, 'error' => $error->getMessage()];
        }
    }
    
    private function ttdown($url) {
        try {
            $this->log("Starting MusicalDown method", ['url' => $url]);
            
            if (strpos($url, 'tiktok.com') === false) {
                throw new Exception('Invalid TikTok URL');
            }
            
            $userAgent = 'Mozilla/5.0 (Linux; Android 15; SM-F958 Build/AP3A.240905.015) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.6723.86 Mobile Safari/537.36';
            
            $this->log("Step 1: Fetching MusicalDown homepage");
            
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
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9'
                ]
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);
            
            $this->log("Step 1 response", [
                'http_code' => $httpCode,
                'response_length' => strlen($response),
                'curl_error' => $curlError ?: 'none'
            ]);
            
            if (!$response || $httpCode != 200) {
                throw new Exception("Failed to fetch MusicalDown homepage. HTTP Code: $httpCode, Error: $curlError");
            }
            
            $header = substr($response, 0, $headerSize);
            $html = substr($response, $headerSize);
            
            if (empty($html) || strlen($html) < 100) {
                throw new Exception("Empty or invalid HTML response from MusicalDown");
            }
            
            preg_match_all('/Set-Cookie:\s*([^;]+)/i', $header, $matches);
            $cookies = isset($matches[1]) ? implode('; ', $matches[1]) : '';
            
            $this->log("Cookies extracted", ['cookies' => $cookies ?: 'none']);
            
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $loadResult = @$dom->loadHTML($html);
            libxml_clear_errors();
            
            if (!$loadResult) {
                throw new Exception("Failed to parse HTML with DOMDocument");
            }
            
            $xpath = new DOMXPath($dom);
            
            $payload = [];
            $inputs = $xpath->query('//form[@id="submit-form"]//input');
            
            $this->log("Form inputs found", ['count' => $inputs->length]);
            
            if ($inputs->length === 0) {
                $inputs = $xpath->query('//form//input[@name]');
                $this->log("Alternative form inputs found", ['count' => $inputs->length]);
            }
            
            foreach ($inputs as $input) {
                $name = $input->getAttribute('name');
                $value = $input->getAttribute('value');
                if ($name) {
                    $payload[$name] = $value ?: '';
                }
            }
            
            $this->log("Form payload extracted", ['payload' => $payload]);
            
            if (empty($payload)) {
                throw new Exception("No form inputs found. MusicalDown structure may have changed.");
            }
            
            $urlInserted = false;
            foreach ($payload as $key => $value) {
                if (empty($value)) {
                    $payload[$key] = $url;
                    $urlInserted = true;
                    $this->log("URL inserted into field", ['field' => $key]);
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
                $this->log("URL forced into payload");
            }
            
            $postData = http_build_query($payload);
            
            $this->log("Step 2: Submitting form", ['post_data' => $postData]);
            
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
                    'User-Agent: ' . $userAgent,
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
                ]
            ]);
            
            $data = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            $this->log("Step 2 response", [
                'http_code' => $httpCode,
                'response_length' => strlen($data),
                'curl_error' => $curlError ?: 'none'
            ]);
            
            if (!$data || $httpCode != 200) {
                throw new Exception("Failed to submit form. HTTP Code: $httpCode, Error: $curlError");
            }
            
            if (empty($data) || strlen($data) < 100) {
                throw new Exception("Empty or invalid response after form submission");
            }
            
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $loadResult = @$dom->loadHTML($data);
            libxml_clear_errors();
            
            if (!$loadResult) {
                throw new Exception("Failed to parse response HTML");
            }
            
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
            
            // Determine content type
            $hasVideo = false;
            $hasSlideshow = false;
            $videoHd = null;
            $videoNowm = null;
            $audio = null;
            
            foreach ($downloads as $download) {
                if ($download['type'] === 'video' || $download['type'] === 'hd') {
                    $hasVideo = true;
                }
                if ($download['type'] === 'slide') {
                    $hasSlideshow = true;
                }
                
                if ($download['type'] === 'hd') {
                    $videoHd = $download['url'];
                } elseif ($download['type'] === 'video') {
                    $videoNowm = $download['url'];
                } elseif ($download['type'] === 'music') {
                    $audio = $download['url'];
                }
            }
            
            if (count($images) > 0) {
                $hasSlideshow = true;
            }
            
            // Build download data
            $downloadData = [
                'type' => $hasSlideshow ? 'image' : 'video',
                'thumbnail' => $cover
            ];
            
            if ($hasSlideshow) {
                $downloadData['images'] = $images;
            } else {
                $downloadData['download'] = $videoHd ?: $videoNowm;
                if ($videoHd) $downloadData['video_hd'] = $videoHd;
                if ($videoNowm) $downloadData['video_nowm'] = $videoNowm;
            }
            
            if ($audio) {
                $downloadData['audio'] = $audio;
            }
            
            // Build unified response dengan metadata
            return $this->buildUnifiedResponse($downloadData, $this->sharedMetadata);
            
        } catch (Exception $error) {
            $this->log("MusicalDown exception", ['error' => $error->getMessage()]);
            throw new Exception($error->getMessage());
        }
    }
    
    // Method untuk test semua API sekaligus
    private function testAllApiMethods($url, $startTime) {
        $this->log("=== TESTING ALL APIs ===");
        
        $results = [
            'test_mode' => true,
            'url' => $url,
            'metadata' => null,
            'apis' => []
        ];
        
        // Test Metadata
        $metaStart = microtime(true);
        try {
            $metadata = $this->getTikTokMetadata($url);
            $metaDuration = round((microtime(true) - $metaStart) * 1000, 2);
            
            $results['metadata'] = [
                'success' => !empty($metadata),
                'duration_ms' => $metaDuration,
                'provider' => $metadata['provider'] ?? 'failed',
                'data' => $metadata
            ];
            
            // Store for use by API methods
            $this->sharedMetadata = $metadata;
        } catch (Exception $e) {
            $results['metadata'] = [
                'success' => false,
                'duration_ms' => round((microtime(true) - $metaStart) * 1000, 2),
                'error' => $e->getMessage()
            ];
        }
        
        // Test MusicalDown
        $mdStart = microtime(true);
        try {
            $mdResult = $this->ttdown($url);
            $mdDuration = round((microtime(true) - $mdStart) * 1000, 2);
            
            $results['apis']['musicaldown'] = [
                'success' => true,
                'duration_ms' => $mdDuration,
                'data' => $mdResult
            ];
        } catch (Exception $e) {
            $results['apis']['musicaldown'] = [
                'success' => false,
                'duration_ms' => round((microtime(true) - $mdStart) * 1000, 2),
                'error' => $e->getMessage()
            ];
        }
        
        // Test SaveTik
        $stStart = microtime(true);
        try {
            $stResult = $this->getVideoFromSaveTik($url);
            $stDuration = round((microtime(true) - $stStart) * 1000, 2);
            
            $results['apis']['savetik'] = [
                'success' => $stResult['success'],
                'duration_ms' => $stDuration,
                'data' => $stResult['success'] ? $stResult['data'] : null,
                'error' => $stResult['error'] ?? null
            ];
        } catch (Exception $e) {
            $results['apis']['savetik'] = [
                'success' => false,
                'duration_ms' => round((microtime(true) - $stStart) * 1000, 2),
                'error' => $e->getMessage()
            ];
        }
        
        // Summary
        $totalDuration = round((microtime(true) - $startTime) * 1000, 2);
        $results['summary'] = [
            'total_duration_ms' => $totalDuration,
            'metadata_status' => $results['metadata']['success'] ? 'working' : 'failed',
            'musicaldown_status' => $results['apis']['musicaldown']['success'] ? 'working' : 'failed',
            'savetik_status' => $results['apis']['savetik']['success'] ? 'working' : 'failed',
            'working_apis_count' => 
                ($results['apis']['musicaldown']['success'] ? 1 : 0) + 
                ($results['apis']['savetik']['success'] ? 1 : 0)
        ];
        
        if ($this->debugMode) {
            $results['debug_logs'] = $this->debugLogs;
        }
        
        return $results;
    }
    
    public function download($url) {
        $startTime = microtime(true);
        
        try {
            $this->log("=== Download started ===", ['url' => $url]);
            
            // STEP 1: Get metadata dari TikTok (digunakan oleh semua method)
            $this->sharedMetadata = $this->getTikTokMetadata($url);
            if ($this->sharedMetadata) {
                $this->log("Shared metadata retrieved for all methods", $this->sharedMetadata);
            }
            
            // NEW: Test All APIs Mode
            if ($this->testAllApis) {
                return $this->testAllApiMethods($url, $startTime);
            }
            
            // ORIGINAL: Normal fallback mode
            // STEP 2: Primary method - MusicalDown
            $methodStartTime = microtime(true);
            $result = $this->ttdown($url);
            $methodEndTime = microtime(true);
            $methodDuration = round(($methodEndTime - $methodStartTime) * 1000, 2);
            
            $response = [
                'success' => true,
                'method' => 'MusicalDown',
                'data' => $result
            ];
            
            if ($this->debugMode) {
                $endTime = microtime(true);
                $totalDuration = round(($endTime - $startTime) * 1000, 2);
                
                $response['debug'] = [
                    'performance' => [
                        'method_used' => 'MusicalDown (Primary)',
                        'duration_ms' => $totalDuration,
                        'method_duration_ms' => $methodDuration,
                        'metadata_source' => $this->sharedMetadata ? ($this->sharedMetadata['provider'] ?? 'unknown') : 'none',
                        'status' => 'SUCCESS'
                    ],
                    'api_status' => [
                        'musicaldown' => [
                            'status' => 'working',
                            'tested' => true,
                            'response_time_ms' => $methodDuration,
                            'success' => true
                        ],
                        'tiktok_metadata' => [
                            'status' => $this->sharedMetadata ? 'retrieved' : 'not_available',
                            'provider' => $this->sharedMetadata ? ($this->sharedMetadata['provider'] ?? 'unknown') : 'none'
                        ],
                        'savetik' => [
                            'status' => 'not_tested',
                            'tested' => false,
                            'note' => 'Primary method succeeded, backup not needed'
                        ]
                    ],
                    'logs' => $this->debugLogs
                ];
            }
            
            $this->log("=== Download successful ===");
            
            return $response;
            
        } catch (Exception $error) {
            $this->log("Primary method failed, trying backup", ['error' => $error->getMessage()]);
            
            // STEP 3: Backup method - SaveTik (menggunakan metadata yang sama)
            $backupStartTime = microtime(true);
            $saveTikResult = $this->getVideoFromSaveTik($url);
            $backupEndTime = microtime(true);
            $backupDuration = round(($backupEndTime - $backupStartTime) * 1000, 2);
            
            if ($saveTikResult['success']) {
                $response = [
                    'success' => true,
                    'method' => 'SaveTik (Backup)',
                    'data' => $saveTikResult['data']
                ];
                
                if ($this->debugMode) {
                    $endTime = microtime(true);
                    $totalDuration = round(($endTime - $startTime) * 1000, 2);
                    
                    $response['debug'] = [
                        'performance' => [
                            'method_used' => 'SaveTik (Backup)',
                            'duration_ms' => $totalDuration,
                            'method_duration_ms' => $backupDuration,
                            'metadata_source' => $this->sharedMetadata ? ($this->sharedMetadata['provider'] ?? 'unknown') : 'none',
                            'status' => 'SUCCESS_WITH_FALLBACK'
                        ],
                        'api_status' => [
                            'musicaldown' => [
                                'status' => 'failed',
                                'tested' => true,
                                'success' => false,
                                'error' => $error->getMessage()
                            ],
                            'tiktok_metadata' => [
                                'status' => $this->sharedMetadata ? 'retrieved' : 'not_available',
                                'provider' => $this->sharedMetadata ? ($this->sharedMetadata['provider'] ?? 'unknown') : 'none'
                            ],
                            'savetik' => [
                                'status' => 'working',
                                'tested' => true,
                                'response_time_ms' => $backupDuration,
                                'success' => true
                            ]
                        ],
                        'logs' => $this->debugLogs
                    ];
                }
                
                $this->log("=== Backup method successful ===");
                
                return $response;
            }
            
            $this->log("=== All methods failed ===");
            
            $endTime = microtime(true);
            $totalDuration = round(($endTime - $startTime) * 1000, 2);
            
            $response = [
                'success' => false,
                'message' => 'Semua metode gagal. TikTok mungkin telah mengubah struktur API atau URL tidak valid.',
                'error_details' => $error->getMessage()
            ];
            
            if ($this->debugMode) {
                $response['debug'] = [
                    'performance' => [
                        'method_used' => 'None (All Failed)',
                        'duration_ms' => $totalDuration,
                        'metadata_source' => $this->sharedMetadata ? ($this->sharedMetadata['provider'] ?? 'unknown') : 'none',
                        'status' => 'FAILED'
                    ],
                    'api_status' => [
                        'musicaldown' => [
                            'status' => 'failed',
                            'tested' => true,
                            'success' => false,
                            'error' => $error->getMessage()
                        ],
                        'tiktok_metadata' => [
                            'status' => $this->sharedMetadata ? 'retrieved' : 'failed',
                            'provider' => $this->sharedMetadata ? ($this->sharedMetadata['provider'] ?? 'unknown') : 'none'
                        ],
                        'savetik' => [
                            'status' => 'failed',
                            'tested' => true,
                            'success' => false,
                            'error' => isset($saveTikResult['error']) ? $saveTikResult['error'] : 'Unknown error'
                        ]
                    ],
                    'recommendations' => [
                        'Check if TikTok URL is valid and accessible',
                        'Both API providers may have changed their structure',
                        'Check network connectivity and firewall rules',
                        'Verify PHP extensions (cURL, OpenSSL, DOM) are enabled'
                    ],
                    'logs' => $this->debugLogs
                ];
            }
            
            return $response;
        }
    }
}

// API Handler
$debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';
$testMode = isset($_GET['test']) && $_GET['test'] == '1';
$testAllApis = isset($_GET['test_all']) && $_GET['test_all'] == '1';

// Test endpoint
if ($testMode && empty($_GET['url']) && empty($_GET['v']) && empty($_GET['id'])) {
    $testResults = [
        'server_info' => [
            'php_version' => PHP_VERSION,
            'server_time' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ],
        'extensions' => [
            'curl' => [
                'available' => function_exists('curl_init'),
                'version' => function_exists('curl_version') ? curl_version()['version'] : 'N/A'
            ],
            'openssl' => [
                'available' => extension_loaded('openssl'),
                'version' => OPENSSL_VERSION_TEXT ?? 'N/A'
            ],
            'dom' => [
                'available' => class_exists('DOMDocument')
            ],
            'json' => [
                'available' => function_exists('json_encode')
            ],
            'libxml' => [
                'available' => function_exists('libxml_use_internal_errors'),
                'version' => defined('LIBXML_DOTTED_VERSION') ? LIBXML_DOTTED_VERSION : 'N/A'
            ]
        ],
        'api_endpoints' => [
            'tiktok_metadata' => [
                'name' => 'TikTok Direct Metadata',
                'priority' => 'Shared (Used by all methods)',
                'features' => ['Author Info', 'Stats', 'Hashtags', 'Music', 'Duration', 'Timestamps']
            ],
            'musicaldown' => [
                'name' => 'MusicalDown',
                'url' => 'https://musicaldown.com',
                'method' => 'HTML Parsing',
                'priority' => 'Primary',
                'features' => ['Video HD', 'No Watermark', 'Slideshow', 'Audio', 'Enhanced Metadata']
            ],
            'savetik' => [
                'name' => 'SaveTik',
                'url' => 'https://savetik.app',
                'method' => 'Encrypted API',
                'priority' => 'Backup',
                'features' => ['Video', 'Audio', 'Enhanced Metadata']
            ]
        ],
        'connectivity_test' => []
    ];
    
    $testUrls = [
        'tiktok' => 'https://www.tiktok.com',
        'musicaldown' => 'https://musicaldown.com/en',
        'savetik' => 'https://savetik.app'
    ];
    
    foreach ($testUrls as $name => $url) {
        $startTime = microtime(true);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_NOBODY => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 (Linux; Android 15) AppleWebKit/537.36'
            ]
        ]);
        
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $endTime = microtime(true);
        $responseTime = round(($endTime - $startTime) * 1000, 2);
        curl_close($ch);
        
        $testResults['connectivity_test'][$name] = [
            'url' => $url,
            'http_code' => $httpCode,
            'response_time_ms' => $responseTime,
            'status' => ($httpCode >= 200 && $httpCode < 400) ? 'reachable' : 'unreachable',
            'error' => $curlError ?: null
        ];
    }
    
    $tiktokOk = $testResults['connectivity_test']['tiktok']['status'] === 'reachable';
    $musicaldownOk = $testResults['connectivity_test']['musicaldown']['status'] === 'reachable';
    $savetikOk = $testResults['connectivity_test']['savetik']['status'] === 'reachable';
    
    $testResults['overall_status'] = [
        'metadata_source' => $tiktokOk ? 'operational' : 'down',
        'primary_api' => $musicaldownOk ? 'operational' : 'down',
        'backup_api' => $savetikOk ? 'operational' : 'down',
        'service_status' => (($musicaldownOk || $savetikOk) && $tiktokOk) ? 'operational' : ($musicaldownOk || $savetikOk ? 'degraded' : 'all_down')
    ];
    
    echo json_encode($testResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$api = new TikTokDownloader($debugMode, $testAllApis);

$url = $_GET['url'] ?? $_GET['v'] ?? $_GET['id'] ?? $_POST['url'] ?? '';

if (empty($url)) {
    echo json_encode([
        'success' => false,
        'message' => 'Parameter URL diperlukan',
        'usage' => [
            'Normal Download' => '?url=TIKTOK_URL',
            'Debug Mode' => '?url=TIKTOK_URL&debug=1',
            'Test ALL APIs' => '?url=TIKTOK_URL&test_all=1',
            'Test ALL + Debug' => '?url=TIKTOK_URL&test_all=1&debug=1',
            'API Status Check' => '?test=1'
        ],
        'modes' => [
            'normal' => [
                'description' => 'Smart fallback system',
                'behavior' => 'Try MusicalDown first, fallback to SaveTik if fails',
                'use_case' => 'Production use - fastest response'
            ],
            'test_all' => [
                'description' => 'Test all APIs simultaneously',
                'behavior' => 'Both MusicalDown AND SaveTik are tested',
                'use_case' => 'Testing, debugging, API comparison',
                'parameter' => 'test_all=1'
            ],
            'debug' => [
                'description' => 'Detailed logging',
                'behavior' => 'Shows detailed logs and performance metrics',
                'use_case' => 'Troubleshooting',
                'parameter' => 'debug=1'
            ]
        ],
        'architecture' => [
            'unified_metadata' => 'All methods (MusicalDown & SaveTik) use the same TikTok metadata',
            'flow_normal' => [
                '1. Fetch metadata directly from TikTok (shared)',
                '2. Try MusicalDown for download URLs',
                '3. If fails, fallback to SaveTik',
                '4. Both methods merge their data with shared metadata'
            ],
            'flow_test_all' => [
                '1. Fetch metadata directly from TikTok',
                '2. Test MusicalDown (regardless of success/fail)',
                '3. Test SaveTik (regardless of success/fail)',
                '4. Return results from ALL APIs with comparison'
            ],
            'benefits' => [
                'Consistent data across all methods',
                'Rich metadata (stats, author, music, hashtags)',
                'Better reliability with shared source',
                'No duplicate metadata requests'
            ]
        ],
        'endpoints' => [
            'download' => [
                'description' => 'Download TikTok video or slideshow with unified metadata',
                'examples' => [
                    'https://yourdomain.com/tiktok.php?url=https://www.tiktok.com/@username/video/123',
                    'https://yourdomain.com/tiktok.php?v=https://vm.tiktok.com/xxxxx'
                ]
            ],
            'test_all' => [
                'description' => 'Test ALL APIs and compare results',
                'examples' => [
                    'https://yourdomain.com/tiktok.php?url=https://www.tiktok.com/@username/video/123&test_all=1'
                ]
            ],
            'debug' => [
                'description' => 'Download with detailed debug information',
                'examples' => [
                    'https://yourdomain.com/tiktok.php?url=https://www.tiktok.com/@username/video/123&debug=1'
                ]
            ],
            'test' => [
                'description' => 'Test API connectivity and server configuration',
                'examples' => [
                    'https://yourdomain.com/tiktok.php?test=1'
                ]
            ]
        ],
        'parameters' => [
            'url / v / id' => 'TikTok video URL (required)',
            'debug' => 'Enable debug mode (optional, value: 1)',
            'test_all' => 'Test all APIs simultaneously (optional, value: 1)',
            'test' => 'Test mode for API status check (optional, value: 1)'
        ],
        'response_data' => [
            'Always included (when available)' => [
                'Title/Caption',
                'Author (Username, Nickname, Avatar)',
                'Statistics (Likes, Comments, Shares, Views)',
                'Hashtags',
                'Music/Sound info',
                'Duration',
                'Creation timestamp',
                'Download URLs (HD, No Watermark, Audio)'
            ],
            'Content type specific' => [
                'Video: Download URLs, Quality info, Thumbnail',
                'Slideshow: Image URLs, Image count'
            ]
        ],
        'supported_formats' => [
            'Full TikTok URL (tiktok.com)',
            'Short URL (vm.tiktok.com, vt.tiktok.com)',
            'Video content',
            'Slideshow/Images content'
        ],
        'api_methods' => [
            'shared_metadata' => [
                'name' => 'TikTok Direct',
                'usage' => 'Used by all methods',
                'features' => ['Stats', 'Author Info', 'Hashtags', 'Music', 'Timestamps']
            ],
            'primary' => [
                'name' => 'MusicalDown',
                'features' => ['HD Video', 'No Watermark', 'Slideshow', 'Audio', 'Unified Metadata']
            ],
            'backup' => [
                'name' => 'SaveTik',
                'features' => ['Standard Video', 'Audio', 'Unified Metadata'],
                'note' => 'Auto-fallback if primary fails (normal mode), Always tested (test_all mode)'
            ]
        ],
        'features' => [
            '✅ Unified Metadata Across All Methods',
            '✅ Video Download (HD & No Watermark)',
            '✅ Slideshow/Images Download',
            '✅ Audio/MP3 Extract',
            '✅ Complete Statistics (Likes, Comments, Shares, Views)',
            '✅ Author Information with Avatar',
            '✅ Hashtags Extraction',
            '✅ Music/Sound Information',
            '✅ Video Duration & Quality Info',
            '✅ Creation Timestamps',
            '✅ Full Absolute URLs (Ready to Download)',
            '✅ Smart Auto Fallback (Normal Mode)',
            '✅ Multi-API Testing (test_all Mode)',
            '✅ Debug Mode with Detailed Logging',
            '✅ Performance Metrics & API Health Check'
        ],
        'quick_start' => [
            '1. Test API status' => '?test=1',
            '2. Try downloading' => '?url=YOUR_TIKTOK_URL',
            '3. Compare APIs' => '?url=YOUR_TIKTOK_URL&test_all=1',
            '4. Debug if fails' => '?url=YOUR_TIKTOK_URL&debug=1'
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$result = $api->download($url);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>