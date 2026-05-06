<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Play, Globe, CheckCircle2, XCircle, AlertCircle, RefreshCw, Eye, Code } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps<{
    sources: Array<{
        id: string;
        name: string;
        base_url: string;
        detection_method: string;
    }>;
    selectedSourceId: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/dashboard' },
            { title: 'Source Management', href: '/admin/sources' },
            { title: 'Scraper Tester', href: '/admin/sources/tester' },
        ],
    },
});

const form = ref({
    source_id: props.selectedSourceId || '',
    url: '',
});

const running = ref(false);
const testResult = ref<any>(null);
const viewMode = ref<'preview' | 'json'>('preview');

const selectedSource = computed(() => {
    return props.sources.find(s => s.id === form.value.source_id);
});

const runTest = async () => {
    if (!form.value.source_id || !form.value.url) return;
    
    running.value = true;
    testResult.value = null;
    
    try {
        const response = await axios.post('/admin/sources/tester/run', form.value);
        testResult.value = response.data;
    } catch (error: any) {
        testResult.value = {
            success: false,
            message: error.response?.data?.message || error.message || 'Scrape test failed',
        };
    } finally {
        running.value = false;
    }
};

onMounted(() => {
    if (props.selectedSourceId) {
        form.value.source_id = props.selectedSourceId;
    }
});
</script>

