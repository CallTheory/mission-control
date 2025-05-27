<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\System\Settings;
use Symfony\Component\HttpFoundation\IpUtils;
class ApiWhitelistMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response) $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $settings = Settings::first();
        //allow by default, restrict only if api_whitelist is set
        if($settings){
            $whitelist = $settings->api_whitelist;
            if($whitelist){
                $iplist = json_decode($whitelist);
                if(count($iplist)){
                    if(!IpUtils::checkIp($request->ip(), $iplist)){
                        abort(403,'Forbidden');
                    }
                }
            }
        }
        return $next($request);
    }
}
