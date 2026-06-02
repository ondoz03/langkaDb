# Rekomendasi Strategi Autentikasi & Login AetherDB AI (Desktop App)

**Analisis dilakukan pada:** 2 Juni 2026  
**Stack:** Laravel 13 + Vue 3 + Inertia + Tauri v2  
**Konteks:** Aplikasi desktop database intelligence yang mengelola koneksi DB (MySQL/MariaDB) dengan kredensial sensitif. Pengguna privacy-conscious (menggunakan Tailscale/WireGuard).

---

## Ringkasan Temuan Saat Ini

### 1. Laravel Fortify (`config/fortify.php`)
- **Guard:** `web` (session-based cookie)
- **Fitur aktif:**
  - `registration()`
  - `resetPasswords()`
  - `emailVerification()`
  - `twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true])`
  - `passkeys(['confirmPassword' => true])`
- **Passkeys:** Relying Party ID = `APP_URL`, allowed origins = `[APP_URL]`, timeout 60 detik
- **Rate limiting:** Login, 2FA, dan passkeys terpisah

### 2. Login.vue (`resources/js/pages/auth/Login.vue`)
- Menggunakan `Form` dari `@inertiajs/vue3`
- Input: email + password + checkbox "Remember me"
- Terdapat komponen `PasskeyVerify` dari `@laravel/passkeys/vue`
- Tidak ada penanganan khusus desktop/native

### 3. Tauri Desktop (`src-tauri/`)
- `tauri.conf.json`: `devUrl: http://localhost:5173`, `frontendDist: ../public/build`
- **CSP: `null`** — tidak ada kebijakan keamanan konten
- Plugin aktif: `store`, `notification`, `shell`, `updater`, `log`
- **Tidak ada** plugin keychain/biometric/stronghold

### 4. Rust Vault (`src-tauri/src/commands/vault.rs`)
```rust
// Menggunakan tauri-plugin-store → credentials.json
store.set(key, serde_json::Value::String(value));
```
- **Kredensial disimpan sebagai plaintext JSON** di direktori data aplikasi
- Tidak ada enkripsi, tidak ada binding ke OS keychain/keyring

### 5. Keamanan Koneksi DB (`app/Modules/Connection/`)
- `ConnectionEncryptor` menggunakan `Crypt::encryptString()` (AES-256-CBC via `APP_KEY`)
- Password DB dan SSH key dienkripsi sebelum disimpan ke SQLite
- Namun, **enkripsi bergantung sepenuhnya pada `APP_KEY`** — jika key bocor, semua kredensial DB dapat didekripsi

### 6. Sesi (`config/session.php`)
- Driver: `database`
- Lifetime: 120 menit
- `expire_on_close: false`
- `encrypt: false`
- `same_site: lax`

---

## Rekomendasi Prioritas Tinggi → Rendah

---

### 1. Desktop-First Auth Flow (Prioritas #1 — KRITIS)

#### Masalah
Aplikasi desktop saat ini hanyalah "webview wrapper" untuk aplikasi web Laravel. Autentikasi bergantung pada session cookie yang disimpan di dalam webview Tauri. Ini memiliki beberapa risiko:
- Session cookie bisa terhapus saat app update atau cache webview dibersihkan
- Tidak ada mekanisme "native session" yang persisten dan aman di desktop
- Webview tidak memiliki isolasi cookie sekuat browser modern
- Tidak ada refresh token yang bisa dirotasi

#### Rekomendasi: Hybrid Token + Secure Store

**Arsitektur yang direkomendasikan:**

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│   Tauri Desktop │────▶│  Laravel Backend │────▶│   SQLite DB     │
│   (Vue + Rust)  │     │  (Fortify + API) │     │                 │
└─────────────────┘     └──────────────────┘     └─────────────────┘
         │
         ▼
