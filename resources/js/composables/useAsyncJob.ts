import { ref, onUnmounted } from 'vue'

export interface JobStatus {
  id: number
  job_class: string
  connection_id: string
  status: 'pending' | 'processing' | 'completed' | 'failed'
  progress: number
  created_at: string
  updated_at: string
}

export interface JobResult<T = any> {
  id: number
  status: 'completed' | 'failed' | 'pending' | 'processing'
  result?: T
  error?: string
  progress?: number
}

export function useAsyncJob() {
  let pollTimer: ReturnType<typeof setInterval> | null = null
  const polling = ref(false)
  const jobStatus = ref<JobStatus | null>(null)
  const jobResult = ref<JobResult | null>(null)
  const error = ref<string | null>(null)

  function startPolling(jobId: number, intervalMs = 2000) {
    polling.value = true
    error.value = null

    pollTimer = setInterval(async () => {
      try {
        const res = await fetch(`/api/jobs/${jobId}/status`, {
          credentials: 'include',
          headers: { 'Accept': 'application/json' },
        })

        if (!res.ok) {
          throw new Error(`HTTP ${res.status}`)
        }

        const json = await res.json()
        jobStatus.value = json.data

        // If completed or failed, fetch the result
        if (json.data.status === 'completed') {
          await fetchResult(jobId)
          stopPolling()
        } else if (json.data.status === 'failed') {
          await fetchResult(jobId)
          stopPolling()
        }
      } catch (e: any) {
        error.value = e.message ?? 'Polling failed'
        stopPolling()
      }
    }, intervalMs)
  }

  async function fetchResult(jobId: number) {
    try {
      const res = await fetch(`/api/jobs/${jobId}/result`, {
        credentials: 'include',
        headers: { 'Accept': 'application/json' },
      })

      const json = await res.json()
      jobResult.value = json.data
    } catch (e: any) {
      error.value = e.message ?? 'Failed to fetch result'
    }
  }

  function stopPolling() {
    if (pollTimer) {
      clearInterval(pollTimer)
      pollTimer = null
    }
    polling.value = false
  }

  function reset() {
    stopPolling()
    jobStatus.value = null
    jobResult.value = null
    error.value = null
  }

  onUnmounted(() => {
    stopPolling()
  })

  return {
    polling,
    jobStatus,
    jobResult,
    error,
    startPolling,
    stopPolling,
    fetchResult,
    reset,
  }
}
