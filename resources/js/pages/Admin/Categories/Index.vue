<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search, MoreHorizontal, Edit, Trash2, FolderOpen } from 'lucide-vue-next';

defineProps<{
    categories: {
        data: Array<{
            id: string;
            name: string;
            slug: string;
            description: string | null;
            is_active: boolean;
            created_at: string;
        }>;
        links: any;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/dashboard' },
            { title: 'Categories', href: '/admin/categories' },
        ],
    },
});

const deleteCategory = (id: string) => {
    if (confirm('Are you sure you want to delete this category?')) {
        router.delete(`/admin/categories/${id}`);
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};
</script>

<template>
    <Head title="Category Management" />

    <div class="p-6 space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">News Categories</h1>
                <p class="text-sm text-muted-foreground mt-1">Organize your scraped news into manageable categories</p>
            </div>
            <Link href="/admin/categories/create" class="inline-flex items-center justify-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700 dark:bg-white dark:text-black dark:hover:bg-zinc-200 transition-colors shadow-sm">
                <Plus class="h-4 w-4 mr-2" />
                Add Category
            </Link>
        </div>

        <div class="bg-card border rounded-xl shadow-sm overflow-hidden">
            <div class="p-4 border-b flex items-center justify-between bg-zinc-50/50 dark:bg-zinc-900/50">
                <div class="relative w-72">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <input type="text" placeholder="Search categories..." class="w-full pl-9 pr-4 py-2 text-sm bg-transparent border rounded-md focus:outline-none focus:ring-1 focus:ring-primary" />
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-muted-foreground uppercase bg-zinc-50/50 dark:bg-zinc-900/50 border-b">
                        <tr>
                            <th class="px-6 py-4 font-medium">Category Name</th>
                            <th class="px-6 py-4 font-medium">Slug</th>
                            <th class="px-6 py-4 font-medium">Description</th>
                            <th class="px-6 py-4 font-medium text-center">Status</th>
                            <th class="px-6 py-4 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="category in categories.data" :key="category.id" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-900/50 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="h-9 w-9 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center mr-3 shadow-sm border border-blue-200 dark:border-blue-800">
                                        <FolderOpen class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <span class="font-semibold text-foreground">{{ category.name }}</span>
                                        <div class="text-xs text-muted-foreground mt-0.5">Created {{ formatDate(category.created_at) }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <code class="px-2 py-1 bg-zinc-100 dark:bg-zinc-800 rounded text-xs font-mono text-zinc-600 dark:text-zinc-400">{{ category.slug }}</code>
                            </td>
                            <td class="px-6 py-4 max-w-xs">
                                <p class="text-muted-foreground line-clamp-1 italic">{{ category.description || 'No description provided' }}</p>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span v-if="category.is_active" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    Active
                                </span>
                                <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-400">
                                    Inactive
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end space-x-2">
                                    <Link :href="`/admin/categories/${category.id}/edit`" class="p-2 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-md text-zinc-600 dark:text-zinc-400 transition-colors" title="Edit">
                                        <Edit class="h-4 w-4" />
                                    </Link>
                                    <button @click="deleteCategory(category.id)" class="p-2 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md text-red-600 transition-colors" title="Delete">
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="categories.data.length === 0">
                            <td colspan="5" class="px-6 py-12 text-center text-muted-foreground italic">
                                No categories found. Click "Add Category" to create your first one.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination could go here -->
        </div>
    </div>
</template>
