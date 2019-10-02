
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
                    <h1>{{__('loginReg.LoginH')}}</h1>
                    <div class="form-area-login-register">
                        <div   class="@php echo($errors->count() > 0) ? 'd-none' : 'hideBtn' @endphp">
                            <a class="btn-login btn-default-login mb-20 btnOpenMenu" href="#">{{ __('loginReg.EmailPass') }}</a>
                            <a class="btn-login btn-default-login-disabled mb-20 btn-fb" href="{{url('login/facebook')}}">Facebook</a>
                            <a class="btn-login btn-default-login-disabled mb-20 btn-google" href="{{url('login/google')}}">Google</a>
                            <a class="btn-login btn-default-login-disabled mb-20 btn-twitch" href="#">Twitch</a>
                            <a class="btn-login btn-default-login-disabled mb-20 btn-twitter" href="#">Twitter</a>
                        </div>
                        <div class="showBtn" @if($errors->count() > 0) style="display: block" @else style="display: none" @endif  >
                            <form id="formAll" class="form-group" method="POST" action="{{ route('login') }}" aria-label="{{ __('Login') }}">
                                @csrf

                                <fieldset class="form-group">
                                    <label for="email" class="text-uppercase">{{__('loginReg.E-mail')}}</label>
                                    <input id="email" type="email" name="email" placeholder="{{ __('loginReg.E-mailPhrase') }}" class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}">

                                    @if ($errors->has('email'))
                                        <span class="invalid-feedback" role="alert">
            <strong>{{ $errors->first('email') }}</strong>
                                    </span>
                                    @endif
                                </fieldset>
                                <fieldset class="form-group">
                                    <label for="password" class="text-uppercase">{{ __('loginReg.Password') }}</label>
                                    <input id="password" name="password" type="password" placeholder="{{ __('loginReg.PasswordPhrase') }}" class="form-control{{ $errors->has('password') ? ' is-invalid' : '' }}">

                                    @if ($errors->has('password'))
                                        <span class="invalid-feedback" role="alert">
            <strong>{{ $errors->first('password') }}</strong>
                                    </span>
                                    @endif
                                </fieldset>
                                <div class="g-recaptcha-container">
                                    <div id="g-recaptcha" class="g-recaptcha" data-sitekey="6LfXC7IUAAAAABcCVJVg4RnNhdJDGgV8tDvSanAc" data-theme="dark" data-type="image" data-size="normal" data-badge="bottomright" data-tabindex="0"></div>
                                    @if ($errors->has('g-recaptcha-response'))
                                        <span class="invalid-feedback recaptcha-register" role="alert">
        <strong>{{ $errors->first('g-recaptcha-response') }}</strong>
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
                                    <!-- <a  href="{{url('login/facebook')}}" disabled class="btn btn-secondary btn-sm btn-block">Facebook</a>
                                    <a  href="{{url('login/google')}}" disabled class="btn btn-secondary btn-sm btn-block" style="">Google</a> -->
                                    <button type="button" disabled class="btn btn-secondary btn-sm btn-block" style="">Facebook</button>
                                    <button type="button" disabled class="btn btn-secondary btn-sm btn-block" style="">Google</button>
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

    {{Session::put('nav',verifiNav())}}
    {{Session::put('origin',isMoble())}}
@endsection
