<li>
    <a class="nav-link dropdown-toggle" href="#">Category</a>
    <ul class="dropdown border-0 shadow">
        @php
        $categories = Helper::getAllCategory();
        @endphp

        @foreach($categories as $cat_info)
        <li>
            <a href="{{ route('product-cat', $cat_info->slug) }}">
                {{ $cat_info->title }}
                @if($cat_info->children_count > 0 || $cat_info->children->isNotEmpty())
                <span class="menu-arrow">›</span>
                @endif
            </a>

            @if($cat_info->children->isNotEmpty())
            <ul class="dropdown sub-dropdown border-0 shadow">
                @foreach($cat_info->children as $sub_menu)
                <li>
                    <a href="{{ route('product-sub-cat', [$cat_info->slug, $sub_menu->slug]) }}">
                        {{ $sub_menu->title }}
                        @if($sub_menu->children->isNotEmpty())
                        <span class="menu-arrow">›</span>
                        @endif
                    </a>

                    @if($sub_menu->children->isNotEmpty())
                    <ul class="dropdown sub-dropdown border-0 shadow">
                        @foreach($sub_menu->children as $sub_sub_menu)
                        <li>
                            <a href="{{ route('product-sub-cat', [$sub_menu->slug, $sub_sub_menu->slug]) }}">
                                {{ $sub_sub_menu->title }}
                            </a>
                        </li>
                        @endforeach
                    </ul>
                    @endif
                </li>
                @endforeach
            </ul>
            @endif
        </li>
        @endforeach
    </ul>
</li>