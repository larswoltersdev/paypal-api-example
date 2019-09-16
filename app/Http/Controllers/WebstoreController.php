<?php

namespace App\Http\Controllers;

use App\Product;
use Gloudemans\Shoppingcart\Facades\Cart;

class WebstoreController extends Controller
{
    public function index()
    {
        # We pass all the products from the database into
        # the $products variable, which is an array
        $products = Product::all();

        # We use the home view for the tutorial, but you could
        # use other views too. Home will be our webstore view
        return view('home')->with('products', $products);
    }

    # Our function for adding a certain product to the cart
    public function addToCart(Product $product)
    {
        Cart::add($product->id, $product->name, 1, $product->price);
        return redirect('/home');
    }

    # Our function for removing a certain product from the cart
    public function removeProductFromCart($productId)
    {
        Cart::remove($productId);
        return redirect('/home');
    }

    # Our function for clearing all items from our cart
    public function destroyCart()
    {
        Cart::destroy();
        return redirect('/home');
    }

}
