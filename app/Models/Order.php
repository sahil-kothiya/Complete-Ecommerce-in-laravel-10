<?php

namespace App\Models;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'order_number',        
        'sub_total',
        'quantity',
        'status',
        'transaction_id',
        'total_amount',
        'first_name',
        'last_name',
        'country',
        'post_code',
        'address1',
        'address2',
        'phone',
        'email',
        'payment_method',
        'payment_status',
        'shipping_id',
        'coupon',
        'shipping_cost',   
    ];

    protected $casts = [
        'sub_total' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'coupon' => 'decimal:2',
        'quantity' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Status constants
    const STATUS_NEW = 'new';
    const STATUS_PROCESS = 'process';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancel'; // Note: keeping 'cancel' to match your existing data

    // Payment status constants
    const PAYMENT_STATUS_PAID = 'paid';
    const PAYMENT_STATUS_UNPAID = 'unpaid';

    // Payment method constants
    const PAYMENT_METHOD_COD = 'cod';
    const PAYMENT_METHOD_PAYPAL = 'paypal';
    const PAYMENT_METHOD_STRIPE = 'stripe';
    const PAYMENT_METHOD_SQUARE = 'square';
    const PAYMENT_METHOD_MOLLIE = 'mollie';

    /**
     * Get all valid statuses
     */
    public static function getValidStatuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_PROCESS,
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * Get all valid payment statuses
     */
    public static function getValidPaymentStatuses(): array
    {
        return [
            self::PAYMENT_STATUS_PAID,
            self::PAYMENT_STATUS_UNPAID,
        ];
    }

    /**
     * Get all valid payment methods
     */
    public static function getValidPaymentMethods(): array
    {
        return [
            self::PAYMENT_METHOD_COD,
            self::PAYMENT_METHOD_PAYPAL,
            self::PAYMENT_METHOD_STRIPE,
            self::PAYMENT_METHOD_SQUARE,
            self::PAYMENT_METHOD_MOLLIE,
        ];
    }

    // Relationship methods
    public function cart_info(): HasMany
    {
        return $this->hasMany(Cart::class, 'order_id', 'id');
    }

    public function cart(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function shipping(): BelongsTo
    {
        return $this->belongsTo(Shipping::class, 'shipping_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Static methods (optimized)
    public static function getAllOrder($id)
    {
        return self::with(['cart_info.product', 'user', 'shipping'])->find($id);
    }

    public static function countActiveOrder(): int
    {
        return self::whereIn('status', [
            self::STATUS_NEW,
            self::STATUS_PROCESS
        ])->count();
    }

    public static function getTotalRevenue(): float
    {
        return self::where('payment_status', self::PAYMENT_STATUS_PAID)
            ->where('status', self::STATUS_DELIVERED)
            ->sum('total_amount');
    }

    // Accessor methods
    public function getPaymentMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            self::PAYMENT_METHOD_COD => 'Cash on Delivery',
            self::PAYMENT_METHOD_PAYPAL => 'PayPal',
            self::PAYMENT_METHOD_STRIPE => 'Stripe',
            self::PAYMENT_METHOD_SQUARE => 'Square',
            self::PAYMENT_METHOD_MOLLIE => 'Mollie',
            default => ucfirst($this->payment_method)
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_NEW => 'New Order',
            self::STATUS_PROCESS => 'Processing',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst($this->status)
        };
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            self::PAYMENT_STATUS_PAID => 'Paid',
            self::PAYMENT_STATUS_UNPAID => 'Unpaid',
            default => ucfirst($this->payment_status)
        };
    }

    public function getFormattedTotalAttribute(): string
    {
        return '$' . number_format($this->total_amount, 2);
    }

    public function getFormattedSubTotalAttribute(): string
    {
        return '$' . number_format($this->sub_total, 2);
    }

    public function getTotalItemsAttribute(): int
    {
        return $this->cart_info->sum('quantity');
    }

    // Query scopes
    public function scopeStripeOrders($query)
    {
        return $query->where('payment_method', self::PAYMENT_METHOD_STRIPE);
    }

    public function scopePaypalOrders($query)
    {
        return $query->where('payment_method', self::PAYMENT_METHOD_PAYPAL);
    }

    public function scopeCodOrders($query)
    {
        return $query->where('payment_method', self::PAYMENT_METHOD_COD);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', self::PAYMENT_STATUS_PAID);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', self::PAYMENT_STATUS_UNPAID);
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESS);
    }

    public function scopeNew($query)
    {
        return $query->where('status', self::STATUS_NEW);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // Helper methods
    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_STATUS_PAID;
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_NEW, self::STATUS_PROCESS]);
    }

    public function markAsPaid(): bool
    {
        return $this->update(['payment_status' => self::PAYMENT_STATUS_PAID]);
    }

    public function markAsDelivered(): bool
    {
        return $this->update([
            'status' => self::STATUS_DELIVERED,
            'payment_status' => self::PAYMENT_STATUS_PAID
        ]);
    }

    public function cancel(): bool
    {
        if (!$this->canBeCancelled()) {
            return false;
        }

        return $this->update(['status' => self::STATUS_CANCELLED]);
    }

    /**
     * Generate a unique order number
     */
    public static function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'ORD-' . strtoupper(uniqid());
        } while (self::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    /**
     * Boot method to handle model events
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate order number if not provided
        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber();
            }
        });

        // Update product stock when order is delivered
        static::updated(function ($order) {
            if ($order->isDirty('status') && $order->status === self::STATUS_DELIVERED) {
                foreach ($order->cart_info as $cartItem) {
                    if ($cartItem->product) {
                        $cartItem->product->decrement('stock', $cartItem->quantity);
                    }
                }
            }
        });
    }

    /**
     * Check if order can be refunded (for Mollie integration)
     */
    public function canBeRefunded(): bool
    {
        return $this->payment_status === self::PAYMENT_STATUS_PAID
            && in_array($this->payment_method, [self::PAYMENT_METHOD_MOLLIE, self::PAYMENT_METHOD_STRIPE])
            && $this->transaction_id;
    }
}
