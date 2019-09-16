<?php

namespace App\Http\Controllers;

use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Support\Facades\Input;
use PayPal\Api\Amount;
use PayPal\Api\Item;
use PayPal\Api\WebProfile;
use PayPal\Api\ItemList;
use PayPal\Api\InputFields;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;
use Redirect;
use Session;
use URL;
use App\Invoice;
use App\Order;
use App\Suborder;
use Str;
Use Request;
use Auth;
use Config;

class PaypalController extends Controller
{
    private $apiContext;

    public function __construct()
    {
        # Main configuration in constructor
        $paypalConfig = Config::get('paypal');

        $this->apiContext = new ApiContext(new OAuthTokenCredential(
                $paypalConfig['client_id'],
                $paypalConfig['secret'])
        );

        $this->apiContext->setConfig($paypalConfig['settings']);
    }

    public function index()
    {
        return view('store.index');
    }

    public function payWithpaypal(Request $request)
    {
        # We initialize the payer object and set the payment method to PayPal
        $payer = new Payer();
        $payer->setPaymentMethod('paypal');

        # We insert a new order in the order table with the 'initialised' status
        $order = new Order();
        $order->user_id = Auth::user()->id;
        $order->invoice_id = null;
        $order->status = 'initialised';
        $order->save();

        # We need to update the order if the payment is complete, so we save it to the session
        Session::put('orderId', $order->getKey());

        # We get all the items from the cart and parse the array into the Item object
        $items = [];

        foreach (Cart::content() as $item) {
            $items[] = (new Item())
                ->setName($item->name)
                ->setCurrency('USD')
                ->setQuantity($item->qty)
                ->setPrice($item->price);
        }

        # We create a new item list and assign the items to it
        $itemList = new ItemList();
        $itemList->setItems($items);

        # Disable all irrelevant PayPal aspects in payment
        $inputFields = new InputFields();
        $inputFields->setAllowNote(true)
            ->setNoShipping(1)
            ->setAddressOverride(0);

        $webProfile = new WebProfile();
        $webProfile->setName(uniqid())
            ->setInputFields($inputFields)
            ->setTemporary(true);

        $createProfile = $webProfile->create($this->apiContext);

        # We get the total price of the cart
        $amount = new Amount();
        $amount->setCurrency('USD')
            ->setTotal(Cart::subtotal());

        $transaction = new Transaction();
        $transaction->setAmount($amount);
        $transaction->setItemList($itemList)
            ->setDescription('Your transaction description');

        $redirectURLs = new RedirectUrls();
        $redirectURLs->setReturnUrl(URL::to('status'))
            ->setCancelUrl(URL::to('status'));

        $payment = new Payment();
        $payment->setIntent('Sale')
            ->setPayer($payer)
            ->setRedirectUrls($redirectURLs)
            ->setTransactions(array($transaction));
        $payment->setExperienceProfileId($createProfile->getId());
        $payment->create($this->apiContext);

        foreach ($payment->getLinks() as $link) {
            if ($link->getRel() == 'approval_url') {
                $redirectURL = $link->getHref();
                break;
            }
        }

        # We store the payment ID into the session
        Session::put('paypalPaymentId', $payment->getId());

        if (isset($redirectURL)) {
            return Redirect::away($redirectURL);
        }

        Session::put('error', 'There was a problem processing your payment. Please contact support.');

        return Redirect::to('/home');
    }

    public function getPaymentStatus()
    {
        $paymentId = Session::get('paypalPaymentId');
        $orderId = Session::get('orderId');

        # We now erase the payment ID from the session to avoid fraud
        Session::forget('paypalPaymentId');

        # If the payer ID or token isn't set, there was a corrupt response and instantly abort
        if (empty(Request::get('PayerID')) || empty(Request::get('token'))) {
            Session::put('error', 'There was a problem processing your payment. Please contact support.');
            return Redirect::to('/home');
        }

        $payment = Payment::get($paymentId, $this->apiContext);
        $execution = new PaymentExecution();
        $execution->setPayerId(Request::get('PayerID'));

        $result = $payment->execute($execution, $this->apiContext);

        # Payment is processing but may still fail due e.g to insufficient funds
        $order = Order::find($orderId);
        $order->status = 'processing';

        if ($result->getState() == 'approved') {

            $invoice = new Invoice();
            $invoice->price = $result->transactions[0]->getAmount()->getTotal();
            $invoice->currency = $result->transactions[0]->getAmount()->getCurrency();
            $invoice->customer_email = $result->getPayer()->getPayerInfo()->getEmail();
            $invoice->customer_id = $result->getPayer()->getPayerInfo()->getPayerId();
            $invoice->country_code = $result->getPayer()->getPayerInfo()->getCountryCode();
            $invoice->payment_id = $result->getId();

            # We update the invoice status
            $invoice->payment_status = 'approved';
            $invoice->save();

            # We also update the order status
            $order->invoice_id = $invoice->getKey();
            $order->status = 'pending';
            $order->save();

            # We insert the suborder (products) into the table
            foreach (Cart::content() as $item) {
                $suborder = new Suborder();
                $suborder->order_id = $orderId;
                $suborder->product_id = $item->id;
                $suborder->price = $item->price;
                $suborder->quantity = $item->qty;
                $suborder->save();
            }

            Cart::destroy();

            Session::put('success', 'Your payment was successful. Thank you.');

            return Redirect::to('/home');
        }

        Session::put('error', 'There was a problem processing your payment. Please contact support.');

        return Redirect::to('/home');
    }
}
