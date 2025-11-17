<?php

# Create the AppClient folder in the app/Http/Controllers/ directory
# move to app/Http/Controllers/AppClient/
# v2board.app
namespace App\Http\Controllers\AppClient;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\OrderSave;
use App\Http\Requests\Passport\CommSendEmailVerify;
use App\Http\Requests\Passport\AuthRegister;
use App\Http\Requests\Passport\AuthForget;
use App\Http\Requests\Passport\AuthLogin;

use App\Services\CouponService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\UserService;
use App\Services\ServerService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use App\Services\TelegramService;
use App\Services\PlanService;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Models\InviteCode;
use App\Models\CommissionLog;

use App\Utils\Helper;
use App\Utils\Dict;
use App\Utils\CacheKey;
use ReCaptcha\ReCaptcha;

use Omnipay\Omnipay;
use Stripe\Stripe;
use Stripe\Source;
use Library\BitpayX;
use Library\MGate;
use Library\Epay;

use App\Http\Requests\Admin\UserSendMail;
use App\Jobs\SendEmailJob;

use App\Models\Knowledge;
use App\Models\Notice;
use App\Models\Coupon;

use Illuminate\Http\Exceptions\HttpResponseException;


class AppController extends Controller
{

  private $config = [
      "log" => [
          "disabled" => false,
          "level" => "info",
          "timestamp" => true
      ],
      "dns" => [
          "rules" => [
              [
                  "outbound" => ["any"],
                  "server" => "local"
              ],
              [
                 "geosite" => [
                      "tiktok"
                  ],
                 "domain_keyword" => [
                      "tiktok"
                 ],
                 "domain_suffix" => [
                      "byteoversea.com",
                      "ibytedtos.com"
                 ],
                 "server" => "googledns-tiktok"
              ],
              [
                  "clash_mode" => "direct",
                  "server" => "local"
              ],
              [
                  "geosite" => "cn",
                  "server" => "local"
              ]
          ],
          "servers" => [
              [
                "address" => "https://1.1.1.1/dns-query",
                "detour" => "RULE",
                "tag" => "remote"
            ],
            [
              "address" => "local",
              "detour" => "direct",
              "tag" => "local"
          ],
          [
              "address" => "rcode://success",
              "tag" => "block"
          ],
            [
                "tag" => "googledns-tiktok",
                "address" => "tls://8.8.8.8",
                "strategy" => "prefer_ipv4",
            ]
          ]
      ],
      "inbounds" => [],
      "outbounds" => [],
      "route" => [
          "geoip" => [
              "path" => "geoip.db"
          ],
          "geosite" => [
              "path" => "geosite.db"
          ],
          "rules" => []
      ],
      "experimental" => [
          "clash_api" => [
              "external_controller" => "127.0.0.1:9790",
              "secret" => ""
          ]
      ]
   ];

   private $newappsconfig = [
        "dns" => [
            "servers" => [
                [
                    "tag" => "default",
                    "address" => "tls://8.8.8.8"
                ],
                [
                    "tag" => "local",
                    "address" => "https://223.5.5.5/dns-query",
                    "detour"  => "direct"
                ],
                [
                    "tag" => "remote",
                    "address" => "fakeip"
                ]
            ],
            "rules" => [
                [
                    'server'   => 'local',
                    'outbound' => ['any'],
                ],
                [
                    'server'     => 'local',
                    'clash_mode' => 'direct',
                ],
                [
                    'query_type' => ['A', 'AAAA'],
                    'domaim'     => ['.*openai.*'],
                    'invert'     => true,
                    'action'     => 'reject',
                    'method'     => 'default',
                ],
                [
                    'query_type' => ['A', 'AAAA'],
                    'server'     => 'remote',
                ],
                [
                    'rule_set' => 'geosite-geolocation-cn',
                    'server'   => 'local',
                ],
                [
                    'type'  => 'logical',
                    'mode'  => 'and',
                    'rules' => [
                        [
                            'rule_set' => 'geosite-geolocation-!cn',
                            'invert'   => true,
                        ],
                        [
                            'rule_set' => 'geoip-cn',
                        ],
                    ],
                    'server' => 'local'
                ]
            ],
            "fakeip" => [
                "enabled" => true,
                "inet4_range" => "198.18.0.0/15",
                "inet6_range" => "fc00::/18"
            ],
            "strategy" => "prefer_ipv4"
        ],
        "inbounds" => [],
        "outbounds" => [],
        "route" => [
            "rule_set" => [
                [
                    'type'=> 'remote',
                    'tag' => 'geoip-cn',
                    'format' => 'binary',
                    'url' => 'https://testingcf.jsdelivr.net/gh/MetaCubeX/meta-rules-dat@sing/geo/geoip/cn.srs',
                    'download_detour' => 'direct',
                ],
                [
                    'type' => 'remote',
                    'tag' => 'geosite-geolocation-cn',
                    'format' => 'binary',
                    'url' => 'https://testingcf.jsdelivr.net/gh/SagerNet/sing-geosite@rule-set/geosite-geolocation-cn.srs',
                    'download_detour' => 'direct',
                ],
                [
                    'type' => 'remote',
                    'tag' => 'geosite-geolocation-!cn',
                    'format' => 'binary',
                    'url' => 'https://testingcf.jsdelivr.net/gh/SagerNet/sing-geosite@rule-set/geosite-geolocation-!cn.srs',
                    'download_detour' => 'direct',
                ],
                [
                    'url' => 'https://testingcf.jsdelivr.net/gh/MetaCubeX/meta-rules-dat@sing/geo/geosite/cn.srs',
                    'download_detour' => 'direct',
                    'type' => 'remote',
                    'tag' => 'geosite-cn',
                    'format' => 'binary',
                ]
            ],
            "rules" => []
        ],
        "experimental" => [
            "cache_file" => [
                "enabled"  => true,
                "cache_id"  => "default",
                "store_fakeip" => true
            ],
            "clash_api" => [
                "external_controller" => "127.0.0.1:9790"
            ]
        ]
    ];

   public function appconfig()
   {
     return response([
         'data' => [
             'isEmailVerify' => (int)config('v2board.email_verify', 0) ? 1 : 0,
             'isInviteForce' => (int)config('v2board.invite_force', 0) ? 1 : 0,
             'emailWhitelistSuffix' => (int)config('v2board.email_whitelist_enable', 0)
                 ? $this->getEmailSuffix()
                 : 0,
             'isRecaptcha' => (int)config('v2board.recaptcha_enable', 0) ? 1 : 0,
             'recaptchaSiteKey' => config('v2board.recaptcha_site_key'),
             'appDescription' => config('v2board.app_description'),
             'icon' => config('v2board.logo'),
             'appName' => config('v2board.app_name'),
             'appUrl' => config('v2board.app_url'),
             'tggroup' => config('v2board.telegram_discuss_link'), //tg群組 tg group
             'website' => config('v2board.app_url'), //官方網站 Official website
             'tos' => '', //服務條款 Terms of Service
             'privacy' => '', //隱私策略 privacy policy
             'crispID' => '',
             'chatType' => '', //New add 2.1.1
             'chatLink' => '',//new add 2.1.1
             'chatID' => '',
             'isSupport' => false,
             'inviteUrl' => config('v2board.app_url')."/#/register?code=",
             'panelType' => "v2board",
             'currency_symbol' => config('v2board.currency_symbol', '¥')
         ]
     ]);
   }

   public function getTempToken(Request $request)
   {
       $user = User::where('token', $request->input('token'))->first();
       if (!$user) {
         return response()->json([
              'status' => 0,
              'msg' => 'TOKEN不能为空'
          ]);
       }

       $code = Helper::guid();
       $key = CacheKey::get('TEMP_TOKEN', $code);
       Cache::put($key, $user->id, 60);
       return response([
            'status' => 1,
            'code' => $code,
            'data' => $code
        ]);
   }

    private function isEmailVerify()
    {
        return response([
            'data' => (int)config('v2board.email_verify', 0) ? 1 : 0
        ]);
    }

