<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'

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
    <DialogContent class="font-mono sm:max-w-md">
      <DialogHeader>
        <DialogTitle>{{ title }}</DialogTitle>
        <DialogDescription>{{ description }}</DialogDescription>
      </DialogHeader>
      <DialogFooter>
        <Button variant="outline" @click="emit('cancel')">Cancel</Button>
        <Button :disabled="loading" @click="emit('confirm')">
          <Spinner v-if="loading" />
          {{ confirmText }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
