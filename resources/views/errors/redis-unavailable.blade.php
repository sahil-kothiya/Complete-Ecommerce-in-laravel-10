@extends('frontend.layouts.master')

@section('title', 'Service Temporarily Unavailable')

@section('main-content')
    <section class="error-page section" style="padding: 100px 0;">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 offset-lg-2 col-12">
                    <div class="error-inner text-center">
                        <h1><i class="fa fa-exclamation-triangle text-warning"></i></h1>
                        <h2>Service Temporarily Degraded</h2>
                        <p>{{ $message ?? 'Our caching system is experiencing high load. Your data is safe, but some features may be temporarily slower.' }}
                        </p>
                        <div class="button">
                            <a href="{{ route('home') }}" class="btn"><i class="fa fa-home"></i> Go Home</a>
                            <a href="javascript:window.location.reload()" class="btn btn-primary"><i
                                    class="fa fa-refresh"></i> Retry</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
