<?php

use App\Models\Banner;
use App\Models\Message;
use App\Models\Category;
use App\Models\PostTag;
use App\Models\PostCategory;
use App\Models\Order;
use App\Models\Wishlist;
use App\Models\Shipping;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\DiscountService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// use Auth;
class Helper
{
    public static function messageList()
    {
        return Message::whereNull('read_at')->orderBy('created_at', 'desc')->get();
    }
    public static function getAllCategory()
    {
        $category = new Category();
        $menu = $category->getAllParentWithChild();
        return $menu;
    }

    public static function getHeaderCategory()
    {
        $category = new Category();
        // dd($category);
        $menu = $category->getAllParentWithChild();

        if ($menu) {
?>

            <li>
                <a href="javascript:void(0);">Category<i class="ti-angle-down"></i></a>
                <ul class="dropdown border-0 shadow">
                    <?php
                    foreach ($menu as $cat_info) {
                        if ($cat_info->child_cat->count() > 0) {
                    ?>
                            <li><a href="<?php echo route('product-cat', $cat_info->slug); ?>"><?php echo $cat_info->title; ?></a>
                                <ul class="dropdown sub-dropdown border-0 shadow">
                                    <?php
                                    foreach ($cat_info->child_cat as $sub_menu) {
                                    ?>
                                        <li><a href="<?php echo route('product-sub-cat', [$cat_info->slug, $sub_menu->slug]); ?>"><?php echo $sub_menu->title; ?></a></li>
                                    <?php
                                    }
                                    ?>
                                </ul>
                            </li>
                        <?php
                        } else {
                        ?>
                            <li><a href="<?php echo route('product-cat', $cat_info->slug); ?>"><?php echo $cat_info->title; ?></a></li>
                    <?php
                        }
                    }
                    ?>
                </ul>
            </li>
<?php
        }
    }

    public static function getCategoryTree()
    {
        // Load once — no recursive queries
        $categories = Category::where('status', 'active')
            ->orderBy('parent_id')
            ->orderBy('title')
            ->get();

        // Group by parent_id
        $grouped = $categories->groupBy('parent_id');

        // Recursive builder
        $buildTree = function ($parentId) use (&$buildTree, $grouped) {
            return ($grouped[$parentId] ?? collect())->map(function ($category) use (&$buildTree) {
                $category->children = $buildTree($category->id);
                return $category;
            });
        };

        // Root categories (parent_id = null)
        return $buildTree(null);
    }

    public static function productCategoryList($option = 'all')
    {
        if ($option = 'all') {
            return Category::orderBy('id', 'DESC')->get();
        }
        return Category::has('products')->orderBy('id', 'DESC')->get();
    }

    public static function postTagList($option = 'all')
    {
        if ($option = 'all') {
            return PostTag::orderBy('id', 'desc')->get();
        }
        return PostTag::has('posts')->orderBy('id', 'desc')->get();
    }

    public static function postCategoryList($option = "all")
    {
        if ($option = 'all') {
            return PostCategory::orderBy('id', 'DESC')->get();
        }
        return PostCategory::has('posts')->orderBy('id', 'DESC')->get();
    }
    // Cart Count
    public static function cartCount($user_id = '')
    {

        if (Auth::check()) {
            if ($user_id == "") $user_id = auth()->user()->id;
            return Cart::where('user_id', $user_id)->where('order_id', null)->sum('quantity');
        } else {
            return 0;
        }
    }
    // relationship cart with product
    public function product()
    {
        return $this->hasOne('App\Models\Product', 'id', 'product_id');
    }


   /**
     * Get all cart items for a user or guest.
     *
     * @param string|int|null $user_id User ID (optional, defaults to authenticated user)
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection
     */
    public static function getAllProductFromCart($user_id = '')
    {
        if (Auth::check()) {
            // Use authenticated user's ID if none provided
            $user_id = $user_id ?: Auth::user()->id;

            return Cart::with([
                'product.images' => fn($query) => $query->select(['id', 'product_id', 'image_path', 'thumbnail_path', 'is_primary', 'sort_order']),
                'variant.variantOptions.variantType' => fn($query) => $query->select(['id', 'name', 'display_name', 'sort_order']),
                'variant.images' => fn($query) => $query->select(['id', 'product_variant_id', 'image_path', 'thumbnail_path', 'is_primary', 'sort_order'])
            ])
                ->where('user_id', $user_id)
                ->where('order_id', null) // Only fetch cart items not yet ordered
                ->get();
        } else {
            // Handle guest cart (session-based)
            $cartItems = Session::get('cart', []);

            if (empty($cartItems)) {
                return collect(); // Return empty collection for empty guest cart
            }

            // Fetch products and variants for guest cart items
            $cartItemsCollection = collect($cartItems)->map(function ($item, $index) {
                $cart = new Cart();
                $cart->id = $item['session_key'] ?? 'guest_' . $index; // Use session_key or generate temp ID for JS compatibility
                $cart->quantity = $item['quantity'] ?? 1;
                $cart->product_id = $item['product_id'] ?? null;
                $cart->variant_id = $item['variant_id'] ?? null;

                // Load product and variant relationships
                $cart->product = Product::with([
                    'images' => fn($query) => $query->select(['id', 'product_id', 'image_path', 'thumbnail_path', 'is_primary', 'sort_order'])
                ])->find($item['product_id']);

                if ($item['variant_id']) {
                    $cart->variant = ProductVariant::with([
                        'variantOptions.variantType' => fn($query) => $query->select(['id', 'name', 'display_name', 'sort_order']),
                        'images' => fn($query) => $query->select(['id', 'product_variant_id', 'image_path', 'thumbnail_path', 'is_primary', 'sort_order'])
                    ])->find($item['variant_id']);
                }

                return $cart;
            });

            return $cartItemsCollection;
        }
    }