┌─────────────────┐
│  OS Keychain    │  ← Simpan refresh token + APP_KEY fragment
│  (Keyring)      │
└─────────────────┘
```

**Implementasi konkret:**

1. **Tambahkan Fortify API Token (Sanctum) untuk desktop:**
   - Install `laravel/sanctum`
   - Tambahkan guard `sanctum` di `config/auth.php`
   - Buat endpoint khusus desktop: `/auth/desktop/token`
   - Token ini digunakan untuk autentikasi API dari Tauri, bukan session cookie

2. **Session Desktop di Rust:**
   - Setelah login via webview, backend mengirimkan **refresh token** (long-lived, 30 hari, rotatable) dan **access token** (short-lived, 15 menit)
   - Rust menerima token via Tauri command/event, lalu menyimpannya di **OS keychain**, BUKAN `tauri-plugin-store`
   - Access token disimpan di memori (Tauri State). Refresh token di keychain.

3. **Modifikasi Login.vue untuk Desktop:**
   - Deteksi environment Tauri via `window.__TAURI__`
   - Jika di desktop, setelah Fortify login sukses, panggil Tauri command `auth::store_session(token)`
   - Hindari penggunaan session cookie sebagai mekanisme utama di desktop

4. **Persistensi Sesi Desktop:**
   - Token di keychain → persisten antar restart aplikasi
   - Saat app dibuka, Rust membaca keychain → validasi token ke backend → auto-login
   - Jika token invalid/expired, redirect ke login page

#### Keamanan
- Refresh token di keychain = terlindungi oleh OS (macOS Keychain, Windows Credential Manager, Linux Secret Service)
- Tidak ada plaintext token di disk
- Access token short-lived meminimalkan damage jika terjadi memory dump
- Rotasi refresh token setiap kali access token di-refresh

---

### 2. Passkey Strategy (Prioritas #2 — TINGGI)

#### Masalah
Passkey (WebAuthn) di Tauri desktop memiliki kendala:
- `allowed_origins` di `fortify.php` hanya berisi `APP_URL` (http://localhost di dev)
- Tauri webview origin bisa berbeda antara dev (`http://localhost:5173`) dan production (`tauri://localhost` atau `https://tauri.localhost`)
- `@laravel/passkeys/vue` menggunakan WebAuthn browser API yang tersedia di webview, tapi **credential disimpan di internal webview credential store**, bukan di OS credential manager
- Tidak ada integrasi dengan Windows Hello / macOS Touch ID / Linux biometrics

#### Rekomendasi: Dual Passkey Approach

**A. WebAuthn Hybrid (untuk kompatibilitas)**
1. Konfigurasi `fortify.php` — perluas `allowed_origins`:
```php
'allowed_origins' => array_filter([
    config('app.url'),
    'http://localhost:5173',
    'tauri://localhost',
    'https://tauri.localhost',
]),
```

2. Relying Party ID: gunakan domain yang konsisten (misal `ai.aetherdb.app`) meski app berjalan lokal:
```php
'passkeys' => [
    'relying_party_id' => env('PASSKEY_RP_ID', 'ai.aetherdb.app'),
    'allowed_origins' => explode(',', env('PASSKEY_ORIGINS', config('app.url'))),
],
```

**B. Native OS Credential Manager (Roadmap)**
- Untuk pengalaman premium, tambahkan dukungan Windows Hello / macOS Touch ID via Tauri plugin:
  - `tauri-plugin-biometric` (community) atau implementasi custom Rust menggunakan `windows::Security::Credentials` / `LocalAuthentication` (macOS)
- Flow: User login dengan password → register biometric → subsequent login via sidik jari/Face ID → Rust mengambil refresh token dari keychain setelah biometric sukses

**C. PasskeyVerify.vue — penanganan error desktop:**
```typescript
const { verify, isLoading, error, isSupported } = usePasskeyVerify({
    onError: (err) => {
        if (err.message.includes('NotAllowedError') && isTauri()) {
            // Fallback ke native biometric prompt via Tauri
            invoke('auth:biometric_unlock');
        }
    },
});
```

#### Keamanan
- WebAuthn credential tetap di dalam webview (terisolasi per-app)
- Native biometric menambah lapisan autentikasi faktor kepemilikan (something you are)
- Passkey tidak bisa di-phishing karena binding ke origin

---

### 3. 2FA/TOTP Flow (Prioritas #3 — TINGGI)

