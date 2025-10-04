{{-- Update to resources/views/components/carousel-nav-button.blade.php (Use carouselId in JS) --}}
<button type="button" 
        class="{{ $buttonClass }}" 
        id="{{ $id }}" 
        aria-label="{{ $ariaLabel }}"
        style="
            background-color: {{ $backgroundColor ?? 'inherit' }} !important; 
            color: {{ $textColor ?? 'inherit' }} !important;
        ">
    <i class="{{ $icon }} {{ $iconClass }}"></i>
</button>

@push('styles')
<style>
    #{{ $id }} {
        transition: all 0.2s ease;
        position: relative;
        overflow: hidden;
    }

    #{{ $id }}:hover {
        transform: scale({{ $hoverScale }});
        box-shadow: {{ $hoverShadow }};
    }

    #{{ $id }}.auto-scrolling {
        background-color: #007bff !important;
        color: white !important;
    }

    @if($shimmerAnimation)
    #{{ $id }}.auto-scrolling::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
        animation: shimmer 1s infinite;
    }

    @keyframes shimmer {
        0% { left: -100%; }
        100% { left: 100%; }
    }
    @endif
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const btn = document.getElementById('{{ $id }}');
        const track = document.getElementById('{{ $carouselId }}');
        const viewport = track?.closest('.carousel-viewport');

        let autoScrollInterval = null;
        const autoScrollSpeed = {{ $autoScrollSpeed }};
        const scrollAmount = {{ $scrollAmount }};

        function getItemWidth() {
            const item = track?.querySelector('.carousel-item');
            if (!item) return 180;
            const style = window.getComputedStyle(item);
            return item.offsetWidth + parseInt(style.marginRight || 0) + parseInt(style.marginLeft || 0);
        }

        function scrollByCard(dir = 1) {
            if (viewport) {
                viewport.scrollBy({
                    left: dir * getItemWidth(),
                    behavior: 'smooth'
                });
            }
        }

        function startAutoScroll(direction) {
            clearInterval(autoScrollInterval);
            autoScrollInterval = setInterval(() => {
                if (viewport) {
                    viewport.scrollBy({
                        left: direction * scrollAmount,
                        behavior: 'auto'
                    });
                }
            }, autoScrollSpeed);
        }

        function stopAutoScroll() {
            if (autoScrollInterval) {
                clearInterval(autoScrollInterval);
                autoScrollInterval = null;
            }
        }

        if (btn) {
            btn.addEventListener('click', () => scrollByCard({{ $direction }}));

            btn.addEventListener('mouseenter', () => {
                btn.classList.add('auto-scrolling');
                startAutoScroll({{ $direction }});
            });

            btn.addEventListener('mouseleave', () => {
                btn.classList.remove('auto-scrolling');
                stopAutoScroll();
            });
        }

        // Stop on manual interaction (shared for viewport)
        if (viewport) {
            ['wheel', 'touchstart', 'mousedown'].forEach(event => {
                viewport.addEventListener(event, stopAutoScroll);
            });
        }

        window.addEventListener('beforeunload', stopAutoScroll);
    });
</script>
@endpush