    public function appsendEmailVerify(CommSendEmailVerify $request)
    {

        $email = $request->input('email');

        if (Cache::get(CacheKey::get('LAST_SEND_EMAIL_VERIFY_TIMESTAMP', $email))) {
            return response()->json([
                'status' => 0,
                'msg' => '验证码已发送，请过一会再请求'
            ]);
        }
        $code = rand(100000, 999999);
        $subject = config('v2board.app_name', 'V2Board') . '邮箱验证码';

        SendEmailJob::dispatch([
            'email' => $email,
            'subject' => $subject,
            'template_name' => 'verify',
            'template_value' => [
                'name' => config('v2board.app_name', 'V2Board'),
                'code' => $code,
                'url' => config('v2board.app_url')
            ]
        ]);

        Cache::put(CacheKey::get('EMAIL_VERIFY_CODE', $email), $code, 300);
        Cache::put(CacheKey::get('LAST_SEND_EMAIL_VERIFY_TIMESTAMP', $email), time(), 60);
        return response()->json([
            'status' => 1,
            'data' => true,
            'msg' => "验证码发送成功"
        ]);
    }


     //2.1.3+
     public function invitedetails(Request $request)
    {

        $token = $request->input('token');
        $user = User::where('token', $token)->first();

        if (!$user) {
            return response()->json([
                'status' => 0,
                'msg' => 'User information error'
            ]);
        }

        $current = $request->input('current') ? $request->input('current') : 1;
        $pageSize = $request->input('page_size') >= 10 ? $request->input('page_size') : 10;
        $builder = CommissionLog::where('invite_user_id', $user->id)
            ->where('get_amount', '>', 0)
            ->select([
                'id',
                'trade_no',
                'order_amount',
                'get_amount',
                'created_at'
            ])
            ->orderBy('created_at', 'DESC');
        $total = $builder->count();
        $details = $builder->forPage($current, $pageSize)
            ->get();
        return response([
            'data' => $details,
            'total' => $total
        ]);
    }


    public function inviteCodeNew(Request $request)
    {
        $token = $request->input('token');
        $user = User::where('token', $token)->first();

        if (!$user) {
            return response()->json([
                'status' => 0,
                'msg' => 'User information error'
            ]);
        }

        if (InviteCode::where('user_id', $user->id)->where('status', 0)->count() >= config('v2board.invite_gen_limit', 5)) {
            return response([
                'status' => 0,
                'msg' => '已达到创建次数上限'
            ]);
        }

        $inviteCode = new InviteCode();
        $inviteCode->user_id = $user->id;
        $inviteCode->code = Helper::randomChar(8);
        return response([
            'status' => 1,
            'data' => $inviteCode->save()
        ]);
    }

    public function appinvite(Request $request) {

        $token = $request->input('token');
        $user = User::where('token', $token)->first();

        if (!$user) {
            return response()->json([
                'status' => 0,
                'msg' => 'User information error'
            ]);
        }

        $diycode = $request->input('code');
        $codes = InviteCode::orderBy('created_at', 'DESC')->where('user_id', $user->id)
            ->where('status', 0)
            ->get();

        $commission_rate = config('v2board.invite_commission', 10);

        if ($user->commission_rate) {
            $commission_rate = $user->commission_rate;
        }
        $uncheck_commission_balance = (int)Order::where('status', 3)
            ->where('commission_status', 0)
            ->where('invite_user_id', $user->id)
            ->sum('commission_balance');

        if (config('v2board.commission_distribution_enable', 0)) {
            $uncheck_commission_balance = $uncheck_commission_balance * (config('v2board.commission_distribution_l1') / 100);
        }

        if(count($codes) == 0){

            $inviteCodeNew = Helper::randomChar(8);
            $inviteCode = new InviteCode();
            $inviteCode->user_id = $user->id;
            $inviteCode->code = $inviteCodeNew;
            $inviteCode->save();

            return response()->json([
                'status' => 1,
                'code' => $inviteCodeNew,
                'codes' => [],
                'invite_users' => (int)User::where('invite_user_id', $user->id)->count(),
                'invite_get_amount' => (int)CommissionLog::where('invite_user_id', $user->id)->sum('get_amount'),
                'invite_uncheck_commission_balance' => $uncheck_commission_balance,
                'invite_commission_balance' => (int)$user->commission_balance,
                'invite_commission_rate' => (int)$commission_rate
            ]);

        } else {

            return response()->json([
                'status' => 1,
                'code' => $codes[0]->code,
                'codes' => $codes,
                'invite_users' => (int)User::where('invite_user_id', $user->id)->count(),
                'invite_get_amount' => (int)CommissionLog::where('invite_user_id', $user->id)->sum('get_amount'),
                'invite_uncheck_commission_balance' => $uncheck_commission_balance,
                'invite_commission_balance' => (int)$user->commission_balance,
                'invite_commission_rate' => (int)$commission_rate
            ]);
        }


    }

    public function orderfetch(Request $request) {

        $token = $request->input('token');
        $user = User::where('token', $token)->first();

        if (!$user) {
          return response()->json([
              'status' => 0,
              'msg' => '用户信息错误'
          ]);
        }

        $model = Order::where('user_id', $user->id)
            ->orderBy('created_at', 'DESC');

        $order = $model->get();
        $plan = Plan::get();
        for ($i = 0; $i < count($order); $i++) {
            for ($x = 0; $x < count($plan); $x++) {
                if ($order[$i]['plan_id'] === $plan[$x]['id']) {
                    $order[$i]['plan'] = $plan[$x];
                }
            }
        }

        return response([
            'data' => $order->makeHidden(['id', 'user_id'])
        ]);

    }

    public function couponCheck(Request $request)
   {
       if (empty($request->input('code'))) {
           return response()->json([
             'status' => 0,
             'msg' => '优惠券不能为空'
           ]);
       }

       $token = $request->input('token');
       $user = User::where('token', $token)->first();

       if (!$user) {
         return response()->json([
             'status' => 0,
             'msg' => '用户信息错误'
         ]);
       }

       $couponService = new CouponService($request->input('code'));
       $couponService->setPlanId($request->input('plan_id'));
       $couponService->setUserId($user->id);
       $couponService->check();
       return response([
           'status' => 1,
           'msg' => '已使用'.$request->input('code').'优惠券',
           'data' => $couponService->getCoupon()
       ]);
   }


   public function checktrade(Request $request) {

       $tradeNo = $request->input('trade_no');
       $token = $request->input('token');
       $user = User::where('token', $token)->first();

       if (!$user) {
         return response()->json([
             'status' => 0,
             'msg' => '用户信息错误'
         ]);
       }

       $order = Order::where('trade_no', $tradeNo)
           ->where('user_id', $user->id)
           ->first();

       if (!$order) {
           return response()->json([
               'status' => 0,
               'msg' => '订单不存在'
           ]);
       }

       return response([
           'status' => $order->status
       ]);
   }

   public function ordercancel(Request $request)
   {

       $token = $request->input('token');
       $user = User::where('token', $token)->first();

       if (!$user) {
         return response()->json([
             'status' => 0,
             'msg' => '用户信息错误'
         ]);
       }

       if (empty($request->input('trade_no'))) {
           return response()->json([
               'status' => 0,
               'msg' => '无效参数'
           ]);
       }

       $order = Order::where('trade_no', $request->input('trade_no'))
           ->where('user_id', $user->id)
           ->first();

       if (!$order) {
           return response()->json([
               'status' => 0,
               'msg' => '订单不存在'
           ]);
       }

       if ($order->status !== 0) {
           return response()->json([
               'status' => 0,
               'msg' => '只能取消待处理订单'
           ]);
       }

       $orderService = new OrderService($order);
       if (!$orderService->cancel()) {
           return response()->json([
               'status' => 0,
               'msg' => '订单取消失败'
           ]);
       }

       return response([
           'status' => 1,
           'msg' => '订单取消成功',
           'data' => true
       ]);
   }

