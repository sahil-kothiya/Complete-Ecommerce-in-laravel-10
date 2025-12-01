@php
    function renderCategory($category, $parentSlug = '', $depth = 0)
    {
        if ($depth >= 10) {
            return '';
        }
        $path = $parentSlug ? $parentSlug . '/' . $category->slug : $category->slug;
        $encryptedPath = \App\Helpers\UrlEncryptor::encodePath($path);
        $hasChildren = $category->children && $category->children->count() > 0;

        $output = '<li role="none">';
        $output .= '<a href="' . route('product-cat', $encryptedPath) . '" role="menuitem"';
        if ($hasChildren) {
            $output .= ' aria-haspopup="true" aria-expanded="false"';
        }
        $output .= '>';
        $output .= e($category->title);
        if ($hasChildren) {
            $output .= '<span class="menu-arrow" aria-hidden="true">›</span>';
        }
        $output .= '</a>';

        if ($hasChildren) {
            $output .=
                '<ul class="dropdown sub-dropdown border-0 shadow" role="menu" aria-label="' .
                e($category->title) .
                ' subcategories">';
            foreach ($category->children as $child) {
                $output .= renderCategory($child, $path, $depth + 1);
            }
            $output .= '</ul>';
        }
        $output .= '</li>';
        return $output;
    }

    $categories = Helper::getCategoryTree();
@endphp

<li class="dropdown" role="none">
    <a class="nav-link dropdown-toggle" href="#" role="menuitem" aria-haspopup="true"
        aria-expanded="false">Category</a>
    <ul class="dropdown border-0 shadow" role="menu" aria-label="Product categories">
        @foreach ($categories as $category)
            {!! renderCategory($category, '', 0) !!}
        @endforeach
    </ul>
</li>
