<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * Verifikasi email lewat signed URL (route name: verification.verify).
     * Frontend beda domain, jadi setelah verifikasi user di-redirect ke React.
     *
     * Catatan: route ini PUBLIC tapi dijaga middleware 'signed' — keamanannya
     * berasal dari tanda tangan URL (id + hash + signature), bukan dari token auth.
     */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        // Hash pada URL harus cocok dengan sha1 email user.
        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403, 'Link verifikasi tidak valid.');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect($this->frontend('already_verified'));
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return redirect($this->frontend('verified'));
    }

    /**
     * Kirim ulang link verifikasi. Dibuat PUBLIC (by email) karena login menolak
     * user belum terverifikasi — jadi user belum punya token untuk endpoint ini.
     * Selalu balas pesan generik untuk mencegah enumerasi email.
     */
    public function resend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'message' => 'Jika email terdaftar dan belum terverifikasi, link verifikasi telah dikirim.',
        ]);
    }

    private function frontend(string $status): string
    {
        return rtrim(config('app.frontend_url'), '/') . '/login?status=' . $status;
    }
}
