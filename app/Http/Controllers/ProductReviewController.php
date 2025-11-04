<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Notification;
use App\Notifications\StatusNotification;
use App\User;
use App\Models\ProductReview;
use Illuminate\Support\Facades\Auth;

class ProductReviewController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $reviews = ProductReview::getAllReview();

        return view('backend.review.index')->with('reviews', $reviews);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {}

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'rate' => 'required|numeric|min:1|max:5',
            'review' => 'required|string|max:1000'
        ]);

        $product_info = Product::getProductBySlug($request->slug);
        $data = $request->all();
        $data['product_id'] = $product_info->id;
        $data['user_id'] = Auth::id();
        $data['status'] = 'active';

        $review = ProductReview::create($data);

        // Send notification to admins
        $user = User::where('role', 'admin')->get();
        $details = [
            'title' => 'New Product Rating!',
            'actionURL' => route('product-detail', $product_info->slug),
            'fas' => 'fa-star'
        ];
        Notification::send($user, new StatusNotification($details));

        if ($request->expectsJson()) {
            // Prepare response for AJAX
            $userInfo = $review->user; // Assuming relation: belongsTo(User::class)
            // Make access null-safe. `optional()` returns null when $userInfo is null.
            $newReviewData = [
                'user_name' => optional($userInfo)->name ?? 'Anonymous',
                'user_photo' => optional($userInfo)->photo ? asset(optional($userInfo)->photo) : asset('backend/img/avatar.webp'),
                'rate' => $review->rate,
                'review' => $review->review,
            ];

            // Recalculate average and total
            $allReviews = $product_info->getReview; // Assuming relation
            $avg = $allReviews->avg('rate');
            $total = $allReviews->count();

            return response()->json([
                'success' => true,
                'message' => 'Thank you for your feedback',
                'new_review' => $newReviewData,
                'avg_rating' => (float) $avg,
                'total_reviews' => $total
            ]);
        }

        // Non-AJAX fallback
        return redirect()->back()->with('success', 'Thank you for your feedback');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $review = ProductReview::find($id);
        // return $review;
        return view('backend.review.edit')->with('review', $review);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $review = ProductReview::find($id);
        if ($review) {
            // $product_info=Product::getProductBySlug($request->slug);
            //  return $product_info;
            // return $request->all();
            $data = $request->all();
            $status = $review->fill($data)->update();

            // $user=User::where('role','admin')->get();
            // return $user;
            // $details=[
            //     'title'=>'Update Product Rating!',
            //     'actionURL'=>route('product-detail',$product_info->id),
            //     'fas'=>'fa-star'
            // ];
            // Notification::send($user,new StatusNotification($details));
            if ($status) {
                session()->flash('success', 'Review Successfully updated');
            } else {
                session()->flash('error', 'Something went wrong! Please try again!!');
            }
        } else {
            session()->flash('error', 'Review not found!!');
        }

        return redirect()->route('review.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $review = ProductReview::find($id);
        if (! $review) {
            session()->flash('error', 'Review not found');
            return redirect()->route('review.index');
        }

        $status = $review->delete();
        if ($status) {
            session()->flash('success', 'Successfully deleted review');
        } else {
            session()->flash('error', 'Something went wrong! Try again');
        }

        return redirect()->route('review.index');
    }
}