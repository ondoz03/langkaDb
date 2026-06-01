# REDESIGN.md — AetherDB AI Frontend Redesign
## UI/UX Redesign Plan

**Project:** AetherDB AI (langkaDb)
**Stack:** Laravel + Vue 3 + Inertia + Tailwind CSS v4 + shadcn-vue
**Author:** AI Coding Agent
**Last Updated:** 2026-05-30

---

## Objective

Merombak total tampilan frontend AetherDB AI dari desain monokrom generik (terlihat "AI-generated") menjadi desain modern, profesional, dengan character dan visual hierarchy yang jelas.

---

## Design Tokens

| Token | Value | Keterangan |
|-------|-------|------------|
| `--font-sans` | `'Inter', sans-serif` | Body & heading font |
| `--font-mono` | `'Geist Mono', monospace` | Code font (tetap) |
| `--radius` | `0.5rem` | Border radius untuk cards |
| `--color-accent` | `hsl(187 85% 53%)` | Cyan accent color |
| `--color-accent-foreground` | `hsl(0 0% 0%)` | Text di atas accent |

---

## File yang Akan Diubah

| # | File | Perubahan |
|---|------|-----------|
| 1 | `resources/css/app.css` | Design tokens: radius, accent, font, shadow |
| 2 | `resources/js/pages/Welcome.vue` | **Redesign total** — ganti Laravel branding dengan AetherDB AI |
| 3 | `resources/js/layouts/AuthLayout.vue` | Polish card, spacing |
| 4 | `resources/js/pages/auth/Login.vue` | Consistent form styling |
| 5 | `resources/js/pages/auth/Register.vue` | Consistent form styling |
| 6 | `resources/js/pages/auth/ForgotPassword.vue` | Consistent form styling |
| 7 | `resources/js/pages/auth/ResetPassword.vue` | Consistent form styling |
| 8 | `resources/js/pages/auth/ConfirmPassword.vue` | Consistent form styling |
| 9 | `resources/js/pages/auth/VerifyEmail.vue` | Consistent form styling |
| 10 | `resources/js/pages/auth/TwoFactorChallenge.vue` | Polish InputOTP styling |
| 11 | `resources/js/pages/Dashboard.vue` | **Redesign total** — placeholder ke overview nyata |
| 12 | `resources/js/pages/Connections/Index.vue` | Minor polish |
| 13 | `resources/js/pages/Graph/Index.vue` | Minor polish toolbar |
| 14 | `resources/js/pages/Insights/Index.vue` | Polish results cards |
| 15 | `resources/js/pages/Queries/Index.vue` | Polish SQL editor & chat |
| 16 | `resources/js/pages/Monitoring/Index.vue` | **Redesign** — komponen lebih rapi |
| 17 | `resources/js/components/AppSidebar.vue` | Active state indicator |
| 18 | `resources/js/layouts/settings/Layout.vue` | Polish nav buttons |
| 19 | `resources/js/pages/settings/Profile.vue` | Consistent form card |
| 20 | `resources/js/pages/settings/Security.vue` | Consistent form card |
| 21 | `resources/js/pages/settings/Appearance.vue` | Consistent form card |
| 22 | `resources/js/pages/settings/AI.vue` | Provider cards rapi |

---

## Execution Phases

### Phase 1: Design Tokens
- [ ] `app.css` — update `:root` dan `.dark` dengan radius, accent, font Inter
- [ ] `app.css` — tambah subtle shadow utility
- [ ] `app.css` — ganti `--font-sans` ke Inter

### Phase 2: Welcome Page
- [ ] `Welcome.vue` — redesign total: hero, features, CTA
- [ ] Hapus Laravel branding dan artwork "13"
- [ ] Tambah geometric SVG simple (database theme)

### Phase 3: Auth Pages
- [ ] `AuthLayout.vue` — polish card style
- [ ] `Login.vue` — consistent form with accent focus ring
- [ ] `Register.vue` — same
- [ ] `ForgotPassword.vue` — same
- [ ] `ResetPassword.vue` — same
- [ ] `ConfirmPassword.vue` — same
- [ ] `VerifyEmail.vue` — same
- [ ] `TwoFactorChallenge.vue` — polish InputOTP

### Phase 4: Dashboard
- [ ] `Dashboard.vue` — redesign total dari placeholder
- [ ] Stat cards: connections, active, queries, insights
- [ ] Recent activity feed
- [ ] Quick actions

### Phase 5: Sidebar & Navigation
- [ ] `AppSidebar.vue` — active indicator dengan accent color

### Phase 6: Feature Pages
- [ ] `Connections/Index.vue` — minor polish
- [ ] `Graph/Index.vue` — toolbar polish
- [ ] `Insights/Index.vue` — results cards polish
- [ ] `Queries/Index.vue` — editor & chat polish
- [ ] `Monitoring/Index.vue` — redesign layout

### Phase 7: Settings
- [ ] `settings/Layout.vue` — polish nav
- [ ] `settings/Profile.vue` — card polish
- [ ] `settings/Security.vue` — card polish
- [ ] `settings/Appearance.vue` — card polish
- [ ] `settings/AI.vue` — provider cards redesign

---

## Tidak Disentuh

- Backend PHP / controllers / routes / stores / composables — **zero changes**
- Logika bisnis
- File di luar frontend Vue/CSS
- File `docs/` selain `REDESIGN.md`
