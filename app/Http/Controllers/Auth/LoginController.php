<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Models\SessionLog;
use Jenssegers\Agent\Agent;
use Mobile_Detect;
use App\Jobs\SendEmailNuevoDispositivo;
use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Session as LaravelSession;

class LoginController extends Controller
{
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
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    protected function authenticated(Request $request, $user)
    {
        $agent = new Agent();
        $detect = new Mobile_Detect();
        $session_id = LaravelSession::getId();

        $ip_address = $this->getRealIp($request);

        $device = $agent->device();
        $platform = $agent->platform();
        $platformVersion = $agent->version($platform);
        $browser = $agent->browser();
        $browserVersion = $agent->version($browser);

        if ($detect->isMobile() && !$detect->isTablet()) {
            $deviceType = 'Mobile';
            $deviceModel = $this->getMobileModel($detect);
        } elseif ($detect->isTablet()) {
            $deviceType = 'Tablet';
            $deviceModel = $this->getTabletModel($detect);
        } elseif ($agent->isDesktop()) {
            $deviceType = 'Desktop';
            $deviceModel = $this->getDesktopModel($agent);
        } else {
            $deviceType = 'Unknown';
            $deviceModel = 'Unknown';
        }

        $platform = trim($platform.' '.$platformVersion);
        $browser  = trim($browser.' '.$browserVersion);

        $primer_inicio = SessionLog::where('user_id', $user->id)->exists();
        $nuevo_dispositivo = SessionLog::where('user_id', $user->id)
            ->where('device_model', $deviceModel)
            ->exists();

        if ($user->login_notificacion == 1 && $primer_inicio && !$nuevo_dispositivo) {
            SendEmailNuevoDispositivo::dispatch(
                $user,
                $ip_address,
                $platform,
                $browser,
                $deviceType
            );
        }

        SessionLog::create([
            'session_id'   => $session_id,
            'user_id'      => $user->id,
            'ip_address'   => $ip_address,
            'device'       => $device,
            'platform'     => $platform,
            'browser'      => $browser,
            'device_type'  => $deviceType,
            'device_model' => $deviceModel,
        ]);
    }

    protected function getRealIp(Request $request): string
    {
        if ($request->headers->has('CF-Connecting-IP')) {
            return $request->headers->get('CF-Connecting-IP');
        }

        return $request->ip();
    }

    private function getMobileModel($detect)
    {
        $userAgent = $detect->getUserAgent();

        if ($detect->isiPhone()) {
            return 'iPhone ' . $this->getIphoneModel($userAgent);
        } elseif ($detect->isAndroidOS()) {
            return $this->getAndroidModel($userAgent);
        }

        return 'Unknown Mobile Model';
    }

    private function getTabletModel($detect)
    {
        $userAgent = $detect->getUserAgent();

        if ($detect->isiPad()) {
            return 'iPad ' . $this->getIphoneModel($userAgent);
        } elseif ($detect->isAndroidOS()) {
            return $this->getAndroidModel($userAgent);
        }

        return 'Unknown Tablet Model';
    }

    private function getDesktopModel($agent)
    {
        $userAgent = $agent->getUserAgent();
        if (preg_match('/\bWindows NT\b.*\b(\d+\.\d+)\b/', $userAgent, $matches)) {
            return 'Windows ' . $matches[1];
        } elseif (preg_match('/\bMac OS X\b.*\b(\d+_\d+(_\d+)?)\b/', $userAgent, $matches)) {
            return 'Mac OS X ' . str_replace('_', '.', $matches[1]);
        } elseif (preg_match('/\bLinux\b.*\b(\w+)\b/', $userAgent, $matches)) {
            return 'Linux ' . $matches[1];
        }
        return 'Unknown Desktop Model';
    }

    private function getIphoneModel($userAgent)
    {
        if (preg_match('/\biPhone\b.*\b([0-9]+,[0-9]+)\b/', $userAgent, $matches)) {
            return $matches[1];
        }
        return 'Unknown iPhone Model';
    }

    private function getAndroidModel($userAgent)
    {
        // Tratar de obtener el modelo específico del User-Agent
        if (preg_match('/\bAndroid\b.*\b(\w+[-\w]*\w)\b/', $userAgent, $matches)) {
            return $matches[1];
        }

        // Intentar detectar dispositivos Samsung específicamente
        if (preg_match('/\bSM-\w+\b/', $userAgent, $matches)) {
            return $matches[0];
        }

        // Otros patrones comunes de dispositivos Android
        if (preg_match('/\b(SAMSUNG|HUAWEI|XIAOMI|ONEPLUS|OPPO|VIVO|LG|Nokia|Sony|HTC)\b/', $userAgent, $matches)) {
            return $matches[0];
        }

        return 'Unknown Android Model';
    }
}
