# Skill: make-vue-page

## Trigger
Gunakan skill ini ketika diminta membuat halaman Vue baru (Inertia page).

## Instruksi untuk Agent

Buat file di `resources/js/pages/{NamaPage}.vue` dengan template:

```vue
<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'

interface Props {
  // definisikan props dari Laravel controller
}

const props = defineProps<Props>()
</script>

<template>
  <Head title="{NamaPage}" />

  <AppLayout>
    <div class="flex flex-col gap-4 p-6">
      <!-- konten halaman -->
    </div>
  </AppLayout>
</template>
```

## Checklist Setelah Membuat Page
1. Tambahkan route di `routes/web.php`
2. Buat method di Controller yang me-return `Inertia::render('{NamaPage}')`
3. Tambahkan link di sidebar navigation jika perlu
4. Buat composable di `resources/js/composables/use{NamaPage}.ts` jika ada logic
5. Gunakan komponen dari `resources/js/components/ui/` untuk UI elements

## Design Rules (sesuai PRD theme)
- Gunakan `font-mono` untuk semua text
- Tidak ada rounded corners (`rounded-none` atau tidak pakai `rounded-*`)
- Background: `bg-background`, text: `text-foreground`
- Border: `border-border`
- Mengikuti desain Linear/Vercel/Raycast
