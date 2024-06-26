<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseFormRequest;
use App\Models\Purchase;
use App\Models\Customer;
use App\Models\Seat;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Storage;
use App\Mail\PurchaseReceiptMail;
use App\Http\Controllers\PDFController;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\PDF;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\Screening;
use App\Services\Payment;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PurchaseController extends Controller
{
    public function index()
    {
        $purchases = Purchase::with('customer.user')->orderBy('date','desc')->paginate(15)->withQueryString();;

        return view('purchases.index', compact('purchases'));
    }

    public function myPurchases(Request $request): View
    {
        if (!empty($request->user()?->customer)) {
            $idPurch = $request->user()?->customer?->purchases?->pluck('id')?->toArray();
        }else {
            $purchases =new LengthAwarePaginator([],0,10);
            return view('purchases.my')->with('purchases', $purchases);
        }
        $purchases = Purchase::whereIntegerInRaw('id', $idPurch)
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('purchases.my', compact('purchases'));
    }


    protected function generatePdfReceipt($purchase, $tickets)
    {
        $pdf = PDF::loadView('pdf.receipt', compact('purchase', 'tickets'));
        return $pdf;
    }

    protected function generatePdfTicket($ticket)
    {
        $pdf = PDF::loadView('pdf.ticket', compact('ticket'));
        return $pdf;
    }



    public function store(Request $request)
    {
        //Had to install imagick because of QrCode and dependencies
        $auth = Auth::check();
        if ($auth && empty(session()->get('cart'))){
            $cart = [];
        }else{
            $cart = ($auth) ? session()->get('cart', collect()) : json_decode(Cookie::get('cart'), true) ?? [];
        }
        if (count($cart) == 0){
            return redirect()->back()->with('alert-type', 'danger')
            ->with('alert-msg',  "Cart empty!");

        }

        $customer = $auth ? Customer::find(Auth::user()->id) : [];


        $extra = "";
        if ($request->payment_type === 'PAYPAL') {
            $extra = 'string|lowercase|email|max:255';
        } elseif ($request->payment_type === 'MBWAY') {
            $extra = 'integer|digits:9';
        } elseif ($request->payment_type === 'VISA') {
            $extra = 'integer|digits:19';
        }else{
            return redirect()->back()->with('alert-type', 'danger')
            ->with('alert-msg',  "Invalid payment type!");
        }

        $request->validate([
            'Total_pay' => 'required|numeric|between:0,99999999.99',
            'payment_type' => ['required', Rule::in(['VISA', 'PAYPAL', 'MBWAY'])],
            'payment_ref' => "required|".$extra,
        ]);
        $allright = false;
        match($request->payment_type){
            'PAYPAL' => $allright= Payment::payWithPaypal($request->payment_ref),
            'MBWAY'=> $allright= Payment::payWithMBway($request->payment_ref),
            'VISA'=> $allright= Payment::payWithVisa(substr($request->payment_ref, 0, 16), substr($request->payment_ref, -3))
        };
        if (!$allright){
            return redirect()->back()
            ->with('alert-type', 'danger')
            ->with('alert-msg',  "Payment Error!");
        }

        if (!$auth){

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users|lowercase',
                'nif' => 'sometimes|integer|digits:9|nullable',
            ]);


            $customer = [
                'id'=>null,
                'name' => $request->name,
                'email' => $request->email,
                'nif' => $request?->nif,
            ];
        }elseif(!empty($customer)){
            $customer->payment_type = $request?->payment_type;
            $customer->payment_ref = $request?->payment_ref;
            $customer->save();
            $customer = $customer->user->toArray();
        }
        else{
            $customer = new Customer;
            $customer->id = Auth::user()->id;
            $customer->payment_type = $request->payment_type;
            $customer->payment_ref = $request->payment_ref;
            $customer->save();
            $customer = $customer->user->toArray();
        }

        $purchases = [
            'customer_name' => $customer['name'],
            'customer_email' => $customer['email'],
            'customer_id' => $customer['id'],
            'date' => now(),
            'nif' => $customer['nif'] ?? null,
            'total_price' => $request->get('Total_pay'),
            'payment_type' => $request->get('payment_type'),
            'payment_ref' => $request->get('payment_ref'),
        ];
        $purchase = Purchase::create($purchases); // Create a new purchase record
        $indivValue = $request->get('Total_pay')/count($cart);
        $tickets = [];
        $fails = [];
        $failes = false;
        foreach ($cart as $cartData) {
            $screening = Screening::find($cartData['screening_id']);
            if (Ticket::where('seat_id', $cartData['seat_id'])->where('screening_id', $cartData['screening_id'])->exists()) {
                $seaat = Seat::find($cartData['seat_id']);
                $fails[] = "Screening: ".$screening?->movie?->title.' '.$screening?->date.' '.$screening?->start_time.', Seat: '.$seaat?->row.' '.$seaat?->seat_number;
                $failes = true;
                continue;
            }

            if ( empty($screening) || Carbon::parse($screening->date)->lessThan(now()->startofDay())){
                $fails[] = "Screening: ".$screening?->movie?->title.' '.$screening?->date.' '.$screening?->start_time;
                $failes = true;
                continue;
            }elseif(Carbon::parse(Screening::find($cartData['screening_id'])->date)->equalTo(now()->startOfDay())){
                if (Carbon::parse($screening->start_time)->addMinutes(5)->lessThan(now())){
                    $fails[] = "Screening: ".$screening?->movie?->title.' '.$screening?->date.' '.$screening?->start_time;
                    $failes = true;
                    continue;
                }
            }
            if ($failes){
                continue; //optimize when found problem
            }
            $ticket = Ticket::create([
                'screening_id' => $cartData['screening_id'],
                'seat_id' => $cartData['seat_id'],
                'price' => $indivValue,
                'purchase_id' => $purchase->id,
                'status' => 'valid',
            ]);
            $lsss = Str::random(40);
            $ticket->qrcode_url = $ticket->id ."".$lsss ;


            $pdff = $this->generatePdfTicket($ticket);
            $pdfPathh = "public/ticket_qrcodes/". $ticket->qrcode_url.".pdf";


            Storage::put($pdfPathh, $pdff->output());
            $tickets[] = $ticket;
        }

        if ($failes){
            return redirect()->route('cart.show')
                ->with('alert-type', 'danger')
                ->with('alert-msg',  "Erro ao comprar os tickets: ".implode(',',$fails)."\nJá não estão disponíveis");
        }

        foreach($tickets as $ticket){
            $ticket->save();
        }
        //The receipt PDF file also includes all the tickets – tickets will not generate their own PDF files.
        $pdf = $this->generatePdfReceipt($purchase, $tickets);
        Mail::to($customer['email'])->send(new PurchaseReceiptMail($purchase, $tickets, $pdf->output()));

        if ($auth) {
            $request->session()->forget('cart');
            //Guardar cenas do pdf receipt no storage
            $pdfPath = 'public/pdf_purchases/' . $purchase->id . '.pdf';

            Storage::put($pdfPath, $pdf->output());
            $purchase->receipt_pdf_filename =  $purchase->id . '.pdf';
            $purchase->save();
        }else{

            Cookie::queue(Cookie::forget('cart'));

        }

        return redirect()->route('cart.show')->with('success', 'Purchase created successfully!');
    }

    public function show(Purchase $purchase)
    {
        $purchase->load('customer', 'tickets.seat');

        return view('purchases.show', compact('purchase'));
    }


}
