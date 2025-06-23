<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\{
    AdminController,
    Auth\ForgotPasswordController,
    Auth\LoginController,
    Auth\ResetPasswordController,
    CartController,
    CouponController,
    FrontendController,
    HomeController,
    MessageController,
    NotificationController,
    OrderController,
    PayPalController,
    PostCommentController,
    ProductReviewController,
    WishlistController
};
use UniSharp\LaravelFilemanager\Lfm;

// Utility Routes
Route::get('/check-redis-cache', function () {
    $key = "product_card_123";
    Cache::tags(['product_card'])->put($key, ['id' => 123, 'title' => 'Test Product'], now()->addMinutes(5));
    return response()->json([
        'written' => Cache::tags(['product_card'])->has($key),
        'value' => Cache::tags(['product_card'])->get($key),
    ]);
});

Route::get('cache-clear', function () {
    Artisan::call('optimize:clear');
    session()->flash('success', 'Successfully cache cleared.');
    return back();
})->name('cache.clear');

Route::get('storage-link', [AdminController::class, 'storageLink'])->name('storage.link');

// Authentication
Auth::routes(['register' => false]);

Route::controller(FrontendController::class)->group(function () {
    Route::get('user/login', 'login')->name('login.form');
    Route::post('user/login', 'loginSubmit')->name('login.submit');
    Route::get('user/logout', 'logout')->name('user.logout');
    Route::get('user/register', 'register')->name('register.form');
    Route::post('user/register', 'registerSubmit')->name('register.submit');
});

// Password Reset
Route::controller(ForgotPasswordController::class)->group(function () {
    Route::get('password/reset', 'showLinkRequestForm')->name('password.request');
    Route::post('password/email', 'sendResetLinkEmail')->name('password.email');
});

Route::controller(ResetPasswordController::class)->group(function () {
    Route::get('password/reset/{token}', 'showResetForm')->name('password.reset');
    Route::post('password/reset', 'reset')->name('password.update');
});

// Socialite
Route::controller(LoginController::class)->group(function () {
    Route::get('login/{provider}/', 'redirect')->name('login.redirect');
    Route::get('login/{provider}/callback/', 'callback')->name('login.callback');
});

// Frontend Routes
Route::controller(FrontendController::class)->group(function () {
    Route::get('/', 'home')->name('home');
    Route::get('/home', 'index');
    Route::get('/about-us', 'aboutUs')->name('about-us');
    Route::get('/contact', 'contact')->name('contact');
    Route::post('/contact/message', [MessageController::class, 'store'])->name('contact.store');
    Route::get('product-detail/{slug}', 'productDetail')->name('product-detail');
    Route::match(['get', 'post'], '/search', 'productSearch')->name('product.search');
    Route::get('/autocomplete', 'autocomplete')->name('autocomplete');
    Route::get('/product-cat/{slug}', 'productCat')->name('product-cat');
    Route::get('/product-sub-cat/{slug}/{sub_slug}', 'productSubCat')->name('product-sub-cat');
    Route::get('/product-brand/{slug}', 'productBrand')->name('product-brand');
    Route::get('/product-grids', 'productGrids')->name('product-grids');
    Route::get('/product-lists', 'productLists')->name('product-lists');
    Route::match(['get', 'post'], '/filter', 'productFilter')->name('shop.filter');
    Route::get('/blog', 'blog')->name('blog');
    Route::get('/blog-detail/{slug}', 'blogDetail')->name('blog.detail');
    Route::get('/blog/search', 'blogSearch')->name('blog.search');
    Route::post('/blog/filter', 'blogFilter')->name('blog.filter');
    Route::get('blog-cat/{slug}', 'blogByCategory')->name('blog.category');
    Route::get('blog-tag/{slug}', 'blogByTag')->name('blog.tag');
    Route::post('/subscribe', 'subscribe')->name('subscribe');
});

// Cart Routes
Route::middleware('user')->group(function () {
    Route::get('/add-to-cart/{slug}', [CartController::class, 'addToCart'])->name('add-to-cart');
    Route::post('/add-to-cart', [CartController::class, 'singleAddToCart'])->name('single-add-to-cart');
    Route::get('/checkout', [CartController::class, 'checkout'])->name('checkout');
});

Route::get('/cart', fn() => view('frontend.pages.cart'))->name('cart');
Route::get('cart-delete/{id}', [CartController::class, 'cartDelete'])->name('cart-delete');
Route::post('cart-update', [CartController::class, 'cartUpdate'])->name('cart.update');

// Wishlist
Route::get('/wishlist', fn() => view('frontend.pages.wishlist'))->name('wishlist');
Route::middleware('user')->group(function () {
    Route::get('/wishlist/{slug}', [WishlistController::class, 'wishlist'])->name('add-to-wishlist');
});
Route::get('wishlist-delete/{id}', [WishlistController::class, 'wishlistDelete'])->name('wishlist-delete');

