<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use UniSharp\LaravelFilemanager\Lfm;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

use App\Http\Controllers\{
    AdminController,
    Auth\ForgotPasswordController,
    Auth\LoginController,
    Auth\ResetPasswordController,
    BannerController,
    BrandController,
    CartController,
    CategoryController,
    CouponController,
    DiscountController,
    FilterController,
    FrontendController,
    HighPerformanceFilterController,
    HomeController,
    MessageController,
    MollieController,
    NotificationController,
    OrderController,
    PayPalController,
    PostCategoryController,
    PostCommentController,
    PostController,
    PostTagController,
    ProductController,
    ProductReviewController,
    RazorpayController,
    ShippingController,
    SquareController,
    StripeController,
    UsersController,
    WishlistController
};


// Utility
Route::get('/check-redis-cache', fn() => response()->json([
    'written' => Cache::tags(['product_card'])->has('product_card_123'),
    'value' => Cache::tags(['product_card'])->get('product_card_123'),
]));

Route::get('/cache-clear', fn() => tap(Artisan::call('optimize:clear'), fn() => session()->flash('success', 'Successfully cache cleared.')))->name('cache.clear');

Route::get('/storage-link', [AdminController::class, 'storageLink'])->name('storage.link');

// Auth & Social
Auth::routes(['register' => false]);
Route::get('/user/login', [FrontendController::class, 'login'])->name('login.form');
Route::post('/user/login', [FrontendController::class, 'loginSubmit'])->name('login.submit');
Route::get('/user/logout', [FrontendController::class, 'logout'])->name('user.logout');
Route::get('/user/register', [FrontendController::class, 'register'])->name('register.form');
Route::post('/user/register', [FrontendController::class, 'registerSubmit'])->name('register.submit');

