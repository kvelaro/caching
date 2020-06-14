<?php

namespace Kvelaro\Caching;

use Closure;
use Illuminate\Support\Facades\Cache;

class Caching
{
    const ENV_PROD = 'production';

    const SERVER_ERROR_MIN_BOUND = 500;

    const SERVER_ERROR_MAX_BOUND = 600;

    private $ignorePrefixes = [
        'n',
        'nocache'
    ];


    public function __construct() {
        $envPrefixes = explode(',', env('CACHE_IGNORE_PREFIXES', ''));
        $envPrefixes = array_map(function($x) {
            return trim($x);
        }, $envPrefixes);
        $this->ignorePrefixes = array_merge(
            $this->ignorePrefixes, $envPrefixes
        );
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $url = $request->url();
        $urlData = parse_url($url);
        if($urlData != false) {
            //we interested only in first part of domain, if it's subdomain at all?
            $parts = explode('.', $urlData['host']);
            $subdomain = array_shift($parts);
            if(in_array($subdomain, $this->ignorePrefixes)) {
                $url = $urlData['scheme'] . '://' . implode('.', $parts);
                //if it's generic http port, then key does not exist
                if(isset($urlData['port'])) {
                    $url .= ':' . $urlData['port'];
                }
                if(isset($urlData['path'])) {
                    $url .= $urlData['path'];
                }
            }
        }
                
        $productionEnv = env('APP_ENV') == Caching::ENV_PROD || 1 == 1;
        //null or false? On my local env null, proceeding with null
        $response = null;
        //before request
        if($productionEnv) {
            $response = Cache::store('primary-memcached')->get($url);
        }    
        //we haven't got cache data, so let do it
        if(is_null($response)) {
            //request itself
            $response = $next($request);
            //return response if it's not production environment
            if($productionEnv == false) {
                return $response;
            }
            //after request
            //if we have server error, trying to get from backup-caching service
            if(
                $response->getStatusCode() >= Caching::SERVER_ERROR_MIN_BOUND && 
                $response->getStatusCode() < Caching::SERVER_ERROR_MAX_BOUND
            ) {       
                $backupResponse = Cache::store('backup-memcached')->get($url);
                if(is_null($backupResponse) == false) {
                    $response = $backupResponse;
                }
                //whether find from cache or give user error page
                return $response;
            }
            //save to caching service
            Cache::store('primary-memcached')->forever($url, $response);
            return $response;
        }
        return $response;
    }
}