   public function checkout(Request $request){

       $tradeNo = $request->input('trade_no');
       $method = $request->input('method');
       $token = $request->input('token');
       $user = User::where('token', $token)->first();

       if (!$user) {
         return response()->json([
             'status' => 0,
             'msg' => '用户信息错误'
         ]);
      }

       $order = Order::where('trade_no', $tradeNo)
           ->where('user_id', $user->id)
           ->where('status', 0)
           ->first();

       if (!$order) {
           return response()->json([
               'status' => 0,
               'msg' => '订单不存在或已支付'
           ]);
       }

       // free process
       if ($order->total_amount <= 0) {

           $orderService = new OrderService($order);

           if (!$orderService->paid($order->trade_no)) {
               return response([
                   'status' => 0,
                   'msg' => 'free process error'
               ]);
           };

           return response([
               'status' => -1,
               'msg' => '套餐购买成功',
               'data' => true
           ]);
       }

       $payment = Payment::find($method);

       if (!$payment || $payment->enable !== 1) {
           return response()->json([
               'status' => 0,
               'msg' => '付款方式不可用'
           ]);
       }

       $paymentService = new PaymentService($payment->payment, $payment->id);

       $order->handling_amount = NULL;
       $handling_amount = NULL;

       if ($payment->handling_fee_fixed || $payment->handling_fee_percent) {
           $order->handling_amount = round(($order->total_amount * ($payment->handling_fee_percent / 100)) + $payment->handling_fee_fixed);
           $handling_amount = round(($order->total_amount * ($payment->handling_fee_percent / 100)) + $payment->handling_fee_fixed);
       }

       $order->payment_id = $method;

       if (!$order->save()) {
           return response()->json([
               'status' => 0,
               'msg' => '请求失败，请稍后再试'
           ]);
       }

       $result = $paymentService->pay([
           'trade_no' => $tradeNo,
           'total_amount' => isset($order->handling_amount) ? ($order->total_amount + $order->handling_amount) : $order->total_amount,
           'user_id' => $order->user_id,
           'stripe_token' => $request->input('token')
       ]);

       return response([
           'status' => 1,
           //'handling_amount' => $handling_amount,
           'data' => $result['data']
       ]);
   }


   public function orderdetail(Request $request) {


       $token = $request->input('token');
       $user = User::where('token', $token)->first();

       if (!$user) {
         return response()->json([
             'status' => 0,
             'msg' => '用户信息错误'
         ]);
      }

       $order = Order::where('user_id', $user->id)
           ->where('trade_no', $request->input('trade_no'))
           ->first();

       if (!$order) {
           return response()->json([
               'status' => 0,
               'msg' => '订单不存在或已支付'
           ]);
       }

       $order['plan'] = Plan::find($order->plan_id);
       $order['try_out_plan_id'] = (int)config('v2board.try_out_plan_id');

       if (!$order['plan']) {
           return response()->json([
               'status' => 0,
               'msg' => '订阅计划不存在'
           ]);
       }

       if ($order->surplus_order_ids) {
           $order['surplus_orders'] = Order::whereIn('id', $order->surplus_order_ids)->get();
       }

       return response([
           'status' => 1,
           'data' => $order
       ]);

   }


   public function ordersave(Request $request) {

       $plan_id = $request->input('plan_id');
       $period = $request->input('period');
       $token = $request->input('token');
       $coupon_code = $request->input('coupon_code');

       $user = User::where('token', $token)->first();

       if (!$user) {
         return response()->json([
             'status' => 0,
             'msg' => '用户信息错误'
         ]);
      }

       $userService = new UserService();


       $orderNotComplete = Order::whereIn('status', [0, 1])
           ->where('user_id', $user->id)
           ->first();

       if ($orderNotComplete) {

           $orderService = new OrderService($orderNotComplete);
           if (!$orderService->cancel()) {
               return response()->json([
                   'status' => -2,
                   'msg' => '订单取消失败'
               ]);
           }

           return response()->json([
               'status' => -1,
               'no' => $orderNotComplete->trade_no,
               'msg' => '订单取消成功,继续支付'
           ]);
       }


       $planService = new PlanService($plan_id);
       $plan = $planService->plan;

       if (!$plan) {
           return response()->json([
               'status' => 0,
               'msg' => '套餐不存在'
           ]);
       }

       if ($user->plan_id !== $plan->id && !$planService->haveCapacity() && $period !== 'reset_price') {
           return response()->json([
               'status' => 0,
               'msg' => '当前产品已售罄'
           ]);
       }

       if ($plan[$period] === NULL) {
           return response()->json([
               'status' => 0,
               'msg' => '无法购买此付款期，请选择其他付款期'
           ]);
       }

       if ($period === 'reset_price') {
           if (!$userService->isAvailable($user) || $plan->id !== $user->plan_id) {
               return response()->json([
                   'status' => 0,
                   'msg' => '订购已过期或无有效订购，无法购买数据重置套餐'
               ]);
           }
       }

       if ((!$plan->show && !$plan->renew) || (!$plan->show && $user->plan_id !== $plan->id)) {
           if ($period !== 'reset_price') {
               return response()->json([
                   'status' => 0,
                   'msg' => '此套餐已售罄，请选择其他套餐'
               ]);
           }
       }

       if (!$plan->renew && $user->plan_id == $plan->id && $period !== 'reset_price') {
           return response()->json([
               'status' => 0,
               'msg' => '此套餐无法续订，请更换其他套餐'
           ]);
       }


       if (!$plan->show && $plan->renew && !$userService->isAvailable($user)) {
           return response()->json([
               'status' => 0,
               'msg' => '此订阅已过期，请更换为其他订阅'
           ]);
       }

       DB::beginTransaction();
       $order = new Order();
       $orderService = new OrderService($order);
       $order->user_id = $user->id;
       $order->plan_id = $plan->id;
       $order->period = $period;
       $order->trade_no = Helper::generateOrderNo();
       $order->total_amount = $plan[$period];

       if ($coupon_code) {
           $couponService = new CouponService($coupon_code);
           if (!$couponService->use($order)) {
               DB::rollBack();
               return response()->json([
                   'status' => 0,
                   'msg' => '优惠券失效'
               ]);
           }
           $order->coupon_id = $couponService->getId();
       }

       $orderService->setVipDiscount($user);
       $orderService->setOrderType($user);
       $orderService->setInvite($user);

       if ($user->balance && $order->total_amount > 0) {
           $remainingBalance = $user->balance - $order->total_amount;
           $userService = new UserService();
           if ($remainingBalance > 0) {
               if (!$userService->addBalance($order->user_id, - $order->total_amount)) {
                   DB::rollBack();
                   return response()->json([
                       'status' => 0,
                       'msg' => '余额不足'
                   ]);
               }
               $order->balance_amount = $order->total_amount;
               $order->total_amount = 0;
           } else {
               if (!$userService->addBalance($order->user_id, - $user->balance)) {
                   DB::rollBack();
                   return response()->json([
                       'status' => 0,
                       'msg' => '余额不足'
                   ]);
               }
               $order->balance_amount = $user->balance;
               $order->total_amount = $order->total_amount - $user->balance;
           }
       }

       if (!$order->save()) {
           DB::rollback();
           return response()->json([
               'status' => 0,
               'msg' => '订单创建失败'
           ]);
       }

       DB::commit();

       return response([
           'status' => 1,
           'msg' => '订单创建成功',
           'data' => $order->trade_no
       ]);


   }


   public function getPaymentMethod(Request $request) {

       $methods = Payment::select([
           'id',
           'name',
           'payment',
           'icon',
           'handling_fee_fixed',
           'handling_fee_percent'
       ])
           ->where('enable', 1)
           ->orderBy('sort', 'ASC')
           ->get();

       return response([
           'data' => $methods
       ]);

   }

   public function appshop(Request $request) {

       $counts = PlanService::countActiveUsers();
       $plans = Plan::where('show', 1)
           ->orderBy('sort', 'ASC')
           ->get();
       foreach ($plans as $k => $v) {
           if ($plans[$k]->capacity_limit === NULL) continue;
           if (!isset($counts[$plans[$k]->id])) continue;
           $plans[$k]->capacity_limit = $plans[$k]->capacity_limit - $counts[$plans[$k]->id]->count;
       }
       return response([
           'status' => 1,
           'data' => $plans
       ]);


   }

   public function appalert(Request $request) {

       $lang = $request->input('lang');
       $token = $request->input('token');

       $model = Notice::orderBy('created_at', 'DESC')
           ->where('show', 1);
       $res = $model->forPage(1, 10)
           ->get();

       foreach ($res as $item) {

           if (empty($item['tags']) || empty($item['tags'][0])) {
               continue;
           }

           //2.1.3+
           if($item["tags"][0] == "营销活动") {
              return response()->json([
                   'status' => 1,
                   'title' => $item["title"],
                   'msg' => $item["content"],
                   'img' => $item["img_url"],
                   'tags' => $item["tags"],
                   'context' => $item["content"]
               ]);
           }

           // if($item["tags"][0] == "弹窗" || $item["tags"][0] == "alert") {
           //   return response()->json([
           //         'status' => 1,
           //         'title' => $item["title"],
           //         'msg' => $item["content"],
           //         'img' => $item["img_url"],
           //         'context' => $item["content"]
           //     ]);
           // }
       }

       return response()->json([
           'status' => 0,
           'title' => '',
           'msg' => '',
           'context' => ''
       ]);

   }

