<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\PlanSave;
use App\Http\Requests\Admin\PlanSort;
use App\Http\Requests\Admin\PlanUpdate;
//<<<<<<< HEAD
use App\Utils\Helper;
//=======
use App\Services\PlanService;
//>>>>>>> official/master
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PlanController extends Controller
{
    public function fetch(Request $request)
    {

        Helper::MustSupperAdmin($request);
//        $counts = User::select(
//            DB::raw("plan_id"),
//            DB::raw("count(*) as count")
//        )
//            ->where('plan_id', '!=', NULL)
//            ->where(function ($query) {
//                $query->where('expired_at', '>=', time())
//                    ->orWhere('expired_at', NULL);
//            })
//            ->groupBy("plan_id")
//            ->get();
        $counts = PlanService::countActiveUsers();
        $plans = Plan::orderBy('sort', 'ASC')->get();
        foreach ($plans as $k => $v) {
            $plans[$k]->count = 0;
            foreach ($counts as $kk => $vv) {
                if ($plans[$k]->id === $counts[$kk]->plan_id) $plans[$k]->count = $counts[$kk]->count;
            }
            // 修改 name 属性，在名称前拼接 Plan ID
            $plans[$k]->name = $plans[$k]->id . ' - ' . $plans[$k]->name;

        }
        return response([
            'data' => $plans
        ]);
    }

    public function save(PlanSave $request)
    {
        Helper::MustSupperAdmin($request);
        $params = $request->validated();
        if ($request->input('id')) {
            $plan = Plan::find($request->input('id'));
            if (!$plan) {
                abort(500, '该订阅不存在');
            }
            DB::beginTransaction();
            // update user group id and transfer
            try {
                if ($request->input('force_update')) {
                    User::where('plan_id', $plan->id)->update([
                        'group_id' => $params['group_id'],
                        'transfer_enable' => $params['transfer_enable'] * 1073741824,
                        'speed_limit' => $params['speed_limit']
                    ]);
                }
                $plan->update($params);
            } catch (\Exception $e) {
                DB::rollBack();
                abort(500, '保存失败');
            }
            DB::commit();
            return response([
                'data' => true
            ]);
        }
        if (!Plan::create($params)) {
            abort(500, '创建失败');
        }
        return response([
            'data' => true
        ]);
    }

    public function drop(Request $request)
    {
        Helper::MustSupperAdmin($request);
        if (Order::where('plan_id', $request->input('id'))->first()) {
            abort(500, '该订阅下存在订单无法删除');
        }
        if (User::where('plan_id', $request->input('id'))->first()) {
            abort(500, '该订阅下存在用户无法删除');
        }
        if ($request->input('id')) {
            $plan = Plan::find($request->input('id'));
            if (!$plan) {
                abort(500, '该订阅ID不存在');
            }
        }
        return response([
            'data' => $plan->delete()
        ]);
    }

    public function update(PlanUpdate $request)
    {
        Helper::MustSupperAdmin($request);
        $updateData = $request->only([
            'show',
            'renew'
        ]);

        $plan = Plan::find($request->input('id'));
        if (!$plan) {
            abort(500, '该订阅不存在');
        }

        try {
            $plan->update($updateData);
        } catch (\Exception $e) {
            abort(500, '保存失败');
        }

        return response([
            'data' => true
        ]);
    }

    public function sort(PlanSort $request)
    {
        Helper::MustSupperAdmin($request);
        DB::beginTransaction();
        foreach ($request->input('plan_ids') as $k => $v) {
            if (!Plan::find($v)->update(['sort' => $k + 1])) {
                DB::rollBack();
                abort(500, '保存失败');
            }
        }
        DB::commit();
        return response([
            'data' => true
        ]);
    }
}
