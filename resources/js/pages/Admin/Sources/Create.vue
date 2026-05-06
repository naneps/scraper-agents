<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, XCircle, AlertCircle, RefreshCw, HelpCircle } from 'lucide-vue-next';
import axios from 'axios';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/dashboard' },
            { title: 'Source Management', href: '/admin/sources' },
            { title: 'Create Source', href: '/admin/sources/create' },
        ],
    },
});

const form = useForm({
    name: '',
    base_url: '',
    description: '',
    selector_title: '',
    selector_body: '',
    selector_image: '',
    schedule_type: 'interval',
    schedule_value: '60',
    is_active: true,
    detection_method: 'auto-detect',
});

const detecting = ref(false);
const testing = ref(false);
const detectionResult = ref<any>(null);
const selectorResults = ref<any>(null);
const showManualMode = ref(false);
const showSelectorHelp = ref(false);

const tryAutoDetect = async () => {
    if (!form.base_url) return;
    
    detecting.value = true;
    detectionResult.value = null;
    
    try {
        const response = await axios.post('/admin/sources/verify', {
            url: form.base_url
        });
        
        detectionResult.value = response.data;
        
        if (!detectionResult.value.success) {
            showManualMode.value = true;
        }
    } catch (error: any) {
        detectionResult.value = {
            success: false,
            message: error.response?.data?.message || error.message || 'Detection failed',
        };
        showManualMode.value = true;
    } finally {
        detecting.value = false;
    }
};

const testManualSelectors = async () => {
    if (!form.base_url || !form.selector_title || !form.selector_body) return;
    
    testing.value = true;
    selectorResults.value = null;
    
    try {
        const response = await axios.post('/admin/sources/verify', {
            url: form.base_url,
            selector_title: form.selector_title,
            selector_body: form.selector_body,
            selector_image: form.selector_image,
        });
        
        selectorResults.value = response.data;
    } catch (error: any) {
        selectorResults.value = {
            success: false,
            message: 'Test failed',
            errors: [error.response?.data?.message || error.message || 'Unknown error'],
        };
    } finally {
        testing.value = false;
    }
};

const saveSource = (method: string) => {
    form.detection_method = method;
    form.post('/admin/sources');
};
</script>

