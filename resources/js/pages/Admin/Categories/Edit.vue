<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save, Edit3 } from 'lucide-vue-next';

const props = defineProps<{
    category: {
        id: string;
        name: string;
        slug: string;
        description: string | null;
        is_active: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/dashboard' },
            { title: 'Categories', href: '/admin/categories' },
            { title: 'Edit Category', href: `/admin/categories/${props.category.id}/edit` },
        ],
    },
});

const form = useForm({
    name: props.category.name,
    slug: props.category.slug,
    description: props.category.description || '',
    is_active: props.category.is_active,
});

const submit = () => {
    form.put(`/admin/categories/${props.category.id}`);
};
</script>

<template>
    <Head :title="`Edit ${category.name}`" />

    <div class="p-6 max-w-2xl mx-auto space-y-6">
        <div class="flex items-center space-x-4">
            <Link href="/admin/categories" class="p-2 border rounded hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                <ArrowLeft class="h-4 w-4" />
            </Link>
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Edit Category</h1>
                <p class="text-sm text-muted-foreground mt-1">Update category details and availability</p>
            </div>
        </div>

        <form @submit.prevent="submit" class="bg-card border rounded-xl p-6 shadow-sm space-y-6">
            <div class="space-y-6">
                <div class="flex items-center space-x-3 pb-4 border-b">
                    <div class="h-10 w-10 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-600 flex items-center justify-center">
                        <Edit3 class="h-6 w-6" />
                    </div>
                    <h2 class="text-lg font-semibold">Update Details</h2>
                </div>

                <div class="space-y-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Category Name <span class="text-red-500">*</span></label>
                        <input v-model="form.name" class="w-full h-10 px-3 py-2 border rounded-md bg-transparent" required />
                        <div v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Slug</label>
                        <input v-model="form.slug" class="w-full h-10 px-3 py-2 border rounded-md bg-transparent" />
                        <p class="text-xs text-muted-foreground italic">Updating name will re-generate slug if empty</p>
                        <div v-if="form.errors.slug" class="text-sm text-destructive">{{ form.errors.slug }}</div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Description</label>
                        <textarea v-model="form.description" class="w-full p-3 border rounded-md bg-transparent min-h-[120px]"></textarea>
                        <div v-if="form.errors.description" class="text-sm text-destructive">{{ form.errors.description }}</div>
                    </div>

                    <div class="flex items-center space-x-2 pt-2">
                        <input type="checkbox" id="is_active" v-model="form.is_active" class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                        <label for="is_active" class="text-sm font-medium cursor-pointer">Category is active and visible</label>
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-6 border-t">
                <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-zinc-900 text-white dark:bg-white dark:text-black rounded-md shadow-sm font-medium transition-colors flex items-center space-x-2 disabled:opacity-50">
                    <Save class="h-4 w-4" />
                    <span>{{ form.processing ? 'Updating...' : 'Update Category' }}</span>
                </button>
            </div>
        </form>
    </div>
</template>
