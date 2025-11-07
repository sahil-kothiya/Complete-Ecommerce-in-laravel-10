<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Socialite;
use App\User;
use Auth;
use App\Services\RecentProductService;

class LoginController extends Controller
{
    protected $recentProductService;

    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public function credentials(Request $request){
        return ['email'=>$request->email,'password'=>$request->password,'status'=>'active','role'=>'admin'];
    }

    public function __construct(RecentProductService $recentProductService)
    {
        $this->middleware('guest')->except('logout');
        $this->recentProductService = $recentProductService;
    }

    /**
     * The user has been authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function authenticated(Request $request, $user)
    {
        // Merge session recent products to user account.
        // Laravel regenerates the session id during login, so retrieve the pre-login session id
        // from the incoming request cookie if available and pass it through for correct merging.
        $sessionCookieName = config('session.cookie');
        $preLoginSessionId = $request->cookie($sessionCookieName) ?: null;

        $this->recentProductService->handleUserLogin($user->id, $preLoginSessionId);
    }

    public function redirect($provider)
    {
        // dd($provider);
     return Socialite::driver($provider)->redirect();
    }

    public function Callback($provider)
    {
        $userSocial =   Socialite::driver($provider)->stateless()->user();
        $users      =   User::where(['email' => $userSocial->getEmail()])->first();
        // dd($users);
        if($users){
            Auth::login($users);

            // Merge session recent products to user account
            // Attempt to use pre-login cookie value (Socialite/redirect flow may also regenerate session)
            $sessionCookieName = config('session.cookie');
            $preLoginSessionId = request()->cookie($sessionCookieName) ?: null;
            $this->recentProductService->handleUserLogin($users->id, $preLoginSessionId);

            return redirect('/')->with('success','You are login from '.$provider);
        }else{
            $user = User::create([
                'name'          => $userSocial->getName(),
                'email'         => $userSocial->getEmail(),
                'image'         => $userSocial->getAvatar(),
                'provider_id'   => $userSocial->getId(),
                'provider'      => $provider,
            ]);

            Auth::login($user);

            // Merge session recent products to newly created user account
            $sessionCookieName = config('session.cookie');
            $preLoginSessionId = request()->cookie($sessionCookieName) ?: null;
            $this->recentProductService->handleUserLogin($user->id, $preLoginSessionId);

         return redirect()->route('home');
        }
    }
}
