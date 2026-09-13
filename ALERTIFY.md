# Alertify — Frontend Architecture & Backend Integration Guide

> **Purpose of this document:** A complete reference for the Symfony PHP backend developer.
> Every screen, component, UI function, data shape, and required API endpoint is documented here.

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Tech Stack](#2-tech-stack)
3. [File Structure](#3-file-structure)
4. [Navigation Flow](#4-navigation-flow)
5. [Authentication Module](#5-authentication-module)
   - [Splash Screen](#51-splash-screen)
   - [Onboarding Screen](#52-onboarding-screen)
   - [Login Screen](#53-login-screen)
   - [Register Screen](#54-register-screen)
6. [Main App Module](#6-main-app-module)
   - [Map Screen](#61-map-screen)
   - [App Header](#62-app-header)
   - [Filter Dropdown](#63-filter-dropdown)
7. [Sheets (Push-up Modals)](#7-sheets-push-up-modals)
   - [Profile & Settings Sheet](#71-profile--settings-sheet)
   - [Notifications & Settings Sheet](#72-notifications--settings-sheet)
   - [Create Alert Sheet](#73-create-alert-sheet)
   - [Success Toast](#74-success-toast)
8. [Shared Components](#8-shared-components)
   - [BottomSheet](#81-bottomsheet)
   - [AlertMap](#82-alertmap)
9. [Data Models](#9-data-models)
10. [Required API Endpoints](#10-required-api-endpoints)
11. [Auth Strategy](#11-auth-strategy)
12. [Alert Categories Reference](#12-alert-categories-reference)

---

## 1. Project Overview

**Alertify** is a real-time community safety alert app. Users can:

- View a live map feed of nearby incidents filtered by category
- Create and report new alerts with location, description, media, and visibility settings
- Configure their notification radius and preferred alert categories
- Build a community trust score through contributions and validations
- Trigger an SOS that silently contacts emergency contacts (future feature)

The frontend is a React Native app (Expo SDK 56) using file-based routing via `expo-router`.
The backend will be a **Symfony PHP REST API** communicating over HTTPS with JSON payloads.
Authentication is JWT-based (Bearer token).

---

## 2. Tech Stack

| Layer | Technology |
|---|---|
| Mobile Framework | React Native 0.85 + Expo SDK 56 |
| Routing | expo-router (file-based, Stack navigator) |
| Language | TypeScript |
| State | Local `useState` per screen — no global store yet |
| Animations | React Native `Animated` API (native driver) |
| Maps | Placeholder (swap in `react-native-maps` + Google Maps API) |
| Backend | Symfony PHP (REST API, JSON) |
| Auth | JWT Bearer token |
| Media Upload | Multipart form-data |

---

## 3. File Structure

```
src/
├── app/
│   ├── _layout.tsx              # Root Stack — entry point, dark status bar
│   ├── index.tsx                # Splash screen
│   ├── onboarding.tsx           # 3-slide feature carousel
│   ├── explore.tsx              # Legacy redirect stub
│   ├── auth/
│   │   ├── login.tsx            # Login form
│   │   └── register.tsx         # Registration form
│   └── (app)/
│       ├── _layout.tsx          # Authenticated stack wrapper
│       └── index.tsx            # Main map screen (root of auth'd app)
│
└── components/
    └── alertify/
        ├── app-header.tsx           # Top bar: logo, bell, avatar
        ├── alert-map.tsx            # Map view (placeholder → react-native-maps)
        ├── filter-dropdown.tsx      # Category filter pill + dropdown
        ├── bottom-sheet.tsx         # Reusable animated push-up sheet base
        ├── profile-sheet.tsx        # Profile & Settings modal
        ├── notifications-sheet.tsx  # Notification preferences modal
        ├── create-alert-sheet.tsx   # Create new alert form modal
        └── success-toast.tsx        # Animated confirmation toast
```

---

## 4. Navigation Flow

```
/ (index.tsx — Splash)
├── → /onboarding
│       └── → /auth/login
├── → /auth/login
│       ├── → /auth/register
│       └── → /(app)        ← replace (clears back stack)
└── → /auth/register
        ├── → /auth/login
        └── → /(app)        ← replace (clears back stack)

/(app)/index.tsx — Map screen (authenticated root)
  Sheets rendered over the map (no route change):
  ├── ProfileSheet       (triggered by avatar tap)
  ├── NotificationsSheet (triggered by bell tap)
  ├── CreateAlertSheet   (triggered by FAB '+')
  └── SuccessToast       (auto-shown after alert saved, 3s)
```

**On boot:** The splash screen currently navigates manually. When the backend is ready, replace the `useEffect` in `src/app/index.tsx` with an async auth-check:
1. Read stored JWT from secure storage
2. Call `GET /api/auth/me` to validate token
3. If valid → `router.replace('/(app)')`, else stay on splash

---

## 5. Authentication Module

### 5.1 Splash Screen
**File:** `src/app/index.tsx`

| Element | Action |
|---|---|
| "Get started" button | `router.push('/onboarding')` |
| "Login" button | `router.push('/auth/login')` |

**Backend hook needed:**
On mount, check for a stored JWT. If valid, skip splash and redirect to `/(app)`.

```
// Future implementation slot — src/app/index.tsx useEffect
GET /api/auth/me
Authorization: Bearer <token>
→ 200: redirect to /(app)
→ 401: stay on splash
```

---

### 5.2 Onboarding Screen
**File:** `src/app/onboarding.tsx`

Pure UI — no API calls. Shows 3 feature slides in a horizontal `FlatList` with pagination dots.

| Function | Description |
|---|---|
| `handleNext()` | Advances to the next slide. On the last slide, navigates to `/auth/login` |
| `onViewableItemsChanged` | FlatList callback that updates `activeIndex` state to drive the dot indicators |

**Slides content (static):**

| # | Title | Description |
|---|---|---|
| 1 | Real-Time Map Feed | Filter live incidents by category on a map |
| 2 | Create & Validate Alerts | Report incidents and validate community alerts |
| 3 | Instant SOS | Long-press SOS to alert emergency contacts |

---

### 5.3 Login Screen
**File:** `src/app/auth/login.tsx`

**Local state:**

| State | Type | Description |
|---|---|---|
| `email` | `string` | Controlled input for user email |
| `password` | `string` | Controlled input for password |
| `showPassword` | `boolean` | Toggles `secureTextEntry` on password field |

**Functions:**

#### `handleLogin()` *(to be implemented — currently calls `router.replace('/(app)')`)*

Submits credentials to the backend.

```
POST /api/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "plaintext_password"
}

→ 200 OK:
{
  "token": "eyJ...",
  "user": {
    "id": 1,
    "name": "Tosin Reno",
    "email": "user@example.com",
    "trust_score": 28,
    "contribution_count": 1,
    "avatar_url": null,
    "emergency_contact": "+1234567890"
  }
}

→ 401 Unauthorized:
{ "message": "Invalid credentials" }

→ 422 Unprocessable:
{ "errors": { "email": ["Required"], "password": ["Required"] } }
```

**Post-login:** Store JWT securely (e.g., `expo-secure-store`), cache the user object, then `router.replace('/(app)')`.

**Social auth buttons (Facebook, Google):** UI is present, OAuth flows are not yet implemented. Backend will need OAuth token exchange endpoints.

```
POST /api/auth/social
{
  "provider": "google" | "facebook",
  "access_token": "<provider_access_token>"
}
→ Same response shape as login
```

**Forgot password link:** Taps currently do nothing. Backend endpoint needed:

```
POST /api/auth/forgot-password
{ "email": "user@example.com" }
→ 200: { "message": "Reset email sent" }
```

---

### 5.4 Register Screen
**File:** `src/app/auth/register.tsx`

**Local state:**

| State | Type | Description |
|---|---|---|
| `email` | `string` | User email |
| `password` | `string` | Password |
| `confirmPassword` | `string` | Client-side match validation only |
| `showPassword` | `boolean` | Toggles password visibility |
| `showConfirm` | `boolean` | Toggles confirm password visibility |

**Client-side validation:**
- If `confirmPassword !== password` and both have content, shows inline error text "Passwords don't match"
- Submit button is currently enabled regardless (add disabled check when wiring API)

#### `handleRegister()` *(to be implemented — currently calls `router.replace('/(app)')`)*

```
POST /api/auth/register
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "plaintext_password",
  "password_confirmation": "plaintext_password"
}

→ 201 Created:
{
  "token": "eyJ...",
  "user": {
    "id": 2,
    "name": null,
    "email": "user@example.com",
    "trust_score": 0,
    "contribution_count": 0,
    "avatar_url": null,
    "emergency_contact": null
  }
}

→ 422 Unprocessable:
{
  "errors": {
    "email": ["Already taken"],
    "password": ["Must be at least 8 characters"]
  }
}
```

---

## 6. Main App Module

### 6.1 Map Screen
**File:** `src/app/(app)/index.tsx`

This is the authenticated root. It renders the map full-screen and layers all UI elements on top via an absolutely positioned overlay.

**Local state:**

| State | Type | Default | Description |
|---|---|---|---|
| `profileOpen` | `boolean` | `false` | Controls Profile sheet visibility |
| `notifOpen` | `boolean` | `false` | Controls Notifications sheet visibility |
| `createOpen` | `boolean` | `false` | Controls Create Alert sheet visibility |
| `toastVisible` | `boolean` | `false` | Controls Success toast visibility |
| `filterOpen` | `boolean` | `false` | Controls filter dropdown open/close |
| `activeFilter` | `string` | `"All"` | The currently selected alert category filter |

**Functions:**

#### `handleAlertSaved()`
Called by `CreateAlertSheet` after a successful alert save.
1. Closes the Create Alert sheet (`setCreateOpen(false)`)
2. Shows the success toast (`setToastVisible(true)`)
3. Auto-hides the toast after 3000ms (`setTimeout`)

**Backend hook — on mount (to be implemented):**

```
GET /api/alerts?filter=All&lat=<lat>&lng=<lng>&radius=<radius_km>
Authorization: Bearer <token>

→ 200:
{
  "alerts": [
    {
      "id": "uuid",
      "category": "Crime",
      "lat": 40.6659,
      "lng": -74.2673,
      "description": "...",
      "visibility": "public",
      "created_at": "2026-07-26T10:00:00Z",
      "user": { "id": 1, "name": "Tosin", "trust_score": 28 },
      "media": [],
      "validation_count": 3
    }
  ]
}
```

The `activeFilter` value should be passed as the `filter` query param. `"All"` means no category filter.

---

### 6.2 App Header
**File:** `src/components/alertify/app-header.tsx`

**Props:**

| Prop | Type | Description |
|---|---|---|
| `onProfilePress` | `() => void` | Opens the Profile sheet |
| `onNotifPress` | `() => void` | Opens the Notifications sheet |

**UI elements:**
- **Logo** — static branding (no action)
- **Bell icon** — has a red unread badge dot. When wired up, the badge should show/hide based on unread notification count from the API
- **Avatar circle** — shows the user's initial. When wired up, display `avatar_url` image if present

**Backend hook — unread notifications count:**

```
GET /api/notifications/unread-count
Authorization: Bearer <token>
→ { "count": 4 }
```

---

### 6.3 Filter Dropdown
**File:** `src/components/alertify/filter-dropdown.tsx`

**Props:**

| Prop | Type | Description |
|---|---|---|
| `value` | `string` | Currently active filter value |
| `open` | `boolean` | Whether the dropdown is expanded |
| `onToggle` | `() => void` | Called when the pill is tapped |
| `onSelect` | `(value: string) => void` | Called when a menu item is tapped |

**Available filter options:**

| Value | Emoji | Maps to API `category` param |
|---|---|---|
| `All` | 🗺️ | *(no filter)* |
| `Crime` | 🔫 | `"Crime"` |
| `Accident` | 🚗 | `"Accident"` |
| `Attack` | ⚡ | `"Attack"` |
| `Missing Person` | 🧍 | `"Missing Person"` |
| `Fire` | 🔥 | `"Fire"` |

When a filter is selected the map should re-fetch alerts with the new category. Pass to:
```
GET /api/alerts?category=Crime&lat=...&lng=...&radius=...
```

---

## 7. Sheets (Push-up Modals)

All sheets use the shared `BottomSheet` base component (see [section 8.1](#81-bottomsheet)). They slide up from the bottom with an animated backdrop.

---

### 7.1 Profile & Settings Sheet
**File:** `src/components/alertify/profile-sheet.tsx`

**Props:**

| Prop | Type | Description |
|---|---|---|
| `visible` | `boolean` | Controls sheet visibility |
| `onClose` | `() => void` | Called when backdrop tapped or Cancel pressed |

**Local state:**

| State | Type | Description |
|---|---|---|
| `name` | `string` | User's display name (pre-filled from cached user) |
| `email` | `string` | User's email (pre-filled) |
| `emergency` | `string` | Emergency contact phone number |

**Displayed data (read from backend user object):**
- Avatar initial (first letter of name)
- Full name
- Trust Score (e.g., `28`)
- Contribution count (e.g., `1`)

**Functions:**

#### `handleSave()` *(to be implemented — currently calls `onClose()`)*

```
PATCH /api/user/profile
Authorization: Bearer <token>
Content-Type: application/json

{
  "name": "Tosin Reno",
  "email": "tosin@example.com",
  "emergency_contact": "+1234567890"
}

→ 200:
{
  "user": {
    "id": 1,
    "name": "Tosin Reno",
    "email": "tosin@example.com",
    "emergency_contact": "+1234567890",
    "trust_score": 28,
    "contribution_count": 1,
    "avatar_url": null
  }
}

→ 422: { "errors": { "email": ["Already taken"] } }
```

**Sub-components:**

- `SectionHeader({ title, subtitle })` — renders a labelled section with icon
- `Field({ label, value, onChangeText, placeholder, keyboardType })` — reusable labelled `TextInput`

---

### 7.2 Notifications & Settings Sheet
**File:** `src/components/alertify/notifications-sheet.tsx`

**Props:**

| Prop | Type | Description |
|---|---|---|
| `visible` | `boolean` | Controls sheet visibility |
| `onClose` | `() => void` | Closes the sheet |

**Local state:**

| State | Type | Default | Description |
|---|---|---|---|
| `radius` | `number` | `5` | Alert radius in km (options: 1, 5, 10, 25, 50) |
| `nightMode` | `boolean` | `false` | Suppress notifications during sleep hours |
| `enabledCategories` | `Record<string, boolean>` | See below | Which alert categories notify the user |

**Default `enabledCategories`:**
```json
{
  "Crime": true,
  "Accident": true,
  "Fire": false,
  "Missing Person": true,
  "Attack": false
}
```

**Functions:**

#### `toggleCategory(cat: string)`
Flips the boolean for the given category in `enabledCategories`.
No API call yet — called live on each chip press.

#### `handleSave()` *(to be implemented — currently calls `onClose()`)*

```
PATCH /api/user/notification-settings
Authorization: Bearer <token>
Content-Type: application/json

{
  "alert_radius_km": 5,
  "night_mode": false,
  "enabled_categories": ["Crime", "Accident", "Missing Person"]
}

→ 200:
{
  "settings": {
    "alert_radius_km": 5,
    "night_mode": false,
    "enabled_categories": ["Crime", "Accident", "Missing Person"]
  }
}
```

**On sheet open — prefill from backend:**

```
GET /api/user/notification-settings
Authorization: Bearer <token>

→ 200: same shape as PATCH response body
```

---

### 7.3 Create Alert Sheet
**File:** `src/components/alertify/create-alert-sheet.tsx`

**Props:**

| Prop | Type | Description |
|---|---|---|
| `visible` | `boolean` | Controls sheet visibility |
| `onClose` | `() => void` | Closes without saving |
| `onSave` | `() => void` | Called after a successful API save |

**Local state:**

| State | Type | Default | Description |
|---|---|---|---|
| `category` | `string` | `""` | Alert category (from chips or free text) |
| `location` | `string` | `"Using current GPS location"` | Location string or coordinates |
| `description` | `string` | `""` | Free-text description of the incident |
| `visibility` | `"Pack" \| "Public"` | `"Public"` | Who can see the alert |

**Functions:**

#### `handleSave()`
Current behaviour:
1. Calls `onSave()` prop (which triggers the success toast on the map screen)
2. Resets `category`, `description`, `visibility` to defaults

**When wired to API, add before step 1:**

```
POST /api/alerts
Authorization: Bearer <token>
Content-Type: multipart/form-data

{
  "category": "Crime",
  "description": "Suspicious activity near the park",
  "latitude": 40.6659,
  "longitude": -74.2673,
  "visibility": "public",        // "public" | "pack"
  "media[]": <File>              // optional, one or more files
}

→ 201 Created:
{
  "alert": {
    "id": "uuid-abc",
    "category": "Crime",
    "description": "Suspicious activity near the park",
    "lat": 40.6659,
    "lng": -74.2673,
    "visibility": "public",
    "created_at": "2026-07-26T12:00:00Z",
    "media": ["https://cdn.example.com/media/abc.jpg"],
    "validation_count": 0,
    "user": { "id": 1, "name": "Tosin", "trust_score": 28 }
  }
}

→ 422:
{
  "errors": {
    "category": ["Required"],
    "latitude": ["Required"],
    "longitude": ["Required"]
  }
}
```

**"Change" location button:** Tapping this should open a location picker (map pin drag or address search) — not yet implemented. When ready, the picked coordinates should replace the `location` string and be passed as `latitude` / `longitude` in the POST body.

**Save button disabled state:** The Save button has `disabled={!category}` — it is greyed out until a category is selected or typed.

**Visibility values:**

| UI Label | API value |
|---|---|
| `Pack` | `"pack"` — visible only to the user's trusted contacts |
| `Public` | `"public"` — visible to all users within radius |

---

### 7.4 Success Toast
**File:** `src/components/alertify/success-toast.tsx`

**Props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `visible` | `boolean` | — | Controls show/hide with animation |
| `message` | `string` | — | Message text displayed next to the check icon |
| `bottomInset` | `number` | `0` | Safe area bottom offset so toast clears the home indicator |

No API interaction. Purely presentational. Slides up with a spring animation when `visible` becomes `true` and slides back down when `false`.

Current message: `"Your alert has been posted to the map."`

---

## 8. Shared Components

### 8.1 BottomSheet
**File:** `src/components/alertify/bottom-sheet.tsx`

Reusable animated push-up modal. Used by all three sheets.

**Props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `visible` | `boolean` | — | Controls open/close with animation |
| `onClose` | `() => void` | — | Called when the backdrop is tapped or Android back is pressed |
| `children` | `React.ReactNode` | — | Sheet content |
| `heightFraction` | `number` | `0.82` | Sheet height as a fraction of screen height (0.0–1.0) |
| `style` | `ViewStyle` | — | Optional extra styles on the sheet container |

**Animation behaviour:**
- Open: `translateY` goes from `sheetHeight` → `0` over 320ms with `Easing.out(cubic)`. Backdrop fades in over 280ms.
- Close: `translateY` goes from `0` → `sheetHeight` over 260ms with `Easing.in(cubic)`. Backdrop fades out over 220ms.
- Both use the native animation driver (`useNativeDriver: true`).

---

### 8.2 AlertMap
**File:** `src/components/alertify/alert-map.tsx`

**Current state:** Static placeholder rendering fake roads, place labels, and hardcoded pins.

**Props:**

| Prop | Type | Description |
|---|---|---|
| `activeFilter` | `string` | The selected category filter — not yet used to filter the mock pins |

**Replacing with `react-native-maps`:**

When integrating real maps:
1. Install: `npx expo install react-native-maps`
2. Replace `AlertMap` internals with a `<MapView>` component set to dark mode (`customMapStyle`)
3. Render `<Marker>` components for each alert from the API
4. Re-fetch alerts when `activeFilter` changes
5. The component receives `alerts: Alert[]` as an additional prop from `MapScreen`

**Pin types:**

| `type` | Visual | Meaning |
|---|---|---|
| `"warning"` (large) | Large red circle with pulse ring | High-severity / recent alert |
| `"warning"` (small) | Small bordered circle | Standard alert |
| `"pin"` | Map pin emoji | User location or POI |

---

## 9. Data Models

These are the TypeScript interfaces the frontend expects. Match your Symfony serialization to these shapes.

```typescript
interface User {
  id: number;
  name: string | null;
  email: string;
  trust_score: number;
  contribution_count: number;
  avatar_url: string | null;
  emergency_contact: string | null;
}

interface Alert {
  id: string;             // UUID
  category: AlertCategory;
  description: string;
  lat: number;
  lng: number;
  visibility: 'public' | 'pack';
  created_at: string;     // ISO 8601
  user: Pick<User, 'id' | 'name' | 'trust_score'>;
  media: string[];        // array of CDN URLs
  validation_count: number;
}

interface NotificationSettings {
  alert_radius_km: number;
  night_mode: boolean;
  enabled_categories: AlertCategory[];
}

interface AuthResponse {
  token: string;
  user: User;
}

type AlertCategory =
  | 'Crime'
  | 'Accident'
  | 'Fire'
  | 'Missing Person'
  | 'Attack'
  | 'Other';
```

---

## 10. Required API Endpoints

Complete list of all endpoints the frontend needs, in priority order.

### Authentication

| Method | Endpoint | Description | Auth required |
|---|---|---|---|
| `POST` | `/api/auth/login` | Email + password login, returns JWT + user | No |
| `POST` | `/api/auth/register` | Create account, returns JWT + user | No |
| `GET` | `/api/auth/me` | Validate token, returns user object | Yes |
| `POST` | `/api/auth/logout` | Invalidate token | Yes |
| `POST` | `/api/auth/forgot-password` | Send reset email | No |
| `POST` | `/api/auth/reset-password` | Submit new password with reset token | No |
| `POST` | `/api/auth/social` | OAuth token exchange (Google / Facebook) | No |

### Alerts

| Method | Endpoint | Description | Auth required |
|---|---|---|---|
| `GET` | `/api/alerts` | Fetch alerts (filter by category, lat, lng, radius) | Yes |
| `POST` | `/api/alerts` | Create new alert (multipart for media) | Yes |
| `GET` | `/api/alerts/{id}` | Get single alert detail | Yes |
| `POST` | `/api/alerts/{id}/validate` | Upvote/validate an alert | Yes |
| `DELETE` | `/api/alerts/{id}` | Delete own alert | Yes |

### User / Profile

| Method | Endpoint | Description | Auth required |
|---|---|---|---|
| `PATCH` | `/api/user/profile` | Update name, email, emergency contact | Yes |
| `GET` | `/api/user/notification-settings` | Fetch notification preferences | Yes |
| `PATCH` | `/api/user/notification-settings` | Save notification preferences | Yes |

### Notifications

| Method | Endpoint | Description | Auth required |
|---|---|---|---|
| `GET` | `/api/notifications` | List recent notifications | Yes |
| `GET` | `/api/notifications/unread-count` | Count of unread notifications | Yes |
| `POST` | `/api/notifications/mark-read` | Mark all as read | Yes |

### Media

| Method | Endpoint | Description | Auth required |
|---|---|---|---|
| `POST` | `/api/media/upload` | Upload media file, returns CDN URL | Yes |

---

## 11. Auth Strategy

**Token storage:** Use `expo-secure-store` on native (`SecureStore.setItemAsync('jwt', token)`). On web, use `localStorage` (or `sessionStorage`).

**Request headers:** Every authenticated request must include:
```
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
Content-Type: application/json
```

**Token expiry handling:**
- If any API call returns `401`, clear the stored token and redirect to `/auth/login`
- Optionally implement silent refresh using a refresh token endpoint

**Recommended Symfony packages:**
- `lexik/jwt-authentication-bundle` for JWT generation and validation
- `gesdinet/jwt-refresh-token-bundle` for refresh tokens

---

## 12. Alert Categories Reference

These are the exact string values used in both the frontend UI and expected in API payloads. They must match exactly (case-sensitive).

| Value | UI Label | Filter Icon |
|---|---|---|
| `"Crime"` | Crime | 🔫 |
| `"Accident"` | Accident | 🚗 |
| `"Attack"` | Attack | ⚡ |
| `"Missing Person"` | Missing Person | 🧍 |
| `"Fire"` | Fire | 🔥 |
| `"Other"` | Other | *(create form only)* |

The `"All"` option in the filter UI means **no category filter** — do not send it as a category value to the API.

---

*Last updated: July 26, 2026*
*Frontend stack: Expo SDK 56 · React Native 0.85 · expo-router 56.2*
