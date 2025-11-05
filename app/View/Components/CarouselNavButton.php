<?php
// Update to app/View/Components/CarouselNavButton.php (Add carouselId prop for better reusability)
namespace App\View\Components;

use Illuminate\View\Component;

class CarouselNavButton extends Component
{
    public $id;
    public $ariaLabel;
    public $icon;
    public $direction;
    public $buttonClass;
    public $iconClass;
    public $backgroundColor;
    public $textColor;
    public $hoverScale;
    public $hoverShadow;
    public $autoScrollSpeed;
    public $scrollAmount;
    public $shimmerAnimation;
    public $carouselId; // New prop to specify the target carousel track

    public function __construct(
        $id,
        $ariaLabel = '',
        $icon = 'ti-angle-left',
        $direction = -1,
        $buttonClass = 'carousel-nav-btn',
        $iconClass = '',
        $backgroundColor = null,
        $textColor = null,
        $hoverScale = '1.05',
        $hoverShadow = '0 4px 8px rgba(0, 0, 0, 0.2)',
        $autoScrollSpeed = 150,
        $scrollAmount = 5,
        $shimmerAnimation = true,
        $carouselId = 'recentCarousel' // Default; will be overridden when passed
    ) {
        $this->id = $id;
        $this->ariaLabel = $ariaLabel;
        $this->icon = $icon;
        $this->direction = $direction;
        $this->buttonClass = $buttonClass;
        $this->iconClass = $iconClass;
        $this->backgroundColor = $backgroundColor;
        $this->textColor = $textColor;
        $this->hoverScale = $hoverScale;
        $this->hoverShadow = $hoverShadow;
        $this->autoScrollSpeed = $autoScrollSpeed;
        $this->scrollAmount = $scrollAmount;
        $this->shimmerAnimation = $shimmerAnimation;
        $this->carouselId = $carouselId;
    }

    public function render()
    {
        return view('components.carousel-nav-button');
    }
}