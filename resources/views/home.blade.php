@extends('layouts.app')

@section('content')
    <div class="container">
        @if(Session::has('success'))
            <div class="alert alert-success">
                {{ Session::get('success') }}
                @php
                    Session::forget('success');
                @endphp
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-warning" uk-alert>
                {{ Session::get('error') }}
                @php
                    Session::forget('error');
                @endphp
            </div>
        @endif
        <div class="row">
            @foreach($products as $product)
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-header">
                            <!-- The product name (duh..) -->
                            {{ $product->name }}
                        </div>
                        <div class="card-body">
                            <h3>
                                <!-- We format the number to a price with currency behind it -->
                                {{ number_format($product->price, 2) }} USD
                            </h3>
                            <a href="{{ route('add', [ $product->getRouteKey() ]) }}">
                                <!-- The button for adding the product to the cart -->
                                <button class="btn btn-primary">Add to cart</button>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
