
@extends('site.index')
@section('header')
    @include('site.contents.header')
@endsection
@section('css-site')
    <link href="{{asset('css/login.register.css')}}" rel="stylesheet" />
@endsection
@section('js-site')
    <script src="{{asset('js/login.js')}}"></script>
    <script src="https://www.google.com/recaptcha/api.js?render=6LfXC7IUAAAAADqaerFWnfRpmJD4hX5qv8DH2aHG"></script>
@endsection
@section('content')
    <div class="page login-register-page">
        <div class="body-login-register">
            @if (session('status'))
                <div class="alert alert-success">
                    {{__('loginReg.EmailMessagePhrase1')}} <br /> {{__('loginReg.EmailMessagePhrase2')}} <a href="{{url('rescue-token')}}">{{__('loginReg.RescueToken')}}</a>
                </div>
            @endif
            @if (session('statusReset'))
                <div class="alert alert-success">
                    Reset done successfully! Please login your new password.
                </div>
            @endif
            @if (session('warning'))
                <div class="alert alert-warning">
                    {{ session('warning') }}
                </div>
            @endif
            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif
            <div class="container">
                <div class="offer-left"><span class="white">{{__('loginReg.JoinNow')}}</span></div>
                <div class="form">
                    <div class="form-area-login-register">
                        <div   class="@php echo(count($_SESSION['inputErrors']) > 0) ? 'd-none' : 'hideBtn' @endphp">
                            <a class="btn-login btn-default-login mb-20 btnOpenMenu" href="#">{{ __('loginReg.EmailPass') }}</a>
                            <a class="btn-login btn-default-login mb-20 btn-fb" href="{{url('login/facebook')}}">Facebook</a>
                            <a class="btn-login btn-default-login mb-20 btn-google" href="{{url('login/google')}}">Google</a>
                            <a class="btn-login btn-default-login-disabled mb-20 btn-twitch" href="#">Twitch</a>
                            <a class="btn-login btn-default-login-disabled mb-20 btn-twitter" href="#">Twitter</a>
                        </div>
                        <div class="showBtn" @if(count($_SESSION['inputErrors']) > 0) style="display: block" @else style="display: none" @endif  >
                            <form id="formAll" class="form-group" method="POST" action="{{ route('login') }}" aria-label="{{ __('Login') }}">
                                @csrf

                                <fieldset class="form-group">
                                    <label for="email" class="text-uppercase">{{__('loginReg.E-mail')}}</label>
                                    <input id="email" type="email" name="email" placeholder="{{ __('loginReg.E-mailPhrase') }}" class="form-control{{ isset($_SESSION['inputErrors']['email']) ? ' is-invalid' : '' }}">
                                    @if (isset($_SESSION['inputErrors']['email']))
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $_SESSION['inputErrors']['email'][0] }}</strong>
                                        </span>
                                    @endif
                                </fieldset>
                                <fieldset class="form-group">
                                    <label for="password" class="text-uppercase">{{ __('loginReg.Password') }}</label>
                                    <input id="password" name="password" type="password" placeholder="{{ __('loginReg.PasswordPhrase') }}" class="form-control{{ isset($_SESSION['inputErrors']['password']) ? ' is-invalid' : '' }}">

                                    @if (isset($_SESSION['inputErrors']['password']))
                                        <span class="invalid-feedback" role="alert">
                                        <strong>{{ $_SESSION['inputErrors']['password'][0] }}</strong>
                                    </span>
                                    @endif
                                </fieldset>
                                <div class="g-recaptcha-container">
                                    <div id="g-recaptcha" class="g-recaptcha" data-sitekey="6LfXC7IUAAAAABcCVJVg4RnNhdJDGgV8tDvSanAc" data-theme="dark" data-type="image" data-size="normal" data-badge="bottomright" data-tabindex="0"></div>
                                    @if (isset($_SESSION['inputErrors']['g-recaptcha-response']))
                                        <span class="invalid-feedback recaptcha-register" role="alert">
                                        <strong>{{ $_SESSION['inputErrors']['g-recaptcha-response'][0] }}</strong>
                                    </span>
                                    @endif
                                </div>

                                <div class="link">
                                    <a  href="{{ route('password.request') }}">
                                        {{__('loginReg.ForgotPassword')}}  <span class="red-color">{{__('loginReg.Remind')}}</span>
                                    </a>
                                </div>
                                <button type="submit" class="btn-login btn-default-login">{{__('loginReg.LoginBtn')}}</button>
                                <div class="link">
                                    <a  href="{{url('register') }}">
                                        {{__('loginReg.StillRegistered')}}  <span class="red-color">{{__('loginReg.RegisterNow')}}</span>
                                    </a>
                                </div>
                                <div class="btn-group-login">
                                    <a  href="{{url('login/facebook')}}" class="btn btn-secondary btn-sm btn-block">Facebook</a>
                                    <a  href="{{url('login/google')}}" class="btn btn-secondary btn-sm btn-block" style="">Google</a>
                                    <button type="button" disabled class="btn btn-secondary btn-sm btn-block" style="">Twitch</button>
                                    <button type="button" disabled class="btn btn-secondary btn-sm btn-block" style="">Twitter</button>
                                </div>
                            </form>
                        </div>
                    </div>



                </div>
            </div>
        </div>
    </div>
@endsection
@php 
$_SESSION['inputErrors'] = [];
@endphp