    private function getEmailSuffix()
    {
        $suffix = config('v2board.email_whitelist_suffix', Dict::EMAIL_WHITELIST_SUFFIX_DEFAULT);
        if (!is_array($suffix)) {
            return preg_split('/,/', $suffix);
        }
        return $suffix;
    }

    public function appchangePassword(UserChangePassword $request)
    {

        $user = User::find($request->input('userId'));
        if (!Helper::multiPasswordVerify(
            $user->password_algo,
            $user->password_salt,
            $request->input('old_password'),
            $user->password)
        ) {
            return response()->json([
                'status' => 0,
                'msg' => '旧密码有误'
            ]);
        }
        $user->password = password_hash($request->input('new_password'), PASSWORD_DEFAULT);
        $user->password_algo = NULL;
        if (!$user->save()) {
            return response()->json([
                'status' => 0,
                'msg' => '保存失败'
            ]);
        }
        $request->session()->flush();
        return response([
            'status' => 1,
            'data' => true
        ]);
    }

    public function appnotice(Request $request)
    {
        $current = $request->input('current') ? $request->input('current') : 1;
        $pageSize = 15;
        $model = Notice::orderBy('created_at', 'DESC')
            ->where('show', 1);
        $total = $model->count();
        $res = $model->forPage($current, $pageSize)
            ->get();
        return response([
            'data' => $res,
            'total' => $total
        ]);
    }


    public function appknowledge(Request $request)
    {
        $user = User::find($request->input('id'));

        if ($request->input('id')) {
            $knowledge = Knowledge::where('id', $request->input('id'))
                ->where('show', 1)
                ->first()
                ->toArray();
            if (!$knowledge) {
               return response([
                    'data' => ""
                ]);
            };

            $subscribeUrl = Helper::getSubscribeUrl("/api/v1/client/subscribe?token={$user['token']}");
            $knowledge['body'] = str_replace('{{siteName}}', config('v2board.app_name', 'V2Board'), $knowledge['body']);
            $knowledge['body'] = str_replace('{{subscribeUrl}}', $subscribeUrl, $knowledge['body']);
            $knowledge['body'] = str_replace('{{urlEncodeSubscribeUrl}}', urlencode($subscribeUrl), $knowledge['body']);
            $knowledge['body'] = str_replace(
                '{{safeBase64SubscribeUrl}}',
                str_replace(
                    array('+', '/', '='),
                    array('-', '_', ''),
                    base64_encode($subscribeUrl)
                ),
                $knowledge['body']
            );
            return response([
                'data' => $knowledge
            ]);
        }


        $knowledges = Knowledge::select(['id', 'category', 'title', 'updated_at'])
            ->where('language', $request->input('language'))
            ->where('show', 1)
            ->orderBy('sort', 'ASC')
            ->get()
            ->groupBy('category');
        return response([
            'data' => $knowledges
        ]);

    }


    public function appregister(AuthRegister $request)
    {

        if ((int)config('v2board.register_limit_by_ip_enable', 0)) {
            $registerCountByIP = Cache::get(CacheKey::get('REGISTER_IP_RATE_LIMIT', $request->ip())) ?? 0;
            if ((int)$registerCountByIP >= (int)config('v2board.register_limit_count', 3)) {
                return response()->json([
                    'status' => 0,
                    'msg' => 'Register frequently, please try again after 1 hour'
                ]);
            }
        }

        if ((int)config('v2board.email_whitelist_enable', 0)) {
            if (!Helper::emailSuffixVerify(
                $request->input('email'),
                config('v2board.email_whitelist_suffix', Dict::EMAIL_WHITELIST_SUFFIX_DEFAULT))
            ) {
                return response()->json([
                    'status' => 0,
                    'msg' => 'Email suffix is not in the Whitelist'
                ]);
            }
        }
        if ((int)config('v2board.email_gmail_limit_enable', 0)) {
            $prefix = explode('@', $request->input('email'))[0];
            if (strpos($prefix, '.') !== false || strpos($prefix, '+') !== false) {
                return response()->json([
                    'status' => 0,
                    'msg' => 'Gmail alias is not supported'
                ]);
            }
        }
        if ((int)config('v2board.stop_register', 0)) {
            return response()->json([
                'status' => 0,
                'msg' => 'Registration has closed'
            ]);
        }
        if ((int)config('v2board.invite_force', 0)) {
            if (empty($request->input('invite_code'))) {
                return response()->json([
                    'status' => 0,
                    'msg' => 'You must use the invitation code to register'
                ]);
            }
        }
        if ((int)config('v2board.email_verify', 0)) {
            if (empty($request->input('email_code'))) {
                return response()->json([
                    'status' => 0,
                    'msg' => 'Email verification code cannot be empty'
                ]);
            }
            if ((string)Cache::get(CacheKey::get('EMAIL_VERIFY_CODE', $request->input('email'))) !== (string)$request->input('email_code')) {
                return response()->json([
                    'status' => 0,
                    'msg' => 'Incorrect email verification code'
                ]);
            }
        }
        $email = $request->input('email');
        $password = $request->input('password');
        $exist = User::where('email', $email)->first();
        if ($exist) {
            return response()->json([
                'status' => 0,
                'msg' => 'Email already exists'
            ]);
        }
        $user = new User();
        $user->email = $email;
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->uuid = Helper::guid(true);
        $user->token = Helper::guid();
        if ($request->input('invite_code')) {
            $inviteCode = InviteCode::where('code', $request->input('invite_code'))
                ->where('status', 0)
                ->first();
            if (!$inviteCode) {
                if ((int)config('v2board.invite_force', 0)) {
                    return response()->json([
                        'status' => 0,
                        'msg' => 'Invalid invitation code'
                    ]);
                }
            } else {
                $user->invite_user_id = $inviteCode->user_id ? $inviteCode->user_id : null;
                if (!(int)config('v2board.invite_never_expire', 0)) {
                    $inviteCode->status = 1;
                    $inviteCode->save();
                }
            }
        }

        // try out
        if ((int)config('v2board.try_out_plan_id', 0)) {
            $plan = Plan::find(config('v2board.try_out_plan_id'));
            if ($plan) {
                $user->transfer_enable = $plan->transfer_enable * 1073741824;
                $user->plan_id = $plan->id;
                $user->group_id = $plan->group_id;
                $user->expired_at = time() + (config('v2board.try_out_hour', 1) * 3600);
            }
        }

        if (!$user->save()) {
            return response()->json([
                'status' => 0,
                'msg' => '注册失败'
            ]);
        }
        if ((int)config('v2board.email_verify', 0)) {
            Cache::forget(CacheKey::get('EMAIL_VERIFY_CODE', $request->input('email')));
        }


        $user->last_login_at = time();
        $user->save();

        return response()->json([
            'status' => 1,
            'msg' => '注册成功',
            'data' => true
        ]);
    }


    public function appforget(AuthForget $request)
    {
        if (Cache::get(CacheKey::get('EMAIL_VERIFY_CODE', $request->input('email'))) !== $request->input('email_code')) {
            return response()->json([
                'status' => 0,
                'msg' => '邮箱验证码有误'
            ]);
        }
        $user = User::where('email', $request->input('email'))->first();
        if (!$user) {
            return response()->json([
                'status' => 0,
                'msg' => '该邮箱不存在系统中'
            ]);
        }
        $user->password = password_hash($request->input('password'), PASSWORD_DEFAULT);
        $user->password_algo = NULL;
        if (!$user->save()) {
            return response()->json([
                'status' => 0,
                'msg' => '重置失败'
            ]);
        }
        Cache::forget(CacheKey::get('EMAIL_VERIFY_CODE', $request->input('email')));
        return response()->json([
            'status' => 1,
            'msg' => '重置成功'
        ]);
    }

