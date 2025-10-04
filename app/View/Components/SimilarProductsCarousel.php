<?php
// app/View/Components/SimilarProductsCarousel.php (Main component for similar products)
namespace App\View\Components;

use Illuminate\View\Component;

class SimilarProductsCarousel extends Component
{
    public $products;
    public $carouselId;
    public $title;
    public $noProductsMessage;
    public $itemMinWidth;
    public $itemMaxWidth;
    public $prevButtonId;
    public $nextButtonId;
    public $defaultBackgroundColor;
    public $defaultTextColor;
    public $defaultHoverScale;
    public $defaultHoverShadow;
    public $defaultAutoScrollSpeed;
    public $defaultScrollAmount;
    public $defaultShimmer;
    public $startingTabIndex;

    public function __construct(
        $products = null,
        $carouselId = 'relatedCarousel',
        $title = 'Similar Products',
        $noProductsMessage = 'No related products found.',
        $itemMinWidth = '180px',
        $itemMaxWidth = '180px',
        $prevButtonId = 'carouselPrev',
        $nextButtonId = 'carouselNext',
        $defaultBackgroundColor = null,
        $defaultTextColor = null,
        $defaultHoverScale = '1.05',
        $defaultHoverShadow = '0 4px 8px rgba(0, 0, 0, 0.2)',
        $defaultAutoScrollSpeed = 150,
        $defaultScrollAmount = 5,
        $defaultShimmer = true,
        $startingTabIndex = 47
    ) {
        $this->products = $products ?? collect();
        $this->carouselId = $carouselId;
        $this->title = $title;
        $this->noProductsMessage = $noProductsMessage;
        $this->itemMinWidth = $itemMinWidth;
        $this->itemMaxWidth = $itemMaxWidth;
        $this->prevButtonId = $prevButtonId;
        $this->nextButtonId = $nextButtonId;
        $this->defaultBackgroundColor = $defaultBackgroundColor;
        $this->defaultTextColor = $defaultTextColor;
        $this->defaultHoverScale = $defaultHoverScale;
        $this->defaultHoverShadow = $defaultHoverShadow;
        $this->defaultAutoScrollSpeed = $defaultAutoScrollSpeed;
        $this->defaultScrollAmount = $defaultScrollAmount;
        $this->defaultShimmer = $defaultShimmer;
        $this->startingTabIndex = $startingTabIndex;
    }

    public function render()
    {
        return view('components.similar-products-carousel');
    }
}