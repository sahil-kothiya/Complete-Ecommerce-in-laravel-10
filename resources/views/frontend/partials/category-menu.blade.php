@php
function renderCategory($category, $parentSlug = '', $depth = 0) {
    if ($depth >= 10) {
        return '';
    }
    $path = $parentSlug ? $parentSlug . '/' . $category->slug : $category->slug;
    $encryptedPath = \App\Helpers\UrlEncryptor::encodePath($path);
    $hasChildren = $category->children && $category->children->count() > 0;

    $output = '<li>';
    $output .= '<a href="' . route('product-cat', $encryptedPath) . '">';
    $output .= e($category->title);
    if ($hasChildren) {
        $output .= '<span class="menu-arrow">›</span>';
    }
    $output .= '</a>';

    if ($hasChildren) {
        $output .= '<ul class="dropdown sub-dropdown border-0 shadow">';
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

<li>
    <a class="nav-link dropdown-toggle" href="#" tabindex="11">Category</a>
    <ul class="dropdown border-0 shadow">
        @foreach($categories as $category)
            {!! renderCategory($category, '', 0) !!}
        @endforeach
    </ul>
</li>
