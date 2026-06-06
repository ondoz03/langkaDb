<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { AlertTriangle } from 'lucide-vue-next'

interface Props {
  open: boolean
  title: string
  description: string
  confirmText?: string
  loading?: boolean
}

withDefaults(defineProps<Props>(), {
  confirmText: 'Confirm',
  loading: false,
})

const emit = defineEmits<{
  confirm: []
  cancel: []
}>()
</script>

<template>
  <Dialog :open="open" @update:open="emit('cancel')">
    <DialogContent class="sm:max-w-md p-0 overflow-hidden">
      <div class="px-6 py-5">
        <div class="flex items-start gap-4">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-red-500/20 bg-red-500/5">
            <AlertTriangle class="h-5 w-5 text-red-500" />
          </div>
          <div class="pt-0.5">
            <DialogTitle class="text-base font-semibold text-foreground">{{ title }}</DialogTitle>
            <DialogDescription class="text-sm text-muted-foreground mt-1">{{ description }}</DialogDescription>
          </div>
        </div>
      </div>
      <DialogFooter class="border-t border-border px-6 py-4 gap-2">
        <Button variant="outline" size="sm" @click="emit('cancel')">Cancel</Button>
        <Button size="sm" variant="destructive" :disabled="loading" @click="emit('confirm')" class="gap-1.5">
          <Spinner v-if="loading" class="h-4 w-4" />
          {{ confirmText }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
