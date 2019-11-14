<?php namespace InspireSoftware\MGSSO\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Password;

use InspireSoftware\MGSSO\MGSSOBroker;

trait SSOSendsPasswordResetEmails
{
    /**
     * Display the form to request a password reset link.
     *
     * @return \Illuminate\Http\Response
     */
    public function showLinkRequestForm()
    {
        return view('auth.passwords.email');
    }
    /**
     * Send a reset link to the given user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendResetLinkEmail(Request $request, MGSSOBroker $broker)
    {
        $this->validateEmail($request);
        
        $response = $broker->resetPassword($request->get('email'));

        return $response == Password::RESET_LINK_SENT
            ? $this->sendResetLinkResponse(
                Lang::has('rescue_token.email_sent') ? Lang::get('rescue_token.email_sent') : 'Email sent successful')
            : $this->sendResetLinkFailedResponse($request, 
            Lang::has('rescue_token.error_sent') ? Lang::get('rescue_token.error_sent') : 'Ops! error send email');
    }
    /**
     * Validate the email for the given request.
     *
     * @param \Illuminate\Http\Request  $request
     * @return void
     */
    protected function validateEmail(Request $request)
    {
        $this->validate($request, ['email' => 'required|email']);
    }
    /**
     * Get the response for a successful password reset link.
     *
     * @param  string  $response
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function sendResetLinkResponse($response)
    {
        return back()->with('status', $response);
    }
    /**
     * Get the response for a failed password reset link.
     *
     * @param  \Illuminate\Http\Request
     * @param  string  $response
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function sendResetLinkFailedResponse(Request $request, $response)
    {
        return back()->withErrors(
            ['email' => $response]
        );
    }
}