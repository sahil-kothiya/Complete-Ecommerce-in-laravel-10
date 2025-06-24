<li>
    <a class="nav-link dropdown-toggle" href="#">Category</a>
    <ul class="dropdown border-0 shadow">
        @foreach(Helper::getAllCategory() as $cat_info)
        @if($cat_info->children->count())
        <li>
            <a href="{{ route('product-cat', $cat_info->slug) }}">{{ $cat_info->title }}</a>
            <ul class="dropdown sub-dropdown border-0 shadow">
                @foreach($cat_info->children as $sub_menu)
                <li>
                    <a href="{{ route('product-sub-cat', [$cat_info->slug, $sub_menu->slug]) }}">{{ $sub_menu->title }}</a>
                </li>
                @endforeach
            </ul>
        </li>
        @else
        <li><a href="{{ route('product-cat', $cat_info->slug) }}">{{ $cat_info->title }}</a></li>
        @endif
        @endforeach
    </ul>
</li>