<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    
    public function handle(Request $request, Closure $next): Response
    {
        // 管理者ガードで認証されているか確認
        if (
            !Auth::guard('admin')->check() ||
            !Auth::guard('admin')->user()->admin_status
        ) {
            return redirect('/admin/login');
        }

        // 既存のBladeが参照するデフォルトガードをadminにする
        Auth::shouldUse('admin');

        return $next($request);
    }

}
