<?php namespace InspireSoftware\MGSSO\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

use InspireSoftware\MGSSO\MGSSOBroker;
use InspireSoftware\MGSSO\MGSSOHelper;

class MGSSOController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests, AuthenticatesUsers;

    public function login(Request $request, MGSSOBroker $mgBroker)
    {
        $this->validateLogin($request);
        $loginResult = $mgBroker->loginUser($request->get('email'),$request->get('password'));

        if($loginResult){
            
            if ($this->hasTooManyLoginAttempts($request)) {
                $this->fireLockoutEvent($request);

                return $this->sendLockoutResponse($request);
            }

            Session::put('origin', MGSSOHelper::isMobile());
            Session::put('nav', MGSSOHelper::getBrowser());
            
            return $mgBroker->loginCurrentUser();
            // return $this->sendLoginResponse($request);
            
        }
        
        $this->incrementLoginAttempts($request);

        return $this->sendFailedLoginResponse($request);
    }

    public function logout(){
        MGSSOBroker::flush();
        Auth::logout();
        Session::flush();
        return redirect('/');
    }
}
