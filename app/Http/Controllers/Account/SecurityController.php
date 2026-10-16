<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Account security: password, two-factor authentication (Fortify), active sessions
 * and login history. Shared by merchant users and administrators.
 */
class SecurityController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        $sessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($s) => (object) [
                'id' => $s->id,
                'ip' => $s->ip_address,
                'agent' => $this->describeAgent((string) $s->user_agent),
                'last_active' => \Illuminate\Support\Carbon::createFromTimestamp($s->last_activity),
                'current' => $s->id === $request->session()->getId(),
            ]);

        return view('account.security', [
            'user' => $user,
            'sessions' => $sessions,
            'logins' => $user->loginHistories()->latest('id')->limit(20)->get(),
            'area' => $user->merchant_id ? 'merchant' : 'admin',
        ]);
    }

    /** Reveal recovery codes once, after a fresh password confirmation (route uses password.confirm). */
    public function showRecoveryCodes(): RedirectResponse
    {
        return redirect()->route('security.edit')->with('show_recovery_codes', true);
    }

    public function destroyOtherSessions(Request $request, AuditLogger $audit): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();

        if (! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => 'The password is incorrect.'])->errorBag('sessions');
        }

        Auth::logoutOtherDevices($request->input('password'));
        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
        $audit->log('user.logged_out_other_sessions', $user, user: $user);

        return back()->with('success', 'All other sessions have been logged out.');
    }

    private function describeAgent(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Unknown browser',
        };
        $os = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Unknown OS',
        };

        return "{$browser} on {$os}";
    }
}
