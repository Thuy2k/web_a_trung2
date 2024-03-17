<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Payment;
use App\Models\ProductDetail;
use App\Models\PostCate;
use App\Models\InforContact;
use Auth;
use Illuminate\Support\Str;
use Session;

class OrderController extends Controller
{
    //
    public function index(Request $request)
    {
        // dd(Session::all());
        if(!Auth::check())
        {
            return redirect('/login');
        }
        $categories = Category::whereNull('deleted_at')->get();
        $category_post = PostCate::where('status',1)->get();
        $infor_contact = InforContact::all();
        $order_unpaid = Order::where('email',Auth::user()->email)
                                ->whereHas('payment',function($query){
                                    $query->where('status',0);
                                })
                                ->with('order_detail')
                                ->with('payment')
                                ->orderByDesc('created_at')
                                ->get();
        // dd($order_unpaid);
        $order_paid = Order::where('email',Auth::user()->email)
                                ->whereHas('payment',function($query){
                                    $query->where('status',1);
                                })
                                ->with(['payment','order_detail'])
                                ->orderByDesc('created_at')
                                ->get();
        // dd(count($order_paid));
        $data = [
            'categories' => $categories,
            'order_unpaid'=>$order_unpaid,
            'order_paid'=>$order_paid,
            'category_post' => $category_post,
            'infor_contact' => $infor_contact,
            'title' => 'Đơn hàng',
            'breadcrumbs' => [
                [
                    'name' => 'Trang chủ',
                    'url'  => '/',
                ],
                [
                    'name' => 'Đơn hàng',
                    
                ]
            ]
        ];
        return view('user.order.index',$data);
    }

    public function orderDetail(Request $request,$id)
    {
        if(empty($id))
        {
            abort(404);
        }
        $category_post = PostCate::where('status',1)->get();
        
        $order = Order::where('id',$id)
                        ->whereNull('deleted_at')
                        ->with(['payment' => function($query) {
                            $query->with('ship');
                        }])
                        ->with(['order_detail' => function($query) {
                            $query->with(['product_detail','discount']);
                        }])
                        ->first();

                        // dd($order->payment);
        if(empty($order))
        {
            abort(404);
        }
        $infor_contact = InforContact::all();
        $categories = Category::whereNull('deleted_at')->get();
        $data = [
            'categories' => $categories,
            'order' => $order,
            'infor_contact' => $infor_contact,
            'category_post' => $category_post,
            'breadcrumbs' => [
                [
                    'name' => 'Trang chủ',
                    'url'  => '/',
                ],
                [
                    'name' => 'Đơn hàng',
                    'url'  => '/order',
                ],  
                [
                    'name'=> $id,
                ]
            ]
        ];
        return view('user.checkout.success',$data);
    }

    public function destroyOrder(Request $request)
    {
        $id = $request->id;
        if(empty($id))
        {
            abort(404);
        }
        
        $order = Order::where('id',$id)
            ->whereNull('deleted_at')
            ->with(['payment' => function($query) {
                $query->with('ship');
            }])
            ->with(['order_detail' => function($query) {
                $query->with(['product_detail','discount']);
            }])
            ->first();

        if(empty($order))
        {
            abort(404);
        }
    
        if($order->payment->method == 'online')
        {
            $payment = Payment::where('order_id',$order->id)->first();
            $payment->delete();

            $order_detail = OrderDetail::where('order_id',$order->id)->get();
            if(count($order_detail) > 0)
            {
                foreach($order_detail as $item)
                {
                    $product_detail =  ProductDetail::where('id',$item->product_detail_id)->first();

                    $product_detail->quantity = $product_detail->quantity + $item->quantity;
                    $product_detail->save();

                    $item->delete();
                }
            }

            $order->delete();

            return redirect('/order')->with('success','Huỷ đơn hàng thành công');
        }
        return redirect('/order')->with('error','Lỗi khi huỷ đơn hàng');
    }