Route::get('/password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('/password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('/password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('/password/reset', [ResetPasswordController::class, 'reset'])->name('password.update');

Route::get('/login/{provider}', [LoginController::class, 'redirect'])->name('login.redirect');
Route::get('/login/{provider}/callback', [LoginController::class, 'callback'])->name('login.callback');

// Frontend
Route::get('/', [FrontendController::class, 'home'])->name('home');
Route::get('/home', [FrontendController::class, 'index']);
Route::get('/about-us', [FrontendController::class, 'aboutUs'])->name('about-us');
Route::get('/contact', [FrontendController::class, 'contact'])->name('contact');
Route::post('/contact/message', [MessageController::class, 'store'])->name('contact.store');

Route::get('/product-detail/{slug}', [FrontendController::class, 'productDetail'])->name('product-detail');
Route::match(['get', 'post'], '/search', [FrontendController::class, 'productSearch'])->name('product.search');
Route::get('/autocomplete', [FrontendController::class, 'autocomplete'])->name('autocomplete');

// API Route for JSON Filters (high-perf endpoint)
Route::get('/api/filters/{path?}', [HighPerformanceFilterController::class, 'getFilterData'])
    ->name('api.filters')
    ->where('path', '.*'); // Allow category paths like /product-cat/slug/subslug

// Existing routes delegate to API for AJAX
Route::get('/product-cat/{encryptedPath}', [FrontendController::class, 'productSubCat'])
    ->middleware('validate.filters')
    ->where('encryptedPath', '.*')
    ->name('product-cat');

Route::get('/apply-filters/{encryptedFilters?}', [FrontendController::class, 'applyFilters'])
    ->middleware('validate.filters')
    ->name('apply.filters');

Route::post('/encrypt-filters', [FrontendController::class, 'encryptFilters'])
    ->name('encrypt.filters')
    ->middleware('throttle:60,1'); // 60 requests per minute
    
Route::get('/product-brand/{slug}', [FrontendController::class, 'productBrand'])->name('product-brand');
Route::get('/product-grids', [FrontendController::class, 'productGrids'])->name('product-grids');
Route::get('/product-lists', [FrontendController::class, 'productLists'])->name('product-lists');
Route::match(['get', 'post'], '/filter', [FrontendController::class, 'productFilter'])->name('shop.filter');

Route::get('/blog', [FrontendController::class, 'blog'])->name('blog');
Route::get('/blog-detail/{slug}', [FrontendController::class, 'blogDetail'])->name('blog.detail');
Route::get('/blog/search', [FrontendController::class, 'blogSearch'])->name('blog.search');
Route::post('/blog/filter', [FrontendController::class, 'blogFilter'])->name('blog.filter');
Route::get('/blog-cat/{slug}', [FrontendController::class, 'blogByCategory'])->name('blog.category');
Route::get('/blog-tag/{slug}', [FrontendController::class, 'blogByTag'])->name('blog.tag');
Route::post('/subscribe', [FrontendController::class, 'subscribe'])->name('subscribe');

// Cart & Wishlist
Route::get('/cart', [FrontendController::class, 'cart'])->name('cart');
Route::get('/wishlist', fn() => view('frontend.pages.wishlist'))->name('wishlist');

Route::middleware('auth')->group(function () {
    Route::get('/add-to-cart/{slug}', [CartController::class, 'addToCart'])->name('add-to-cart');
    Route::post('/add-to-cart', [CartController::class, 'singleAddToCart'])->name('single-add-to-cart');
    Route::get('/checkout', [CartController::class, 'checkout'])->name('checkout');
    Route::get('/wishlist/{slug}', [WishlistController::class, 'wishlist'])->name('add-to-wishlist');
});

Route::get('/cart-delete/{id}', [CartController::class, 'cartDelete'])->name('cart-delete');
Route::post('/cart-update', [CartController::class, 'cartUpdate'])->name('cart.update');
Route::get('/wishlist-delete/{id}', [WishlistController::class, 'wishlistDelete'])->name('wishlist-delete');

// Orders
Route::post('/cart/order', [OrderController::class, 'store'])->name('cart.order');
Route::get('/order/pdf/{id}', [OrderController::class, 'pdf'])->name('order.pdf');
Route::get('/income', [OrderController::class, 'incomeChart'])->name('product.order.income');
Route::get('/product/track', [OrderController::class, 'orderTrack'])->name('order.track');
Route::post('/product/track/order', [OrderController::class, 'productTrackOrder'])->name('product.track.order');

// Review & Comment
Route::post('/product/{slug}/review', [ProductReviewController::class, 'store'])->name('review.store');
Route::resource('/review', ProductReviewController::class);

Route::post('/post/{slug}/comment', [PostCommentController::class, 'store'])->name('post-comment.store');
Route::resource('/comment', PostCommentController::class);

// Coupon & Payment
Route::post('/coupon-apply', [CouponController::class, 'applyCoupon'])->name('coupon-apply');
Route::get('/payment', [PayPalController::class, 'payment'])->name('payment');
Route::get('/cancel', [PayPalController::class, 'cancel'])->name('payment.cancel');
Route::get('/payment/success', [PayPalController::class, 'success'])->name('payment.success');

// Stripe Payment Routes
Route::get('stripe/payment', [StripeController::class, 'payment'])->name('stripe.payment');
Route::post('stripe/payment', [StripeController::class, 'payment'])->name('stripe.payment.post');
Route::get('stripe/success', [StripeController::class, 'success'])->name('stripe.success');
Route::get('stripe/cancel', [StripeController::class, 'cancel'])->name('stripe.cancel');
Route::post('stripe/webhook', [StripeController::class, 'webhook'])->name('stripe.webhook');

// Razorpay routes
Route::post('/razorpay/success', [RazorpayController::class, 'success'])->name('razorpay.success');
Route::get('/payment/cancel', [RazorpayController::class, 'cancel'])->name('payment.cancel');
Route::post('/razorpay/webhook', [RazorpayController::class, 'webhook'])->name('razorpay.webhook');

// Square Payment Routes
Route::get('square/payment', [SquareController::class, 'payment'])->name('square.payment');
Route::post('square/payment', [SquareController::class, 'processPayment'])->name('square.payment.post');
Route::get('square/success', [SquareController::class, 'success'])->name('square.success');
Route::get('square/cancel', [SquareController::class, 'cancel'])->name('square.cancel');
Route::post('square/webhook', [SquareController::class, 'webhook'])->name('square.webhook');

// Mollie payment routes
Route::get('/mollie/payment', [MollieController::class, 'payment'])->name('mollie.payment');
Route::get('/mollie/success', [MollieController::class, 'success'])->name('mollie.success');
Route::get('/mollie/cancel', [MollieController::class, 'cancel'])->name('mollie.cancel');
Route::post('/mollie/webhook', [MollieController::class, 'webhook'])->name('mollie.webhook');

// Admin
Route::prefix('admin')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('admin');
    Route::view('/file-manager', 'backend.layouts.file-manager')->name('file-manager');
    Route::post('/category/{id}/child', [CategoryController::class, 'getChildByParent']);

    Route::get('/profile', [AdminController::class, 'profile'])->name('admin-profile');
    Route::post('/profile/{id}', [AdminController::class, 'profileUpdate'])->name('profile-update');
    Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
    Route::post('/setting/update', [AdminController::class, 'settingsUpdate'])->name('settings.update');
    Route::get('/change-password', [AdminController::class, 'changePassword'])->name('change.password.form');
    Route::post('/change-password', [AdminController::class, 'changPasswordStore'])->name('change.password');

    Route::get('/message/five', [MessageController::class, 'messageFive'])->name('messages.five');

    Route::get('/notification/{id}', [NotificationController::class, 'show'])->name('admin.notification');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('all.notification');
    Route::delete('/notification/{id}', [NotificationController::class, 'delete'])->name('notification.delete');

    Route::get('category/tree', [CategoryController::class, 'tree'])->name('category.tree');
    Route::get('category/get-tree-data', [CategoryController::class, 'getTreeData'])->name('category.get-tree-data');
    Route::post('category/update-tree', [CategoryController::class, 'updateTree'])->name('category.update-tree');
    Route::post('category/get-child-by-parent', [CategoryController::class, 'getChildByParent'])->name('category.get-child-by-parent');

    Route::resources([
        'users' => UsersController::class,
        'banner' => BannerController::class,
        'brand' => BrandController::class,
        'category' => CategoryController::class,
        'discount' => DiscountController::class,
        'product' => ProductController::class,
        'post-category' => PostCategoryController::class,
        'post-tag' => PostTagController::class,
        'post' => PostController::class,
        'message' => MessageController::class,
        'order' => OrderController::class,
        'shipping' => ShippingController::class,
        'coupon' => CouponController::class,
        'filter' => FilterController::class,
    ]);

    Route::delete('/product/{product}/image/{image}/delete', [ProductController::class, 'deleteImage'])
        ->name('product.image.delete');

    Route::post('/brand/store-ajax', [BrandController::class, 'storeAjax'])->name('brand.store.ajax');
});

