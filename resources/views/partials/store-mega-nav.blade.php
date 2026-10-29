<nav class="mega-nav d-none d-lg-block">
  <div class="container">
    <ul class="mega-nav__list">
      <li class="mega-nav__item"><a href="{{ route('products.index') }}" class="mega-nav__link active">All Products</a></li>
      @foreach ($navCategories as $category)
        <li class="mega-nav__item">
          <a href="{{ route('products.index', ['cat' => $category->slug]) }}" class="mega-nav__link">
            {{ $category->name }} <i class="bi bi-chevron-down"></i>
          </a>
          @if ($category->subcategories->isNotEmpty())
            <div class="mega-nav__panel">
              <div class="mega-nav__panel-inner">
                <div class="mega-nav__sublist">
                  <div class="mega-nav__sub-title">Shop {{ $category->name }}</div>
                  @foreach ($category->subcategories as $subcategory)
                    <a href="{{ route('products.index', ['cat' => $category->slug, 'sub' => $subcategory->slug]) }}" class="mega-nav__sub-link">{{ $subcategory->name }}</a>
                  @endforeach
                </div>
              </div>
            </div>
          @endif
        </li>
      @endforeach
      <li class="mega-nav__item"><a href="{{ route('products.index', ['deals' => 1]) }}" class="mega-nav__link" style="color:var(--color-danger)">Deals</a></li>
    </ul>
  </div>
</nav>