#### Masalah
- Fortify 2FA aktif dengan `confirm => true`, artinya user harus mengkonfirmasi TOTP saat setup
- Di desktop, prompt 2FA setiap login bisa mengganggu UX
- Tidak ada konsep "trusted device" yang persisten di desktop

#### Rekomendasi: Trusted Device + Desktop 2FA Cache

**Implementasi:**

1. **Trusted Device Token (Backend):**
   - Setelah user sukses 2FA, backend generate **device token** (random 128-bit, hash di DB)
   - Token ini diikat ke `device_id` (generate UUID unik per installasi desktop)
   - Expiry: 30 hari (configurable)

2. **Penyimpanan di Desktop:**
   - Device token disimpan di **OS keychain** dengan service label `ai.aetherdb.app.trusted_device`
   - `device_id` disimpan di `tauri-plugin-store` (bukan secret, hanya identifier)

3. **Flow Login Desktop:**
   ```
   User buka app
   → Rust kirim device_id + trusted_token ke /auth/verify-device
   → Jika valid: login otomatis (skip 2FA)
   → Jika invalid/tidak ada: tampilkan login + 2FA setelah password
   ```

4. **Manajemen Trusted Device:**
   - User bisa melihat dan mencabut trusted device dari Settings → Security
   - Saat logout, hapus trusted_token dari keychain tapi pertahankan device_id
   - Saat "Logout from all devices", backend hapus semua hash trusted device milik user

5. **Konfigurasi Fortify (tetap):**
   - Biarkan `twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true])`
   - Tambahkan middleware custom `VerifyTrustedDevice` yang dijalankan SEBELUM `two-factor` challenge

#### Keamanan
- Trusted device token = high-entropy, hashed di DB, tidak bisa digunakan di device lain
- Keychain menyimpan token → tidak bisa diakses aplikasi lain (kecuali dengan hak akses root/sudo)
- Jika laptop dicuri, token tetap aman selama keychain terlindungi password OS user
- Force 2FA re-verification setiap 30 hari atau saat IP/location berubah drastis (opsional)

---

### 4. Database Connection Credential Security (Prioritas #4 — TINGGI)

#### Masalah
- `ConnectionEncryptor` menggunakan `Crypt::encryptString()` yang bergantung pada `APP_KEY`
- `APP_KEY` disimpan di `.env` file — jika file ini dicuri (backup, git leak, malware), semua password DB bisa didekripsi
- Password DB di-decrypt di runtime PHP dan dikirim ke Doctrine DBAL → potensi exposure di memory/log
- Tidak ada dukungan hardware-backed encryption atau HSM

#### Rekomendasi: Layered Encryption + Desktop Keychain Integration

**A. Perkuat Enkripsi Database (Laravel Backend)**

1. **Key Derivation dari Multiple Sources:**
   ```php
   // Jangan gunakan APP_KEY langsung
   $masterKey = hash_hkdf('sha256', 
       config('app.key') . config('db.encryption.salt'), 
       'aetherdb-connection-v1'
   );
   ```
   - Salt disimpan di DB (tidak rahasia), `APP_KEY` di `.env`
   - Butuh keduanya untuk derivasi master key

2. **Enkripsi per-User dengan User-Specific Key:**
   - Setiap user memiliki `encryption_key` (32 byte random) yang di-generate saat registrasi
   - User key dienkripsi dengan master key, lalu disimpan di DB (`users.encryption_key`)
   - Password DB dienkripsi dengan user key, BUKAN langsung dengan APP_KEY
   - Keuntungan: jika satu user key bocor, user lain tetap aman

3. **Audit & Rotation:**
   - Log setiap dekripsi kredensial (timestamp, user_id, connection_id, action)
   - Support key rotation: re-encrypt semua kredensial saat `APP_KEY` berubah

**B. Integrasi Desktop Keychain untuk APP_KEY Fragment (Tauri Rust)**

1. **Split-Key Architecture:**
   - Saat setup pertama (installasi desktop), Rust generate `DESKTOP_KEY_FRAGMENT` (128-bit random)
   - Fragment ini disimpan di **OS keychain** dengan label `ai.aetherdb.app.key_fragment`
   - Fragment juga di-hash dan hash-nya disimpan di backend (tetapi fragment asli TIDAK pernah dikirim ke backend)