// User
Route::prefix('user')->middleware('user')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('user');
    Route::get('/profile', [HomeController::class, 'profile'])->name('user-profile');
    Route::post('/profile/{id}', [HomeController::class, 'profileUpdate'])->name('user-profile-update');

    Route::get('/order', [HomeController::class, 'orderIndex'])->name('user.order.index');
    Route::get('/order/show/{id}', [HomeController::class, 'orderShow'])->name('user.order.show');
    Route::delete('/order/delete/{id}', [HomeController::class, 'userOrderDelete'])->name('user.order.delete');

    Route::get('/user-review', [HomeController::class, 'productReviewIndex'])->name('user.productreview.index');
    Route::get('/user-review/edit/{id}', [HomeController::class, 'productReviewEdit'])->name('user.productreview.edit');
    Route::patch('/user-review/update/{id}', [HomeController::class, 'productReviewUpdate'])->name('user.productreview.update');
    Route::delete('/user-review/delete/{id}', [HomeController::class, 'productReviewDelete'])->name('user.productreview.delete');

    Route::get('/user-post/comment', [HomeController::class, 'userComment'])->name('user.post-comment.index');
    Route::get('/user-post/comment/edit/{id}', [HomeController::class, 'userCommentEdit'])->name('user.post-comment.edit');
    Route::patch('/user-post/comment/update/{id}', [HomeController::class, 'userCommentUpdate'])->name('user.post-comment.update');
    Route::delete('/user-post/comment/delete/{id}', [HomeController::class, 'userCommentDelete'])->name('user.post-comment.delete');

    Route::get('/change-password', [HomeController::class, 'changePassword'])->name('user.change.password.form');
    Route::post('/change-password', [HomeController::class, 'changPasswordStore'])->name('change.password');
});

// File Manager
Route::prefix('laravel-filemanager')->middleware(['web', 'auth'])->group(function () {
    Lfm::routes();
});