<template>
    <Head title="Scraper Tester" />

    <div class="p-6 max-w-5xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <Link href="/admin/sources" class="p-2 border rounded hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                    <ArrowLeft class="h-4 w-4" />
                </Link>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Scraper Tester</h1>
                    <p class="text-sm text-muted-foreground mt-1">Debug and verify scraping results for your sources</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Configuration Panel -->
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-card border rounded-xl p-5 shadow-sm space-y-4">
                    <h2 class="font-semibold flex items-center">
                        <Globe class="h-4 w-4 mr-2 text-primary" />
                        Test Configuration
                    </h2>
                    
                    <div class="space-y-4">
                        <div class="space-y-2">
                            <label class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Select Source</label>
                            <select v-model="form.source_id" class="w-full h-10 px-3 py-2 border rounded-md bg-transparent text-sm">
                                <option value="" disabled>Choose a source...</option>
                                <option v-for="source in sources" :key="source.id" :value="source.id">
                                    {{ source.name }}
                                </option>
                            </select>
                            <div v-if="selectedSource" class="p-2 bg-zinc-50 dark:bg-zinc-900 rounded border text-[10px] space-y-1">
                                <div class="flex justify-between">
                                    <span>Method:</span>
                                    <span class="font-mono uppercase">{{ selectedSource.detection_method }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Base:</span>
                                    <span class="truncate ml-2">{{ selectedSource.base_url }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Target Article URL</label>
                            <textarea 
                                v-model="form.url" 
                                class="w-full p-3 border rounded-md bg-transparent text-sm min-h-[100px]" 
                                placeholder="Paste a specific article link to test..."
                            ></textarea>
                        </div>

                        <button 
                            @click="runTest" 
                            :disabled="!form.source_id || !form.url || running"
                            class="w-full py-2.5 bg-zinc-900 text-white dark:bg-white dark:text-black rounded-md shadow-sm font-medium transition-all flex items-center justify-center space-x-2 disabled:opacity-50"
                        >
                            <RefreshCw v-if="running" class="h-4 w-4 animate-spin" />
                            <Play v-else class="h-4 w-4 fill-current" />
                            <span>{{ running ? 'Scraping...' : 'Run Scrape Test' }}</span>
                        </button>
                    </div>
                </div>

                <div class="p-4 bg-blue-50 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-800 rounded-xl">
                    <h3 class="text-sm font-semibold text-blue-900 dark:text-blue-300 flex items-center mb-2">
                        <AlertCircle class="h-4 w-4 mr-2" />
                        Testing Tip
                    </h3>
                    <p class="text-xs text-blue-800/80 dark:text-blue-400/80 leading-relaxed">
                        Use this tool to verify your CSS selectors or Readability settings before enabling a source. This test does not save any data to the database.
                    </p>
                </div>
            </div>

            <!-- Results Panel -->
            <div class="lg:col-span-2 space-y-6">
                <div v-if="!testResult && !running" class="h-full min-h-[400px] bg-zinc-50 dark:bg-zinc-900/30 border-2 border-dashed rounded-xl flex flex-col items-center justify-center text-muted-foreground p-12 text-center">
                    <div class="h-16 w-16 bg-zinc-100 dark:bg-zinc-800 rounded-full flex items-center justify-center mb-4">
                        <Play class="h-8 w-8 text-zinc-400" />
                    </div>
                    <h3 class="font-medium text-foreground">Ready to Test</h3>
                    <p class="text-sm max-w-xs mt-1">Configure the source and target URL on the left to see scraping results here.</p>
                </div>

                <div v-if="running" class="h-full min-h-[400px] bg-card border rounded-xl flex flex-col items-center justify-center p-12 text-center animate-pulse">
                    <RefreshCw class="h-12 w-12 text-primary animate-spin mb-4" />
                    <h3 class="font-medium">Extracting Content...</h3>
                    <p class="text-sm text-muted-foreground mt-1">Fetching HTML and applying selectors</p>
                </div>

                <div v-if="testResult" class="bg-card border rounded-xl shadow-sm overflow-hidden flex flex-col h-full animate-in fade-in duration-500">
                    <div class="p-4 border-b flex items-center justify-between bg-zinc-50/50 dark:bg-zinc-900/50">
                        <div class="flex items-center space-x-2">
                            <span :class="[
                                'px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider',
                                testResult.success ? 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300'
                            ]">
                                {{ testResult.success ? 'Success' : 'Failed' }}
                            </span>
                            <span class="text-xs text-muted-foreground">Method: {{ testResult.method }}</span>
                        </div>
                        <div class="flex border rounded-md overflow-hidden">
                            <button @click="viewMode = 'preview'" :class="['px-3 py-1.5 text-xs flex items-center transition-colors', viewMode === 'preview' ? 'bg-zinc-900 text-white dark:bg-white dark:text-black' : 'hover:bg-zinc-100 dark:hover:bg-zinc-800']">
                                <Eye class="h-3 w-3 mr-1.5" /> Preview
                            </button>
                            <button @click="viewMode = 'json'" :class="['px-3 py-1.5 text-xs flex items-center transition-colors', viewMode === 'json' ? 'bg-zinc-900 text-white dark:bg-white dark:text-black' : 'hover:bg-zinc-100 dark:hover:bg-zinc-800']">
                                <Code class="h-3 w-3 mr-1.5" /> Raw Data
                            </button>
                        </div>
                    </div>

                    <div class="flex-1 overflow-auto">
                        <!-- Preview Mode -->
                        <div v-if="viewMode === 'preview'" class="p-6 space-y-8">
                            <div v-if="!testResult.success" class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-red-800 dark:text-red-400 flex items-start space-x-3">
                                <XCircle class="h-5 w-5 shrink-0 mt-0.5" />
                                <div>
                                    <h4 class="font-bold">Extraction Error</h4>
                                    <p class="text-sm mt-1">{{ testResult.message }}</p>
                                    <ul v-if="testResult.errors?.length" class="mt-2 text-xs list-disc list-inside">
                                        <li v-for="(err, i) in testResult.errors" :key="i">{{ err }}</li>
                                    </ul>
                                </div>
                            </div>

                            <div v-if="testResult.results" class="space-y-8">
                                <!-- Title -->
                                <section>
                                    <div class="flex items-center space-x-2 mb-3">
                                        <h4 class="text-xs font-bold uppercase tracking-widest text-muted-foreground">Title</h4>
                                        <div class="h-px flex-1 bg-zinc-100 dark:bg-zinc-800"></div>
                                    </div>
                                    <h3 class="text-2xl font-bold leading-tight">{{ testResult.results.title.value || testResult.results.title.sample || '—' }}</h3>
                                    <div class="mt-2 flex items-center text-[10px] text-muted-foreground">
                                        <span v-if="testResult.results.title.count" class="mr-3">Matches: {{ testResult.results.title.count }}</span>
                                        <span v-if="testResult.results.title.status">Status: {{ testResult.results.title.status }}</span>
                                    </div>
                                </section>

                                <!-- Image -->
                                <section v-if="testResult.results.image?.found || testResult.results.image?.value">
                                    <div class="flex items-center space-x-2 mb-3">
                                        <h4 class="text-xs font-bold uppercase tracking-widest text-muted-foreground">Featured Image</h4>
                                        <div class="h-px flex-1 bg-zinc-100 dark:bg-zinc-800"></div>
                                    </div>
                                    <div class="rounded-xl border overflow-hidden bg-zinc-100 dark:bg-zinc-900 aspect-video flex items-center justify-center group relative">
                                        <img 
                                            v-if="testResult.results.image.value" 
                                            :src="testResult.results.image.value" 
                                            class="w-full h-full object-contain"
                                            alt="Scraped image" 
                                        />
                                        <div v-else class="text-xs text-muted-foreground italic">No image source found</div>
                                        <div class="absolute bottom-2 left-2 right-2 p-2 bg-black/60 backdrop-blur-md rounded text-[10px] text-white truncate opacity-0 group-hover:opacity-100 transition-opacity">
                                            {{ testResult.results.image.value || testResult.results.image.sample }}
                                        </div>
                                    </div>
                                </section>

                                <!-- Content -->
                                <section>
                                    <div class="flex items-center space-x-2 mb-3">
                                        <h4 class="text-xs font-bold uppercase tracking-widest text-muted-foreground">Content Sample</h4>
                                        <div class="h-px flex-1 bg-zinc-100 dark:bg-zinc-800"></div>
                                    </div>
                                    <div class="prose prose-sm dark:prose-invert max-w-none p-5 bg-zinc-50 dark:bg-zinc-900/50 rounded-xl border leading-relaxed text-zinc-600 dark:text-zinc-400 whitespace-pre-wrap">
                                        {{ testResult.results.content?.value || testResult.results.body?.sample || '—' }}
                                    </div>
                                    <div class="mt-2 flex items-center text-[10px] text-muted-foreground">
                                        <span v-if="testResult.results.content?.length || testResult.results.body?.count" class="mr-3">
                                            Length: {{ testResult.results.content?.length || testResult.results.body?.count }} chars
                                        </span>
                                    </div>
                                </section>
                            </div>
                        </div>

                        <!-- JSON Mode -->
                        <div v-else class="p-0 h-full">
                            <pre class="p-6 text-xs font-mono bg-zinc-950 text-green-400 overflow-auto h-full leading-relaxed">{{ JSON.stringify(testResult, null, 4) }}</pre>
                        </div>
                    </div>

                    <div v-if="testResult.success" class="p-4 bg-zinc-50 dark:bg-zinc-900/50 border-t flex justify-end">
                         <div class="flex items-center text-xs text-green-600 dark:text-green-400 font-medium">
                            <CheckCircle2 class="h-3 w-3 mr-1.5" /> Scraper ready for production
                         </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
