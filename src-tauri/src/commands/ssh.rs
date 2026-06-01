use std::process::Command;
use std::sync::Mutex;
use tauri::State;
use serde::{Deserialize, Serialize};

#[derive(Default)]
pub struct TunnelState {
    pub processes: Mutex<Vec<TunnelEntry>>,
}

#[derive(Serialize, Deserialize, Clone)]
pub struct TunnelEntry {
    pub pid: u32,
    pub local_port: u16,
    pub remote_host: String,
    pub remote_port: u16,
}

#[tauri::command]
pub async fn start_tunnel(
    host: String,
    port: u16,
    user: String,
    key_path: String,
    local_port: u16,
    remote_host: String,
    remote_port: u16,
    state: State<'_, TunnelState>,
) -> Result<u32, String> {
    let child = Command::new("ssh")
        .args([
            "-N",
            "-L",
            &format!("{}:{}:{}", local_port, remote_host, remote_port),
            "-i",
            &key_path,
            &format!("{}@{}", user, host),
            "-p",
            &port.to_string(),
            "-o",
            "StrictHostKeyChecking=no",
            "-o",
            "UserKnownHostsFile=/dev/null",
        ])
        .spawn()
        .map_err(|e| format!("Failed to start SSH tunnel: {}", e))?;

    let pid = child.id();
    let mut processes = state.processes.lock().map_err(|e| e.to_string())?;
    processes.push(TunnelEntry {
        pid,
        local_port,
        remote_host: remote_host.clone(),
        remote_port,
    });

    // Detach — don't keep the child handle
    drop(child);

    Ok(pid)
}

#[tauri::command]
pub async fn stop_tunnel(pid: u32) -> Result<(), String> {
    #[cfg(target_os = "windows")]
    {
        Command::new("taskkill")
            .args(["/PID", &pid.to_string(), "/F"])
            .output()
            .map_err(|e| format!("Failed to kill tunnel: {}", e))?;
    }

    #[cfg(not(target_os = "windows"))]
    {
        // Send SIGTERM first, then SIGKILL if needed
        let status = Command::new("kill")
            .args([&pid.to_string()])
            .status()
            .map_err(|e| format!("Failed to kill tunnel: {}", e))?;

        if !status.success() {
            Command::new("kill")
                .args(["-9", &pid.to_string()])
                .status()
                .ok();
        }
    }

    Ok(())
}
