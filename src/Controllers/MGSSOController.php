<?php namespace InspireSoftware\MGSSO\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Lang;

use InspireSoftware\MGSSO\MGSSOBroker;
use InspireSoftware\MGSSO\MGSSOHelper;
use InspireSoftware\MGSSO\Traits\SSOSendsPasswordResetEmails;

class MGSSOController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests, AuthenticatesUsers, SSOSendsPasswordResetEmails;

    public function index(Request $request){
        return view('mgsso::login');
    }

    public function authenticated(Request $request, $user)
    {
        if($user){
            $broker = new MGSSOBroker;
            $ssoUser = $broker->getUserInfo();
            if ($ssoUser && isset($ssoUser['verified']) && !$ssoUser['verified']) {
                $phrase =  Lang::get('loginReg.EmailMessagePhrase1');
                $broker->logout();
                return back()->with('warning', $phrase);
            }
            
            if($user && $user->level_id === 2){
                return redirect('/');
            }

        }

        return redirect()->intended($this->redirectPath());
    }

    public function login(Request $request, MGSSOBroker $mgBroker)
    {
        $this->validateLogin($request);
        $loginResult = $mgBroker->login($request->get('email'),$request->get('password'));

        if($loginResult){
            
            if ($this->hasTooManyLoginAttempts($request)) {
                $this->fireLockoutEvent($request);

                return $this->sendLockoutResponse($request);
            }

            return $mgBroker->sendLoginResponse();
            
        }
        
        $this->incrementLoginAttempts($request);

        return $this->sendFailedLoginResponse($request);
    }

    public function logout(MGSSOBroker $mgBroker){
        $mgBroker->logout();
        return redirect('/');
    }

    public function sendToken(Request $request, MGSSOBroker $mgBroker){
        $email = request('email');
        $userModelClass = config('auth.providers.users.model');
        $user = $userModelClass::where('email', $email)->first();

        if($user){
            try {
                $user->verified = 0;
                $mgBroker->sendToken($user->email);
                $user->save();
                return redirect()->back()->with('status', 'Email successfully sent');
            } catch (\Exception $ex){
                return redirect()->back()->with('status', 'Ops! error send email');
            }

        } else{
            return redirect()->back()->with('status', 'Is email NOT already in our database');
        }
    }

    public function changePassword(Request $request, MGSSOBroker $mgBroker){
        $this->validate($request, [
            'old' => 'required',
            'new_password' => 'required|min:6',
        ]);

        $user = auth()->user();
        $response = $mgBroker->changePassword($request->old, $request->new_password, $user->email);

        if(isset($response['success']) && $response['success']) $user->logUser(6);

        return $response;

    }

    public function checkEmail($email, MGSSOBroker $mgBroker){

        return $mgBroker->checkEmail($email, auth()->user()->network_id);

    }

}
