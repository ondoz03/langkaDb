<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Spinner } from '@/components/ui/spinner'
import { useConnection } from '@/composables/useConnection'
import { Database, Wifi, Server, Eye, EyeOff, AlertCircle, CheckCircle2 } from 'lucide-vue-next'

interface Props {
  open: boolean
  connection?: Record<string, unknown> | null
}

const props = defineProps<Props>()
const emit = defineEmits<{ close: [] }>()

const { createConnection, updateConnection, testConnectionWithData, testConnection, loading } = useConnection()
const testing = ref(false)
const testResult = ref<{ success: boolean; message: string } | null>(null)
const showPassword = ref(false)
const showSshKey = ref(false)

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
  testResult.value = null
}, { immediate: true })

function resetForm() {
  form.name = ''; form.driver = 'mysql'; form.host = '127.0.0.1'
  form.port = 3306; form.database = ''; form.username = ''; form.password = ''
  form.ssl_enabled = false; form.ssh_enabled = false
  form.ssh_host = ''; form.ssh_port = 22; form.ssh_user = ''; form.ssh_key = ''
  testResult.value = null
}

async function handleTest() {
  testing.value = true
  testResult.value = null

  const data = {
    driver: form.driver,
    host: form.host,
    port: form.port,
    database: form.database,
    username: form.username,
    password: form.password,
    ssl_enabled: form.ssl_enabled,
  }

  const result = await testConnectionWithData(data)
  testResult.value = result
  testing.value = false
}

function isEditing() {
  return !!props.connection?.id
}

function getFormData() {
  return { ...form } as Record<string, unknown>
}

async function handleSubmit() {
  const data = getFormData()
  let id = props.connection?.id as string | undefined

  if (id) {
    const result = await updateConnection(id, data)
    if (!result) return
  } else {
    const result = await createConnection(data)
    if (!result) return
    id = result.id
  }

  await testConnection(id)
  resetForm()
  emit('close')
}

function handleClose() {
  resetForm()
  emit('close')
}

function isValid() {
  return form.name.trim() && form.host.trim() && form.database.trim() && form.username.trim()
}
</script>