    function sizecount($filesize) {
        if($filesize >= 1073741824) {
            $filesize = round($filesize / 1073741824 * 100) / 100 . 'G';
        } elseif($filesize >= 1048576) {
            $filesize = round($filesize / 1048576 * 100) / 100 . 'M';
        } elseif($filesize >= 1024) {
            $filesize = round($filesize / 1024 * 100) / 100 . 'K';
        } else {
            $filesize = $filesize . 'B';
        }
        return $filesize;
    }

    function curl_get_https($url){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $tmpInfo = curl_exec($curl);
        curl_close($curl);
        return $tmpInfo;
    }

    public function applogin(AuthLogin $request)
    {

        $email = $request->input('email');
        $password = $request->input('password');
        $user = User::where('email', $email)->first();
        $version = $request->input('v'); //add ios2.1.7&2.1.3+

        if (!$user) {
            return response()->json([
                'status' => 0,
                'msg' => '用户名或密码错误'
            ]);
        }
        if (!Helper::multiPasswordVerify(
            $user->password_algo,
            $user->password_salt,
            $password,
            $user->password)
        ) {
            return response()->json([
                'status' => 0,
                'msg' => '用户名或密码错误'
            ]);
        }

        if ($user->banned) {
            return response()->json([
                'status' => 0,
                'msg' => '该账户已被停止使用'
            ]);
        }


        $subscribeUrl = config('v2board.subscribe_url') ?: config('v2board.app_url');
        $conf = "";

        $planName = "无订阅";
        $confCount = 0;

        date_default_timezone_set("PRC");

        $userService = new UserService();
        $resetDay = $userService->getResetDay($user);
        $days = $resetDay;

        if($days < 1){
            $days = 0;
        }

        if($user->plan_id != 0){
            $plan = Plan::find($user->plan_id);
            if($plan){
                $planName = $plan["name"];
            }
        }

        if($conf != ""){
            $keyword_arr = explode(PHP_EOL, trim(base64_decode($conf)));
            $confCount = count($keyword_arr);
        } else {
            $conf = "";
        }

        $percentage = 0;

        if($user->transfer_enable != 0){

            $_percentage = number_format(($user->transfer_enable-($user->u+$user->d))/$user->transfer_enable*100,2);

            if ($_percentage < 0) {
                $percentage = 0;
            } else {
                $percentage = $_percentage;
            }
        }


        $codes = InviteCode::orderBy('created_at', 'DESC')->where('user_id', $user->id)
            ->where('status', 0)
            ->get();
            //->first();

        $nowinviteCode = "";

        if(count($codes) == 0){
            $inviteCodeNew = Helper::randomChar(8);
            $inviteCode = new InviteCode();
            $inviteCode->user_id = $user->id;
            $inviteCode->code = $inviteCodeNew;
            $inviteCode->save();
            $nowinviteCode = $inviteCodeNew;
        } else {
            $nowinviteCode = $codes[0]->code;
        }


        $temparray=array();

        if ($userService->isAvailable($user)) {
            $serverService = new ServerService();
            $servers = $this->getAvailableServers($user);

            array_push($temparray, array(
                  "name" => "AutoSelect",
                  "server" => "",
                  "server_port" => 80,
                  "flag" => "AUTO",
                  "tags" => "",
                  "index" => 0)
            );

            $index = 0;
            foreach ($servers as $item){
                $index++;
                if (empty($item['tags']) || empty($item['tags'][0])) {
                    continue;
                }
                $item['name'] = $item['id'] . "." . preg_replace('/\#\d+$/', '', $item['name']);
                array_push($temparray, array(
                      "name" => $item['name'],
                      "server" => $item['host'],
                      "server_port" => $item['port'],
                      "flag" => $item['tags'][0],
                      "tags" => $item['tags'],
                      "index" => $index)
                );
            }
        }

        $key = "apps_connect_key";
        $iv = "8c97f304422a60e0";
        $nodes = "";
        $denode = json_encode($temparray);
        $encrypted = openssl_encrypt($denode, 'aes-128-cbc', $key, false, $iv);

        if($encrypted != null && $encrypted != ""){
            $nodes = $encrypted;
        } else {
            $nodes = "";
        }

        $nodeConf = "";
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

        //add ios2.1.7&2.1.3+
        $iosversion = "";
        $version_1_2_3andlater = "oldconfig";

        if (preg_match('/Apps Connect\/([0-9\.]+)/', $userAgent, $matches)) {
            $version = $matches[1];
            $iosversion = $version;

            if (version_compare($iosversion, "2.1.6", ">")) {
                $version_1_2_3andlater = "newconfig";
            } else {
                $version_1_2_3andlater = "oldconfig";
            }

        } else {

            if (version_compare($version, "2.1.2", ">")) {
                $version_1_2_3andlater = "newconfig";
            } else {
                $version_1_2_3andlater = "oldconfig";
            }
        }

        if($userAgent == "windows.v2board.app 2.0") {
            $deconf = $this->appnode($user->token,"windows",$version_1_2_3andlater);
        } else {
            $deconf = $this->appnode($user->token,"",$version_1_2_3andlater);
        }

        //end add ios2.1.7&2.1.3+

        $expired_date = $user->expired_at ? date('Y-m-d H:i:s', $user->expired_at) : '长期有效';
        if (strpos($userAgent, 'Apps Connect') !== false) {
            $expired_date = $user->expired_at ? date('Y-m-d', $user->expired_at) : '长期有效';
        }

        $confencrypted = openssl_encrypt($deconf, 'aes-128-cbc', $key, false, $iv);

        if($confencrypted != null && $confencrypted != ""){
            $nodeConf = $confencrypted;
        } else {
            $nodeConf = "";
        }


        $data = [
            'status' => 1,
            'msg' => '登录成功',
            'id' => $user->id,
            'uuid' => $user->uuid,
            'email' => $user->email,
            'planName' => $planName,
            'balance' => $user->balance,
            'code' => $nowinviteCode,
            'days' => $days,
            't' => $this->sizecount($user->t),
            'u' => $this->sizecount($user->u),
            'd' => $this->sizecount($user->d),
            'useTf' => $this->sizecount($user->u + $user->d),
            'transfer_enable' => $this->sizecount($user->transfer_enable),
            'token' => $user->token,
            'expired' => $expired_date,
            'residue' => $this->sizecount($user->transfer_enable - ($user->u + $user->d)),
            'conf' => $conf,
            'tfPercentage' => $percentage,
            'confCount' => $confCount,
            'configs' => $nodeConf,
            'configsNodes' => $nodes,
            'chatLink' => '',
            'web' => config('v2board.app_url'),
            'link' => config('v2board.telegram_discuss_link'),
            'logo' => config('v2board.logo'),
            'url' => config('v2board.app_url')
        ];

        return response($data);
    }

    public function appupdate(Request $request) {

        $system = $request->input('system');
        $version = $request->input('version');

        if($system == "android"){

            $_nowVersion = "2.1.6";

            if (version_compare($version, $_nowVersion, '>=')) {
                return response()->json([
                    'status' => 0,
                    'msg' => '已是最新版本'
                ]);
            } else {
                return response()->json([
                    'status' => 1,
                    'msg' => '发现新版本 '.$_nowVersion,
                    'link' => '下载地址'
                ]);
            }


        } if($system == "mac"){

            $_nowVersion = "2.1.6";

            if (version_compare($version, $_nowVersion, '>=')) {
                return response()->json([
                    'status' => 0,
                    'msg' => '已是最新版本'
                ]);
            } else {
                return response()->json([
                    'status' => 1,
                    'msg' => '发现新版本 '.$_nowVersion,
                    'link' => '下载地址'
                ]);
            }


        } if($system == "windows"){

            $_nowVersion = "2.1.6";

            if (version_compare($version, $_nowVersion, '>=')) {
                return response()->json([
                    'status' => 0,
                    'msg' => '已是最新版本'
                ]);
            } else {
                return response()->json([
                    'status' => 1,
                    'msg' => '发现新版本 '.$_nowVersion,
                    'link' => '下载地址'
                ]);
            }


        } else {

            return response()->json([
                'status' => 0,
                'msg' => '已是最新版本'
            ]);

        }
    }