2. **Zero-Knowledge Local Decryption:**
   - Saat app membutuhkan password DB:
     - Backend kirim encrypted password ke desktop
     - Desktop menerima, kemudian meminta fragment dari keychain
     - Desktop menggabungkan fragment + user key (dari session) → decrypt password LOKAL di Rust
     - Password plaintext **tidak pernah** masuk ke JavaScript/webview
     - Rust passing connection config langsung ke DB driver native (jika ada) atau ke PHP via secure channel

3. **Alternative: Rust sebagai DB Proxy:**
   - Jika memungkinkan, gunakan Rust Tauri sebagai DB proxy:
     - Webview JS → Tauri command `db::execute_query(connection_id, sql)`
     - Rust mengambil kredensial dari keychain, membuat koneksi DB langsung dari Rust (menggunakan `mysql`/`sqlx` crate)
     - Hasil dikembalikan ke JS sebagai JSON
   - Keuntungan: password DB **tidak pernah** masuk ke JavaScript memory

**C. Enkripsi at Rest — Penyimpanan Lokal**

- `tauri-plugin-store` (`credentials.json`) saat ini **TIDAK AMAN** untuk data sensitif
- Ganti dengan:
  - **macOS**: `tauri-plugin-keychain` atau `security` CLI → Keychain Access
  - **Windows**: Windows Credential Manager (via `keyring` crate atau `wincred`)
  - **Linux**: Secret Service API / `libsecret` (via `secret-service` crate)

**Crate Rust yang direkomendasikan:**
```toml
[dependencies]
keyring = "3"  # Cross-platform keyring (macOS, Windows, Linux)
# atau
secret-service = "4" # Linux-specific, lebih granular
```

Implementasi vault baru:
```rust
use keyring::Entry;

#[tauri::command]
pub async fn store_credential(app: AppHandle, key: String, value: String) -> Result<(), String> {
    let entry = Entry::new("ai.aetherdb.app", &key)
        .map_err(|e| e.to_string())?;
    entry.set_password(&value).map_err(|e| e.to_string())?;
    Ok(())
}

#[tauri::command]
pub async fn get_credential(key: String) -> Result<Option<String>, String> {
    let entry = Entry::new("ai.aetherdb.app", &key)
        .map_err(|e| e.to_string())?;
    match entry.get_password() {
        Ok(password) => Ok(Some(password)),
        Err(keyring::Error::NoEntry) => Ok(None),
        Err(e) => Err(e.to_string()),
    }
}
```

#### Keamanan
- Multi-layered encryption: APP_KEY + salt → master key → user key → encrypted password
- Desktop keychain = hardware-backed (macOS Secure Enclave, Windows TPM jika tersedia)
- Password DB tidak pernah masuk ke JS/webview memory
- Fallback: jika keychain tidak tersedia, app menolak menyimpan kredensial (fail-secure)

---

### 5. Guest/Anonymous Mode (Prioritas #5 — MENENGAH)

#### Analisis Trade-off

| Aspek | Pro (Boleh Guest) | Kontra (Wajib Login) |
|-------|-------------------|----------------------|
| UX | User bisa langsung eksplorasi UI | Memaksa login sebelum melihat apa pun |
| Conversion | Lebih rendah friction untuk trial | Meningkatkan komitmen user |
| Security | — | Semua fitur memerlukan identitas untuk audit log |
| Data | — | AetherDB mengelola koneksi DB sensitif; guest = liability |
| AI Agent | Tanpa login, AI tidak bisa personalize | AI perlu konteks user untuk rekomendasi |

#### Rekomendasi: **Demo Mode Terbatas (TANPA koneksi DB nyata)**

**Implementasi:**

1. **Welcome Screen dengan Dual CTA:**
   - "Explore Demo" → masuk ke dashboard dengan data dummy/SQLite in-memory
   - "Sign In / Register" → login penuh

