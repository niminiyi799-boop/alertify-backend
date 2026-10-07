# Alertify Backend API

Base URL: `https://<your-railway-domain>`  
All protected endpoints require a JWT Bearer token:

```
Authorization: Bearer <token>
```

---

## Authentication

### POST /api/auth/register

Register a new user.

**Body (JSON)**

```json
{
    "email": "user@example.com",
    "password": "secret123",
    "password_confirmation": "secret123"
}
```

**Response 201**

```json
{
  "token": "<jwt>",
  "user": { "id": 1, "email": "user@example.com", ... }
}
```

**Errors 422** — `email` (required / invalid / already taken), `password` (required / min 8 chars), `password_confirmation` (mismatch)

---

### POST /api/auth/login

Authenticate and receive a JWT. Handled by Lexik JWT Authentication.

**Body (JSON)**

```json
{
    "email": "user@example.com",
    "password": "secret123"
}
```

**Response 200**

```json
{
    "token": "<jwt>"
}
```

---

### GET /api/auth/me

🔒 Returns the currently authenticated user.

**Response 200**

```json
{
  "user": { "id": 1, "email": "user@example.com", ... }
}
```

---

### POST /api/auth/logout

🔒 Stateless logout (client discards the token). Provided for API consistency.

**Response 200**

```json
{
    "message": "Logged out successfully"
}
```

---

### POST /api/auth/forgot-password

Request a password reset email.

**Body (JSON)**

```json
{
    "email": "user@example.com"
}
```

**Response 200** — Always returns success to prevent email enumeration.

```json
{
    "message": "Reset email sent"
}
```

---

### POST /api/auth/reset-password

Reset password using a token from the reset email.

**Body (JSON)**

```json
{
    "token": "<reset-token>",
    "password": "newpassword123"
}
```

**Response 200**

```json
{
    "message": "Password reset successfully"
}
```

**Errors 422** — `token` (required / invalid or expired), `password` (required / min 8 chars)

---

### POST /api/auth/social

OAuth token exchange stub (Google / Facebook). Not yet implemented.

**Body (JSON)**

```json
{
    "provider": "google",
    "access_token": "<oauth-token>"
}
```

**Response 501**

```json
{
    "message": "Social auth not yet implemented"
}
```

---

## Alerts

### GET /api/alerts

🔒 List alerts near a location.

**Query Parameters**
| Param | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `lat` | float | ✅ | — | Latitude |
| `lng` | float | ✅ | — | Longitude |
| `radius` | float | ❌ | `10` | Search radius in km |
| `category` / `filter` | string | ❌ | — | Filter by category. Pass `All` or omit for no filter |

**Response 200**

```json
{
    "alerts": [
        {
            "id": "uuid",
            "category": "Fire",
            "description": "...",
            "latitude": 6.5244,
            "longitude": 3.3792,
            "visibility": "public",
            "validation_count": 3,
            "media_urls": [],
            "created_at": "2026-09-13T00:00:00+00:00"
        }
    ]
}
```

**Errors 422** — `lat` / `lng` required

---

### POST /api/alerts

🔒 Create a new alert. Accepts `multipart/form-data` (to attach media) or `application/json`.

**Body**
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `category` | string | ✅ | One of the defined alert categories |
| `description` | string | ✅ | |
| `latitude` | float | ✅ | |
| `longitude` | float | ✅ | |
| `visibility` | string | ❌ | `public` (default) or `pack` |
| `media[]` | file | ❌ | Multipart only — attach one or more media files |

**Response 201**

```json
{
  "alert": { "id": "uuid", "category": "Fire", ... }
}
```

**Errors 422** — `category`, `latitude`, `longitude`, `description` (required), `visibility` (invalid value)

---

### GET /api/alerts/{id}

🔒 Get a single alert by ID.

**Response 200**

```json
{
  "alert": { "id": "uuid", ... }
}
```

**Errors 404** — Alert not found

---

### POST /api/alerts/{id}/validate

🔒 Validate an alert (upvote its credibility). Cannot validate your own alert or validate the same alert twice.

**Response 200**

```json
{
  "alert": { "id": "uuid", "validation_count": 4, ... }
}
```

**Errors**

- `404` — Alert not found
- `422` — Cannot validate your own alert / Already validated

---

### DELETE /api/alerts/{id}

🔒 Delete an alert. Only the alert's author can delete it.

**Response 204** — No content

**Errors**

- `403` — Forbidden (not the owner)
- `404` — Alert not found

---

## Notifications

### GET /api/notifications

🔒 List all notifications for the authenticated user.

**Response 200**

```json
{
    "notifications": [
        {
            "id": 1,
            "message": "...",
            "is_read": false,
            "created_at": "2026-09-13T00:00:00+00:00"
        }
    ]
}
```

---

### GET /api/notifications/unread-count

🔒 Get the number of unread notifications.

**Response 200**

```json
{
    "count": 5
}
```

---

### POST /api/notifications/mark-read

🔒 Mark all notifications as read.

**Response 200**

```json
{
    "message": "All notifications marked as read"
}
```

---

## User

### PATCH /api/user/profile

🔒 Update the authenticated user's profile.

**Body (JSON)** — all fields optional

```json
{
    "name": "John Doe",
    "email": "newemail@example.com",
    "emergency_contact": "+2348000000000"
}
```