<template>
  <Dialog :open="open" @update:open="handleClose">
    <DialogContent class="sm:max-w-lg p-0 overflow-hidden">
      <!-- Header -->
      <div class="border-b border-border px-6 py-5">
        <div class="flex items-center gap-3">
          <div class="flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-muted/50">
            <Database class="h-4 w-4 text-muted-foreground" />
          </div>
          <div>
            <DialogTitle class="text-base font-semibold text-foreground">
              {{ connection ? 'Edit Connection' : 'Add Connection' }}
            </DialogTitle>
            <DialogDescription class="text-sm text-muted-foreground mt-0.5">
              {{ connection ? 'Update your database connection details.' : 'Enter your database connection details.' }}
            </DialogDescription>
          </div>
        </div>
      </div>

      <!-- Form -->
      <div class="px-6 py-4 space-y-4">
        <!-- Connection Name -->
        <div class="space-y-1.5">
          <Label for="name" class="text-xs font-medium text-foreground">Connection Name</Label>
          <Input id="name" v-model="form.name" placeholder="My Database" class="h-9" />
        </div>

        <!-- Driver -->
        <div class="space-y-1.5">
          <Label for="driver" class="text-xs font-medium text-foreground">Driver</Label>
          <Select v-model="form.driver">
            <SelectTrigger class="h-9">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="mysql">MySQL</SelectItem>
              <SelectItem value="mariadb">MariaDB</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <!-- Host + Port -->
        <div class="grid grid-cols-3 gap-3">
          <div class="col-span-2 space-y-1.5">
            <Label for="host" class="text-xs font-medium text-foreground">Host</Label>
            <Input id="host" v-model="form.host" placeholder="127.0.0.1" class="h-9" />
          </div>
          <div class="space-y-1.5">
            <Label for="port" class="text-xs font-medium text-foreground">Port</Label>
            <Input id="port" v-model.number="form.port" type="number" placeholder="3306" class="h-9" />
          </div>
        </div>

        <!-- Database -->
        <div class="space-y-1.5">
          <Label for="database" class="text-xs font-medium text-foreground">Database</Label>
          <Input id="database" v-model="form.database" placeholder="my_database" class="h-9" />
        </div>

        <!-- Username + Password -->
        <div class="grid grid-cols-2 gap-3">
          <div class="space-y-1.5">
            <Label for="username" class="text-xs font-medium text-foreground">Username</Label>
            <Input id="username" v-model="form.username" placeholder="root" class="h-9" />
          </div>
          <div class="space-y-1.5">
            <Label for="password" class="text-xs font-medium text-foreground">Password</Label>
            <div class="relative">
              <Input
                id="password"
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                :placeholder="connection ? 'Leave empty to keep' : ''"
                class="h-9 pr-9"
              />
              <button
                type="button"
                class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                @click="showPassword = !showPassword"
              >
                <Eye v-if="!showPassword" class="h-4 w-4" />
                <EyeOff v-else class="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>

        <!-- SSL + SSH -->
        <div class="space-y-3">
          <div class="flex items-center gap-2">
            <Checkbox id="ssl" v-model:checked="form.ssl_enabled" />
            <Label for="ssl" class="text-sm text-muted-foreground cursor-pointer">Enable SSL connection</Label>
          </div>
          <div class="flex items-center gap-2">
            <Checkbox id="ssh" v-model:checked="form.ssh_enabled" />
            <Label for="ssh" class="text-sm text-muted-foreground cursor-pointer">Connect via SSH tunnel</Label>
          </div>
        </div>

        <!-- SSH fields -->
        <template v-if="form.ssh_enabled">
          <div class="border-t border-border pt-4 space-y-3">
            <p class="text-xs font-medium text-foreground">SSH Configuration</p>
            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-1.5">
                <Label for="ssh_host" class="text-xs">SSH Host</Label>
                <Input id="ssh_host" v-model="form.ssh_host" placeholder="ssh.example.com" class="h-9" />
              </div>
              <div class="space-y-1.5">
                <Label for="ssh_port" class="text-xs">SSH Port</Label>
                <Input id="ssh_port" v-model.number="form.ssh_port" type="number" placeholder="22" class="h-9" />
              </div>
            </div>
            <div class="space-y-1.5">
              <Label for="ssh_user" class="text-xs">SSH User</Label>
              <Input id="ssh_user" v-model="form.ssh_user" placeholder="ubuntu" class="h-9" />
            </div>
            <div class="space-y-1.5">
              <Label for="ssh_key" class="text-xs">SSH Private Key</Label>
              <div class="relative">
                <Input
                  id="ssh_key"
                  v-model="form.ssh_key"
                  :type="showSshKey ? 'text' : 'password'"
                  placeholder="Leave empty to keep current"
                  class="h-9 pr-9"
                />
                <button
                  type="button"
                  class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                  @click="showSshKey = !showSshKey"
                >
                  <Eye v-if="!showSshKey" class="h-4 w-4" />
                  <EyeOff v-else class="h-4 w-4" />
                </button>
              </div>
            </div>
          </div>
        </template>

        <!-- Test result -->
        <div v-if="testResult" class="flex items-start gap-2.5 rounded-lg border p-3 text-sm" :class="testResult.success ? 'border-green-500/20 bg-green-500/5' : 'border-red-500/20 bg-red-500/5'">
          <CheckCircle2 v-if="testResult.success" class="mt-0.5 h-4 w-4 shrink-0 text-green-500" />
          <AlertCircle v-else class="mt-0.5 h-4 w-4 shrink-0 text-red-500" />
          <span :class="testResult.success ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
            {{ testResult.message }}
          </span>
        </div>
      </div>

      <!-- Footer -->
      <DialogFooter class="border-t border-border px-6 py-4 gap-2">
        <Button variant="outline" size="sm" @click="handleClose">Cancel</Button>
        <Button size="sm" variant="secondary" :disabled="testing || !isValid()" @click="handleTest" class="gap-1.5">
          <Spinner v-if="testing" class="h-4 w-4" />
          <Wifi v-else class="h-4 w-4" />
          Test Connection
        </Button>
        <Button size="sm" :disabled="loading || testing || !isValid()" @click="handleSubmit" class="gap-1.5">
          <Spinner v-if="loading" class="h-4 w-4" />
          <Server v-else class="h-4 w-4" />
          {{ connection ? 'Update & Verify' : 'Save & Verify' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