    public function appDelete(Request $request){

        $token = $request->input('token');
        $user = User::where('token', $token)->first();

        if (!$user) {
            return response()->json([
                'status' => 0,
                'msg' => 'User information error'
            ]);
        }

        if ($user->banned) {
            return response()->json([
                'status' => 0,
                'msg' => 'This account has been deleted'
            ]);
        }

        $user->banned = true;

        if ($user->save()) {
            $data["status"] = 1;
            $data["msg"] = "Account deleted successfully";
        }else{
            $data["status"] = 0;
            $data["msg"] = "Failed to delete";
        }

        return response($data);

    }


    public function appsync(Request $request)
    {

        $token = $request->input('token');
        $user = User::where('token', $token)->first();
        $version = $request->input('v'); //add ios2.1.7&2.1.3+

        if (!$user) {
            return response()->json([
                'status' => 0,
                'msg' => 'User information error'
            ]);
        }

        if ($user->banned) {
            return response()->json([
                'status' => 0,
                'msg' => 'This account has been suspended'
            ]);
        }

        //<2.0.5
        $subscribeUrl = config('v2board.subscribe_url') ?: config('v2board.app_url');
        $conf = "";

        $planName = "无订阅";
        $confCount = 0;

        date_default_timezone_set("PRC");

        $userService = new UserService();
        $resetDay = $userService->getResetDay($user);
        $days = $resetDay;

        if($days < 1){
            $days = 0;
        }

        if($user->plan_id != 0){
            $plan = Plan::find($user->plan_id);
            if($plan){
                $planName = $plan["name"];
            }
        }

        if($conf != ""){
            $keyword_arr = explode(PHP_EOL, trim(base64_decode($conf)));
            $confCount = count($keyword_arr);

            $key = "apps_connect_key";
            $iv = "8c97f304422a60e0";

            $encrypted = openssl_encrypt($conf, 'aes-128-cbc', $key, false, $iv);

            if($encrypted != null && $encrypted != ""){
                $conf = $encrypted;
            }

        } else {
            $conf = "";
        }

        $percentage = 0;

        if($user->transfer_enable != 0){

            $_percentage = number_format(($user->transfer_enable-($user->u+$user->d))/$user->transfer_enable*100,2);

            if ($_percentage < 0) {
                $percentage = 0;
            } else {
                $percentage = $_percentage;
            }
        }

        $codes = InviteCode::orderBy('created_at', 'DESC')->where('user_id', $user->id)
            ->where('status', 0)
            ->get();
            //->first();

        $nowinviteCode = "";

        if(count($codes) == 0){
            $inviteCodeNew = Helper::randomChar(8);
            $inviteCode = new InviteCode();
            $inviteCode->user_id = $user->id;
            $inviteCode->code = $inviteCodeNew;
            $inviteCode->save();
            $nowinviteCode = $inviteCodeNew;
        } else {
            $nowinviteCode = $codes[0]->code;
        }

        $userService = new UserService();

        $temparray=array();

        if ($userService->isAvailable($user)) {
            $serverService = new ServerService();
            $servers = $this->getAvailableServers($user);

            array_push($temparray, array(
                  "name" => "AutoSelect",
                  "server" => "",
                  "server_port" => 80,
                  "flag" => "AUTO",
                  "tags" => "",
                  "index" => 0)
            );

            $index = 0;
            foreach ($servers as $item){
                $index++;
                if (empty($item['tags']) || empty($item['tags'][0])) {
                    continue;
                }
                $item['name'] = $item['id'] . "." . preg_replace('/\#\d+$/', '', $item['name']);
                array_push($temparray, array(
                      "name" => $item['name'],
                      "server" => $item['host'],
                      "server_port" => $item['port'],
                      "flag" => $item['tags'][0],
                      "tags" => $item['tags'],
                      "index" => $index)
                );
            }
        }

        $key = "apps_connect_key";
        $iv = "8c97f304422a60e0";
        $nodes = "";
        $denode = json_encode($temparray);
        $encrypted = openssl_encrypt($denode, 'aes-128-cbc', $key, false, $iv);

        if($encrypted != null && $encrypted != ""){
            $nodes = $encrypted;
        } else {
            $nodes = "";
        }

        $nodeConf = "";
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

        //add ios2.1.7&2.1.3+
        $iosversion = "";
        $version_1_2_3andlater = "oldconfig";

        if (preg_match('/Apps Connect\/([0-9\.]+)/', $userAgent, $matches)) {
            $version = $matches[1];
            $iosversion = $version;

            if (version_compare($iosversion, "2.1.6", ">")) {
                $version_1_2_3andlater = "newconfig";
            } else {
                $version_1_2_3andlater = "oldconfig";
            }

        } else {

            if (version_compare($version, "2.1.2", ">")) {
                $version_1_2_3andlater = "newconfig";
            } else {
                $version_1_2_3andlater = "oldconfig";
            }
        }

        if($userAgent == "windows.v2board.app 2.0") {
            $deconf = $this->appnode($user->token,"windows",$version_1_2_3andlater);
        } else {
            $deconf = $this->appnode($user->token,"",$version_1_2_3andlater);
        }

        //end add ios2.1.7&2.1.3+

        $confencrypted = openssl_encrypt($deconf, 'aes-128-cbc', $key, false, $iv);
        if($confencrypted != null && $confencrypted != ""){
            $nodeConf = $confencrypted;
        } else {
            $nodeConf = "";
        }

        $expired_date = $user->expired_at ? date('Y-m-d H:i:s', $user->expired_at) : '长期有效';
        if (strpos($userAgent, 'Apps Connect') !== false) {
            $expired_date = $user->expired_at ? date('Y-m-d', $user->expired_at) : '长期有效';
        }

        $data = [
            'status' => 1,
            'msg' => 'Success',
            'appname' => config('v2board.app_name'),
            'email' => $user->email,
            'planName' => $planName,
            'days' => $days,
            'code' => $nowinviteCode,
            'useTf' => $this->sizecount($user->u + $user->d),
            'transfer_enable' => $this->sizecount($user->transfer_enable),
            'token' => $user->token,
            'expired' => $expired_date,
            'residue' => $this->sizecount($user->transfer_enable - ($user->u + $user->d)),
            'conf' => $conf,
            'tfPercentage' => $percentage,
            'confCount' => $confCount,
            'web' => config('v2board.app_url'), //Web 官網
            'link' => config('v2board.telegram_discuss_link'), //TG 群組
            'logo' => config('v2board.logo'), //LOGO 圖標
            'url' => config('v2board.app_url'),
            'configs' => $nodeConf,
            'configsNodes' => $nodes
        ];

        return response($data);

    }