**Response 200**

```json
{
  "user": { "id": 1, "email": "...", "name": "John Doe", ... }
}
```

**Errors 422** — `email` (invalid format / already taken)

---

### GET /api/user/notification-settings

🔒 Get the authenticated user's notification preferences.

**Response 200**

```json
{
    "settings": {
        "alert_radius_km": 10,
        "night_mode": false,
        "enabled_categories": ["Fire", "Flood", "Robbery"]
    }
}
```

---

### PATCH /api/user/notification-settings

🔒 Update notification preferences.

**Body (JSON)** — all fields optional

```json
{
    "alert_radius_km": 25,
    "night_mode": true,
    "enabled_categories": ["Fire", "Flood"]
}
```

`alert_radius_km` must be one of: `1`, `5`, `10`, `25`, `50`.

**Response 200**

```json
{
  "settings": { "alert_radius_km": 25, "night_mode": true, ... }
}
```

---

## Media

### POST /api/media/upload

🔒 Upload a single media file. Returns a publicly accessible URL.

**Body (multipart/form-data)**
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `file` | file | ✅ | JPEG, PNG, WEBP, GIF, or MP4. Max 20 MB |

**Response 201**

```json
{
    "url": "https://<base-url>/uploads/media/upload_abc123.jpg"
}
```

**Errors 422** — `file` (required / unsupported type / exceeds 20 MB)

---

## Location

### GET /api/

Health check.

**Response 200**

```json
{
    "status": "success",
    "message": "API is working!"
}
```

---

### POST /api/update-location

🔒 Update the authenticated user's current location. Saves to the database and publishes a real-time update to all Mercure subscribers for this user.

**The mobile app should call this every 5 minutes.**

**Body (JSON)**

```json
{
    "latitude": 6.5244,
    "longitude": 3.3792
}
```

**Response 200**

```json
{
    "status": "success",
    "data": {
        "latitude": 6.5244,
        "longitude": 3.3792,
        "address": "Lagos, Nigeria"
    }
}
```

**Errors 400** — Missing `latitude` or `longitude`

After saving, the backend publishes to Mercure topic `alertify/location/{userId}`:

```json
{
    "user_id": 42,
    "latitude": 6.5244,
    "longitude": 3.3792,
    "address": "Lagos, Nigeria",
    "updated_at": "2026-09-13T03:00:00+00:00"
}
```

---

### GET /api/location/subscribe-token

🔒 Issues a short-lived Mercure subscriber JWT (valid 6 hours) so the mobile client can open a persistent WebSocket/SSE connection and receive live location updates.

**Response 200**

```json
{
    "token": "<mercure-subscriber-jwt>",
    "mercure_url": "https://<host>/.well-known/mercure",
    "topic": "alertify/location/42"
}
```

**Mobile client usage (JavaScript / React Native example)**

```js
// 1. Fetch the subscriber token once (refresh every 6h)
const { token, mercure_url, topic } = await api.get(
    "/api/location/subscribe-token",
);

// 2. Open a persistent EventSource connection
const url = new URL(mercure_url);
url.searchParams.append("topic", topic);

const es = new EventSource(url.toString(), {
    headers: { Authorization: `Bearer ${token}` },
});

es.onmessage = (event) => {
    const { latitude, longitude, address } = JSON.parse(event.data);
    // update map marker
};

// 3. POST /api/update-location every 5 minutes (from the device sending its own location)
setInterval(
    async () => {
        const { latitude, longitude } = await getCurrentPosition();
        await api.post("/api/update-location", { latitude, longitude });
    },
    5 * 60 * 1000,
);
```

---

### POST /api/notify-nearby

🔒 Find the 5 nearest users (by location) and send them an email notification.

**Body (JSON)**

```json
{
    "latitude": 6.5244,
    "longitude": 3.3792
}
```

**Response 200**

```json
{
    "status": "emails sent",
    "recipients": ["a@example.com", "b@example.com"]
}
```

**Errors 404** — No nearby users found

---

## Real-Time Architecture (Mercure)

```
Mobile App ──POST /api/update-location (every 5 min)──► Symfony API
                                                              │
                                                    saves to DB + publishes
                                                              │
                                                              ▼
Mobile App ◄──WebSocket/SSE (persistent)──────────── Mercure Hub
             topic: alertify/location/{userId}
```

The Mercure hub runs as a sidecar process inside the same Docker container.
Set these environment variables on Railway:

| Variable             | Description                                                                         |
| -------------------- | ----------------------------------------------------------------------------------- |
| `MERCURE_URL`        | Internal URL Symfony publishes to, e.g. `http://localhost:3000/.well-known/mercure` |
| `MERCURE_PUBLIC_URL` | Public URL clients connect to, e.g. `https://<railway-domain>/.well-known/mercure`  |
| `MERCURE_JWT_SECRET` | Shared secret between Symfony and the Mercure hub — use a long random string        |
| `MERCURE_PORT`       | Port for the Mercure hub inside the container (default: `3000`)                     |

---

## Error Format

Validation errors return `422` with this shape:

```json
{
    "errors": {
        "field_name": ["Error message"]
    }
}
```

General errors return the appropriate HTTP status with:

```json
{
    "message": "Human-readable error"
}
```