    public function payBack(Request $request)
    {
        $id = $request->id;
        if(empty($id))
        {
            abort(404);
        }

        $order = Order::find($id);
        
        if(empty($order))
        {
            abort(404);
        }
        Session::put('order',$order);

        $payment = Payment::where('order_id',$id)->first();

        if(empty($payment))
        {
            abort(404);
        }

        // dd($payment);

        if($payment->payment_gateway == "vnpay")
        {
            if(Session::has('order') || empty(Session::has('order')))
            {
                Session::forget('order');
            }

            Session::put('order',$order);
            $vnp_TmnCode = "M0BKWT0Y"; 
            $vnp_HashSecret = "JNDBAWSROHDXUQVKHHQZOXQZBHYXNXTI"; 
            $vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
            $vnp_Returnurl = "http://127.0.0.1:8000/return-vnpay";
            $vnp_TxnRef = date("Y-m-d H:i:s"); 
            $vnp_OrderInfo = "Thanh toán hóa đơn phí dich vụ";
            $vnp_OrderType = 'billpayment';
            $vnp_Amount = $payment->amount * 100  ;
            $vnp_Locale = 'vn';
            $vnp_IpAddr = request()->ip();
    
            $inputData = array(
                "vnp_Version" => "2.0.0",
                "vnp_TmnCode" => $vnp_TmnCode,
                "vnp_Amount" => $vnp_Amount,
                "vnp_Command" => "pay",
                "vnp_CreateDate" => date('YmdHis'),
                "vnp_CurrCode" => "VND",
                "vnp_IpAddr" => $vnp_IpAddr,
                "vnp_Locale" => $vnp_Locale,
                "vnp_OrderInfo" => $vnp_OrderInfo,
                "vnp_OrderType" => $vnp_OrderType,
                "vnp_ReturnUrl" => $vnp_Returnurl,
                "vnp_TxnRef" => $vnp_TxnRef,
            );
    
            $vnp_BankCode = $payment->code_bank;
            if (isset($vnp_BankCode) && $vnp_BankCode != "") {
                $inputData['vnp_BankCode'] = $vnp_BankCode;
            }
            ksort($inputData);
            $query = "";
            $i = 0;
            $hashdata = "";
            foreach ($inputData as $key => $value) {
                if ($i == 1) {
                    $hashdata .= '&' . $key . "=" . $value;
                } else {
                    $hashdata .= $key . "=" . $value;
                    $i = 1;
                }
                $query .= urlencode($key) . "=" . urlencode($value) . '&';
            }
    
            $vnp_Url = $vnp_Url . "?" . $query;
            if (isset($vnp_HashSecret)) {
               // $vnpSecureHash = md5($vnp_HashSecret . $hashdata);
                $vnpSecureHash = hash('sha256', $vnp_HashSecret . $hashdata);
                $vnp_Url .= 'vnp_SecureHashType=SHA256&vnp_SecureHash=' . $vnpSecureHash;
            }
            // Session::put('order',$order);
            return redirect($vnp_Url);
        }
        else if($payment->payment_gateway == "momo")
        {
            $response = \MoMoAIO::purchase([
                'amount' => $payment->amount,
                'returnUrl' => 'http://localhost:8000/return-momo/',
                'notifyUrl' => 'http://localhost:8000/checkout/',
                'orderId' => Str::orderedUuid(),
                'orderInfo'=>'Đơn hàng '.$payment->order_id,
                'requestId' => Str::orderedUuid(),
            ])->send();
                    // dd($response);
            if ($response->isRedirect()) {
                $redirectUrl = $response->getRedirectUrl();
                if(Session::has('order') || empty(Session::has('order')))
                {
                    Session::forget('order');
                }

                Session::put('order',$order);
                return redirect($redirectUrl);
            }
            return redirect()->back()->with('error','Lỗi khi thanh toán');
        }
    }
}
