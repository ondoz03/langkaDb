use tauri::AppHandle;
use tauri_plugin_store::StoreExt;

#[tauri::command]
pub async fn store_credential(app: AppHandle, key: String, value: String) -> Result<(), String> {
    let store = app.store("credentials.json").map_err(|e| e.to_string())?;
    store.set(key, serde_json::Value::String(value));
    store.save().map_err(|e| e.to_string())?;
    Ok(())
}

#[tauri::command]
pub async fn get_credential(app: AppHandle, key: String) -> Result<Option<String>, String> {
    let store = app.store("credentials.json").map_err(|e| e.to_string())?;
    let value = store.get(&key);
    Ok(value.and_then(|v| v.as_str().map(|s| s.to_string())))
}

#[tauri::command]
pub async fn delete_credential(app: AppHandle, key: String) -> Result<(), String> {
    let store = app.store("credentials.json").map_err(|e| e.to_string())?;
    store.delete(&key);
    store.save().map_err(|e| e.to_string())?;
    Ok(())
}