<template>
    <Head title="Create Source" />

    <div class="p-6 max-w-4xl mx-auto space-y-6">
        <div class="flex items-center space-x-4">
            <Link href="/admin/sources" class="p-2 border rounded hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                <ArrowLeft class="h-4 w-4" />
            </Link>
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Add News Source</h1>
                <p class="text-sm text-muted-foreground mt-1">Configure automated content scraping</p>
            </div>
        </div>

        <!-- STEP 1: Basic Info -->
        <div class="bg-card border rounded-xl p-6 shadow-sm space-y-6">
            <div class="flex items-center space-x-2 pb-4 border-b">
                <div class="flex items-center justify-center h-8 w-8 rounded-full bg-primary text-primary-foreground font-bold">1</div>
                <h2 class="text-xl font-semibold">Basic Information</h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="text-sm font-medium">Source Name</label>
                    <input v-model="form.name" class="w-full h-10 px-3 py-2 border rounded-md bg-transparent" placeholder="e.g., Kompas News" />
                    <div v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</div>
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium">Website URL</label>
                    <input v-model="form.base_url" type="url" class="w-full h-10 px-3 py-2 border rounded-md bg-transparent" placeholder="https://kompas.com" />
                    <div v-if="form.errors.base_url" class="text-sm text-destructive">{{ form.errors.base_url }}</div>
                </div>
                <div class="md:col-span-2 space-y-2">
                    <label class="text-sm font-medium">Description (optional)</label>
                    <textarea v-model="form.description" class="w-full p-3 border rounded-md bg-transparent min-h-[100px]" placeholder="What is this source about?"></textarea>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4">
                <div class="space-y-2">
                    <label class="text-sm font-medium">Schedule Type</label>
                    <select v-model="form.schedule_type" class="flex h-10 w-full rounded-md border bg-transparent px-3 py-2 text-sm">
                        <option value="interval" class="bg-card text-foreground">Interval (Minutes)</option>
                        <option value="cron" class="bg-card text-foreground">Cron Expression</option>
                        <option value="once" class="bg-card text-foreground">Once (Manual)</option>
                    </select>
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium">Schedule Value</label>
                    <input v-model="form.schedule_value" class="w-full h-10 px-3 py-2 border rounded-md bg-transparent" placeholder="e.g., 60" />
                </div>
            </div>

            <div class="pt-4 flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <input type="checkbox" id="is_active" v-model="form.is_active" class="h-4 w-4 rounded border-gray-300" />
                    <label for="is_active" class="text-sm font-medium">Activate this source immediately</label>
                </div>
                <button 
                    @click="tryAutoDetect" 
                    :disabled="!form.base_url || detecting"
                    class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md shadow-sm transition-colors flex items-center space-x-2 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <RefreshCw v-if="detecting" class="h-4 w-4 animate-spin" />
                    <span>{{ detecting ? 'Detecting Content...' : 'Try Auto-Detect' }}</span>
                </button>
            </div>
        </div>

        <!-- STEP 2: Auto-Detect Results -->
        <div v-if="detectionResult" class="bg-card border rounded-xl p-6 shadow-sm space-y-6 animate-in fade-in slide-in-from-bottom-4">
            <div class="flex items-center space-x-2 pb-4 border-b">
                <div class="flex items-center justify-center h-8 w-8 rounded-full bg-primary text-primary-foreground font-bold">2</div>
                <h2 class="text-xl font-semibold">Detection Results</h2>
            </div>

            <!-- Success -->
            <div v-if="detectionResult.success" class="space-y-6">
                <div class="p-4 bg-green-50 dark:bg-green-950/30 border border-green-200 dark:border-green-900 rounded-lg flex items-start space-x-3 text-green-800 dark:text-green-300">
                    <CheckCircle2 class="h-5 w-5 shrink-0 mt-0.5" />
                    <div>
                        <h3 class="font-medium">Auto-Detected Successfully!</h3>
                        <p class="text-sm mt-1">We were able to automatically read the article structure from this website.</p>
                    </div>
                </div>

                <div class="grid gap-4 bg-zinc-50 dark:bg-zinc-900 rounded-lg p-5 border">
                    <div>
                        <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Extracted Title</h4>
                        <p class="font-medium">{{ detectionResult.results.title.value }}</p>
                    </div>
                    <div>
                        <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Extracted Content Sample</h4>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 line-clamp-3">{{ detectionResult.results.content.value }}</p>
                    </div>
                    <div v-if="detectionResult.results.image.found">
                        <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Extracted Image URL</h4>
                        <p class="text-sm text-blue-600 dark:text-blue-400 truncate">{{ detectionResult.results.image.value }}</p>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-2">
                    <button @click="showManualMode = true" class="px-4 py-2 border rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800 text-sm font-medium transition-colors">
                        Override with Manual Selectors
                    </button>
                    <button 
                        @click="saveSource('auto-detect')" 
                        :disabled="form.processing"
                        class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md shadow-sm font-medium transition-colors flex items-center space-x-2"
                    >
                        <CheckCircle2 class="h-4 w-4" />
                        <span>Save Auto-Detect Source</span>
                    </button>
                </div>
            </div>

            <!-- Failed -->
            <div v-else class="space-y-6">
                <div class="p-4 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900 rounded-lg flex items-start space-x-3 text-amber-800 dark:text-amber-300">
                    <AlertCircle class="h-5 w-5 shrink-0 mt-0.5" />
                    <div>
                        <h3 class="font-medium">Auto-Detect Failed</h3>
                        <p class="text-sm mt-1">{{ detectionResult.message }}</p>
                    </div>
                </div>
                
                <p class="text-sm text-muted-foreground">
                    Don't worry! This is common for sites with complex layouts. You can manually provide CSS selectors below to tell the scraper exactly where to look.
                </p>

                <div class="flex justify-start">
                    <button @click="showManualMode = true" class="px-4 py-2 bg-zinc-900 text-white dark:bg-white dark:text-black rounded-md shadow-sm font-medium transition-colors">
                        Use Manual Selectors
                    </button>
                </div>
            </div>
        </div>

        <!-- STEP 3: Manual Selectors -->
        <div v-if="showManualMode" class="bg-card border rounded-xl p-6 shadow-sm space-y-6 animate-in fade-in slide-in-from-bottom-4">
            <div class="flex items-center justify-between pb-4 border-b">
                <div class="flex items-center space-x-2">
                    <div class="flex items-center justify-center h-8 w-8 rounded-full bg-primary text-primary-foreground font-bold">3</div>
                    <h2 class="text-xl font-semibold">Manual CSS Selectors</h2>
                </div>
                <button @click="showSelectorHelp = true" class="text-sm text-blue-600 dark:text-blue-400 hover:underline flex items-center">
                    <HelpCircle class="h-4 w-4 mr-1" /> Help finding selectors
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="text-sm font-medium">Title Selector <span class="text-red-500">*</span></label>
                    <input v-model="form.selector_title" class="w-full h-10 px-3 py-2 border rounded-md bg-transparent" placeholder="e.g., h1.headline" />
                    <p class="text-xs text-muted-foreground">CSS selector matching the article title.</p>
                    <div v-if="form.errors.selector_title" class="text-sm text-destructive">{{ form.errors.selector_title }}</div>
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium">Body Selector <span class="text-red-500">*</span></label>
                    <input v-model="form.selector_body" class="w-full h-10 px-3 py-2 border rounded-md bg-transparent" placeholder="e.g., div.article-content" />
                    <p class="text-xs text-muted-foreground">CSS selector matching the article text.</p>
                    <div v-if="form.errors.selector_body" class="text-sm text-destructive">{{ form.errors.selector_body }}</div>
                </div>
                <div class="space-y-2 md:col-span-2">
                    <label class="text-sm font-medium">Image Selector (Optional)</label>
                    <input v-model="form.selector_image" class="w-full h-10 px-3 py-2 border rounded-md bg-transparent" placeholder="e.g., img.featured" />
                    <p class="text-xs text-muted-foreground">CSS selector matching the featured image.</p>
                    <div v-if="form.errors.selector_image" class="text-sm text-destructive">{{ form.errors.selector_image }}</div>
                </div>
            </div>

            <div class="pt-2 flex justify-start">
                <button 
                    @click="testManualSelectors" 
                    :disabled="!form.selector_title || !form.selector_body || testing"
                    class="px-6 py-2 border border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 font-medium rounded-md transition-colors flex items-center space-x-2 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <RefreshCw v-if="testing" class="h-4 w-4 animate-spin" />
                    <span>{{ testing ? 'Testing Selectors...' : 'Test Selectors' }}</span>
                </button>
            </div>

            <!-- Selector Test Results -->
            <div v-if="selectorResults" class="pt-6 border-t animate-in fade-in">
                <div :class="[
                    'p-4 rounded-lg mb-6 flex items-start space-x-3 border',
                    selectorResults.success 
                        ? 'bg-green-50 dark:bg-green-950/30 border-green-200 dark:border-green-900 text-green-800 dark:text-green-300' 
                        : 'bg-red-50 dark:bg-red-950/30 border-red-200 dark:border-red-900 text-red-800 dark:text-red-300'
                ]">
                    <CheckCircle2 v-if="selectorResults.success" class="h-5 w-5 shrink-0 mt-0.5" />
                    <XCircle v-else class="h-5 w-5 shrink-0 mt-0.5" />
                    <div>
                        <h3 class="font-medium">{{ selectorResults.message }}</h3>
                        
                        <ul v-if="selectorResults.errors && selectorResults.errors.length > 0" class="mt-2 text-sm list-disc list-inside">
                            <li v-for="(error, i) in selectorResults.errors" :key="i">{{ error }}</li>
                        </ul>
                    </div>
                </div>

                <div v-if="selectorResults.results" class="border rounded-md overflow-hidden">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-muted-foreground uppercase bg-zinc-50 dark:bg-zinc-900/50 border-b">
                            <tr>
                                <th class="px-4 py-3 font-medium">Field</th>
                                <th class="px-4 py-3 font-medium">Matches</th>
                                <th class="px-4 py-3 font-medium w-1/2">Sample Output</th>
                                <th class="px-4 py-3 font-medium text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr :class="{'bg-red-50/50 dark:bg-red-900/10': selectorResults.results.title.count === 0}">
                                <td class="px-4 py-3 font-medium">Title</td>
                                <td class="px-4 py-3">{{ selectorResults.results.title.count }}</td>
                                <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400 line-clamp-2" :title="selectorResults.results.title.sample">{{ selectorResults.results.title.sample || '—' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span v-if="selectorResults.results.title.count > 0" class="inline-flex px-2 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded">Valid</span>
                                    <span v-else class="inline-flex px-2 py-1 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 rounded">Invalid</span>
                                </td>
                            </tr>
                            <tr :class="{'bg-red-50/50 dark:bg-red-900/10': selectorResults.results.body.count === 0}">
                                <td class="px-4 py-3 font-medium">Body</td>
                                <td class="px-4 py-3">{{ selectorResults.results.body.count }}</td>
                                <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400 line-clamp-2" :title="selectorResults.results.body.sample">{{ selectorResults.results.body.sample || '—' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span v-if="selectorResults.results.body.count > 0" class="inline-flex px-2 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded">Valid</span>
                                    <span v-else class="inline-flex px-2 py-1 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 rounded">Invalid</span>
                                </td>
                            </tr>
                            <tr :class="{'bg-amber-50/50 dark:bg-amber-900/10': selectorResults.results.image && selectorResults.results.image.count === 0 && form.selector_image}">
                                <td class="px-4 py-3 font-medium">Image (Opt)</td>
                                <td class="px-4 py-3">{{ selectorResults.results.image ? selectorResults.results.image.count : 0 }}</td>
                                <td class="px-4 py-3 text-blue-500 truncate max-w-xs" :title="selectorResults.results.image ? selectorResults.results.image.sample : ''">{{ selectorResults.results.image ? selectorResults.results.image.sample : '—' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span v-if="selectorResults.results.image && selectorResults.results.image.count > 0" class="inline-flex px-2 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded">Valid</span>
                                    <span v-else-if="form.selector_image" class="inline-flex px-2 py-1 text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200 rounded">None</span>
                                    <span v-else class="inline-flex px-2 py-1 text-xs font-medium bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200 rounded">Skipped</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end pt-6">
                    <button 
                        v-if="selectorResults.success"
                        @click="saveSource('manual-selector')" 
                        :disabled="form.processing"
                        class="px-6 py-2.5 bg-zinc-900 text-white dark:bg-white dark:text-black rounded-md shadow-sm font-medium transition-colors flex items-center space-x-2"
                    >
                        <Save class="h-4 w-4" />
                        <span>{{ form.processing ? 'Saving...' : 'Save Manual Source' }}</span>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Global Form Errors -->
        <div v-if="Object.keys(form.errors).length > 0" class="p-4 bg-red-100 text-red-800 rounded-lg">
            <div class="font-medium mb-2">There were some errors with your submission:</div>
            <ul class="list-disc list-inside text-sm">
                <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
            </ul>
        </div>
    </div>

    <!-- Help Modal -->
    <div v-if="showSelectorHelp" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click="showSelectorHelp = false">
        <div class="bg-card w-full max-w-lg rounded-xl shadow-lg border p-6 space-y-4" @click.stop>
            <h3 class="text-xl font-bold">How to Find CSS Selectors</h3>
            
            <div class="space-y-4 text-sm">
                <div>
                    <h4 class="font-semibold text-primary mb-1">Method 1: Browser DevTools</h4>
                    <ol class="list-decimal list-inside space-y-1 text-muted-foreground ml-1">
                        <li>Open the target article in your browser.</li>
                        <li>Right-click on the article title and click <strong>Inspect</strong>.</li>
                        <li>Find the HTML element containing the title (e.g., <code>&lt;h1 class="entry-title"&gt;</code>).</li>
                        <li>Use that tag and class as your selector: <code>h1.entry-title</code>.</li>
                    </ol>
                </div>
                
                <div>
                    <h4 class="font-semibold text-primary mb-1">Common Selectors Examples</h4>
                    <ul class="list-disc list-inside space-y-1 text-muted-foreground ml-1">
                        <li><code>h1.title</code> — an h1 element with class "title"</li>
                        <li><code>#main-article</code> — an element with id "main-article"</li>
                        <li><code>div.content-body p</code> — paragraphs inside a content-body div</li>
                        <li><code>article</code> — any article tag</li>
                    </ul>
                </div>
            </div>
            
            <div class="pt-4 flex justify-end">
                <button @click="showSelectorHelp = false" class="px-4 py-2 bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 rounded text-sm font-medium">
                    Got it!
                </button>
            </div>
        </div>
    </div>
</template>
