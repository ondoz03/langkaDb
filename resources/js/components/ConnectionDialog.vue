<script setup lang="ts">
import { reactive, watch } from 'vue'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Spinner } from '@/components/ui/spinner'
import { useConnection } from '@/composables/useConnection'

interface Props {
  open: boolean
  connection?: Record<string, unknown> | null
}

const props = defineProps<Props>()

const emit = defineEmits<{
  close: []
}>()

const { createConnection, updateConnection, loading } = useConnection()

const form = reactive({
  name: '',
  driver: 'mysql',
  host: '127.0.0.1',
  port: 3306,
  database: '',
  username: '',
  password: '',
  ssl_enabled: false,
  ssh_enabled: false,
  ssh_host: '',
  ssh_port: 22,
  ssh_user: '',
  ssh_key: '',
})

watch(() => props.connection, (conn) => {
  if (conn) {
    form.name = (conn.name as string) ?? ''
    form.driver = (conn.driver as string) ?? 'mysql'
    form.host = (conn.host as string) ?? '127.0.0.1'
    form.port = (conn.port as number) ?? 3306
    form.database = (conn.database as string) ?? ''
    form.username = (conn.username as string) ?? ''
    form.password = ''
    form.ssl_enabled = (conn.ssl_enabled as boolean) ?? false
    form.ssh_enabled = (conn.ssh_enabled as boolean) ?? false
    form.ssh_host = (conn.ssh_host as string) ?? ''
    form.ssh_port = (conn.ssh_port as number) ?? 22
    form.ssh_user = (conn.ssh_user as string) ?? ''
    form.ssh_key = ''
  }
}, { immediate: true })

function resetForm() {
  form.name = ''
  form.driver = 'mysql'
  form.host = '127.0.0.1'
  form.port = 3306
  form.database = ''
  form.username = ''
  form.password = ''
  form.ssl_enabled = false
  form.ssh_enabled = false
  form.ssh_host = ''
  form.ssh_port = 22
  form.ssh_user = ''
  form.ssh_key = ''
}

async function handleSubmit() {
  const data: Record<string, unknown> = { ...form }

  if (props.connection?.id) {
    await updateConnection(props.connection.id as string, data)
  } else {
    await createConnection(data)
  }

  resetForm()
  emit('close')
}

function handleClose() {
  resetForm()
  emit('close')
}
</script>

<template>
  <Dialog :open="open" @update:open="handleClose">
    <DialogContent class="font-mono sm:max-w-lg">
      <DialogHeader>
        <DialogTitle>{{ connection ? 'Edit Connection' : 'Add Connection' }}</DialogTitle>
        <DialogDescription>
          {{ connection ? 'Update your database connection details.' : 'Enter your database connection details.' }}
        </DialogDescription>
      </DialogHeader>

      <div class="grid gap-4 py-4">
        <div class="grid gap-2">
          <Label for="name">Connection Name</Label>
          <Input id="name" v-model="form.name" placeholder="My Database" />
        </div>

        <div class="grid gap-2">
          <Label for="driver">Driver</Label>
          <Select v-model="form.driver">
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="mysql">MySQL</SelectItem>
              <SelectItem value="mariadb">MariaDB</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="grid grid-cols-3 gap-3">
          <div class="col-span-2 grid gap-2">
            <Label for="host">Host</Label>
            <Input id="host" v-model="form.host" placeholder="127.0.0.1" />
          </div>
          <div class="grid gap-2">
            <Label for="port">Port</Label>
            <Input id="port" v-model.number="form.port" type="number" placeholder="3306" />
          </div>
        </div>

        <div class="grid gap-2">
          <Label for="database">Database</Label>
          <Input id="database" v-model="form.database" placeholder="my_database" />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div class="grid gap-2">
            <Label for="username">Username</Label>
            <Input id="username" v-model="form.username" placeholder="root" />
          </div>
          <div class="grid gap-2">
            <Label for="password">Password</Label>
            <Input id="password" v-model="form.password" type="password" placeholder="Leave empty to keep current" />
          </div>
        </div>

        <div class="flex items-center gap-2">
          <Checkbox id="ssl" v-model:checked="form.ssl_enabled" />
          <Label for="ssl" class="text-xs text-muted-foreground">Enable SSL connection</Label>
        </div>

        <div class="flex items-center gap-2">
          <Checkbox id="ssh" v-model:checked="form.ssh_enabled" />
          <Label for="ssh" class="text-xs text-muted-foreground">Connect via SSH tunnel</Label>
        </div>

        <template v-if="form.ssh_enabled">
          <div class="grid grid-cols-2 gap-3">
            <div class="grid gap-2">
              <Label for="ssh_host">SSH Host</Label>
              <Input id="ssh_host" v-model="form.ssh_host" placeholder="ssh.example.com" />
            </div>
            <div class="grid gap-2">
              <Label for="ssh_port">SSH Port</Label>
              <Input id="ssh_port" v-model.number="form.ssh_port" type="number" placeholder="22" />
            </div>
          </div>
          <div class="grid gap-2">
            <Label for="ssh_user">SSH User</Label>
            <Input id="ssh_user" v-model="form.ssh_user" placeholder="ubuntu" />
          </div>
          <div class="grid gap-2">
            <Label for="ssh_key">SSH Private Key</Label>
            <Input id="ssh_key" v-model="form.ssh_key" type="password" placeholder="Leave empty to keep current" />
          </div>
        </template>
      </div>

      <DialogFooter>
        <Button variant="outline" @click="handleClose">Cancel</Button>
        <Button :disabled="loading" @click="handleSubmit">
          <Spinner v-if="loading" />
          {{ connection ? 'Update' : 'Add' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
