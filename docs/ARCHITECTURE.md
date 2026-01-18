# Backend Architecture Guide

This document describes the conventions and layer responsibilities observed in the codebase. It is intended to be the single source of truth for how modules are structured and how requests flow through the system.

## Folder structure and layers

- `app/Http/Controllers/API/`: HTTP controllers for the public API. Controllers are thin and delegate work to services.
- `app/Http/Requests/`: Request validation classes (FormRequest). Includes shared base request logic.
- `app/Http/Resources/`: API resources for shaping response payloads.
- `app/Services/`: Business logic and orchestration; coordinates repositories, transactions, and side effects.
- `app/Repositories/`: Data access layer using Eloquent; interfaces live in `app/Repositories/Interfaces/`.
- `app/Models/`: Eloquent models.
- `app/Filters/`: Filter objects used for list endpoints (e.g., bookings).
- `app/Policies/`: Authorization policies.
- `app/Providers/`: Service provider bindings and app bootstrapping.
- `routes/api.php`: HTTP routing definitions and middleware groups.

## Module example: User

The User module demonstrates the standard flow:

- Controller: `app/Http/Controllers/API/UsersController.php`
- Requests: `app/Http/Requests/UpdateUserDetailsRequest.php`, `ChangeUserPasswordRequest.php`
- Service: `app/Services/UserService.php`
- Repository: `app/Repositories/UserRepository.php` with interface `app/Repositories/Interfaces/UserRepositoryInterface.php`
- Resource: `app/Http/Resources/UserResource.php`
- Model: `app/Models/User.php`

Request flow:
1. Controller receives a typed FormRequest and uses `ApiResponse` for all responses.
2. Service enforces business rules, handles transactions, and delegates data operations to repositories.
3. Repository performs Eloquent queries and persistence.
4. Resource transforms the model to API shape.

## Naming conventions

- Controllers: `<Resource>Controller` or `<Resource>sController` in `app/Http/Controllers/API/`.
- Services: `<Resource>Service` in `app/Services/`.
- Repositories: `<Resource>Repository` with interface `<Resource>RepositoryInterface`.
- Requests: `<Action><Resource>Request` (e.g., `UpdateUserDetailsRequest`).
- Resources: `<Resource>Resource` extending `BaseResource`.
- Routes: REST-like names with action verbs where needed (e.g., `/user/change-password`, `/admin/staff/create-many`).

## Controller → Service → Repository flow

Standard pattern:

- Controller responsibilities:
  - Accept `FormRequest` or `Request` input.
  - Perform auth checks if needed.
  - Call service methods.
  - Return standardized `ApiResponse`.

- Service responsibilities:
  - Business rules, validation beyond request schema, and orchestration.
  - Transaction management via `DB::transaction`.
  - Coordinating side effects (emails, notifications).
  - Throwing validation or error responses using `ApiResponse`.

- Repository responsibilities:
  - Data access via Eloquent.
  - Query composition, pagination, and simple persistence.

Dependency injection is done via repository interfaces bound in `app/Providers/AppServiceProvider.php`.

## Request validation

Validation is centralized in `FormRequest` classes, all of which extend `BaseFormRequest`.

Key behaviors:

- Rules and messages are defined per request class.
- `BaseFormRequest` maps camelCase keys to snake_case automatically.
  - Custom mappings can be set via `$fieldMap` in each request.
  - Mapping happens in `passedValidation()` before controller code runs.
- Validation failures throw an `HttpResponseException` using `ApiResponse::error` with status `422`.

Controllers generally use `$request->all()` or `$request->validated()` after validation passes.

## Resource usage

Resources live in `app/Http/Resources/` and extend `BaseResource`.

Conventions:

- Use `<Resource>Resource` for single items.
- Use `<Resource>Resource::collection(...)` for lists.
- `BaseResource` applies locale-aware translation for fields ending with `_ar` when `api_locale` is `ar`.

Resources shape output fields (e.g., `UserResource` exposes `dateOfBirth` and nested `referral`).

## ApiResponse format

All API responses should use `App\Services\ApiResponse`.

Success response shape:

```
{
  "status": 200,
  "success": true,
  "message": "Success",
  "data": { ... },
  "errors": {}
}
```

Error response shape:

```
{
  "status": 422,
  "success": false,
  "message": "Validation failed",
  "errors": { ... },
  "data": {}
}
```

Notes:
- `data` is always an object (empty object when no data).
- `errors` is always an object (empty object when no errors).
- Controllers and services use `ApiResponse::success(...)` and `ApiResponse::error(...)`.

## Routing conventions

Routes are defined in `routes/api.php` and follow these patterns:

- API routes are grouped under `Route::middleware(['set.locale'])` to set request locale.
  - Localization is centralized in `resources/lang/` with locale folders `en/` and `ar/`.
  - Message catalogs include: `auth.php`, `errors.php`, `messages.php`, `success.php`, `validation.php`, `validation_scoped.php`, plus content-specific files like `home.php`, `about.php`, `contact.php`, `blog.php`, `store.php`, `content.php`, `general.php`, and `attributes.php`.
  - Controllers/services return localized messages via `__()` keys (e.g., `__('success.*')`, `__('messages.*')`, `__('auth.*')`, `__('validation.*')`), while `FormRequest` classes map field-level rules to translated messages (see `messages()` in request classes).
  - `validation.php` holds shared, cross-cutting validation messages and error keys (global defaults and common booking/auth/profile keys). `validation_scoped.php` groups validation messages by feature/action (e.g., `change_password`, `category`, `product`, `working_hours`) so identical field names in different modules (like `name` for user vs service) can return module-specific, user-friendly text instead of a single generic message.
- Auth-related endpoints are under the `auth` prefix.
- Admin endpoints are under the `admin` prefix and guarded by role middleware.
- Authenticated routes use middleware such as `jwt.custom`, `verified`, and `auth:api`.
- HTTP verbs indicate intent:
  - `GET` for reads
  - `POST` for creation and actions
  - `PUT` for full updates
  - `PATCH` for partial updates
  - `DELETE` for deletions

Examples:
- `PATCH /user/details`
- `PATCH /user/change-password`
- `GET /admin/staff`
- `POST /admin/staff/create-many`

## Patterns in other modules

The same layering and conventions are visible in:

- Bookings: `BookingsController` → `BookingService` → `BookingRepository` with request validation and `BookingResource` output.
- Categories: `CategoriesController` → `CategoryService` → `CategoryRepository`, returning `CategoryResource` collections.

These confirm the module structure and `ApiResponse` usage are consistent across the codebase.

## Payments (Stripe-only)

- The backend currently supports **Stripe only** for `pay_now` flows.
- `POST /bookings` with `paymentMode=pay_now` starts a Stripe PaymentIntent and returns `clientSecret`.
- Card data is collected by the frontend via Stripe SDK; the backend never receives card details.
- Stripe webhooks update payment, order, and booking status.

## Operational notes

- Booking auto-cancel is handled by the scheduled command `bookings:expire-pending`.
  - It cancels `pending_payment` bookings with `payment_mode=pay_now` after the hold window.
  - Hold window is controlled by `BOOKING_HOLD_MINUTES` (defaults to 10 via `config/payment.php`).
  - Scheduler must be running in production (e.g. `php artisan schedule:work` or a cron running `schedule:run`).