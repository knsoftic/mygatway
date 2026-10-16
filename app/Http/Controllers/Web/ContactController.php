<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        // Honeypot: bots fill hidden fields; pretend success without storing anything.
        if ($request->filled('website')) {
            return back()->with('success', 'Thanks! We will get back to you soon.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'company' => ['nullable', 'string', 'max:120'],
            'subject' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        ContactMessage::query()->create($data + ['ip_address' => $request->ip()]);

        return back()->with('success', 'Thanks! Your message has been received and we will reply by email.');
    }
}
