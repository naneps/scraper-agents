<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Source;
use App\Services\ContentAutoDetectService;
use App\Services\SelectorVerificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SourceController extends Controller
{
    public function __construct(
        private ContentAutoDetectService $autoDetectService,
        private SelectorVerificationService $selectorService,
    ) {}

    /**
     * Show the scraper tester page.
     */
    public function tester(Request $request)
    {
        $sources = Source::orderBy('name')->get();
        $selectedSourceId = $request->query('source_id');
        
        return Inertia::render('Admin/Sources/ScraperTester', [
            'sources' => $sources,
            'selectedSourceId' => $selectedSourceId,
        ]);
    }

    /**
     * Run a test scrape.
     */
    public function runTester(Request $request)
    {
        $request->validate([
            'source_id' => 'required|exists:sources,id',
            'url' => 'required|url',
        ]);

        $source = Source::findOrFail($request->input('source_id'));
        $url = $request->input('url');

        if ($source->detection_method === 'auto-detect') {
            $result = $this->autoDetectService->detect($url);
        } else {
            // Fetch HTML for manual selector test
            try {
                $client = new \GuzzleHttp\Client(['timeout' => 10, 'verify' => false]);
                $response = $client->request('GET', $url, [
                    'headers' => [
                        'User-Agent' => 'NewsBot/1.0 (+https://your-site.com/bot)',
                    ],
                ]);
                $html = (string)$response->getBody();

                $result = $this->selectorService->verify($html, [
                    'title' => $source->selector_title,
                    'body' => $source->selector_body,
                    'image' => $source->selector_image,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch URL: ' . $e->getMessage(),
                ], 400);
            }
        }

        return response()->json($result);
    }

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
        if ($request->has('selector_title') && $request->input('selector_title') !== null) {
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
            $client = new \GuzzleHttp\Client(['timeout' => 10, 'verify' => false]);
            $response = $client->request('GET', $url, [
                'headers' => [
                    'User-Agent' => 'NewsBot/1.0 (+https://your-site.com/bot)',
                ],
            ]);
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
     * Display a listing of the resource.
     */
    public function index()
    {
        $sources = Source::latest()->paginate(10);
        return Inertia::render('Admin/Sources/Index', [
            'sources' => $sources,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Admin/Sources/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'base_url' => 'required|url|max:255',
            'description' => 'nullable|string',
            'detection_method' => 'required|in:auto-detect,manual-selector',
            'selector_title' => 'nullable|string|max:255',
            'selector_body' => 'nullable|string|max:255',
            'selector_image' => 'nullable|string|max:255',
            'schedule_type' => 'required|in:interval,cron,once',
            'schedule_value' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        Source::create($validated);

        return redirect()->route('admin.sources.index')->with('success', 'Source created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Source $source)
    {
        return Inertia::render('Admin/Sources/Edit', [
            'source' => $source,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Source $source)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'base_url' => 'required|url|max:255',
            'description' => 'nullable|string',
            'detection_method' => 'required|in:auto-detect,manual-selector',
            'selector_title' => 'nullable|string|max:255',
            'selector_body' => 'nullable|string|max:255',
            'selector_image' => 'nullable|string|max:255',
            'schedule_type' => 'required|in:interval,cron,once',
            'schedule_value' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $source->update($validated);

        return redirect()->route('admin.sources.index')->with('success', 'Source updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Source $source)
    {
        $source->delete();

        return redirect()->route('admin.sources.index')->with('success', 'Source deleted successfully.');
    }
}
