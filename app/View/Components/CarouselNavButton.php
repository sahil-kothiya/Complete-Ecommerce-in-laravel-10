<?php
// app/View/Components/CarouselNavButton.php (Class-based component for reusability)
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

    public function __construct(
        $id,
        $ariaLabel,
        $icon = 'ti-angle-left',
        $direction = -1,
        $buttonClass = 'carousel-nav-btn',
        $iconClass = '',
        $backgroundColor = null, // Default: inherit from CSS
        $textColor = null, // Default: inherit from CSS
        $hoverScale = '1.05',
        $hoverShadow = '0 4px 8px rgba(0, 0, 0, 0.2)',
        $autoScrollSpeed = 150, // ms between scrolls
        $scrollAmount = 5, // pixels per scroll step
        $shimmerAnimation = true // Enable shimmer on auto-scroll
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
    }

    public function render()
    {
        return view('components.carousel-nav-button');
    }
}