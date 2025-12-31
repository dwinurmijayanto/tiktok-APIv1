<?php
/**
 * TikTok Metadata API
 * Endpoint: metadata.php?url=TIKTOK_URL
 * 
 * Get TikTok video metadata (stats, author, hashtags, music, etc.)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

class TikTokMetadata {
    
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
    
    public function getMetadata($url) {
        try {
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
                    $metadata = [
                        'title' => $data['title'] ?? null,
                        'author_name' => $data['author_name'] ?? null,
                        'author_url' => $data['author_url'] ?? null,
                        'thumbnail' => $data['thumbnail_url'] ?? null,
                        'provider' => 'oembed'
                    ];
                    
                    if (isset($data['author_unique_id'])) {
                        $metadata['username'] = $data['author_unique_id'];
                    } elseif ($metadata['author_url']) {
                        if (preg_match('/@([^\/\?]+)/', $metadata['author_url'], $match)) {
                            $metadata['username'] = $match[1];
                        }
                    }
                    
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
            
            // Method 2: Scrape TikTok page directly
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
                // Extract __UNIVERSAL_DATA_FOR_REHYDRATION__
                if (preg_match('/<script[^>]*id="__UNIVERSAL_DATA_FOR_REHYDRATION__"[^>]*>(.*?)<\/script>/s', $html, $match)) {
                    $jsonData = json_decode($match[1], true);
                    if ($jsonData) {
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
                            
                            if (isset($detail['challenges']) && is_array($detail['challenges'])) {
                                $metadata['hashtags'] = array_map(function($tag) {
                                    return $tag['title'] ?? null;
                                }, $detail['challenges']);
                                $metadata['hashtags'] = array_filter($metadata['hashtags']);
                            }
                            
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
            throw new Exception("Failed to fetch metadata: " . $e->getMessage());
        }
    }
}

// API Handler
$url = $_GET['url'] ?? $_GET['v'] ?? $_GET['id'] ?? $_POST['url'] ?? '';

if (empty($url)) {
    echo json_encode([
        'success' => false,
        'message' => 'URL parameter required',
        'endpoint' => 'metadata.php',
        'usage' => 'metadata.php?url=TIKTOK_URL',
        'description' => 'Get TikTok video metadata only',
        'features' => [
            'Title/Caption',
            'Author info (username, nickname, avatar)',
            'Statistics (likes, comments, shares, views)',
            'Hashtags',
            'Music/Sound information',
            'Duration',
            'Creation timestamp',
            'Thumbnail/Cover'
        ],
        'examples' => [
            'metadata.php?url=https://www.tiktok.com/@user/video/123',
            'metadata.php?url=https://vm.tiktok.com/xxxxx'
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $api = new TikTokMetadata();
    $metadata = $api->getMetadata($url);
    
    if ($metadata) {
        echo json_encode([
            'success' => true,
            'method' => 'TikTok Metadata',
            'data' => $metadata
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch metadata'
        ], JSON_PRETTY_PRINT);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>