2. **Demo Mode Features:**
   - Dashboard dengan sample database (Chinook-style dataset bawaan)
   - AI Agent bisa menjawab pertanyaan umum tentang SQL (tanpa akses ke DB user)
   - Schema visualization dengan data dummy
   - Query builder bisa diuji dengan sample DB

3. **Demo Mode Restrictions:**
   - **TIDAK BOLEH** menambah koneksi DB nyata
   - **TIDAK BOLEH** mengekspor data
   - **TIDAK BOLEH** mengakses SSH tunnel
   - AI Agent tidak menyimpan chat history (stateless)
   - Data demo disimpan di memory / temporary SQLite (hapus saat app ditutup)

4. **Konversi ke Full Account:**
   - Tombol "Connect Your Database" yang muncul di setiap halaman demo → redirect ke registrasi
   - Saat registrasi, data demo bisa dipertahankan (migrasi ke akun) atau dihapus

5. **Backend Handling:**
   - Buat guard `demo` terpisah di Laravel
   - Routes demo tidak memerlukan `auth`, tapi memerlukan `demo.session` (cookie/session khusus)
   - Middleware `BlockRealConnections` untuk mode demo

#### Keamanan
- Demo mode sepenuhnya terisolasi dari sistem auth utama
- Tidak ada risiko kredensial DB bocor karena tidak ada input kredensial
- Session demo tidak bisa di-escalate ke session penuh tanpa login ulang
- Mencegah "anonymous abuse" terhadap AI API (rate limit demo per IP/device ID)

---

## Prioritas Implementasi (Roadmap)

### Fase 1 — Foundation Security (Minggu 1-2)
1. **Ganti vault storage** dari `tauri-plugin-store` ke `keyring` crate (OS keychain)
2. **Aktifkan CSP** di `tauri.conf.json`:
   ```json
   "csp": "default-src 'self'; connect-src 'self' http://localhost:* https://*.aetherdb.app; script-src 'self'; object-src 'none';"
   ```
3. **Enkripsi session** di Laravel: `SESSION_ENCRYPT=true`
4. **Tambahkan Sanctum** dan setup `auth:sanctum` guard untuk API desktop

### Fase 2 — Desktop Auth (Minggu 3-4)
1. Implementasi token-based auth (refresh + access token)
2. Simpan token di OS keychain via Rust commands
3. Auto-login saat app dibuka
4. Modifikasi Login.vue untuk deteksi Tauri dan panggilan native

### Fase 3 — Trusted Device + 2FA (Minggu 5-6)
1. Implementasi trusted device token
2. Skip 2FA prompt untuk device yang sudah trusted
3. Halaman manajemen trusted device di Settings → Security
4. Notifikasi push (via tauri-plugin-notification) saat login dari device baru

### Fase 4 — DB Credential Hardening (Minggu 7-8)
1. Implementasi user-specific encryption key
2. Tambahkan salt per-connection di DB
3. Audit logging untuk dekripsi kredensial
4. Rust local decryption (optional: Rust DB proxy)

### Fase 5 — Guest Mode + Passkey Polish (Minggu 9-10)
1. Implementasi demo mode terbatas
2. Perluas passkey origins untuk Tauri
3. Research native biometric integration (roadmap)

---

## Catatan Khusus untuk Pengguna Privacy-Conscious

Mengingat target user AetherDB menggunakan Tailscale/WireGuard (menandakan preferensi self-hosted, zero-trust, dan data sovereignty):

1. **Jangan mengirimkan analytics atau crash report tanpa consent** — Tauri updater sudah ada, tapi pastikan opt-in
2. **Semua AI processing sebaiknya bisa diarahkan ke Ollama lokal** (sudah ada di environment user: `/home/who/.ollama`)
3. **Autentikasi offline** — pertimbangkan apakah aplikasi HARUS online untuk login, atau bisa cache session untuk penggunaan LAN-only via Tailscale
4. **Transparansi enkripsi:** dokumentasikan secara terbuka bagaimana kredensial dienkripsi (bukan security through obscurity)

---

*Dokumen ini disusun berdasarkan analisis kode aktual di repository AetherDB AI. Implementasi detail memerlukan diskusi lebih lanjut dengan tim engineering.*
