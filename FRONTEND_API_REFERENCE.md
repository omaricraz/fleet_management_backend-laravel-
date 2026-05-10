# Frontend API Reference — Laravel Sanctum (`/api/v1`)

**Default base:** `{APP_URL}/api/v1` (Laravel’s `routes/api.php` under the `api` prefix + `v1` group.)

---

## Conventions

### Success envelope

```json
{ "success": true, "message": "...", "data": {} }
```

Common statuses: **200**, **201** (creation).

### Error envelope (`controllers` → `ApiResponse::errorResponse`)

```json
{ "success": false, "message": "...", "errors": {} }
```

`errors` is often `{}`; validation uses the structure below.

### Validation (`422`)

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": { "field": ["message"] }
}
```

### Authentication (`401`)

```json
{ "success": false, "message": "Unauthenticated" | "Invalid credentials", "errors": {} }
```

Login failures use **`Invalid credentials`**.

### Role / tenant / platform (`403`)

Examples: **`Forbidden`**, **`Tenant context is required`**, **`Tenant user account required`**, **`Tenant not found`**.

### Model not found (`404`)

**`Resource not found`** or **`Not found`** depending on exception type.

### Insufficient inventory (`422`)

```json
{
  "success": false,
  "message": "Insufficient inventory.",
  "details": {
    "car_id": 0,
    "product_id": 0,
    "available": "...",
    "requested": "..."
  }
}
```

### Headers

| Header | Usage |
|--------|--------|
| `Accept` | `application/json` recommended |
| `Content-Type` | `application/json` for JSON bodies |
| `Authorization` | `Bearer <token>` — required wherever `auth:sanctum` applies |

---

## Middleware cheat sheet

| Stack | Meaning |
|--------|---------|
| _(none)_ | Public |
| `auth:sanctum` | Logged-in user |
| `auth:sanctum` + `platform.admin` | `User.is_platform_admin === true` |
| `auth:sanctum` + `tenant` | Non–platform user with valid `tenant_id` (tenant injected on request) |
| `tenant` + `role:admin,manager` | Roles `admin` or `manager` |
| `tenant` + `role:driver` | Role `driver` |
| `tenant` + `role:admin,manager,driver` | Any of those three |

**Tenant route models** (`Trip`, `User`, `Car`, … with `ResolvesTenantRouteBinding`): ID must belong to **`tenant_id`** on the authenticated user → wrong tenant returns **404**.

---

## Pagination (list endpoints)

Trait **`PaginatesTenantResources`**: response `data` shape:

```json
{
  "items": [ /* JsonResource arrays */ ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 0,
    "last_page": 1
  }
}
```

**Query parameters** (all optional unless noted):

| Param | Default | Notes |
|-------|---------|--------|
| `per_page` | `15` | Clamped **1–100** |
| `sort` | per resource | Must be in that endpoint’s allow-list or falls back |
| `direction` | `asc` | `desc` or else treated as `asc` |
| `search` | `""` | `LIKE %search%` on that module’s search columns |

**Search / sort by resource**

| Resource | `search` columns | `sort` allow-list |
|----------|------------------|-------------------|
| Zones | `city`, `name` | `id`, `city`, `name`, `number_of_stores`, `created_at`, `updated_at` |
| Cars | `model`, `plate_number`, `color` | `id`, `model`, `plate_number`, `created_at`, `updated_at` |
| Drivers | `full_name`, `phone` | `id`, `full_name`, `phone`, `created_at`, `updated_at` |
| Products | `item`, `type` | `id`, `item`, `type`, `price`, `created_at`, `updated_at` |
| Customers | `full_name`, `phone` | `id`, `full_name`, `phone`, `created_at`, `updated_at` |
| Users (tenant) | `name`, `email`, `role` | `id`, `name`, `email`, `role`, `created_at`, `updated_at` |
| Platform tenants | `name`, `subscription_plan` | `id`, `name`, `subscription_plan`, `created_at`, `updated_at` |
| Platform users | `name`, `email`, `role` | `id`, `name`, `email`, `role`, `tenant_id`, `created_at`, `updated_at` |

---

## 1. Auth

### 1.1 Login

| | |
|--|--|
| **Method / path** | `POST /api/v1/auth/login` |
| **Auth** | No |

**Body**

| Field | Type | Req | Rules |
|-------|------|-----|--------|
| `email` | string | ✓ | email |
| `password` | string | ✓ | |

**Success `200`** — `data`:

```json
{
  "token": "plainTextSanctumToken",
  "user": {
    "id": 1,
    "name": "...",
    "email": "...",
    "role": "admin|manager|driver",
    "tenant_id": 1,
    "is_platform_admin": false
  }
}
```

(**`UserResource`** shape.)

**Error `401`** — invalid credentials.

#### Copy-paste: fetch

```javascript
const BASE = "https://your-app.test";
const res = await fetch(`${BASE}/api/v1/auth/login`, {
  method: "POST",
  headers: {
    Accept: "application/json",
    "Content-Type": "application/json",
  },
  body: JSON.stringify({ email: "you@corp.com", password: "********" }),
});
const json = await res.json();
if (!json.success) throw new Error(json.message);
const bearer = json.data.token;
```

#### Copy-paste: axios

```javascript
import axios from "axios";
const { data } = await axios.post(`${BASE}/api/v1/auth/login`, {
  email: "you@corp.com",
  password: "********",
}, { headers: { Accept: "application/json" } });
const bearer = data.data.token;
```

---

### 1.2 Logout

`POST /api/v1/auth/logout` · **`auth:sanctum`** · body optional · **`200`** `data: {}` (current token revoked).

---

### 1.3 Me

`GET /api/v1/auth/me` · **`auth:sanctum`** · **`200`** `data`: **`UserResource`**.

---

## 2. Platform — tenants (`/api/v1/platform/tenants`)

**`auth:sanctum`** + **`platform.admin`**

REST: `GET` index, `POST` store, `GET {tenant}`, `PUT|PATCH {tenant}`, `DELETE {tenant}`.

**Store body:** `name` (required string), `subscription_plan` (optional string).

**Update:** partial with `sometimes`/`required` on `name`; `subscription_plan` nullable.

**`data`:** **`TenantResource`**: `id`, `name`, `subscription_plan`, `created_at`, `updated_at`.

---

## 3. Platform — users (`/api/v1/platform/users`)

**`auth:sanctum`** + **`platform.admin`** · `{id}` numeric.

**Index** — query validated: `tenant_id` (nullable, `exists:tenant,id`), `per_page` (1–100). Plus standard `search` / `sort` / `direction`.

**Store body:** `tenant_id` (required), `name`, `email` (unique), `password` (min 8), `role` in `admin|manager|driver`.

**Update** (`PUT|PATCH /platform/users/{id}`): optional `tenant_id`, `name`, `email`, `password` (null/empty → unchanged), `role`.

**Response:** **`UserResource`**.

**Delete:** cannot delete self → **403** `You cannot delete your own account`.

---

## 4. Tenant — Zones (`/api/v1/zones`)

**`auth:sanctum`**, **`tenant`**, **`role:admin,manager`**

Full `apiResource`. **Store:** `city`, `name` required; `number_of_stores` optional int ≥0.

**`data`:** **`ZoneResource`** (`id`, `tenant_id`, `name`, `city`, `number_of_stores`, timestamps, `deleted_at`).

---

## 5. Tenant — Cars (`/api/v1/cars`)

Same middleware as zones.

**Store:** `model`, `plate_number` (unique per tenant), optional capacity/fuel/`color` fields.

**`data`:** **`CarResource`**.

---

## 6. Tenant — Drivers (`/api/v1/drivers`)

Same middleware.

**Store:** `full_name`, `phone` required; `zone_id` optional (tenant zone).

**`data`:** **`DriverResource`** (includes nested **`zone`** when loaded).

---

## 7. Tenant — Products (`/api/v1/products`)

Same middleware.

**Store:** `item` required; `type`, `price`, `unit_volume`, `unit_weight` optional.

**`data`:** **`ProductResource`**.

---

## 8. Tenant — Customers (`/api/v1/customers`)

Same middleware.

**Store:** `full_name`, `phone`; optional `zone_id`, `latitude`, `longitude`.

**`data`:** **`CustomerResource`** (nested **`zone`** when loaded).

---

## 9. Tenant — Users (`/api/v1/users`)

| Action | Middleware |
|--------|------------|
| `GET /users`, `GET /users/{user}` | + `role:admin,manager` |
| `POST`, `PUT|PATCH`, `DELETE` | + **`role:admin`** only |

**Store (admin):** `name`, `email` (unique), `password` (≥8), `role` (`admin|manager|driver`). `tenant_id` set server-side.

**Update:** `sometimes` updates for `name`, `email`, `password`, `role`.

**List/show:** **`UserResource`**, paginated list uses `items` + `meta`.

---

## 10. Inventory (tenant admin/manager)

Unless noted: **`auth:sanctum`**, **`tenant`**, **`role:admin,manager`**.

### `GET /api/v1/inventory`

Query (validated):

| Field | Rules |
|-------|--------|
| `car_id` | optional int, tenant car |
| `product_id` | optional int, tenant product |
| `low_stock` | optional `0` or `1` |
| `search` | nullable string |

**`data`:** fleet snapshot — array of `{ car_id, car_name, items: [{ product_id, product_name, quantity }] }` (quantities as normalized strings).

---

### `GET /api/v1/inventory/alerts`

**`data`:** **`InventoryService::getAlerts`** — includes e.g. `low_stock`, `zero_stock`, `negative_variance_recent` (see backend for exact keys).

---

### `GET /api/v1/cars/{car}/inventory`

Query: `limit` optional int **1–200** (history length).

**`data`:**

```json
{
  "car": { "...": "CarResource" },
  "snapshot": [{ "product_id": 1, "product_name": "", "quantity": "" }],
  "transactions": [
    {
      "id": 1,
      "product_id": 1,
      "product_name": "",
      "quantity": "",
      "type": "",
      "trip_id": null,
      "sale_id": null,
      "before_qty": null,
      "after_qty": null,
      "created_at": ""
    }
  ]
}
```

---

### `POST /api/v1/inventory/opening-balance`

**Form request:** **`InventoryOpeningCountRequest`**

Body:

| Field | Type | Req | Rules |
|-------|------|-----|-------|
| `trip_id` | int | ✗ | nullable, tenant trip exists |
| `items` | array | ✓ | min 1 |
| `items.*.car_id` | int | ✓ | tenant car |
| `items.*.product_id` | int | ✓ | tenant product |
| `items.*.actual_quantity` | number | ✓ | (mapped to ledger quantity) |

Multiple rows may share one **`car_id`**; backend groups by car and calls **`applyOpeningBalance`** per car.

**Success `200`:** message `Opening balance applied successfully`; **`data`:** array of

```json
{ "car_id": 1, "lines": [{ "product_id": 1, "before_qty": "", "after_qty": "", "transaction_id": 1 }] }
```

---

### `POST /api/v1/inventory/load` · `POST /api/v1/inventory/manual-sale`

**`InventoryCarBatchRequest`:**

| Path | Rules |
|------|--------|
| `cars` | required array ≥1 |
| `cars.*.car_id` | required, tenant car |
| `cars.*.trip_id` | nullable int, tenant trip |
| `cars.*.items` | required array ≥1 |
| `cars.*.items.*.product_id` | required |
| `cars.*.items.*.quantity` | required numeric `> 0` |

Responses merge `{ car_id, product_id }` with operation payload (`before_qty`, `after_qty`, `transaction_id`, …).

---

### `POST /api/v1/inventory/close-count`

**`InventoryClosingCountRequest`**

| Field | Type | Req |
|-------|------|-----|
| `trip_id` | int | ✗ nullable, tenant trip |
| `car_id` | int | ✓ tenant car |
| `items` | array | ✓ ≥1 |
| `items.*.product_id` | int | ✓ |
| `items.*.actual_quantity` | number | ✓ |

**Success:** `Closing count applied successfully`; **`data`:** rows with `product_id`, `expected_quantity`, `actual_quantity`, `variance`, `before_qty`, `after_qty`, `transaction_id`.

**Trip close:** **`POST /trips/{trip}/close`** requires a **`closing`** inventory transaction on that **`trip_id`** (**`TripService::endTrip`**).

---

### `POST /api/v1/inventory/return`

Same middleware subgroup as zones (admin/manager).

**`InventoryReturnRequest`:** `notes` required (1–2000 chars); `cars[]` same batch shape as loads (`car_id`, optional `trip_id`, `items` with `product_id`, `quantity > 0`).

---

### `POST /api/v1/inventory/adjustment`

**`role:admin`** only (+ `tenant`, `sanctum`).

**`InventoryAdjustmentRequest`:** `car_id`, optional `trip_id`; `items[]` with `product_id`, `mode` ∈ `increase|decrease|set`, `quantity` ≥ 0.

---

## 11. Driver inventory

`GET /api/v1/driver/inventory` · **`auth:sanctum`**, **`tenant`**, **`role:driver`**

Resolves **`Driver`** for the authenticated user (**404** with message if unlinkable).

**`data`:** same structure as **`GET /cars/{car}/inventory`**; **`car`** may be **`null`**.

---

## 12. Trips

**`auth:sanctum`**, **`tenant`**, **`role:admin,manager,driver`**.

**Driver access:** restricted to trips whose **`driver_id`** matches resolved driver (**403** if no linked driver).

### `GET /api/v1/trips`

Query (**`ListTripsRequest`):** optional **`status`** ∈ **`active`|`closed`**; optional **`driver_id`**, **`car_id`**.

**`data`:** array of **`Trip::toArray()`** with `driver`, `car`, `zone` loaded (**no Laravel pagination wrapper**).

---

### `POST /api/v1/trips`

**`StoreTripRequest`:** `driver_id`, `car_id` required (tenant FKs); optional `zone_id`, `destination`, `arrival_time` / `departure` as `H:i` strings.

Drivers must send **`driver_id`** equal to their own profile (**403** otherwise).

**Business rules:** unique “active” trip per driver/car (no **`end_date`**); new trip **`status`** = **`active`**. **`201`** on success.

---

### `GET /api/v1/trips/{trip}`

**`data`:**

```json
{
  "trip": {},
  "driver": {},
  "car": {},
  "timeline": [],
  "inventory_summary": {
    "on_hand": [{ "product_id": 1, "product_name": "", "quantity": "" }],
    "transactions_by_type_for_trip": {}
  },
  "sales_summary": [{ "product_id": 1, "quantity": 0.0, "total_price": "" }]
}
```

`timeline`: **`TripEvent`** rows (`event_type`, `user_id`, optional `quantity`/`amount`/`product_id`/`metadata`, `created_at`).

---

### `POST /api/v1/trips/{trip}/open`

Triggers **`TripService::startTrip`**. **`200`** with message **`Trip started successfully`**; **`data`** = trip array as JSON.

Fails **422** if trip **`status`** is not **`active`** (see service message).

> **Note:** There is **no** `PATCH /trips/{trip}/status` route in **`routes/api.php`** (removed).

---

### `POST /api/v1/trips/{trip}/close`

Ends trip — requires prior **closing count** inventory for **`trip_id`** or **422** `Inventory has not been close counted before ending the trip.`

Sets **`end_date`**, **`status`** = **`closed`**.

---

### `DELETE /api/v1/trips/{trip}`

Deletes trip (`Trip` uses **`SoftDeletes`**). **`200`** empty `data`.

---

## 13. Sales (`/api/v1/sales`)

**`auth:sanctum`**, **`tenant`**, **`role:admin,manager,driver`**.

Drivers: **show / update / delete** scoped to their **`sale.driver_id`**.

### `POST /sales`

**`StoreSaleRequest`:** `trip_id`, `product_id`, `customer_id`; `quantity` > 0; `total_price` ≥ 0. Creates **`Sale`** and inventory sale (**`InsufficientInventory`** may occur).

---

### `GET /sales`

**`ListSalesRequest`:** optional `trip_id`, `driver_id`, `product_id`, `customer_id`, `date_from`, `date_to` (date strings).

Drivers auto-scoped to their driver (**403** if unlinkable).

**`data`:** array of **`Sale::toArray()`** (model hides **`sale_invoice_image`** unless you extend upload flow elsewhere).

---

### `GET /sales/{sale}` · `PATCH /sales/{sale}` · `DELETE /sales/{sale}`

**Update:** **`UpdateSaleRequest`:** `total_price` required, ≥ 0.

**Delete:** restores inventory then soft-deletes sale (**422** with service messages).

---

### `GET /sales/my`

**`role:driver` only.** List of **`Sale::toArray()`** for matched driver.

---

## 14. Fleet requests (`/api/v1/requests`)

Path parameter **`fleet_request`** (numeric id). Rows live in **`requests`** table (**`FleetRequest`** model).

**Types:** `fuel`, `maintenance`, `inventory` · **Statuses:** `pending`, `approved`, `rejected`.

### Driver

- **`POST /requests`** — **`StoreFleetRequest`** (conditional fields per `type`; optional `notes`).
- **`GET /requests/my`** — own requests (**403** without driver linkage).

### Admin / manager

- **`GET /requests`** — **`ListFleetRequestsRequest`** filters: `status`, `type`, `driver_id`.
- **`POST /requests/{fleet_request}/approve`** — no body; pending only (**422** otherwise).
- **`POST /requests/{fleet_request}/reject`** — **`RejectFleetRequest`:** `notes` required (max ~20000).
- **`DELETE /requests/{fleet_request}`**

### Shared (`admin|manager|driver`)

- **`GET /requests/{fleet_request}`** — drivers only their rows. **`type=fuel`** may add **`estimated_total_cost`** (5 dp) in **`data`**.

---

## 15. Modules not in `routes/api.php`

No dedicated **Expenses** or **Notifications** HTTP resources in this file.

---

## Resources quick reference (`data` payloads)

| Class | Keys (typical) |
|-------|----------------|
| **`UserResource`** | `id`, `name`, `email`, `role`, `tenant_id`, `is_platform_admin` |
| **`TenantResource`** | `id`, `name`, `subscription_plan`, timestamps |
| **`ZoneResource`** | `id`, `tenant_id`, `name`, `city`, `number_of_stores`, … |
| **`CarResource`** | `id`, `tenant_id`, `model`, `plate_number`, capacities, fuel fields, `color`, … |
| **`DriverResource`** | `id`, `tenant_id`, `full_name`, `phone`, `zone_id`, `zone?`, … |
| **`ProductResource`** | `id`, `tenant_id`, `item`, `type`, `price`, `unit_volume`, `unit_weight`, … |
| **`CustomerResource`** | `id`, `tenant_id`, `full_name`, `phone`, `zone_id`, `zone?`, `latitude`, `longitude`, … |

---

## Driver ↔ user linkage (operations)

Several services resolve **`driver_id`** for role **`driver`** by matching **`Driver.full_name`** to **`User.name`** (see **`SalesService::resolveDriverScopeId`** pattern). Frontend should keep these aligned if driver-scoped endpoints return **403/404**.

---

*Document regenerated from codebase; aligns with **`routes/api.php`**, **`ApiResponse`**, form requests, and resources.*
