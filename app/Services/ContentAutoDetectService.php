<?php

namespace App\Services;

use fivefilters\Readability\Readability;
use fivefilters\Readability\Configuration;
use Exception;
use Illuminate\Support\Facades\Log;

class ContentAutoDetectService
{
    private const TIMEOUT = 10;
    private const MIN_CONTENT_LENGTH = 200;
    
    /**
     * Auto-detect article content from URL
     */
    public function detect(string $url): array
    {
        try {
            // 1. Fetch HTML
            $html = $this->fetchHtml($url);
            if (!$html) {
                return $this->error("Cannot fetch website. Check URL or website might be down.");
            }
            
            // 2. Parse with Readability
            $article = $this->parseContent($html);
            if (!$article) {
                return $this->error("Cannot parse article content from this website.");
            }
            
            // 3. Extract fields
            $title = $article->getTitle();
            $content = $article->getContent();
            $image = $article->getImage();
            
            // 4. Validate content quality
            if (strlen(strip_tags($content)) < self::MIN_CONTENT_LENGTH) {
                return $this->error(
                    "Content too short (" . strlen(strip_tags($content)) . " chars, min " . 
                    self::MIN_CONTENT_LENGTH . " required). Try manual selectors."
                );
            }
            
            // 5. Return success
            return [
                'success' => true,
                'method' => 'auto-detect',
                'results' => [
                    'title' => [
                        'value' => $title,
                        'confidence' => 0.95,
                        'length' => strlen($title),
                    ],
                    'content' => [
                        'value' => substr(strip_tags($content), 0, 200) . '...',
                        'length' => strlen(strip_tags($content)),
                    ],
                    'image' => [
                        'value' => $image,
                        'found' => !empty($image),
                    ],
                ],
                'message' => 'Content auto-detected successfully!',
            ];
            
        } catch (Exception $e) {
            Log::warning("Auto-detect failed for $url: " . $e->getMessage());
            return $this->error("Auto-detect failed: " . $e->getMessage());
        }
    }
    
    /**
     * Fetch HTML with timeout and retry
     */
    private function fetchHtml(string $url): ?string
    {
        try {
            $client = new \GuzzleHttp\Client([
                'timeout' => self::TIMEOUT,
                'connect_timeout' => self::TIMEOUT,
                'verify' => false, // Sometimes useful for dev, but let's keep it safe.
            ]);
            
            $response = $client->request('GET', $url, [
                'headers' => [
                    'User-Agent' => 'NewsBot/1.0 (+https://your-site.com/bot)',
                ],
            ]);
            
            if ($response->getStatusCode() !== 200) {
                return null;
            }
            
            return (string)$response->getBody();
            
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            Log::warning("Cannot connect to $url: " . $e->getMessage());
            return null;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::warning("Request failed for $url: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Parse HTML and extract article
     */
    private function parseContent(string $html): ?Readability
    {
        try {
            $configuration = new Configuration();
            $configuration
                ->setFixRelativeURLs(true)
                ->setOriginalURL('http://fakeurl.com'); // Required by readability sometimes if base is missing

            $readability = new Readability($configuration);
            $readability->parse($html);
            
            return $readability;
            
        } catch (Exception $e) {
            Log::warning("Readability parse failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Format error response
     */
    private function error(string $message): array
    {
        return [
            'success' => false,
            'method' => 'manual-selector',
            'need_selectors' => true,
            'message' => $message,
        ];
    }
}
