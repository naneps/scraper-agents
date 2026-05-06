<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { PlusCircle, Edit, Trash2, Play } from 'lucide-vue-next';

interface Source {
    id: string;
    name: string;
    base_url: string;
    schedule_type: string;
    schedule_value: string;
    is_active: boolean;
    last_scraped_at: string | null;
}

defineProps<{
    sources: {
        data: Source[];
        links: any[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/dashboard' },
            { title: 'Source Management', href: '/admin/sources' },
        ],
    },
});

const deleteSource = (id: string) => {
    if (confirm('Are you sure you want to delete this source?')) {
        router.delete(`/admin/sources/${id}`);
    }
};
</script>

<template>
    <Head title="Source Management" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div class="flex justify-between items-center px-2">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">Sources</h1>
                <p class="text-muted-foreground text-sm mt-1">
                    Manage your news scraping sources and schedules.
                </p>
            </div>
            <Button as-child>
                <Link href="/admin/sources/create">
                    <PlusCircle class="mr-2 h-4 w-4" />
                    Add Source
                </Link>
            </Button>
        </div>

        <div class="border rounded-xl bg-card overflow-hidden">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Base URL</TableHead>
                        <TableHead>Schedule</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Last Scraped</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="source in sources.data" :key="source.id">
                        <TableCell class="font-medium">{{ source.name }}</TableCell>
                        <TableCell>
                            <a :href="source.base_url" target="_blank" class="text-blue-500 hover:underline">
                                {{ source.base_url }}
                            </a>
                        </TableCell>
                        <TableCell>
                            <span class="capitalize">{{ source.schedule_type }}</span> ({{ source.schedule_value }})
                        </TableCell>
                        <TableCell>
                            <Badge :variant="source.is_active ? 'default' : 'secondary'">
                                {{ source.is_active ? 'Active' : 'Inactive' }}
                            </Badge>
                        </TableCell>
                        <TableCell>
                            {{ source.last_scraped_at ? new Date(source.last_scraped_at).toLocaleString() : 'Never' }}
                        </TableCell>
                        <TableCell class="text-right space-x-2">
                            <Button variant="outline" size="icon" as-child title="Test Scrape">
                                <Link :href="`/admin/sources/tester?source_id=${source.id}`">
                                    <Play class="h-4 w-4 fill-current" />
                                </Link>
                            </Button>
                            <Button variant="outline" size="icon" as-child>
                                <Link :href="`/admin/sources/${source.id}/edit`">
                                    <Edit class="h-4 w-4" />
                                </Link>
                            </Button>
                            <Button variant="destructive" size="icon" @click="deleteSource(source.id)">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="sources.data.length === 0">
                        <TableCell colspan="6" class="text-center h-24 text-muted-foreground">
                            No sources found.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>

