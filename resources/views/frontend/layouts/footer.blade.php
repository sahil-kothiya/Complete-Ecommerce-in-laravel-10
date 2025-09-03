<!-- Footer -->
<footer class="footer">
    <div class="footer-top section">
        <div class="container">
            <div class="row">
                <div class="col-lg-5 col-md-6 col-12">
                    <div class="single-footer about">
                        <a href="{{ route('home') }}" class="logo" tabindex="84">
                            <img src="{{ $settings->logo ?? asset('images/default-logo.png') }}"
                                 alt="Logo"
                                 loading="eager"
                                 width="120"
                                 height="40"
                                 style="max-width: 100%; height: auto;">
                        </a>
                        <p class="text">{{ $settings->short_des ?? 'Your one-stop shop for quality products.' }}</p>
                        <p class="call">Got Question? Call us 24/7 
                            <a href="tel:{{ $settings->phone ?? '' }}" tabindex="85">{{ $settings->phone ?? '' }}</a>
                        </p>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 col-12">
                    <div class="single-footer links">
                        <h4>Information</h4>
                        <ul>
                            <li><a href="{{ route('about-us') }}" tabindex="86">About Us</a></li>
                            <!-- <li><a href="#" tabindex="87">FAQ</a></li> -->
                            <!-- <li><a href="#" tabindex="88">Terms & Conditions</a></li> -->
                            <li><a href="{{ route('contact') }}" tabindex="87">Contact Us</a></li>
                            <!-- <li><a href="#" tabindex="90">Help</a></li> -->
                        </ul>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 col-12">
                    <div class="single-footer links">
                        <!-- Customer Service section is commented out, so no tabindex assigned -->
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-12">
                    <div class="single-footer social">
                        <h4>Get In Touch</h4>
                        <ul class="contact">
                            <li>{{ $settings->address ?? '' }}</li>
                            <li>{{ $settings->email ?? '' }}</li>
                            <li>{{ $settings->phone ?? '' }}</li>
                        </ul>
                        <div class="sharethis-inline-follow-buttons" tabindex="88"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="copyright">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 col-12">
                    <p>Copyright © {{ date('Y') }} All Rights Reserved.</p>
                </div>
                <div class="col-lg-6 col-12">
                    <!-- <img src="{{ asset('frontend/images/payments.webp') }}" alt="Payment Methods" loading="lazy"> -->
                </div>
            </div>
        </div>
    </div>
</footer>
