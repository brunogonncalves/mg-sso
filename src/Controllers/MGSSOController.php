<?php namespace InspireSoftware\MGSSO\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use \Validator;

use InspireSoftware\MGSSO\MGSSOBroker;
use InspireSoftware\MGSSO\Traits\SSOSendsPasswordResetEmails;

class MGSSOController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests, AuthenticatesUsers, SSOSendsPasswordResetEmails;

    public function index(){
        return view('mgsso::login');
    }

    public function login(Request $request, MGSSOBroker $mgBroker)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'g-recaptcha-response' => config('app.env') !== 'local' ? 'required' : '',
        ]);

        $loginResult = $mgBroker->login($request->get('email'),$request->get('password'));
        if($loginResult === true){

            if ($this->hasTooManyLoginAttempts($request)) {
                $this->fireLockoutEvent($request);

                return $this->sendLockoutResponse($request);
            }

            return redirect('/dashboard');

        }

        if($validator->fails()){
            foreach($validator->errors()->messages() as $key => $errors){
                $_SESSION['inputErrors'][$key] = $errors;
            }
        } else {
            $_SESSION['inputErrors']['email'] = [];
        }

        $_SESSION['inputErrors']['email'][] = Lang::has('auth.failed')
            ? Lang::get('auth.failed')
            : 'These credentials do not match our records.';

        $this->incrementLoginAttempts($request);

        return redirect()
            ->back()
            ->withErrors([
                'email' => $_SESSION['inputErrors']['email']
            ]);
    }

    public function logout(MGSSOBroker $mgBroker){
        $mgBroker->logout();
        return redirect('/');
    }

    public function setLocale(MGSSOBroker $mgBroker){
        $mgBroker->setLocale(request('locale'));
        return response()->json(true);
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
                return redirect()->back()->with('status',
                    Lang::has('rescue_token.email_sent') ? Lang::get('rescue_token.email_sent') : 'Email successfully sent');
            } catch (\Exception $ex){
                return redirect()->back()->with('status',
                    Lang::has('rescue_token.error_sent') ? Lang::get('rescue_token.error_sent') : 'Ops! error send email'
                );
            }

        } else{
            return redirect()->back()->with('status',
                Lang::has('rescue_token.email_not_exists') ? Lang::get('rescue_token.email_not_exists') : 'Is email NOT already in our database'
            );
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