    private function generateUniqueSlug($title, $excludeId = null)
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        $query = Banner::where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;

            $query = Banner::where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }

    public static function totalCartPrice($user_id = null)
    {
        if (!Auth::check()) return 0;

        $user_id = $user_id ?? auth()->id();

        $cartItems = Cart::with('product.cat_info.discounts')
            ->where('user_id', $user_id)
            ->whereNull('order_id')
            ->get();

        $discountService = app(DiscountService::class);
        $total = 0;

        foreach ($cartItems as $cart) {
            $product = $cart->product;
            $quantity = $cart->quantity;

            // Original product price
            $originalPrice = $product->price;

            // Get all applicable discounts (category, product, etc.)
            $discounts = $discountService->getEffectiveDiscounts($product);

            // Apply all discounts to get the final price
            $discountedPrice = $discountService->applyAllDiscounts($originalPrice, $discounts);

            // Add to total based on quantity
            $total += $discountedPrice * $quantity;
        }

        return round($total, 2);
    }


    public static function totalCartPriceWithBreakdown($user_id = '')
    {
        if (!Auth::check()) {
            return [
                'total' => 0,
                'saved' => 0,
                'discount_breakdown' => [],
            ];
        }

        $user_id = $user_id ?: auth()->id();
        $carts = Cart::with(['product.cat_info.discounts'])->where('user_id', $user_id)->whereNull('order_id')->get();
        $discountService = app(DiscountService::class);

        $total = 0;
        $saved = 0;
        $discountBreakdown = [];

        foreach ($carts as $cart) {
            $product = $cart->product;
            $originalPrice = $product->price;
            $quantity = $cart->quantity;

            // Get all discounts (product & category)
            $discounts = $discountService->getEffectiveDiscounts($product);
            $discountedPrice = $discountService->applyAllDiscounts($originalPrice, $discounts);

            // Compute total price and savings
            $total += $discountedPrice * $quantity;
            $saved += ($originalPrice - $discountedPrice) * $quantity;

            // Aggregate discount breakdown per type/title
            // Compute saved amount per discount type (in order)
            $intermediatePrice = $originalPrice;
            foreach ($discounts as $discount) {
                $key = ($discount['title'] ?? ucfirst($discount['source'])) . ' (' . $discount['type'] . ')';

                if (!isset($discountBreakdown[$key])) {
                    $discountBreakdown[$key] = [
                        'title' => $discount['title'] ?? ucfirst($discount['source']),
                        'type' => $discount['type'],
                        'value' => $discount['value'],
                        'source' => $discount['source'],
                        'saved' => 0,
                    ];
                }

                // Apply discount to intermediate price
                if ($discount['type'] === 'percentage') {
                    $savedPerUnit = $intermediatePrice * ($discount['value'] / 100);
                } else {
                    $savedPerUnit = $discount['value'];
                }

                $discountBreakdown[$key]['saved'] += $savedPerUnit * $quantity;

                // Reduce price for next layer
                $intermediatePrice -= $savedPerUnit;
            }
        }

        return [
            'total' => round($total, 2),
            'saved' => round($saved, 2),
            'discount_breakdown' => collect($discountBreakdown)->values()->toArray(),
            // 'discount_breakdown' => collect($discountBreakdown)->sortByDesc('saved')->values()->toArray(),
        ];
    }

    // Total amount cart
    // public static function totalCartPrice($user_id = '')
    // {
    //     if (Auth::check()) {
    //         if ($user_id == "") $user_id = auth()->user()->id;
    //         return Cart::where('user_id', $user_id)->where('order_id', null)->sum('amount');
    //     } else {
    //         return 0;
    //     }
    // }
    // Wishlist Count
    public static function wishlistCount($user_id = '')
    {
        if (Auth::check()) {
            if ($user_id == "") $user_id = auth()->user()->id;
            return Wishlist::where('user_id', $user_id)->where('cart_id', null)->sum('quantity');
        } else {
            return 0;
        }
    }
    public static function getAllProductFromWishlist($user_id = '')
    {
        if (Auth::check()) {
            $user_id = $user_id ?: auth()->user()->id;

            return Wishlist::with([
                    'product' => function ($q) {
                        $q->select('id', 'title', 'slug', 'summary', 'base_price', 'base_discount', 'has_variants', 'status')
                        ->with([
                            'images' => function ($query) {
                                $query->select(['id', 'product_id', 'image_path', 'thumbnail_path', 'is_primary', 'sort_order'])
                                        ->orderByDesc('is_primary')
                                        ->orderBy('sort_order');
                            }
                        ]);
                    },
                    'variant' => function ($q) {
                        $q->select('id', 'product_id', 'sku', 'price', 'discount', 'stock', 'status', 'variant_values')
                        ->with([
                            'variantOptions' => function ($query) {
                                $query->select('product_variant_options.id', 'variant_type_id', 'display_value', 'value', 'sort_order')
                                        ->orderBy('sort_order');
                            },
                            'variantOptions.variantType' => function ($query) {
                                $query->select('id', 'name', 'display_name', 'sort_order');
                            },
                            'images' => function ($query) {
                                $query->select(['id', 'product_variant_id', 'image_path', 'thumbnail_path', 'is_primary', 'sort_order'])
                                        ->orderByDesc('is_primary')
                                        ->orderBy('sort_order');
                            }
                        ]);
                    }
                ])
                ->where('user_id', $user_id)
                ->whereNull('cart_id')
                ->get();
        }

        return collect();
    }

    // public static function totalWishlistPrice($user_id = '')
    // {
    //     if (Auth::check()) {
    //         if ($user_id == "") $user_id = auth()->user()->id;
    //         return Wishlist::where('user_id', $user_id)->where('cart_id', null)->sum('amount');
    //     } else {
    //         return 0;
    //     }
    // }

    public static function totalWishlistPrice()
    {
        return Helper::getAllProductFromWishlist()->sum(function ($item) {
            return $item->variant ? $item->variant->discounted_price : 
                ($item->price ?? $item->product->base_price * (1 - ($item->product->base_discount ?? 0)/100));
        });
    }

    private function cleanupOldVariants(Product $product)
    {
        if ($product->getOriginal('has_variants')) {
            foreach ($product->variants as $variant) {
                foreach ($variant->images as $img) {
                    Storage::delete('public/' . $img->image_path);
                }
                $variant->delete();
            }
        }
    }

    private function cleanupOldProductImages(Product $product)
    {
        if (!$product->getOriginal('has_variants')) {
            foreach ($product->images as $img) {
                Storage::delete('public/' . $img->image_path);
            }
            ProductImage::where('product_id', $product->id)->delete();
        }
    }

    public static function isProductInWishlist($productSlug)
    {
        if (!auth()->check()) return false;

        return \App\Models\Wishlist::where('user_id', auth()->id())
            ->whereHas('product', function ($query) use ($productSlug) {
                $query->where('slug', $productSlug);
            })->exists();
    }

    // Total price with shipping and coupon
    public static function grandPrice($id, $user_id)
    {
        $order = Order::find($id);
        dd($id);
        if ($order) {
            $shipping_price = (float)$order->shipping->price;
            $order_price = self::orderPrice($id, $user_id);
            return number_format((float)($order_price + $shipping_price), 2, '.', '');
        } else {
            return 0;
        }
    }


    // Admin home
    public static function earningPerMonth()
    {
        $month_data = Order::where('status', 'delivered')->get();
        // return $month_data;
        $price = 0;
        foreach ($month_data as $data) {
            $price = $data->cart_info->sum('price');
        }
        return number_format((float)($price), 2, '.', '');
    }

    public static function shipping()
    {
        return Shipping::orderBy('id', 'DESC')->get();
    }
}