// Order
Route::post('cart/order', [OrderController::class, 'store'])->name('cart.order');
Route::get('order/pdf/{id}', [OrderController::class, 'pdf'])->name('order.pdf');
Route::get('/income', [OrderController::class, 'incomeChart'])->name('product.order.income');
Route::get('/product/track', [OrderController::class, 'orderTrack'])->name('order.track');
Route::post('product/track/order', [OrderController::class, 'productTrackOrder'])->name('product.track.order');

// Product Review
Route::resource('/review', ProductReviewController::class);
Route::post('product/{slug}/review', [ProductReviewController::class, 'store'])->name('review.store');

// Post Comment
Route::resource('/comment', PostCommentController::class);
Route::post('post/{slug}/comment', [PostCommentController::class, 'store'])->name('post-comment.store');

// Coupon
Route::post('/coupon-store', [CouponController::class, 'couponStore'])->name('coupon-store');

// Payment
Route::controller(PayPalController::class)->group(function () {
    Route::get('payment', 'payment')->name('payment');
    Route::get('cancel', 'cancel')->name('payment.cancel');
    Route::get('payment/success', 'success')->name('payment.success');
});

// Admin Routes
Route::prefix('admin')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('admin');
    Route::view('/file-manager', 'backend.layouts.file-manager')->name('file-manager');
    Route::resources([
        'users' => 'UsersController',
        'banner' => 'BannerController',
        'brand' => 'BrandController',
        'category' => 'CategoryController',
        'product' => 'ProductController',
        'post-category' => 'PostCategoryController',
        'post-tag' => 'PostTagController',
        'post' => 'PostController',
        'message' => 'MessageController',
        'order' => 'OrderController',
        'shipping' => 'ShippingController',
        'coupon' => 'CouponController'
    ]);

    Route::post('/category/{id}/child', 'CategoryController@getChildByParent');
    Route::get('/profile', [AdminController::class, 'profile'])->name('admin-profile');
    Route::post('/profile/{id}', [AdminController::class, 'profileUpdate'])->name('profile-update');
    Route::get('settings', [AdminController::class, 'settings'])->name('settings');
    Route::post('setting/update', [AdminController::class, 'settingsUpdate'])->name('settings.update');
    Route::controller(NotificationController::class)->group(function () {
        Route::get('/notification/{id}', 'show')->name('admin.notification');
        Route::get('/notifications', 'index')->name('all.notification');
        Route::delete('/notification/{id}', 'delete')->name('notification.delete');
    });
    Route::get('change-password', [AdminController::class, 'changePassword'])->name('change.password.form');
    Route::post('change-password', [AdminController::class, 'changPasswordStore'])->name('change.password');
    Route::get('/message/five', [MessageController::class, 'messageFive'])->name('messages.five');
});

// User Routes
Route::prefix('user')->middleware('user')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('user');
    Route::get('/profile', [HomeController::class, 'profile'])->name('user-profile');
    Route::post('/profile/{id}', [HomeController::class, 'profileUpdate'])->name('user-profile-update');

    Route::get('/order', [HomeController::class, 'orderIndex'])->name('user.order.index');
    Route::get('/order/show/{id}', [HomeController::class, 'orderShow'])->name('user.order.show');
    Route::delete('/order/delete/{id}', [HomeController::class, 'userOrderDelete'])->name('user.order.delete');

    Route::prefix('user-review')->group(function () {
        Route::get('/', [HomeController::class, 'productReviewIndex'])->name('user.productreview.index');
        Route::delete('/delete/{id}', [HomeController::class, 'productReviewDelete'])->name('user.productreview.delete');
        Route::get('/edit/{id}', [HomeController::class, 'productReviewEdit'])->name('user.productreview.edit');
        Route::patch('/update/{id}', [HomeController::class, 'productReviewUpdate'])->name('user.productreview.update');
    });

    Route::prefix('user-post/comment')->group(function () {
        Route::get('/', [HomeController::class, 'userComment'])->name('user.post-comment.index');
        Route::delete('/delete/{id}', [HomeController::class, 'userCommentDelete'])->name('user.post-comment.delete');
        Route::get('/edit/{id}', [HomeController::class, 'userCommentEdit'])->name('user.post-comment.edit');
        Route::patch('/udpate/{id}', [HomeController::class, 'userCommentUpdate'])->name('user.post-comment.update');
    });

    Route::get('change-password', [HomeController::class, 'changePassword'])->name('user.change.password.form');
    Route::post('change-password', [HomeController::class, 'changPasswordStore'])->name('change.password');
});

// File Manager
Route::prefix('laravel-filemanager')->middleware(['web', 'auth'])->group(function () {
    Lfm::routes();
});
