<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';
import { Sparkles, ArrowRight } from 'lucide-vue-next';

const page = usePage();
const name = page.props.name;

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0 bg-background overflow-hidden"
    >
        <div
            class="relative hidden h-full flex-col bg-slate-950 text-white lg:flex justify-between p-12 overflow-hidden"
        >
            <!-- Premium Animated Gradient Background -->
            <div class="absolute inset-0 bg-gradient-to-br from-indigo-900/40 via-slate-900 to-slate-950 z-0"></div>
            
            <!-- Abstract decorative elements -->
            <div class="absolute top-0 right-0 -mt-20 -mr-20 w-96 h-96 bg-blue-600/20 rounded-full blur-[100px] pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 -mb-20 -ml-20 w-80 h-80 bg-indigo-600/20 rounded-full blur-[100px] pointer-events-none"></div>

            <div class="relative z-20 flex items-center gap-2 text-xl font-bold tracking-tight">
                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 shadow-lg shadow-blue-500/20">
                    <AppLogoIcon class="size-5 text-white" />
                </div>
                <span class="bg-clip-text text-transparent bg-gradient-to-r from-white to-white/70">
                    {{ name }}
                </span>
            </div>
            
            <div class="relative z-20 mt-auto">
                <div class="inline-flex items-center gap-2 px-3 py-1 mb-6 rounded-full bg-white/10 border border-white/10 backdrop-blur-md text-sm font-medium text-blue-200">
                    <Sparkles class="size-4" />
                    <span>AI-Powered News Aggregator</span>
                </div>
                <h2 class="text-4xl sm:text-5xl font-bold tracking-tight mb-4 leading-tight">
                    Stay ahead with <br/>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-indigo-400">intelligent insights.</span>
                </h2>
                <p class="text-lg text-slate-400 max-w-md font-medium leading-relaxed">
                    Automated scraping, multi-language AI summaries, and real-time news categorization at your fingertips.
                </p>
                
                <div class="mt-10 flex gap-4">
                    <div class="flex items-center justify-center size-12 rounded-full border border-white/10 bg-white/5 backdrop-blur-md">
                        <AppLogoIcon class="size-5 text-slate-300" />
                    </div>
                    <div class="flex items-center justify-center size-12 rounded-full border border-white/10 bg-white/5 backdrop-blur-md">
                        <Sparkles class="size-5 text-blue-400" />
                    </div>
                </div>
            </div>
        </div>
        
        <div class="lg:p-8 flex items-center justify-center relative z-10 w-full h-full bg-background/50 backdrop-blur-xl lg:bg-transparent">
            <!-- Mobile Logo -->
            <div class="absolute top-8 left-8 lg:hidden flex items-center gap-2 font-bold text-lg">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600">
                    <AppLogoIcon class="size-4 text-white" />
                </div>
                {{ name }}
            </div>
            
            <div
                class="mx-auto flex w-full flex-col justify-center space-y-8 sm:w-[400px] p-6 sm:p-8 rounded-2xl bg-card border border-border/50 shadow-2xl shadow-indigo-500/5 lg:border-transparent lg:shadow-none lg:bg-transparent lg:p-0"
            >
                <div class="flex flex-col space-y-2 text-center lg:text-left">
                    <h1 class="text-3xl font-bold tracking-tight text-foreground" v-if="title">
                        {{ title }}
                    </h1>
                    <p class="text-base text-muted-foreground mt-1" v-if="description">
                        {{ description }}
                    </p>
                </div>
                <div class="bg-card/50 lg:bg-transparent rounded-xl">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