    public function appnode($token,$type,$version_1_2_3andlater) {

        if($token == "") {
            return "";
        }

        $user = User::where('token', $token)->first();

        if (!$user) {
            return "";
        }

        if ($user->banned) {
            return "";
        }

        $userService = new UserService();
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

        if ($userService->isAvailable($user)) {

            $serverService = new ServerService();
            $servers = $this->getAvailableServers($user);
            //add ios2.1.7&2.1.3+
            $data = "";
            if($version_1_2_3andlater == "newconfig") {
                $data = $this->newappsconfig;
            } else {
                $data = $this->config;
            }
            $proxy = [];
            $proxies = [];
            $ruleProxies = [];
            $serversName = [];

            $uuidtest = $user->uuid;

            foreach ($servers as $item) {
                $item['name'] = $item['id'] . "." . preg_replace('/\#\d+$/', '', $item['name']);
                if ($item['type'] === 'shadowsocks'
                    && in_array($item['cipher'], [
                        'aes-128-gcm',
                        'aes-192-gcm',
                        'aes-256-gcm',
                        'chacha20-ietf-poly1305'
                    ])
                ) {
                    array_push($proxy, $this->buildShadowsocks($uuidtest, $item));
                    array_push($proxies, $item['name']);
                    array_push($ruleProxies, $item['name']);
                    array_push($serversName, $item["host"]);
                }
                if ($item['type'] === 'vmess' || $item['type'] === 'v2ray') {
                    array_push($proxy, $this->buildVmess($uuidtest, $item));
                    array_push($proxies, $item['name']);
                    array_push($ruleProxies, $item['name']);
                    array_push($serversName, $item["host"]);
                }
                if ($item['type'] === 'trojan') {
                    array_push($proxy, $this->buildTrojan($uuidtest, $item));
                    array_push($proxies, $item['name']);
                    array_push($ruleProxies, $item['name']);
                    array_push($serversName, $item["host"]);
                }

                if ($item['type'] === 'vless') {
                    array_push($proxy, $this->buildVless($uuidtest, $item));
                    array_push($proxies, $item['name']);
                    array_push($ruleProxies, $item['name']);
                    array_push($serversName, $item["host"]);
                }

                 if ($item['type'] === 'hysteria') {
                    array_push($proxy, $this->buildHysteria($uuidtest, $item));
                    array_push($proxies, $item['name']);
                    array_push($ruleProxies, $item['name']);
                    array_push($serversName, $item["host"]);
                 }
            }

            //Direct
            $directOutbound = [
                'tag' => "direct",
                'type' => "direct"
            ];

            //dns
            $dnsOutbound = [
                'tag' => "dns",
                'type' => "dns"
            ];

            //auto
            $autoOutbound = [
                "type" => "urltest",
                "tag" => "AutoSelect",
                "outbounds" => $proxies,
                "url" => "https://www.gstatic.com/generate_204"
            ];

            //RULE
            array_push($ruleProxies, "direct");
            $ruleOutbound = [
                'outbounds' => $ruleProxies,
                'tag' => "RULE",
                'type' => "selector"
            ];

            //GLOBAL
            $globalOutbound = [
                'outbounds' => $proxies,
                'tag' => "GLOBAL",
                'type' => "selector"
            ];


            //outbounds
            $outbounds = [];
            $outbounds += $proxy;

            array_push($outbounds, $autoOutbound);
            array_push($outbounds, $globalOutbound);
            array_push($outbounds, $ruleOutbound);
            array_push($outbounds, $directOutbound);
            array_push($outbounds, $dnsOutbound);

            $data["outbounds"] = $outbounds;


            $globalRule = [
               "clash_mode" => "global",
               "outbound" => "GLOBAL"
            ];

            $directRule = [
               "geoip" => [
                    "cn",
                    "private"
               ],
               "geosite" => [
                    "cn",
                    "apple@cn"
                ],
               "outbound" => "direct"
            ];


            $rule = [
               "clash_mode" => "rule",
               "outbound" => "RULE"
            ];

            //add ios2.1.7&2.1.3+
            $newdnsconfighijack = [
                "type"    => "logical",
                "mode"    => "or",
                "rules"   => [
                    [
                        "network" => "udp",
                        "port"    => 53
                    ],
                    [
                        "protocol" => "dns"
                    ]
                ],
                "action"  => "hijack-dns"
            ];

            $newipprivate = [
                "ip_is_private" => true,
                "outbound" => "direct"
            ];


            $geolocationRule = [
                "rule_set" => "geosite-geolocation-cn",
                "outbound" => "direct"
            ];

            $geositeRule = [
                "rule_set" => "geosite-cn",
                "outbound" => "direct"
            ];


            $geoipRule = [
                "rule_set" => "geoip-cn",
                "outbound" => "direct"
            ];

            $inbounds = [];

            //add ios2.1.7&2.1.3+
            if($version_1_2_3andlater == "newconfig") {

                array_push($data["route"]["rules"], $newdnsconfighijack);
                array_push($data["route"]["rules"], $newipprivate);
                array_push($data["route"]["rules"], $globalRule);
                array_push($data["route"]["rules"], $geolocationRule);
                array_push($data["route"]["rules"], $geositeRule);
                array_push($data["route"]["rules"], $geoipRule);
                array_push($data["route"]["rules"], $rule);

                $newwininbounds = [
                    [
                        'strict_route' => true,
                        'type' => 'tun',
                        'auto_route' => true,
                        'endpoint_independent_nat'  => true,
                        'sniff_override_destination'=> true,
                        'sniff' => true,
                        'stack' => 'system',
                        'address' => [
                            '172.19.0.1/30',
                            'fdfe:dcba:9876::1/126'
                        ]
                    ],
                    [
                        "type" => "mixed",
                        "listen" => "127.0.0.1",
                        "listen_port" => 10090
                    ]
                ];

                $data["inbounds"] = $newwininbounds;
                $data['route']['auto_detect_interface'] = true;

            } else {

                //rules
                $dnsRule = [
                   "outbound" => "dns",
                   "protocol" => "dns"
                ];

                $tkRule = [
                   "geosite" => [
                        "tiktok",
                        "openai"
                    ],
                   "domain_keyword" => [
                        "tiktok",
                        "chatgpt"
                   ],
                   "domain_suffix" => [
                        "byteoversea.com",
                        "ibytedtos.com"
                   ],
                   "outbound" => "RULE"
                ];

                //route
                array_push($data["route"]["rules"], $dnsRule);
                array_push($data["route"]["rules"], $tkRule);
                array_push($data["route"]["rules"], $globalRule);
                array_push($data["route"]["rules"], $directRule);
                array_push($data["route"]["rules"], $rule);

                //inbounds
                $otherinbounds = [
                    "auto_route" => true,
                    "domain_strategy" =>  "prefer_ipv4",
                    "endpoint_independent_nat" =>  true,
                    "inet4_address" => "172.19.0.1/30",
                    "inet6_address" => "2001:0470:f9da:fdfa::1/64",
                    "mtu" => 9000,
                    "sniff" => true,
                    "sniff_override_destination" => true,
                    "stack" => "system",
                    "strict_route" => true,
                    "type" => "tun"
                ];

                $wininbounds = [
                  "listen" => "127.0.0.1",
                  "listen_port" => 10090,
                  "sniff" => true,
                  "tag" => "mixed-in",
                  "type" => "mixed"
                ];


                if($type == "") {
                    array_push($inbounds, $otherinbounds);
                    $data["inbounds"] = $inbounds;
                    $data['route']['auto_detect_interface'] = true;
                } else {
                    array_push($inbounds, $wininbounds);
                    $data["inbounds"] = $inbounds;
                }

            }


            $jsonString = json_encode($data);

            return $jsonString;

        } else {

            return "";

        }

    }


    //xboard version
    public static function buildHysteria($password, $server) {
       $passwd = $password;
       $array = [
           'server' => $server['host'],
           'server_port' => $server['port'],
           'tls' => [
               'enabled' => true,
               'insecure' => $server['insecure'] ? true : false,
               'server_name' => $server['server_name'] ? $server['server_name'] : ""
               //'apln' => "h3"
           ]
       ];

       if (is_null($server['version']) || $server['version'] == 1) {

           $array['auth_str'] = $password;
           $array['tag'] = $server['name'];
           $array['type'] = 'hysteria';
           $array['up_mbps'] = $server['down_mbps'];
           $array['down_mbps'] = $server['up_mbps'];
           if (isset($server['obfs']) && isset($server['obfs_password'])) {
               $array['obfs'] = $server['obfs_password'];
           }
           $array['disable_mtu_discovery'] = true;

       } elseif ($server['version'] == 2) {

           $array['password'] = $password;
           $array['tag'] = $server['name'];
           $array['type'] = 'hysteria2';
           $array['password'] = $password;

           $array['up_mbps'] = $server['down_mbps'];
           $array['down_mbps'] = $server['up_mbps'];

           if (isset($server['is_obfs'])) {
               $array['obfs']['type'] = "salamander";
               $array['obfs']['password'] = $server['server_key'];
           }
       }

       return $array;
   }

     public static function buildShadowsocks($uuid, $server)
    {
        $array = [];
        $array['type'] = 'shadowsocks';
        $array['tag'] = $server['name'];
        $array['method'] = $server['cipher'];
        $array['server'] = $server['host'];
        $array['server_port'] = $server['port'];
        $array['password'] = $uuid;
        return $array;
    }

