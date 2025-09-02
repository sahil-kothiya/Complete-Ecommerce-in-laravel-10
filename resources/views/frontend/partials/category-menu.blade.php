{{-- Single file: category-menu.blade.php --}}
@php
    $categories = Helper::getAllCategory();
    
    // Recursive function to render categories
    function renderCategory($category, $parentSlug = '', $visited = [], $depth = 0) {
        // Prevent infinite loops
        if (in_array($category->id, $visited) || $depth >= 10) {
            return '';
        }
        
        $visited[] = $category->id;
        $path = $parentSlug ? $parentSlug . '/' . $category->slug : $category->slug;
        $hasChildren = $category->children && $category->children->count() > 0;
        
        $output = '<li>';
        $output .= '<a href="' . route('product-cat', $path) . '">';
        $output .= e($category->title);
        
        if ($hasChildren) {
            $output .= '<span class="menu-arrow">›</span>';
        }
        
        $output .= '</a>';
        
        if ($hasChildren) {
            $output .= '<ul class="dropdown sub-dropdown border-0 shadow">';
            foreach ($category->children as $child) {
                $output .= renderCategory($child, $path, $visited, $depth + 1);
            }
            $output .= '</ul>';
        }
        
        $output .= '</li>';
        return $output;
    }
@endphp

<li>
    <a class="nav-link dropdown-toggle" href="#">Category</a>
    <ul class="dropdown border-0 shadow">
        @foreach($categories as $category)
            {!! renderCategory($category, '', [], 0) !!}
        @endforeach
    </ul>
</li>