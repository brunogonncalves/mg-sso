<?php namespace InspireSoftware\MGSSO\Middlewares;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;

use InspireSoftware\MGSSO\MGSSOBroker;

class MGSSOValidateTermsMiddleware
{
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
        $user = Auth::user();
        $ignoreRoutes = ['login', 'terms-save', 'terms-user', 'step'];
        $run = true;

        foreach($ignoreRoutes as $route){
            if($route === $path) $run = false;
        }

        if($user && $run){
            if(!$user->verified){
                $broker = new MGSSOBroker;
                $mgUser = $broker->getUserInfo(true);
                if(!$mgUser['verified']) {
                    $phrase =  Lang::get('loginReg.EmailMessagePhrase1');
                    MGSSOBroker::flush();
                    Auth::logout();
                    return back()->with('warning', $phrase);
                }
            }
            if(empty($user->terms_use) || empty($user->policy)) return redirect('terms-user');
            if(empty($user->nickname) || empty($user->date_birth)) return redirect('step');
        }

        return $next($request);
    }
}
