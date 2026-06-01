<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Database, MessageSquareText, Search } from 'lucide-vue-next';
import { login } from '@/routes';
import { register } from '@/routes';

const dashboardUrl = '/dashboard';

const features = [
  {
    icon: Database,
    title: 'Database Visualizer',
    description: 'Interactive ERD graph with real-time schema exploration. Visualize tables, relationships, and indexes.',
  },
  {
    icon: MessageSquareText,
    title: 'AI Insights',
    description: 'Get intelligent recommendations on schema optimization, missing indexes, and performance bottlenecks.',
  },
  {
    icon: Search,
    title: 'Query Analyzer',
    description: 'Execute and analyze SQL queries with AI-powered explanations and optimization suggestions.',
  },
];
</script>

<template>
  <Head title="Welcome" />

  <div class="flex min-h-screen flex-col bg-background">
    <header class="flex items-center justify-between px-6 py-4 lg:px-12">
      <div class="flex items-center gap-2">
        <div class="flex h-8 w-8 items-center justify-center rounded-md bg-accent-brand text-accent-brand-foreground text-xs font-bold">
          A
        </div>
        <span class="text-sm font-medium text-foreground">AetherDB AI</span>
      </div>
      <nav class="flex items-center gap-3">
        <Link
          v-if="$page.props.auth.user"
          :href="dashboardUrl"
          class="inline-flex items-center rounded-lg border border-border bg-card px-4 py-2 text-sm font-medium text-foreground hover:bg-accent transition-colors"
        >
          Dashboard
        </Link>
        <template v-else>
          <Link
            :href="login()"
            class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
          >
            Log in
          </Link>
          <Link
            :href="register()"
            class="inline-flex items-center rounded-lg bg-accent-brand px-4 py-2 text-sm font-medium text-accent-brand-foreground hover:opacity-90 transition-opacity"
          >
            Register
          </Link>
        </template>
      </nav>
    </header>

    <main class="flex flex-1 flex-col items-center justify-center px-6 py-16 lg:px-12">
      <div class="mx-auto max-w-3xl text-center">
        <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-border bg-card px-4 py-1.5">
          <span class="h-1.5 w-1.5 rounded-full bg-accent-brand" />
          <span class="text-xs text-muted-foreground">AI-Powered Database Intelligence</span>
        </div>

        <h1 class="text-4xl font-bold tracking-tight text-foreground lg:text-6xl">
          Understand your
          <span class="text-accent-brand">database</span>
          <br />with AI
        </h1>

        <p class="mt-6 text-lg text-muted-foreground lg:text-xl">
          Connect any MySQL or MariaDB database, visualize your schema instantly,
          and get intelligent insights powered by AI. All locally, all secure.
        </p>

        <div v-if="!$page.props.auth.user" class="mt-10 flex items-center justify-center gap-4">
          <Link
            :href="register()"
            class="inline-flex items-center rounded-lg bg-accent-brand px-6 py-3 text-sm font-medium text-accent-brand-foreground hover:opacity-90 transition-opacity"
          >
            Get Started Free
          </Link>
          <Link
            :href="login()"
            class="inline-flex items-center rounded-lg border border-border bg-card px-6 py-3 text-sm font-medium text-foreground hover:bg-accent transition-colors"
          >
            Sign In
          </Link>
        </div>
      </div>

      <div class="mt-20 grid w-full max-w-5xl gap-4 md:grid-cols-3">
        <div
          v-for="feature in features"
          :key="feature.title"
          class="rounded-lg border border-border bg-card p-6 hover:border-accent-brand/30 transition-colors"
        >
          <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-accent-brand/10 text-accent-brand mb-4">
            <component :is="feature.icon" class="h-5 w-5" />
          </div>
          <h3 class="text-sm font-semibold text-foreground">{{ feature.title }}</h3>
          <p class="mt-2 text-xs text-muted-foreground leading-relaxed">{{ feature.description }}</p>
        </div>
      </div>
    </main>

    <footer class="border-t border-border px-6 py-4 lg:px-12">
      <p class="text-center text-xs text-muted-foreground">
        AetherDB AI &mdash; Open source database intelligence tool
      </p>
    </footer>
  </div>
</template>
