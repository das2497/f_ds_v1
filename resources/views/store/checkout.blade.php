@php
    $usStates = ['AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY','DC'];
@endphp
<x-checkout-layout title="Checkout" step="shipping" heading="Checkout: shipping details and payment">

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('checkout.store') }}" method="POST">
        @csrf
        <div class="row g-4">
            <div class="col-lg-7">

                <div class="card-surface p-4 mb-4">
                    <h5 class="mb-3"><i class="bi bi-person me-2 text-accent"></i>Contact Information</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small">Email Address</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', auth()->user()?->email) }}" placeholder="you@example.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" value="{{ old('phone') }}" placeholder="(555) 123-4567">
                        </div>
                    </div>
                </div>

                <div class="card-surface p-4 mb-4">
                    <h5 class="mb-3"><i class="bi bi-geo-alt me-2 text-accent"></i>Shipping Address</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small">Full Name</label>
                            <input type="text" name="shipping_name" class="form-control" value="{{ old('shipping_name', auth()->user()?->name) }}" placeholder="John Carter" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Country</label>
                            <input type="text" class="form-control" value="United States" disabled>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Address Line 1</label>
                            <input type="text" name="shipping_address_line1" class="form-control" value="{{ old('shipping_address_line1') }}" placeholder="Street address" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Address Line 2 <span class="text-muted-slate">(optional)</span></label>
                            <input type="text" name="shipping_address_line2" class="form-control" value="{{ old('shipping_address_line2') }}" placeholder="Apartment, suite, etc.">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small">City</label>
                            <input type="text" name="shipping_city" class="form-control" value="{{ old('shipping_city') }}" placeholder="City" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">State</label>
                            <select name="shipping_state" class="form-select" required>
                                <option value="" disabled selected>Select state</option>
                                @foreach ($usStates as $state)
                                    <option value="{{ $state }}" @selected(old('shipping_state') === $state)>{{ $state }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">ZIP Code</label>
                            <input type="text" name="shipping_zip" class="form-control" value="{{ old('shipping_zip') }}" placeholder="90001" required>
                        </div>
                    </div>
                </div>

                <div class="card-surface p-4">
                    <h5 class="mb-3"><i class="bi bi-truck me-2 text-accent"></i>Shipping Method</h5>
                    <div class="form-check d-flex justify-content-between align-items-center border rounded-3 p-3 mb-2" style="border-color:var(--color-border)!important">
                        <div>
                            <input class="form-check-input me-2" type="radio" name="shipping_method" value="standard" id="shipStandard" checked>
                            <label class="form-check-label" for="shipStandard">
                                <strong>Standard Shipping</strong>
                                <div class="small text-muted-slate">5–7 business days</div>
                            </label>
                        </div>
                        <div class="fw-semibold">$8.50</div>
                    </div>
                    <div class="form-check d-flex justify-content-between align-items-center border rounded-3 p-3" style="border-color:var(--color-border)!important">
                        <div>
                            <input class="form-check-input me-2" type="radio" name="shipping_method" value="express" id="shipExpress">
                            <label class="form-check-label" for="shipExpress">
                                <strong>Express Shipping</strong>
                                <div class="small text-muted-slate">2–3 business days</div>
                            </label>
                        </div>
                        <div class="fw-semibold">$19.99</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="cart-summary">
                    <h5 class="mb-3">Order Summary</h5>

                    @foreach ($cart->items as $item)
                        <div class="d-flex gap-2 mb-2">
                            <div class="cart-item__image" style="width:56px;height:56px">
                                @if (! empty($item->variant->product->images[0]))
                                    <img src="{{ $item->variant->product->images[0] }}" style="max-width:100%;max-height:100%;object-fit:contain">
                                @else
                                    <div class="ph-icon-wrap" role="img" aria-label="{{ $item->variant->product->title }}"><i class="bi bi-box-seam"></i></div>
                                @endif
                            </div>
                            <div class="flex-grow-1 d-flex justify-content-between align-items-center">
                                <div class="small">{{ $item->variant->product->title }} <span class="text-muted-slate">&times;{{ $item->quantity }}</span></div>
                                <div class="small fw-semibold">${{ number_format($item->lineTotal(), 2) }}</div>
                            </div>
                        </div>
                    @endforeach

                    <hr>

                    <div class="row-line"><span class="text-muted-slate">Subtotal</span><span>${{ number_format($subtotal, 2) }}</span></div>
                    <div class="small text-muted-slate mb-2">Shipping &amp; tax are calculated from your address above once you continue.</div>

                    <button type="submit" class="btn btn-accent w-100 mt-2 btn-lg">
                        Continue to Payment <i class="bi bi-arrow-right"></i>
                    </button>

                    <div class="small text-muted-slate mt-3">
                        By placing your order, you agree to our <a href="{{ route('policies.terms') }}" class="text-accent">Terms of Service</a> and acknowledge the <a href="{{ route('policies.shipping-refund') }}" class="text-accent">2-hour cancellation policy</a>.
                    </div>
                </div>
            </div>
        </div>
    </form>

</x-checkout-layout>
