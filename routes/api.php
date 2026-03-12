<?php

use App\Http\Controllers\API\Admin\ClientsController;
use App\Http\Controllers\API\Admin\SuppliersController;
use App\Http\Controllers\API\Admin\ProductCategoriesController as AdminProductCategoriesController;
use App\Http\Controllers\API\Admin\PagesController as AdminPagesController;
use App\Http\Controllers\API\BookingsController;
use App\Http\Controllers\API\BookingPaymentController;
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
use App\Http\Controllers\API\Admin\PageSeoController;
use App\Http\Controllers\API\Admin\TrackingConfigController;
use App\Http\Controllers\API\Admin\DiscountSettingController;
use App\Http\Controllers\API\Webhook\StripeWebhookController;
use App\Http\Controllers\API\Webhook\TabbyWebhookController;
use App\Http\Controllers\API\WeekdaysController;
use App\Http\Controllers\API\WorkingHoursController;
use App\Http\Controllers\API\CountriesController;
use App\Http\Controllers\API\FaqController;
use App\Http\Controllers\API\Admin\WorkingHoursController as AdminWorkingHoursController;
use App\Http\Controllers\API\Admin\FaqController as AdminFaqController;
use App\Http\Controllers\LeadsController;
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
    Route::get('/products/by-slug/{slug}', [ProductsController::class, 'getBySlug']);
    Route::get('/product-categories', [ProductCategoriesController::class, 'index']);
    Route::get('/countries', [CountriesController::class, 'index']);
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/items', [CartController::class, 'store']);
    Route::post('/cart/items/{product}/increment', [CartController::class, 'increment']);
    Route::post('/cart/items/{product}/decrement', [CartController::class, 'decrement']);
    Route::patch('/cart/items/{product}', [CartController::class, 'update']);
    Route::delete('/cart/items/{product}', [CartController::class, 'destroy']);
    Route::delete('/cart', [CartController::class, 'clear']);
    Route::post('/cart/checkout', [CartController::class, 'checkout']);

    // Payment verification for orders (works for both guests and authenticated users)
    Route::get('/orders/{order}', [OrdersController::class, 'show']);
    Route::post('/orders/{order}/verify-payment', [OrdersController::class, 'verifyPayment']);

    Route::prefix('auth')->group(function () {
        Route::post('signup', [AuthController::class, 'signup']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('google', [AuthController::class, 'google']);

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
    Route::post('/bookings/batch', [BookingsController::class, 'storeBatch']);

    Route::middleware(['jwt.custom', 'verified'])->group(function () {

        Route::post('image/upload', [FilesController::class, 'upload']);
        Route::post('image/upload-multiple', [FilesController::class, 'uploadMultiple']);

        Route::patch('/user/details', [UsersController::class, 'updateDetails']);
        Route::patch('/user/change-password', [UsersController::class, 'changePassword']);
        Route::middleware('auth:api')->get('me', function () {
            $user = auth()->user()->load(['role', 'referral'])->loadCount('clientBookings');
            return ApiResponse::success([
                'user' => new UserResource($user)
            ]);
        });

        Route::get('/bookings', [BookingsController::class, 'index']);
        Route::put('/bookings/{booking}', [BookingsController::class, 'update']);
        Route::patch('/bookings/cancel/{booking}', [BookingsController::class, 'cancel']);
        Route::post('/bookings/{booking}/pay', [BookingPaymentController::class, 'initiatePayment']);
        Route::post('/bookings/{booking}/confirm-payment', [BookingPaymentController::class, 'confirmPayment']);

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
    Route::get('/bookings/{booking}/calendar.ics', [BookingsController::class, 'calendarIcs'])
        ->middleware('signed')
        ->name('booking.calendar.ics');
    // Note: This route must come AFTER /bookings/available-slots to avoid wildcard matching issues
    Route::get('/bookings/{booking}', [BookingsController::class, 'show'])
        ->middleware(['jwt.custom', 'verified']);
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
        Route::post('/admin/sub-services/{subService}', [AdminSubServicesController::class, 'update']); // POST with _method for PUT/DELETE
        Route::put('/admin/sub-services/{subService}', [AdminSubServicesController::class, 'update']);
        Route::delete('/admin/sub-services/{subService}', [AdminSubServicesController::class, 'destroy']);


        Route::post('/admin/sub-service-items/{subServiceItem}', [SubServiceItemsController::class, 'destroy']); // POST with _method for DELETE
        Route::delete('/admin/sub-service-items/{subServiceItem}', [SubServiceItemsController::class, 'destroy']);

        Route::post('/admin/staff/create', [AdminStaffController::class, 'store']);
        Route::put('/admin/staff/update/{user}', [AdminStaffController::class, 'update']);
        Route::delete('/admin/staff/delete/{user}', [AdminStaffController::class, 'destroy']);
        Route::post('/admin/staff/create-many', [AdminStaffController::class, 'createMany']);
        Route::post('/admin/staff/update-many', [AdminStaffController::class, 'updateMany']);
        Route::patch('/admin/staff/{id}/restore', [AdminStaffController::class, 'restore']);
        Route::post('/admin/staff/{user}/reset-password', [AdminStaffController::class, 'resetPassword']);

        Route::get('/admin/reports/today-turnover', [AdminReportsController::class, 'todaysTurnover']);
        Route::get('/admin/reports/top-services', [AdminReportsController::class, 'topServices']);
        Route::get('/admin/reports/top-products', [AdminReportsController::class, 'topProducts']);
    });

    Route::middleware(['jwt.custom', 'verified', 'role:superadmin,admin,marketer'])->group(function () {
        Route::get('/admin/posts', [AdminPostsController::class, 'index']);
        Route::get('/admin/posts/{post}', [AdminPostsController::class, 'show']);
        Route::post('/admin/post/create', [AdminPostsController::class, 'store']);
        Route::put('/admin/post/update/{post}', [AdminPostsController::class, 'update']);
        Route::delete('/admin/post/delete/{post}', [AdminPostsController::class, 'destroy']);

        Route::put('/admin/pages', [AdminPagesController::class, 'update']);

        Route::get('/admin/page-seo', [PageSeoController::class, 'index']);
        Route::get('/admin/page-seo/{page_key}', [PageSeoController::class, 'show']);
        Route::put('/admin/page-seo/{page_key}', [PageSeoController::class, 'update']);

        Route::get('/admin/tracking-config', [TrackingConfigController::class, 'index']);
        Route::put('/admin/tracking-config', [TrackingConfigController::class, 'update']);

        Route::get('/admin/discount-setting', [DiscountSettingController::class, 'show']);
        Route::put('/admin/discount-setting', [DiscountSettingController::class, 'update']);
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

        // Suppliers management
        Route::get('/admin/suppliers', [SuppliersController::class, 'index']);
        Route::get('/admin/suppliers/dropdown', [SuppliersController::class, 'dropdown']);
        Route::post('/admin/suppliers', [SuppliersController::class, 'store']);
        Route::get('/admin/suppliers/{supplier}', [SuppliersController::class, 'show']);
        Route::put('/admin/suppliers/{supplier}', [SuppliersController::class, 'update']);
        Route::delete('/admin/suppliers/{supplier}', [SuppliersController::class, 'destroy']);

        // Product Categories management
        Route::get('/admin/product-categories', [AdminProductCategoriesController::class, 'index']);
        Route::get('/admin/product-categories/dropdown', [AdminProductCategoriesController::class, 'dropdown']);
        Route::post('/admin/product-categories', [AdminProductCategoriesController::class, 'store']);
        Route::get('/admin/product-categories/{productCategory}', [AdminProductCategoriesController::class, 'show']);
        Route::put('/admin/product-categories/{productCategory}', [AdminProductCategoriesController::class, 'update']);
        Route::delete('/admin/product-categories/{productCategory}', [AdminProductCategoriesController::class, 'destroy']);
        Route::post('/admin/product-categories/reorder', [AdminProductCategoriesController::class, 'reorder']);

        Route::get('/admin/referrals', [ReferralsController::class, 'index']);
        Route::put('/admin/referrals/{id}', [ReferralsController::class, 'update']);

        Route::post('/admin/orders', [AdminOrdersController::class, 'store']);
        Route::post('/admin/orders/in-store', [AdminOrdersController::class, 'storeInStore']);

        Route::get('/admin/contact-messages', [AdminContactMessageController::class, 'index']);
        Route::patch('/admin/contact-messages/{contactMessage}/read', [AdminContactMessageController::class, 'markRead']);
        Route::get('/admin/contact-messages/unread-count', [AdminContactMessageController::class, 'unreadCount']);
        Route::put('/admin/working-hours', [AdminWorkingHoursController::class, 'bulkUpdate']);
        Route::put('/admin/working-hours/{day}', [AdminWorkingHoursController::class, 'updateDay']);

        // FAQ management
        Route::get('/admin/faqs', [AdminFaqController::class, 'index']);
        Route::post('/admin/faqs', [AdminFaqController::class, 'store']);
        Route::get('/admin/faqs/{id}', [AdminFaqController::class, 'show']);
        Route::put('/admin/faqs/{id}', [AdminFaqController::class, 'update']);
        Route::delete('/admin/faqs/{id}', [AdminFaqController::class, 'destroy']);
        Route::post('/admin/faqs/reorder', [AdminFaqController::class, 'reorder']);

        Route::patch('/admin/clients/{user}/add-referral', [ClientsController::class, 'addReferral']);
        Route::get('/admin/clients', [ClientsController::class, 'index']);
        Route::get('/admin/clients/search', [ClientsController::class, 'search']);
        Route::get('/admin/clients/{user}', [ClientsController::class, 'show']);
        Route::get('/admin/clients/{user}/bookings', [ClientsController::class, 'bookings']);
        Route::get('/admin/clients/{user}/orders', [ClientsController::class, 'orders']);
        Route::patch('/admin/clients/{user}/toggle-lock', [ClientsController::class, 'toggleLock']);
        Route::post('/admin/clients/{user}/notes', [ClientsController::class, 'addNote']);
        Route::delete('/admin/clients/{user}/notes/{note}', [ClientsController::class, 'deleteNote']);

        // Leads management
        Route::get('/admin/leads', [LeadsController::class, 'index']);
        Route::post('/admin/leads', [LeadsController::class, 'store']);
        Route::get('/admin/leads/export', [LeadsController::class, 'export']);
        Route::get('/admin/leads/{lead}', [LeadsController::class, 'show']);
        Route::put('/admin/leads/{lead}', [LeadsController::class, 'update']);
        Route::delete('/admin/leads/{lead}', [LeadsController::class, 'destroy']);


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
    });

    Route::get('/masters', [StaffController::class, 'getMasters']);
    Route::get('/weekdays', [WeekdaysController::class, 'index']);
    Route::get('working-hours', [WorkingHoursController::class, 'index']);
    Route::get('/faqs', [FaqController::class, 'index']);
    Route::get('/referrals', [ReferralsController::class, 'index']);

    Route::get('/tracking-config/public', [TrackingConfigController::class, 'public']);
});

Route::post('/webhooks/tabby', [TabbyWebhookController::class, 'handle']);
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);




