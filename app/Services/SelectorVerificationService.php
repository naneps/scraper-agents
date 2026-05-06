<?php

namespace App\Services;

use Symfony\Component\DomCrawler\Crawler;
use Exception;
use Illuminate\Support\Facades\Log;

class SelectorVerificationService
{
    /**
     * Test CSS selectors against HTML
     */
    public function verify(string $html, array $selectors): array
    {
        $results = [];
        $errors = [];
        
        try {
            $crawler = new Crawler($html);
            
            // Test title selector
            $titleResult = $this->testSelector($crawler, $selectors['title'] ?? null);
            $results['title'] = $titleResult['result'];
            if (!$titleResult['valid']) {
                $errors[] = "Title selector: " . $titleResult['error'];
            }
            
            // Test body selector
            $bodyResult = $this->testSelector($crawler, $selectors['body'] ?? null);
            $results['body'] = $bodyResult['result'];
            if (!$bodyResult['valid']) {
                $errors[] = "Body selector: " . $bodyResult['error'];
            }
            
            // Test image selector (optional)
            $imageResult = $this->testSelector($crawler, $selectors['image'] ?? null, true);
            $results['image'] = $imageResult['result'];
            if (!$imageResult['valid'] && !$imageResult['optional']) {
                $errors[] = "Image selector: " . $imageResult['error'];
            }
            
            // Overall validation: at least title + body must work
            $isValid = $results['title']['count'] > 0 && $results['body']['count'] > 0;
            
            return [
                'success' => $isValid,
                'method' => 'manual-selector',
                'results' => $results,
                'errors' => $errors,
                'message' => $isValid
                    ? 'All selectors working! You can save this source.'
                    : 'Some selectors failed. Check errors below.',
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'method' => 'manual-selector',
                'errors' => [$e->getMessage()],
                'message' => 'Verification failed',
            ];
        }
    }
    
    /**
     * Test single selector
     */
    private function testSelector(Crawler $crawler, ?string $selector, bool $optional = false): array
    {
        if (empty($selector)) {
            return [
                'valid' => $optional,
                'optional' => $optional,
                'error' => 'Selector empty',
                'result' => [
                    'count' => 0,
                    'sample' => null,
                    'status' => 'empty',
                ],
            ];
        }
        
        try {
            $nodes = $crawler->filter($selector);
            $count = $nodes->count();
            
            if ($count === 0) {
                return [
                    'valid' => $optional, // Optional fields can be empty
                    'optional' => $optional,
                    'error' => "No matches found (0 results)",
                    'result' => [
                        'count' => 0,
                        'sample' => null,
                        'status' => 'no-match',
                    ],
                ];
            }
            
            $sample = $nodes->first()->text();
            
            // For images, we want the src attribute
            if (str_contains(strtolower($selector), 'img')) {
                $sample = $nodes->first()->attr('src') ?? 'Image matched, but no src attribute found.';
            }
            
            return [
                'valid' => true,
                'optional' => $optional,
                'error' => null,
                'result' => [
                    'count' => $count,
                    'sample' => substr($sample, 0, 150),
                    'status' => 'valid',
                ],
            ];
            
        } catch (\InvalidArgumentException $e) {
            return [
                'valid' => false,
                'optional' => $optional,
                'error' => "Invalid CSS selector syntax: {$e->getMessage()}",
                'result' => [
                    'count' => 0,
                    'sample' => null,
                    'status' => 'syntax-error',
                ],
            ];
        }
    }
}
