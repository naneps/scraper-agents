# Hybrid Source Scraping Guide

## Table of Contents
1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Database Schema](#database-schema)
4. [Complete Flow](#complete-flow)
5. [Backend Implementation](#backend-implementation)
6. [Frontend Implementation](#frontend-implementation)
7. [API Endpoints](#api-endpoints)
8. [Error Handling](#error-handling)
9. [Testing Strategy](#testing-strategy)
10. [Integration with Main Scraper](#integration-with-main-scraper)

---

## Overview

**Hybrid Scraping Approach:**
- **Primary Method:** Auto-detect content using Readability library
- **Fallback Method:** Manual CSS selector input by admin
- **UX Goal:** Admin just input URL, system handles the rest

**Why Hybrid?**
- 80-85% of news sites work with auto-detect (zero admin effort)
- 15-20% require manual selectors (admin provides CSS selector)
- Best of both worlds: Smart + Flexible

---

## Architecture

### System Flow

```
┌─────────────────────────────────────────┐
│         Admin Add Source Flow            │
└────────────────┬────────────────────────┘
                 │
                 ▼
        ┌──────────────────┐
        │ Input URL        │
        │ (e.g., kompas..) │
        └────────┬─────────┘
                 │
                 ▼
     ┌───────────────────────┐
     │   Try Auto-Detect     │
     │ (Readability lib)     │
     └────────┬──────────────┘
              │
         ┌────┴─────┐
         │           │
      SUCCESS     FAILED
         │           │
         ▼           ▼
    ┌──────┐   ┌──────────────────┐
    │ Show │   │ Show Form:       │
    │ Demo │   │ CSS Selectors    │
    │ ✓    │   │ - Title          │
    └──────┘   │ - Body           │
               │ - Image          │
               └────┬─────────────┘
                    │
                    ▼
          ┌──────────────────┐
          │ Test Selectors   │
          │ (Verify match)   │
          └────────┬─────────┘
                   │
              ┌────┴─────┐
              │           │
           VALID      INVALID
              │           │
              ▼           ▼
         ┌──────┐   ┌─────────┐
         │ Save │   │ Retry   │
         │ ✓    │   │ Adjust  │
         └──────┘   └─────────┘
                        │
                        └─→ Loop back to test
```

### Component Breakdown

| Component | Responsibility |
|-----------|-----------------|
| **Readability Library** | Auto-extract article title, content, image from HTML |
| **CSS Selector Parser** | Parse user-input CSS selectors and extract content |
| **Verification Service** | Test both methods, return results to admin |
| **Frontend Controller** | Manage UI flow (auto-detect → success/fallback) |
| **Source Repository** | Store source config with method + selectors |

---

## Database Schema

### `sources` Table

```sql
CREATE TABLE sources (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    base_url VARCHAR(255) NOT NULL,
    description TEXT,
    
    -- NEW: Track which method this source uses
    detection_method ENUM('auto-detect', 'manual-selector') DEFAULT 'auto-detect',
    
    -- Manual selectors (nullable if using auto-detect)
    selector_title VARCHAR(255),
    selector_body VARCHAR(255),
    selector_image VARCHAR(255),
    
    -- Scheduling
    schedule_type ENUM('interval', 'cron', 'once') DEFAULT 'interval',
    schedule_value VARCHAR(255) DEFAULT '60 minutes',
    
    -- Status tracking
    is_active BOOLEAN DEFAULT true,
    last_scraped_at TIMESTAMP,
    next_scrape_at TIMESTAMP,
    last_error_message TEXT,
    consecutive_failures INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_is_active (is_active),
    INDEX idx_next_scrape_at (next_scrape_at)
);
```

### `source_verification_logs` Table (Optional, for debugging)

```sql
CREATE TABLE source_verification_logs (
    id CHAR(36) PRIMARY KEY,
    source_id CHAR(36),
    
    -- What was tested
    test_url VARCHAR(255),
    detection_method VARCHAR(50),
    
    -- Results
    title_found BOOLEAN,
    title_sample TEXT,
    body_found BOOLEAN,
    body_sample TEXT,
    image_found BOOLEAN,
    image_sample VARCHAR(255),
    
    -- Errors
    has_errors BOOLEAN,
    errors JSON,
    
    -- Metadata
    verified_at TIMESTAMP,
    verified_by_user_id CHAR(36),
    
    FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE CASCADE,
    INDEX idx_source_id (source_id),
    INDEX idx_verified_at (verified_at)
);
```

---

## Complete Flow

### Step 1: Admin Initiates Source Creation

**Input:**
```
Name: "Kompas News"
Base URL: "https://kompas.com"
```

**Trigger:** Admin clicks "Add Source" → UI shows URL input field

---

### Step 2: Auto-Detect Attempt

**Input:**
```
URL: https://kompas.com/news/123
```

**Backend Process:**

1. **Fetch HTML**
   ```
   GET https://kompas.com/news/123
   Timeout: 10 seconds
   User-Agent: NewsBot/1.0
   ```

2. **Parse with Readability**
   ```
   Extract: Title, Content, Image, Author, etc
   ```

3. **Validate Content**
   ```
   - Title length > 0?
   - Content length > 200 chars?
   - At least one of these valid?
   ```

4. **Return Results**
   ```json
   {
     "success": true,
     "method": "auto-detect",
     "results": {
       "title": {
         "value": "Tesla Announce New EV Model",
         "confidence": 0.95
       },
       "content": {
         "value": "Lorem ipsum dolor sit amet...",
         "length": 1250
       },
       "image": {
         "value": "https://kompas.com/image/123.jpg",
         "found": true
       }
     }
   }
   ```

**Outcome:**
- ✓ **Success** → Show preview, admin confirm save
- ✗ **Failed** → Show error, offer manual selector option

---

### Step 3a: Auto-Detect Success - Preview & Confirm

**UI Shows:**
```
✓ Auto-Detected Successfully!

Title: Tesla Announce New EV Model

Content: Lorem ipsum dolor sit amet, consectetur...
(truncated, show first 150 chars)

Image: https://kompas.com/image/123.jpg

[Save Source ✓]  [Manual Override...]
```

**Admin Actions:**
- **Save Source** → Store with `detection_method='auto-detect'`
- **Manual Override** → Switch to manual selector form (see Step 3b)

---

### Step 3b: Auto-Detect Failed - Fallback to Manual

**Scenario:**
- Auto-detect failed (site structure weird, or content too short)
- System suggests manual CSS selector

**UI Shows:**
```
⚠️ Auto-Detect Failed

The system couldn't automatically detect content.
Please provide CSS selectors manually.

Title Selector:
[_________________________________]
Example: h1.headline, h1.title, h2.article-title

Body Selector:
[_________________________________]
Example: div.article-content, div.article-body

Image Selector:
[_________________________________]
Example: img.featured, img.article-image
(Optional - leave blank if no image)

[Test Selectors]
```

---

### Step 4: Test Manual Selectors

**Input:**
```
Title: h1.headline
Body: div.article-content
Image: img.featured
```

**Backend Process:**

1. **Fetch same URL again**

2. **Parse with each selector**
   ```
   Selector: h1.headline
   Result count: 3 matches
   Sample: "Tesla Announce New EV Model"
   ```

3. **Validate results**
   ```
   Title selector: ✓ Found 3 matches
   Body selector: ✓ Found 2 matches
   Image selector: ✓ Found 1 match
   
   Overall: ✓ Valid (title + body must have results)
   ```

4. **Return detailed results**
   ```json
   {
     "success": true,
     "method": "manual-selector",
     "results": {
       "title": {
         "count": 3,
         "sample": "Tesla Announce New EV Model",
         "status": "valid"
       },
       "body": {
         "count": 2,
         "sample": "Lorem ipsum dolor sit amet...",
         "status": "valid"
       },
       "image": {
         "count": 1,
         "sample": "https://kompas.com/image/123.jpg",
         "status": "valid"
       }
     },
     "message": "All selectors working! You can save."
   }
   ```

---

### Step 5: Save Source

**Data to Store:**
```sql
INSERT INTO sources (
    name,
    base_url,
    detection_method,
    selector_title,
    selector_body,
    selector_image,
    schedule_type,
    schedule_value,
    is_active
) VALUES (
    'Kompas News',
    'https://kompas.com',
    'manual-selector',           ← Method used
    'h1.headline',               ← Selector
    'div.article-content',       ← Selector
    'img.featured',              ← Selector
    'interval',
    '60 minutes',
    true
);
```

**Optional: Log verification**
```sql
INSERT INTO source_verification_logs (
    source_id,
    detection_method,
    title_found,
    title_sample,
    body_found,
    body_sample,
    image_found,
    image_sample,
    has_errors,
    verified_by_user_id
) VALUES (...);
```

**Response to Admin:**
```
✓ Source saved successfully!

Source: Kompas News
Method: Manual Selector
Title Selector: h1.headline
Body Selector: div.article-content
Image Selector: img.featured

Next scrape: 2024-05-06 15:00

[View Sources] [Add Another]
```

---

## Backend Implementation

### Prerequisites

**Install Readability Library:**
```bash
composer require fivefilters/readability.php
```

### Service: Auto-Detect Content

```php
// app/Services/ContentAutoDetectService.php

namespace App\Services;

use Readability\Readability;
use Readability\Parser;
use Exception;
use Log;

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
            if (strlen($content) < self::MIN_CONTENT_LENGTH) {
                return $this->error(
                    "Content too short (" . strlen($content) . " chars, min " . 
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
                        'value' => substr($content, 0, 200) . '...',
                        'length' => strlen($content),
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
            $readability = new Readability($html);
            $readability->init();
            
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
```

### Service: Manual Selector Verification

```php
// app/Services/SelectorVerificationService.php

namespace App\Services;

use Symfony\Component\DomCrawler\Crawler;
use Exception;
use Log;

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
            $imageResult = $this->testSelector($crawler, $selectors['image'] ?? null, optional: true);
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
```

### Controller: Source Verification

```php
// app/Http/Controllers/Admin/SourceController.php

namespace App\Http\Controllers\Admin;

use App\Models\Source;
use App\Services\ContentAutoDetectService;
use App\Services\SelectorVerificationService;
use App\Http\Requests\SourceRequest;
use Illuminate\Http\Request;

class SourceController extends Controller
{
    public function __construct(
        private ContentAutoDetectService $autoDetectService,
        private SelectorVerificationService $selectorService,
    ) {}
    
    /**
     * Verify source - try auto-detect first, then manual selectors
     */
    public function verify(Request $request)
    {
        $url = $request->input('url');
        
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid URL format',
            ], 400);
        }
        
        // Check if manual selectors provided
        if ($request->has('selector_title') || $request->has('selector_body')) {
            // User provided selectors, test them
            return $this->verifyManualSelectors($url, $request);
        }
        
        // Try auto-detect first
        $result = $this->autoDetectService->detect($url);
        
        return response()->json($result);
    }
    
    /**
     * Verify manual selectors
     */
    private function verifyManualSelectors(string $url, Request $request)
    {
        try {
            // Fetch HTML
            $client = new \GuzzleHttp\Client(['timeout' => 10]);
            $response = $client->request('GET', $url);
            $html = (string)$response->getBody();
            
            // Verify selectors
            $result = $this->selectorService->verify($html, [
                'title' => $request->input('selector_title'),
                'body' => $request->input('selector_body'),
                'image' => $request->input('selector_image'),
            ]);
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Verification failed: ' . $e->getMessage(),
            ], 400);
        }
    }
    
    /**
     * Store new source
     */
    public function store(SourceRequest $request)
    {
        $source = Source::create([
            'name' => $request->input('name'),
            'base_url' => $request->input('base_url'),
            'description' => $request->input('description'),
            
            'detection_method' => $request->input('detection_method', 'auto-detect'),
            'selector_title' => $request->input('selector_title'),
            'selector_body' => $request->input('selector_body'),
            'selector_image' => $request->input('selector_image'),
            
            'schedule_type' => $request->input('schedule_type', 'interval'),
            'schedule_value' => $request->input('schedule_value', '60 minutes'),
            'is_active' => true,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Source created successfully',
            'source' => $source,
        ], 201);
    }
    
    /**
     * List all sources
     */
    public function index()
    {
        $sources = Source::orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'data' => $sources,
        ]);
    }
}
```

---

## Frontend Implementation

### Component: Source Add/Edit Form

```vue
<!-- components/SourceForm.vue -->

<template>
  <div class="source-form">
    <!-- STEP 1: Basic Info -->
    <div class="step step-1">
      <h3>📰 Add News Source</h3>
      
      <div class="form-group">
        <label>Source Name</label>
        <input 
          v-model="form.name" 
          placeholder="e.g., Kompas News, BBC"
        >
      </div>
      
      <div class="form-group">
        <label>Website URL</label>
        <input 
          v-model="form.base_url" 
          type="url"
          placeholder="https://kompas.com"
        >
      </div>
      
      <div class="form-group">
        <label>Description (optional)</label>
        <textarea 
          v-model="form.description" 
          rows="3"
          placeholder="What is this source about?"
        ></textarea>
      </div>
      
      <button @click="tryAutoDetect" :disabled="!form.base_url || detecting">
        {{ detecting ? '🔄 Detecting...' : '🤖 Try Auto-Detect' }}
      </button>
    </div>
    
    <!-- STEP 2: Auto-Detect Results -->
    <div v-if="detectionResult" class="step step-2">
      
      <!-- Success -->
      <div v-if="detectionResult.success" class="detection-success">
        <div class="success-banner">
          ✓ Auto-Detected Successfully!
        </div>
        
        <div class="preview-box">
          <div class="preview-item">
            <strong>Title:</strong>
            <p>{{ detectionResult.results.title.value }}</p>
          </div>
          
          <div class="preview-item">
            <strong>Content:</strong>
            <p>{{ detectionResult.results.content.value }}</p>
          </div>
          
          <div v-if="detectionResult.results.image.found" class="preview-item">
            <strong>Image:</strong>
            <p class="image-url">{{ detectionResult.results.image.value }}</p>
          </div>
        </div>
        
        <div class="actions">
          <button @click="saveSource('auto-detect')" class="btn-primary">
            Save Source ✓
          </button>
          <button @click="showManualMode = true" class="btn-secondary">
            Adjust Manually
          </button>
        </div>
      </div>
      
      <!-- Failed - Offer Manual Fallback -->
      <div v-else class="detection-failed">
        <div class="warning-banner">
          ⚠️ Auto-Detect Failed
        </div>
        
        <p class="message">
          {{ detectionResult.message }}
        </p>
        
        <p class="hint">
          💡 No worries! You can manually provide CSS selectors below.
        </p>
        
        <button @click="showManualMode = true" class="btn-secondary">
          Use Manual Selectors
        </button>
      </div>
    </div>
    
    <!-- STEP 3: Manual Selectors (Fallback) -->
    <div v-if="showManualMode" class="step step-3">
      <h3>📝 Manual CSS Selectors</h3>
      
      <p class="instruction">
        Provide CSS selectors to extract content. 
        <a href="#selector-help" @click="showSelectorHelp = true">Need help?</a>
      </p>
      
      <div class="form-group">
        <label>Title Selector *</label>
        <input 
          v-model="form.selector_title" 
          placeholder="e.g., h1.headline, h1.title, .article-title"
        >
        <p class="help-text">CSS selector that matches article titles</p>
      </div>
      
      <div class="form-group">
        <label>Body Selector *</label>
        <input 
          v-model="form.selector_body" 
          placeholder="e.g., div.article-content, div.article-body, article"
        >
        <p class="help-text">CSS selector that matches article content</p>
      </div>
      
      <div class="form-group">
        <label>Image Selector (optional)</label>
        <input 
          v-model="form.selector_image" 
          placeholder="e.g., img.featured, img.article-image, .hero-image img"
        >
        <p class="help-text">CSS selector that matches featured image</p>
      </div>
      
      <button @click="testManualSelectors" :disabled="testing">
        {{ testing ? '🔄 Testing...' : '🧪 Test Selectors' }}
      </button>
      
      <!-- Selector Test Results -->
      <div v-if="selectorResults" class="test-results">
        
        <div :class="['status-banner', selectorResults.success ? 'success' : 'error']">
          {{ selectorResults.message }}
        </div>
        
        <!-- Errors -->
        <div v-if="selectorResults.errors.length > 0" class="errors-box">
          <h4>⚠️ Issues Found:</h4>
          <ul>
            <li v-for="error in selectorResults.errors" :key="error">
              {{ error }}
            </li>
          </ul>
          <p class="hint">Adjust selectors above and test again</p>
        </div>
        
        <!-- Results Table -->
        <table class="results-table">
          <thead>
            <tr>
              <th>Field</th>
              <th>Matches</th>
              <th>Sample</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr :class="{ valid: selectorResults.results.title.count > 0 }">
              <td>Title</td>
              <td>{{ selectorResults.results.title.count }}</td>
              <td class="sample">{{ selectorResults.results.title.sample || '—' }}</td>
              <td>
                <span 
                  :class="['badge', selectorResults.results.title.count > 0 ? 'success' : 'error']"
                >
                  {{ selectorResults.results.title.count > 0 ? '✓' : '✗' }}
                </span>
              </td>
            </tr>
            
            <tr :class="{ valid: selectorResults.results.body.count > 0 }">
              <td>Body</td>
              <td>{{ selectorResults.results.body.count }}</td>
              <td class="sample">{{ selectorResults.results.body.sample || '—' }}</td>
              <td>
                <span 
                  :class="['badge', selectorResults.results.body.count > 0 ? 'success' : 'error']"
                >
                  {{ selectorResults.results.body.count > 0 ? '✓' : '✗' }}
                </span>
              </td>
            </tr>
            
            <tr :class="{ valid: selectorResults.results.image.count > 0 }">
              <td>Image (opt.)</td>
              <td>{{ selectorResults.results.image.count }}</td>
              <td class="sample">{{ selectorResults.results.image.sample || '—' }}</td>
              <td>
                <span 
                  :class="['badge', selectorResults.results.image.count > 0 ? 'success' : 'warning']"
                >
                  {{ selectorResults.results.image.count > 0 ? '✓' : '○' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- Save Button -->
      <button 
        v-if="selectorResults?.success"
        @click="saveSource('manual-selector')" 
        class="btn-primary"
      >
        Save Source ✓
      </button>
    </div>
    
    <!-- Selector Help Modal -->
    <div v-if="showSelectorHelp" class="modal-overlay" @click="showSelectorHelp = false">
      <div class="modal" @click.stop>
        <h3>How to Find CSS Selectors?</h3>
        
        <div class="help-content">
          <h4>Method 1: Browser DevTools (Recommended)</h4>
          <ol>
            <li>Open article page in browser</li>
            <li>Right-click on article title → Inspect</li>
            <li>Find the HTML element containing the title</li>
            <li>Look for class name (e.g., <code>class="headline"</code>)</li>
            <li>Use that as selector: <code>h1.headline</code></li>
          </ol>
          
          <h4>Common Selectors Examples:</h4>
          <ul>
            <li><code>h1.title</code> — h1 element with class "title"</li>
            <li><code>h1#main-title</code> — h1 with id "main-title"</li>
            <li><code>div.article-content</code> — div with class "article-content"</li>
            <li><code>article</code> — article tag</li>
            <li><code>.post-body</code> — any element with class "post-body"</li>
          </ul>
        </div>
        
        <button @click="showSelectorHelp = false">Got it!</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const form = ref({
  name: '',
  base_url: '',
  description: '',
  selector_title: '',
  selector_body: '',
  selector_image: '',
})

const detecting = ref(false)
const testing = ref(false)
const detectionResult = ref(null)
const selectorResults = ref(null)
const showManualMode = ref(false)
const showSelectorHelp = ref(false)

const tryAutoDetect = async () => {
  detecting.value = true
  
  try {
    detectionResult.value = await $fetch('/api/admin/sources/verify', {
      method: 'POST',
      body: { url: form.value.base_url },
    })
    
    // If failed, auto-show manual mode
    if (!detectionResult.value.success) {
      showManualMode.value = true
    }
    
  } catch (error) {
    detectionResult.value = {
      success: false,
      message: error.message,
    }
    showManualMode.value = true
  } finally {
    detecting.value = false
  }
}

const testManualSelectors = async () => {
  testing.value = true
  
  try {
    selectorResults.value = await $fetch('/api/admin/sources/verify', {
      method: 'POST',
      body: {
        url: form.value.base_url,
        selector_title: form.value.selector_title,
        selector_body: form.value.selector_body,
        selector_image: form.value.selector_image,
      },
    })
  } catch (error) {
    selectorResults.value = {
      success: false,
      message: 'Test failed',
      errors: [error.message],
    }
  } finally {
    testing.value = false
  }
}

const saveSource = async (method) => {
  try {
    await $fetch('/api/admin/sources', {
      method: 'POST',
      body: {
        ...form.value,
        detection_method: method,
      },
    })
    
    // Success - navigate or show message
    await navigateTo('/admin/sources')
    
  } catch (error) {
    alert('Failed to save source: ' + error.message)
  }
}
</script>

<style scoped>
.source-form {
  max-width: 700px;
  margin: 0 auto;
}

.step {
  padding: 2rem;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  margin-bottom: 2rem;
  background: white;
}

.step h3 {
  margin-top: 0;
  margin-bottom: 1.5rem;
}

.form-group {
  margin-bottom: 1.5rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 600;
  font-size: 0.95rem;
}

.form-group input,
.form-group textarea {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-family: inherit;
  font-size: 0.95rem;
}

.form-group input:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #007bff;
  box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
}

.help-text {
  margin: 0.5rem 0 0;
  font-size: 0.85rem;
  color: #666;
}

button {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 6px;
  font-size: 0.95rem;
  cursor: pointer;
  transition: all 0.2s;
}

button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary {
  background: #007bff;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #0056b3;
}

.btn-secondary {
  background: #f0f0f0;
  color: #333;
}

.btn-secondary:hover:not(:disabled) {
  background: #e0e0e0;
}

/* Detection Results */
.detection-success,
.detection-failed {
  padding: 1.5rem;
  border-radius: 8px;
  margin-bottom: 1.5rem;
}

.success-banner,
.warning-banner {
  padding: 1rem;
  border-radius: 6px;
  margin-bottom: 1.5rem;
  font-weight: 600;
}

.success-banner {
  background: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}

.warning-banner {
  background: #fff3cd;
  color: #856404;
  border: 1px solid #ffeeba;
}

.detection-failed .message {
  margin: 1rem 0;
  color: #666;
}

.detection-failed .hint {
  font-size: 0.9rem;
  color: #999;
  font-style: italic;
}

.preview-box {
  background: #f9f9f9;
  padding: 1.5rem;
  border-radius: 6px;
  margin-bottom: 1.5rem;
}

.preview-item {
  margin-bottom: 1rem;
}

.preview-item:last-child {
  margin-bottom: 0;
}

.preview-item strong {
  display: block;
  margin-bottom: 0.5rem;
  color: #333;
}

.preview-item p {
  margin: 0;
  color: #666;
  line-height: 1.5;
}

.image-url {
  font-family: monospace;
  font-size: 0.85rem;
  word-break: break-all;
}

.actions {
  display: flex;
  gap: 1rem;
}

/* Test Results */
.test-results {
  margin-top: 2rem;
  padding: 1.5rem;
  background: #f9f9f9;
  border-radius: 6px;
}

.status-banner {
  padding: 1rem;
  border-radius: 6px;
  margin-bottom: 1.5rem;
  font-weight: 600;
}

.status-banner.success {
  background: #d4edda;
  color: #155724;
}

.status-banner.error {
  background: #f8d7da;
  color: #721c24;
}

.errors-box {
  background: #fffbea;
  padding: 1rem;
  border-left: 3px solid #ff9800;
  border-radius: 4px;
  margin-bottom: 1.5rem;
}

.errors-box h4 {
  margin: 0 0 0.5rem;
  color: #ff6f00;
}

.errors-box ul {
  margin: 0;
  padding-left: 1.5rem;
}

.errors-box li {
  margin: 0.5rem 0;
  color: #e65100;
}

.errors-box .hint {
  margin-top: 0.75rem;
  font-size: 0.85rem;
  color: #999;
}

.results-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 1.5rem;
}

.results-table th {
  background: #f0f0f0;
  padding: 0.75rem;
  text-align: left;
  font-weight: 600;
  border-bottom: 2px solid #ddd;
}

.results-table td {
  padding: 0.75rem;
  border-bottom: 1px solid #eee;
}

.results-table tr.valid {
  background: #f0f9ff;
}

.sample {
  font-family: monospace;
  font-size: 0.8rem;
  color: #666;
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.badge {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  border-radius: 3px;
  font-weight: 600;
  font-size: 0.75rem;
  text-align: center;
  min-width: 30px;
}

.badge.success {
  background: #c8e6c9;
  color: #2e7d32;
}

.badge.error {
  background: #ffcdd2;
  color: #c62828;
}

.badge.warning {
  background: #fff9c4;
  color: #f57f17;
}

/* Modal */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal {
  background: white;
  padding: 2rem;
  border-radius: 8px;
  max-width: 600px;
  max-height: 80vh;
  overflow-y: auto;
}

.modal h3 {
  margin-top: 0;
}

.help-content h4 {
  margin-top: 1.5rem;
  margin-bottom: 0.75rem;
}

.help-content ol,
.help-content ul {
  margin: 0.75rem 0;
  padding-left: 1.5rem;
}

.help-content li {
  margin: 0.5rem 0;
  line-height: 1.6;
}

.help-content code {
  background: #f5f5f5;
  padding: 0.2rem 0.4rem;
  border-radius: 3px;
  font-family: monospace;
  font-size: 0.9rem;
  color: #d63384;
}
</style>
```

---

## API Endpoints

### POST /api/admin/sources/verify

**Purpose:** Verify source with auto-detect or manual selectors

**Request - Auto-Detect:**
```json
{
  "url": "https://kompas.com/news/123"
}
```

**Request - Manual Selector:**
```json
{
  "url": "https://kompas.com/news/123",
  "selector_title": "h1.headline",
  "selector_body": "div.article-content",
  "selector_image": "img.featured"
}
```

**Response - Auto-Detect Success:**
```json
{
  "success": true,
  "method": "auto-detect",
  "results": {
    "title": {
      "value": "Tesla Announce New EV Model",
      "confidence": 0.95,
      "length": 32
    },
    "content": {
      "value": "Lorem ipsum dolor sit amet...",
      "length": 1250
    },
    "image": {
      "value": "https://kompas.com/image/123.jpg",
      "found": true
    }
  },
  "message": "Content auto-detected successfully!"
}
```

**Response - Auto-Detect Failed:**
```json
{
  "success": false,
  "method": "manual-selector",
  "need_selectors": true,
  "message": "Auto-detect failed: Content too short..."
}
```

**Response - Selector Test Success:**
```json
{
  "success": true,
  "method": "manual-selector",
  "results": {
    "title": {
      "count": 3,
      "sample": "Tesla Announce New EV Model",
      "status": "valid"
    },
    "body": {
      "count": 2,
      "sample": "Lorem ipsum dolor sit amet...",
      "status": "valid"
    },
    "image": {
      "count": 1,
      "sample": "https://kompas.com/image/123.jpg",
      "status": "valid"
    }
  },
  "message": "All selectors working! You can save."
}
```

**Response - Selector Test Failed:**
```json
{
  "success": false,
  "method": "manual-selector",
  "results": {
    "title": {
      "count": 0,
      "sample": null,
      "status": "no-match"
    },
    "body": {
      "count": 0,
      "sample": null,
      "status": "no-match"
    },
    "image": {
      "count": 0,
      "sample": null,
      "status": "no-match"
    }
  },
  "errors": [
    "Title selector returned 0 results. Check selector syntax.",
    "Body selector returned 0 results."
  ],
  "message": "Some selectors failed. Check errors below."
}
```

---

### POST /api/admin/sources

**Purpose:** Create new source

**Request:**
```json
{
  "name": "Kompas News",
  "base_url": "https://kompas.com",
  "description": "Indonesian news source",
  "detection_method": "auto-detect",
  "selector_title": null,
  "selector_body": null,
  "selector_image": null,
  "schedule_type": "interval",
  "schedule_value": "60 minutes"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Source created successfully",
  "source": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "Kompas News",
    "base_url": "https://kompas.com",
    "detection_method": "auto-detect",
    "is_active": true,
    "created_at": "2024-05-06T12:00:00Z"
  }
}
```

---

## Error Handling

### Error Scenarios

| Scenario | Status | Response |
|----------|--------|----------|
| Invalid URL format | 400 | `{ error: "Invalid URL format" }` |
| Website unreachable | 400 | `{ error: "Cannot fetch website" }` |
| Content too short | 400 | `{ error: "Content too short (50 chars, min 200 required)" }` |
| Selector syntax invalid | 400 | `{ error: "Invalid CSS selector syntax: ..." }` |
| No results from selector | 400 | `{ error: "Title selector returned 0 results" }` |
| Network timeout | 500 | `{ error: "Request timeout after 10 seconds" }` |
| Server error | 500 | `{ error: "Internal server error" }` |

---

## Testing Strategy

### Unit Tests

```php
// tests/Unit/ContentAutoDetectServiceTest.php

public function test_auto_detect_valid_article()
{
    $html = file_get_contents('fixtures/kompas-article.html');
    $result = $this->autoDetectService->detect($html);
    
    $this->assertTrue($result['success']);
    $this->assertEquals('auto-detect', $result['method']);
    $this->assertNotEmpty($result['results']['title']['value']);
    $this->assertGreaterThan(200, strlen($result['results']['content']['value']));
}

public function test_selector_verification_success()
{
    $html = file_get_contents('fixtures/kompas-article.html');
    $result = $this->selectorService->verify($html, [
        'title' => 'h1.headline',
        'body' => 'div.article-content',
        'image' => 'img.featured',
    ]);
    
    $this->assertTrue($result['success']);
    $this->assertGreaterThan(0, $result['results']['title']['count']);
}

public function test_selector_verification_failed()
{
    $html = file_get_contents('fixtures/kompas-article.html');
    $result = $this->selectorService->verify($html, [
        'title' => 'h1.nonexistent',
        'body' => 'div.nonexistent',
    ]);
    
    $this->assertFalse($result['success']);
    $this->assertNotEmpty($result['errors']);
}
```

### Integration Tests

```php
// tests/Feature/SourceVerificationTest.php

public function test_auto_detect_endpoint_success()
{
    $this->actingAs($admin)
        ->postJson('/api/admin/sources/verify', [
            'url' => 'https://kompas.com/article',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('method', 'auto-detect');
}

public function test_manual_selector_endpoint_success()
{
    $this->actingAs($admin)
        ->postJson('/api/admin/sources/verify', [
            'url' => 'https://kompas.com/article',
            'selector_title' => 'h1.headline',
            'selector_body' => 'div.article-content',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('method', 'manual-selector');
}
```

### Manual Testing Checklist

- [ ] Auto-detect works for news article
- [ ] Auto-detect shows correct title
- [ ] Auto-detect shows correct content
- [ ] Auto-detect shows correct image
- [ ] Manual selector form appears when auto-detect fails
- [ ] Manual selector test validates correctly
- [ ] Error messages are clear and helpful
- [ ] Save button disabled until verification success
- [ ] Source saved to database with correct method
- [ ] Manual selectors stored for later scraping

---

## Integration with Main Scraper

### How Scraper Uses Detection Method

```php
// app/Jobs/ScrapeNewsJob.php

public function handle()
{
    $sources = Source::where('is_active', true)->get();
    
    foreach ($sources as $source) {
        try {
            $html = $this->fetchHtml($source->base_url);
            
            // Check detection method
            if ($source->detection_method === 'auto-detect') {
                $articles = $this->extractWithReadability($html);
            } else {
                $articles = $this->extractWithSelectors($html, [
                    'title' => $source->selector_title,
                    'body' => $source->selector_body,
                    'image' => $source->selector_image,
                ]);
            }
            
            foreach ($articles as $article) {
                // Process article...
            }
            
        } catch (\Exception $e) {
            $source->update([
                'last_error_message' => $e->getMessage(),
                'consecutive_failures' => $source->consecutive_failures + 1,
            ]);
        }
    }
}

private function extractWithReadability(string $html)
{
    $readability = new Readability($html);
    $readability->init();
    
    return [[
        'title' => $readability->getTitle(),
        'body' => $readability->getContent(),
        'image' => $readability->getImage(),
    ]];
}

private function extractWithSelectors(string $html, array $selectors)
{
    $crawler = new Crawler($html);
    
    return $crawler->filter($selectors['body'])->each(function($node) use ($selectors) {
        return [
            'title' => $node->filter($selectors['title'])->text() ?? 'Untitled',
            'body' => $node->text(),
            'image' => $node->filter($selectors['image'])->attr('src') ?? null,
        ];
    });
}
```

---

## Summary

**Hybrid Scraping Flow:**

1. **Admin Input** → URL
2. **Try Auto-Detect** → Readability library
   - If success → Show preview → Save
   - If failed → Offer manual fallback
3. **Manual Selectors** → CSS selectors
   - Admin provides selectors
   - System tests them
   - If valid → Save
   - If invalid → Show errors, loop
4. **Save Source** → Store with method + selectors
5. **During Scraping** → Use appropriate method
   - Auto-detect source → Use Readability
   - Manual source → Use CSS selectors

**Key Benefits:**
- ✓ Admin-friendly (most use auto-detect)
- ✓ Flexible (fallback for edge cases)
- ✓ Maintainable (no hardcoded selectors)
- ✓ Scalable (works for any news site)

---

*Version: 1.0*
*Last Updated: May 2024*
