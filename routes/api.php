<?php

use App\Http\Controllers\API\Admin\ClientsController;
use App\Http\Controllers\API\Admin\PagesController as AdminPagesController;
use App\Http\Controllers\API\BookingsController;
use App\Http\Controllers\API\Client\AddressController;
use App\Http\Controllers\API\Client\PaymentMethodsController;
use App\Http\Controllers\API\ContactController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\OrdersController;
use App\Http\Controllers\API\EmailVerificationController;
use App\Http\Controllers\API\SubServiceMastersController;
use App\Http\Controllers\API\PagesController;
use App\Http\Controllers\API\Admin\ReferralsController;
use App\Http\Controllers\API\Admin\SubServiceItemsController;
use App\Http\Controllers\API\PostsController;
use App\Http\Controllers\API\Admin\PostsController as AdminPostsController;
use App\Http\Controllers\API\ProductsController;
use App\Http\Controllers\API\Admin\ProductsController as AdminProductsController;
use App\Http\Controllers\API\Admin\ProductImportsController;
use App\Http\Controllers\API\ProductCategoriesController;
use App\Http\Controllers\API\Admin\ServicesController as AdminServicesController;
use App\Http\Controllers\API\Admin\CategoriesController as AdminCategoriesController;
use App\Http\Controllers\API\Admin\SubServicesController as AdminSubServicesController;
use App\Http\Controllers\API\Admin\ReportsController as AdminReportsController;
use App\Http\Controllers\API\ServicesController as ServicesController;
use App\Http\Controllers\API\CategoriesController as CategoriesController;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\Auth\ResetPasswordController;
use App\Http\Controllers\API\Content\PageContentController;
use App\Http\Controllers\API\FilesController;
use App\Http\Controllers\API\StaffController;
use App\Http\Controllers\API\SubServicesController;
use App\Http\Controllers\API\UsersController;
use App\Http\Controllers\API\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\API\Admin\BookingsController as AdminBookingsController;
use App\Http\Controllers\API\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\API\Admin\OrdersController as AdminOrdersController;
use App\Http\Controllers\API\Webhook\StripeWebhookController;
use App\Http\Controllers\API\Webhook\TabbyWebhookController;
use App\Http\Controllers\API\WeekdaysController;
use App\Http\Controllers\API\WorkingHoursController;
use App\Http\Controllers\API\Admin\WorkingHoursController as AdminWorkingHoursController;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ApiResponse;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['set.locale'])->group(function () {
    Route::post('/contact', [ContactController::class, 'store'])
        ->middleware('throttle:contact');
    Route::prefix('content')->group(function () {
        Route::get('/home', [PageContentController::class, 'home']);
        Route::get('/about', [PageContentController::class, 'about']);
        Route::get('/contact', [PageContentController::class, 'contact']);
        Route::get('/blog', [PageContentController::class, 'blog']);
        Route::get('/store', [PageContentController::class, 'store']);
        Route::get('/general', [PageContentController::class, 'general']);
    });

    Route::get('/pages', [PagesController::class, 'index']);

    Route::get('/posts', [PostsController::class, 'index']);
    Route::get('/posts/{slug}', [PostsController::class, 'getBySlug']);

    Route::get('/products', [ProductsController::class, 'index']);
    Route::get('/product-categories', [ProductCategoriesController::class, 'index']);
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/items', [CartController::class, 'store']);
    Route::post('/cart/items/{product}/increment', [CartController::class, 'increment']);
    Route::post('/cart/items/{product}/decrement', [CartController::class, 'decrement']);
    Route::patch('/cart/items/{product}', [CartController::class, 'update']);
    Route::delete('/cart/items/{product}', [CartController::class, 'destroy']);
    Route::delete('/cart', [CartController::class, 'clear']);
    Route::post('/cart/checkout', [CartController::class, 'checkout']);

    Route::prefix('auth')->group(function () {
        Route::post('signup', [AuthController::class, 'signup']);
        Route::post('login', [AuthController::class, 'login']);

        Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');
        
        Route::post('email/verify/resend', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:6,1')
            ->name('verification.resend');

        Route::middleware(['auth:api'])->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('refresh', [AuthController::class, 'refresh']);
        });
    });

    Route::post('/bookings', [BookingsController::class, 'store']);

    Route::middleware(['jwt.custom', 'verified'])->group(function () {

        Route::post('image/upload', [FilesController::class, 'upload']);
        Route::post('image/upload-multiple', [FilesController::class, 'uploadMultiple']);

        Route::patch('/user/details', [UsersController::class, 'updateDetails']);
        Route::patch('/user/change-password', [UsersController::class, 'changePassword']);
        Route::middleware('auth:api')->get('me', function () {
            $user = auth()->user()->load([
                'role',
                'referral',
                 'clientBookings',
            ]);
            return ApiResponse::success([
                'user' => new UserResource($user)
            ]);
        });

        Route::get('/bookings', [BookingsController::class, 'index']);
        Route::put('/bookings/{booking}', [BookingsController::class, 'update']);
        Route::patch('/bookings/cancel/{booking}', [BookingsController::class, 'cancel']);

        Route::get('/orders', [OrdersController::class, 'index']);



        Route::get('/addresses', [AddressController::class, 'index']);
        Route::post('/addresses', [AddressController::class, 'store']);
        Route::put('/addresses/{address}', [AddressController::class, 'update']);
        Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);

        Route::get('/payment-methods', [PaymentMethodsController::class, 'index']);
        Route::post('/payment-methods', [PaymentMethodsController::class, 'store']);
        Route::put('/payment-methods/{paymentMethod}', [PaymentMethodsController::class, 'update']);
        Route::delete('/payment-methods/{paymentMethod}', [PaymentMethodsController::class, 'destroy']);
    });

    Route::get('/bookings/available-slots', [BookingsController::class, 'availableSlots']);
    Route::post('/bookings/selection', [BookingsController::class, 'selectSlot'])
        ->middleware('jwt.optional');
    Route::get('/categories', [CategoriesController::class, 'index']);
    Route::get('/categories/{id}', [CategoriesController::class, 'show']);
    Route::get('/services', [ServicesController::class, 'index']);
    Route::get('/services-by-ids/', [ServicesController::class, 'getByIds']);
    Route::get('/services/{service}/subservices', [SubServicesController::class, 'index']);
    Route::get('/subservices/{subservice}/masters', [SubServiceMastersController::class, 'index']);
    Route::post('/password/forgot', [ResetPasswordController::class, 'forgot']);
    Route::post('/password/reset', [ResetPasswordController::class, 'reset']);


    Route::middleware(['jwt.custom', 'verified','role:superadmin'])->group(function () {


        Route::post('/admin/categories', [AdminCategoriesController::class, 'store']);
        Route::put('/admin/categories/{category}', [AdminCategoriesController::class, 'update']);
        Route::delete('/admin/categories/{category}', [AdminCategoriesController::class, 'destroy']);

        Route::post('/admin/services', [AdminServicesController::class, 'store']);
        Route::put('/admin/services/{service}', [AdminServicesController::class, 'update']);
        Route::delete('/admin/services/{service}', [AdminServicesController::class, 'destroy']);


        Route::post('/admin/sub-services', [AdminSubServicesController::class, 'store']);
        Route::put('/admin/sub-services/{subService}', [AdminSubServicesController::class, 'update']);
        Route::delete('/admin/sub-services/{subService}', [AdminSubServicesController::class, 'destroy']);


        Route::delete('/admin/sub-service-items/{subServiceItem}', [SubServiceItemsController::class, 'destroy']);

        Route::post('/admin/staff/create', [AdminStaffController::class, 'store']);
        Route::put('/admin/staff/update/{user}', [AdminStaffController::class, 'update']);
        Route::delete('/admin/staff/delete/{user}', [AdminStaffController::class, 'destroy']);;
        Route::post('/admin/staff/create-many', [AdminStaffController::class, 'createMany']);
        Route::post('/admin/staff/update-many', [AdminStaffController::class, 'updateMany']);
        Route::patch('/admin/staff/{id}/restore', [AdminStaffController::class, 'restore']);

        Route::get('/admin/reports/today-turnover', [AdminReportsController::class, 'todaysTurnover']);
        Route::get('/admin/reports/top-services', [AdminReportsController::class, 'topServices']);
        Route::get('/admin/reports/top-products', [AdminReportsController::class, 'topProducts']);
    });

    Route::middleware(['jwt.custom', 'verified', 'role:superadmin,admin'])->group(function () {
        Route::get('/admin/products', [AdminProductsController::class, 'index']);
        Route::get('/admin/categories', [AdminCategoriesController::class, 'index']);
        Route::get('/admin/staff', [AdminStaffController::class, 'index']);
        Route::get('/admin/services', [AdminServicesController::class, 'index']);
        Route::post('/admin/product/create', [AdminProductsController::class, 'store']);
        Route::put('/admin/product/update/{product}', [AdminProductsController::class, 'update']);
        Route::post('/admin/product/import', [ProductImportsController::class, 'import']);
        Route::get('/admin/product/download-inventory', [AdminProductsController::class, 'downloadInventory']);
        Route::post('/admin/product/bulk-delete', [AdminProductsController::class, 'bulkDelete']);
        Route::post('/admin/product/bulk-status', [AdminProductsController::class, 'bulkStatus']);

        Route::post('/admin/post/create', [AdminPostsController::class, 'store']);
        Route::put('/admin/post/update/{post}', [AdminPostsController::class, 'update']);
        Route::delete('/admin/post/delete/{post}', [AdminPostsController::class, 'destroy']);

        Route::get('/referrals', [ReferralsController::class, 'index']);

        Route::put('/admin/pages', [AdminPagesController::class, 'update']);

        Route::get('/admin/contact-messages', [AdminContactMessageController::class, 'index']);
        Route::patch('/admin/contact-messages/{contactMessage}/read', [AdminContactMessageController::class, 'markRead']);
        Route::get('/admin/contact-messages/unread-count', [AdminContactMessageController::class, 'unreadCount']);
        Route::put('/admin/working-hours', [AdminWorkingHoursController::class, 'bulkUpdate']);
        Route::put('/admin/working-hours/{day}', [AdminWorkingHoursController::class, 'updateDay']);

        Route::patch('/admin/clients/{user}/add-referral', [ClientsController::class, 'addReferral']);
        Route::get('/admin/clients', [ClientsController::class, 'index']);


        Route::post('/admin/booking/break', [AdminBookingsController::class, 'storeBreak']);
        Route::delete('/admin/booking/break/{booking}', [AdminBookingsController::class, 'deleteBreak']);
        Route::put('/admin/booking/break/{booking}', [AdminBookingsController::class, 'updateBreak']);
        Route::get('/admin/bookings', [AdminBookingsController::class, 'index']);
        Route::patch('/admin/bookings/{booking}/mark-paid', [AdminBookingsController::class, 'markPaid']);

        Route::get('/admin/orders', [AdminOrdersController::class, 'index']);
        Route::get('/admin/orders/{order}', [AdminOrdersController::class, 'show']);
        Route::patch('/admin/orders/{order}/delivery-status', [AdminOrdersController::class, 'updateDeliveryStatus']);
        Route::patch('/admin/orders/{order}/status', [AdminOrdersController::class, 'updateStatus']);
        Route::get('/admin/orders/{order}/invoice/pdf', [AdminOrdersController::class, 'downloadInvoicePdf']);
        Route::get('/admin/orders/{order}/invoice/xlsx', [AdminOrdersController::class, 'downloadInvoiceXlsx']);
        Route::get('/admin/orders/export/pdf', [AdminOrdersController::class, 'exportOrdersPdf']);
        Route::get('/admin/orders/export/xlsx', [AdminOrdersController::class, 'exportOrdersXlsx']);

        Route::post('/admin/post/create', [AdminPostsController::class, 'store']);
        Route::put('/admin/post/update/{post}', [AdminPostsController::class, 'update']);

        Route::get('/referrals', [ReferralsController::class, 'index']);
    });

    Route::get('/masters', [StaffController::class, 'getMasters']);
    Route::get('/weekdays', [WeekdaysController::class, 'index']);
    Route::get('working-hours', [WorkingHoursController::class, 'index']);
});

Route::post('/webhooks/tabby', [TabbyWebhookController::class, 'handle']);
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);




