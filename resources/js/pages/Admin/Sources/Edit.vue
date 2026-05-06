<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, XCircle, RefreshCw, Save } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps<{
    source: {
        id: string;
        name: string;
        base_url: string;
        description: string | null;
        detection_method: string;
        selector_title: string | null;
        selector_body: string | null;
        selector_image: string | null;
        schedule_type: string;
        schedule_value: string;
        is_active: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/dashboard' },
            { title: 'Source Management', href: '/admin/sources' },
            { title: 'Edit Source', href: '/admin/sources' },
        ],
    },
});

const form = useForm({
    name: props.source.name,
    base_url: props.source.base_url,
    description: props.source.description || '',
    detection_method: props.source.detection_method || 'auto-detect',
    selector_title: props.source.selector_title || '',
    selector_body: props.source.selector_body || '',
    selector_image: props.source.selector_image || '',
    schedule_type: props.source.schedule_type,
    schedule_value: props.source.schedule_value,
    is_active: props.source.is_active,
});

const testing = ref(false);
const selectorResults = ref<any>(null);

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

const submit = () => {
    form.put(`/admin/sources/${props.source.id}`);
};
</script>

<template>
    <Head :title="`Edit ${source.name}`" />

    <div class="p-6 max-w-4xl mx-auto space-y-6">
        <div class="flex items-center space-x-4">
            <Link href="/admin/sources" class="p-2 border rounded hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                <ArrowLeft class="h-4 w-4" />
            </Link>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">Edit Source: {{ source.name }}</h1>
            </div>
        </div>

        <form @submit.prevent="submit" class="bg-card border rounded-xl p-6 shadow-sm space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="text-sm font-medium">Source Name</label>
                    <input v-model="form.name" class="w-full h-10 px-3 py-2 border rounded-md bg-transparent" required />
                    <div v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</div>
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium">Base URL</label>
                    <input v-model="form.base_url" type="url" class="w-full h-10 px-3 py-2 border rounded-md bg-transparent" required />
                    <div v-if="form.errors.base_url" class="text-sm text-destructive">{{ form.errors.base_url }}</div>
                </div>
                <div class="md:col-span-2 space-y-2">
                    <label class="text-sm font-medium">Description</label>
                    <textarea v-model="form.description" class="w-full p-3 border rounded-md bg-transparent min-h-[100px]"></textarea>
                </div>
            </div>

            <div class="pt-4 border-t space-y-4">
                <h3 class="text-lg font-semibold">Detection Method</h3>
                
                <div class="flex space-x-4">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="radio" v-model="form.detection_method" value="auto-detect" class="h-4 w-4 text-primary" />
                        <span>Auto-Detect (Readability)</span>
                    </label>
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="radio" v-model="form.detection_method" value="manual-selector" class="h-4 w-4 text-primary" />
                        <span>Manual CSS Selectors</span>
                    </label>
                </div>
            </div>

            <div v-if="form.detection_method === 'manual-selector'" class="grid grid-cols-1 md:grid-cols-2 gap-6 p-4 border rounded-lg bg-zinc-50/50 dark:bg-zinc-900/50">
                <div class="space-y-2">
                    <label class="text-sm font-medium">Title Selector *</label>
                    <input v-model="form.selector_title" class="w-full h-10 px-3 py-2 border rounded-md bg-transparent bg-white dark:bg-black" :required="form.detection_method === 'manual-selector'" />
                    <div v-if="form.errors.selector_title" class="text-sm text-destructive">{{ form.errors.selector_title }}</div>
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium">Body Selector *</label>
                    <input v-model="form.selector_body" class="w-full h-10 px-3 py-2 border rounded-md bg-transparent bg-white dark:bg-black" :required="form.detection_method === 'manual-selector'" />
                    <div v-if="form.errors.selector_body" class="text-sm text-destructive">{{ form.errors.selector_body }}</div>
                </div>
                <div class="space-y-2 md:col-span-2 flex items-end space-x-4">
                    <div class="flex-1 space-y-2">
                        <label class="text-sm font-medium">Image Selector (Optional)</label>
                        <input v-model="form.selector_image" class="w-full h-10 px-3 py-2 border rounded-md bg-transparent bg-white dark:bg-black" />
                    </div>
                    <button 
                        type="button"
                        @click="testManualSelectors" 
                        :disabled="!form.selector_title || !form.selector_body || testing"
                        class="px-4 py-2 h-10 border border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 font-medium rounded-md transition-colors flex items-center justify-center disabled:opacity-50"
                    >
                        <RefreshCw v-if="testing" class="h-4 w-4 animate-spin mr-2" />
                        <span>Test Selectors</span>
                    </button>
                </div>

                <!-- Test Results Inline -->
                <div v-if="selectorResults" class="md:col-span-2 pt-2">
                    <div :class="[
                        'p-3 rounded-md text-sm border flex items-start space-x-2',
                        selectorResults.success 
                            ? 'bg-green-50 dark:bg-green-950/30 border-green-200 dark:border-green-900 text-green-800 dark:text-green-300' 
                            : 'bg-red-50 dark:bg-red-950/30 border-red-200 dark:border-red-900 text-red-800 dark:text-red-300'
                    ]">
                        <CheckCircle2 v-if="selectorResults.success" class="h-4 w-4 shrink-0" />
                        <XCircle v-else class="h-4 w-4 shrink-0" />
                        <div>
                            <span class="font-medium">{{ selectorResults.message }}</span>
                            <ul v-if="selectorResults.errors?.length" class="mt-1 list-disc list-inside">
                                <li v-for="(error, i) in selectorResults.errors" :key="i">{{ error }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t">
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
                    <input v-model="form.schedule_value" class="w-full h-10 px-3 py-2 border rounded-md bg-transparent" required />
                </div>
            </div>

            <div class="flex items-center space-x-2 pt-4">
                <input type="checkbox" id="is_active" v-model="form.is_active" class="h-4 w-4 rounded border-gray-300" />
                <label for="is_active" class="text-sm font-medium">Source is active</label>
            </div>

            <div class="flex justify-end pt-6 border-t">
                <button 
                    type="submit" 
                    :disabled="form.processing"
                    class="px-6 py-2.5 bg-zinc-900 text-white dark:bg-white dark:text-black rounded-md shadow-sm font-medium transition-colors flex items-center space-x-2"
                >
                    <Save class="h-4 w-4" />
                    <span>{{ form.processing ? 'Updating...' : 'Update Source' }}</span>
                </button>
            </div>
            
            <div v-if="Object.keys(form.errors).length > 0" class="p-4 bg-red-100 text-red-800 rounded-lg">
                <div class="font-medium mb-2">Please fix the following errors:</div>
                <ul class="list-disc list-inside text-sm">
                    <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
                </ul>
            </div>
        </form>
    </div>
</template>