    public static function buildVmess($uuid, $server)
    {
        $array = [];
        $array['type'] = 'vmess';
        $array['tag'] = $server['name'];
        $array['server'] = $server['host'];
        $array['server_port'] = $server['port'];
        $array['uuid'] = $uuid;
        $array['security'] = 'auto';
        $array['alter_id'] = 0;
        $array['global_padding'] = false;
        $array['authenticated_length'] = false;

        if ($server['tls']) {
            $array['tls'] = [];
            $array['tls']['enabled'] = true;

            if ($server['tlsSettings']) {
                $tlsSettings = $server['tlsSettings'];
                if (isset($tlsSettings['allowInsecure']) && !empty($tlsSettings['allowInsecure']))
                    $array['tls']['insecure'] = ($tlsSettings['allowInsecure'] ? true : false);
                if (isset($tlsSettings['serverName']) && !empty($tlsSettings['serverName']))
                    $array['tls']['server_name'] = $tlsSettings['serverName'];
            }
        }


        if ($server['network'] === 'ws') {

            $array['transport'] = [];

            $array['transport']['type'] = "ws";

            if ($server['networkSettings']) {
                $wsSettings = $server['networkSettings'];
                if (isset($wsSettings['path']) && !empty($wsSettings['path']))
                    $array['transport']['path'] = $wsSettings['path'];
                if (isset($wsSettings['headers']['Host']) && !empty($wsSettings['headers']['Host']))
                    $array['transport']['headers'] = ['Host' => $wsSettings['headers']['Host']];
                if (isset($wsSettings['path']) && !empty($wsSettings['path']))
                    $array['transport']['path'] = $wsSettings['path'];
                if (isset($wsSettings['headers']['Host']) && !empty($wsSettings['headers']['Host']))
                    $array['transport']['headers'] = ['Host' => $wsSettings['headers']['Host']];
            }

            $array['transport']['early_data_header_name'] = "Sec-WebSocket-Protocol";
        }

        if ($server['network'] === 'grpc') {
            $array['transport']['type'] ='grpc';
            if ($server['networkSettings']) {
                $grpcSettings = $server['networkSettings'];
                if (isset($grpcSettings['serviceName'])) $array['transport']['service_name'] = $grpcSettings['serviceName'];
            }
        }

        return $array;
    }

    public static function buildVless($uuid, $server) {

        $array = [];
        $array['type'] = 'vless';
        $array['tag'] = $server['name'];
        $array['server'] = $server['host'];
        $array['server_port'] = $server['port'];
        $array['uuid'] = $uuid;
        $array['flow'] = $server["flow"];

        if ($server['tls']) {
            $array['tls'] = [];
            $array['tls']['enabled'] = true;
            $array['tls']['disable_sni'] = false;

            if ($server['tls_settings']) {

                $tlsSettings = $server['tls_settings'];

                //if (isset($tlsSettings['allow_insecure']) && !empty($tlsSettings['allow_insecure']))
                    $array['tls']['insecure'] = false; //($tlsSettings['allow_insecure'] ? true : false);

                if (isset($tlsSettings['server_name']) && !empty($tlsSettings['server_name']))
                    $array['tls']['server_name'] = $tlsSettings['server_name'];


                $array["tls"]['utls'] = [];
                $array["tls"]['utls']['enabled'] = true;
                $array["tls"]['utls']['fingerprint'] = "";

                $array["tls"]['reality'] = [];
                $array["tls"]['reality']['enabled'] = true;
                $array["tls"]['reality']['public_key'] = $server['tls_settings']["public_key"];
                $array["tls"]['reality']['short_id'] = $server['tls_settings']["short_id"];
            }

            $array['packet_encoding'] = "xudp";
        }

        return $array;

    }

    protected function buildTrojan($password, $server)
    {
        $array = [];
        $array['tag'] = $server['name'];
        $array['type'] = 'trojan';
        $array['server'] = $server['host'];
        $array['server_port'] = $server['port'];
        $array['password'] = $password;

        $array['tls'] = [
            'enabled' => true,
            'insecure' => $server['allow_insecure'] ? true : false,
            'server_name' => $server['server_name']
        ];

        if(isset($server['network']) && in_array($server['network'], ["grpc", "ws"])){
            $array['transport']['type'] = $server['network'];
            // grpc配置
            if($server['network'] === "grpc" && isset($server['network_settings']['serviceName'])) {
                $array['transport']['service_name'] = $server['network_settings']['serviceName'];
            }
            // ws配置
            if($server['network'] === "ws") {
                if(isset($server['network_settings']['path'])) {
                    $array['transport']['path'] = $server['network_settings']['path'];
                }
                if(isset($server['network_settings']['headers']['Host'])){
                    $array['transport']['headers'] = ['Host' => array($server['network_settings']['headers']['Host'])];
                }
                $array['transport']['max_early_data'] = 2048;
                $array['transport']['early_data_header_name'] = 'Sec-WebSocket-Protocol';
            }
        };

        return $array;
    }

    public function token2Login(Request $request)
    {
        if ($request->input('token')) {
            $redirect = '/#/login?verify=' . $request->input('token') . '&redirect=' . ($request->input('redirect') ? $request->input('redirect') : 'dashboard');
            if (config('v2board.app_url')) {
                $location = config('v2board.app_url') . $redirect;
            } else {
                $location = url($redirect);
            }
            return redirect()->to($location)->send();
        }
    }

    private function getAvailableServers(User $user, $all = false)
    {
        $serverService = new ServerService();
        $servers = $serverService->getAvailableServers($user);

        // 硬编码所有国家码（ISO 3166-1 Alpha-2）
        $validCountryCodes = [
            "AF", "AX", "AL", "DZ", "AS", "AD", "AO", "AI", "AQ", "AG", "AR", "AM", "AW", "AU", "AT", "AZ", "BS",
            "BH", "BD", "BB", "BY", "BE", "BZ", "BJ", "BM", "BT", "BO", "BQ", "BA", "BW", "BV", "BR", "IO", "BN",
            "BG", "BF", "BI", "CV", "KH", "CM", "CA", "KY", "CF", "TD", "CL", "CN", "CX", "CC", "CO", "KM", "CG",
            "CD", "CK", "CR", "CI", "HR", "CU", "CW", "CY", "CZ", "DK", "DJ", "DM", "DO", "EC", "EG", "SV", "GQ",
            "ER", "EE", "ET", "FK", "FO", "FJ", "FI", "FR", "GF", "PF", "TF", "GA", "GM", "GE", "DE", "GH", "GI",
            "GR", "GL", "GD", "GP", "GU", "GT", "GG", "GN", "GW", "GY", "HT", "HM", "VA", "HN", "HK", "HU", "IS",
            "IN", "ID", "IR", "IQ", "IE", "IM", "IL", "IT", "JM", "JP", "JE", "JO", "KZ", "KE", "KI", "KP", "KR",
            "KW", "KG", "LA", "LV", "LB", "LS", "LR", "LY", "LI", "LT", "LU", "MO", "MG", "MW", "MY", "MV", "ML",
            "MT", "MH", "MQ", "MR", "MU", "YT", "MX", "FM", "MD", "MC", "MN", "ME", "MS", "MA", "MZ", "MM", "NA",
            "NR", "NP", "NL", "NC", "NZ", "NI", "NE", "NG", "NU", "NF", "MK", "MP", "NO", "OM", "PK", "PW", "PS",
            "PA", "PG", "PY", "PE", "PH", "PN", "PL", "PT", "PR", "QA", "RE", "RO", "RU", "RW", "BL", "SH", "KN",
            "LC", "MF", "PM", "VC", "WS", "SM", "ST", "SA", "SN", "RS", "SC", "SL", "SG", "SX", "SK", "SI", "SB",
            "SO", "ZA", "GS", "SS", "ES", "LK", "SD", "SR", "SJ", "SE", "CH", "SY", "TW", "TJ", "TZ", "TH", "TL",
            "TG", "TK", "TO", "TT", "TN", "TR", "TM", "TC", "TV", "UG", "UA", "AE", "GB", "UM", "US", "UY", "UZ",
            "VU", "VE", "VN", "VG", "VI", "WF", "EH", "YE", "ZM", "ZW"
        ];

        foreach ($servers as &$item) {
            if (!isset($item['tags']) || !is_array($item['tags'])) {
                continue; // 如果 tags 不存在或不是数组，跳过
            }

            $countryCodes = [];
            $remainingTags = [];

            // 遍历当前 tags，分类为国家码和其他标签
            foreach ($item['tags'] as $tag) {
                if (in_array(strtoupper($tag), $validCountryCodes)) {
                    $countryCodes[] = strtoupper($tag); // 转为大写并保存
                } else {
                    $remainingTags[] = $tag;
                }
            }

            // 将国家码放在数组头部，重新组织 tags
            $item['tags'] = array_merge($countryCodes, $remainingTags);
        }

        return $servers;
    }
}
