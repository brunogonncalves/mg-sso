<?php namespace InspireSoftware\MGSSO\Middlewares;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;

use InspireSoftware\MGSSO\MGSSOBroker;

class MGSSOValidateTermsMiddleware
{
    protected $ignoreRoutes = ['login', 'logout', 'terms-save', 'terms-user', 'step', 'save-step', 'check-nickname','select-step-state'];
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        $path = $request->path();
        $log = request('logUser', null);
        $userNetworkId = request('userNetworkId', null);
        $slashPos = stripos($path, '/');

        if($slashPos !== false) $path = substr($path, 0, $slashPos);

        $flashSessionStatus = $request->get('with');
        $user = Auth::user();
        $ignoreRoutes = config('mgsso.ignoreMiddlewareFor');
        $run = true;

        // log user action from network
        if($log && $userNetworkId){
            $userModelClass = config('auth.providers.users.model');
            $userForLog = $userModelClass::where('network_id', $userNetworkId)->first();
            if($userForLog) $userForLog->logUser($log, $userForLog->id);
        }

        // verify flash session from network
        if($flashSessionStatus) session()->flash($flashSessionStatus, $request->get('message'));

        foreach($this->ignoreRoutes as $route){
            if($route === $path) $run = false;
        }

        if($user && $run){
            if(empty($user->terms_use) || empty($user->policy)) return redirect('terms-user');
            if(empty($user->nickname) || empty($user->date_birth)) return redirect('step');
            // validate user verified
            if($user && !$user->verified){
                $broker = new MGSSOBroker;
                $mgUser = $broker->getSSOUser();
                if(!$mgUser['verified']) {
                    $phrase =  Lang::get('loginReg.EmailMessagePhrase1');
                    $broker->logout();
                    return back()->with('warning', $phrase);
                }
            }
        }

        return $next($request);
    }
}