if (!function_exists('generateUniqueSlug')) {
    /**
     * Generate a unique slug for a given title and model.
     *
     * @param string $title
     * @param string $modelClass
     * @return string
     */
    function generateUniqueSlug($title, $modelClass)
    {
        $slug = Str::slug($title);
        $count = $modelClass::where('slug', $slug)->count();

        if ($count > 0) {
            $slug = $slug . '-' . date('ymdis') . '-' . rand(0, 999);
        }

        return $slug;
    }
}

if (!function_exists('generateUniqueCode')) {
    /**
     * Generate a unique 3-character uppercase code based on a string.
     *
     * @param string $title
     * @param array $usedCodes
     * @return string
     */
    function generateUniqueCode(string $title, array $usedCodes): string
    {
        $slug = strtoupper(Str::slug($title));
        $slug = preg_replace('/[^A-Z]/', '', $slug);
        $base = substr($slug, 0, 3);

        if (strlen($base) < 3) {
            $base = strtoupper(Str::random(3));
        }

        $code = $base;
        $i = 1;

        while (in_array($code, $usedCodes)) {
            $suffix = strtoupper(base_convert($i, 10, 36));
            $code = substr($base, 0, 3 - strlen($suffix)) . $suffix;
            $code = str_pad($code, 3, 'X');
            $i++;
        }

        return $code;
    }
}
?>