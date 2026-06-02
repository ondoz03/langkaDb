<script setup lang="ts">
import { ref, watch } from 'vue'
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Performance settings',
                href: '/settings/performance',
            },
        ],
    },
});

const redisEnabled = ref(localStorage.getItem('aetherdb_redis_enabled') === 'true')

watch(redisEnabled, (val) => {
    localStorage.setItem('aetherdb_redis_enabled', val ? 'true' : 'false')
})
</script>

<template>
    <Head title="Performance settings" />

    <h1 class="sr-only">Performance settings</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Performance settings"
            description="Configure caching and queue drivers"
        />

        <div class="rounded-lg border border-border bg-card p-5">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs font-medium text-foreground">Redis Cache & Queue</span>
                    <p class="mt-0.5 text-[10px] text-muted-foreground">
                        Use Redis for queue jobs and schema caching. Falls back to database driver when disabled.
                    </p>
                </div>
                <label class="relative inline-flex cursor-pointer items-center">
                    <input
                        v-model="redisEnabled"
                        type="checkbox"
                        class="peer sr-only"
                    />
                    <div class="h-6 w-11 rounded-full border border-border bg-muted/40 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-border after:bg-card after:transition-all after:content-[''] peer-checked:bg-accent-brand/80 peer-checked:after:translate-x-full peer-checked:after:border-white" />
                </label>
            </div>
        </div>

        <div class="rounded-lg border border-border bg-card p-5 opacity-60">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs font-medium text-foreground">Schema Cache TTL</span>
                    <p class="mt-0.5 text-[10px] text-muted-foreground">
                        Available when Redis is enabled. Default: 5 minutes.
                    </p>
                </div>
                <span class="text-[10px] text-muted-foreground">5 min</span>
            </div>
        </div>

        <div class="rounded-lg border border-border bg-card p-5">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 text-xs text-muted-foreground">ⓘ</span>
                <div>
                    <p class="text-[10px] text-muted-foreground leading-relaxed">
                        <strong>Redis disabled:</strong> Uses database driver for queue and file/sqlite cache. No extra setup needed.<br />
                        <strong>Redis enabled:</strong> Requires Redis server running locally (default: <code class="text-accent-brand">127.0.0.1:6379</code>). Set up via <code class="text-accent-brand">docker compose -f docker/docker-compose.yml up -d</code>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
